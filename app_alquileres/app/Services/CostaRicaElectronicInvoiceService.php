<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceElectronicDetail;
use App\Models\Lessor;
use App\Services\Hacienda\Catalogs;
use App\Services\Hacienda\ClaveGenerator;
use App\Services\Hacienda\Contracts\XmlSignerInterface;
use App\Services\Hacienda\HaciendaAuthService;
use App\Services\Hacienda\InvoiceXmlBuilder;
use App\Services\Hacienda\ReceptionClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Orquesta el envío directo a Hacienda (ATV, comprobantes electrónicos v4.4): genera la
 * clave/consecutivo, arma el XML de negocio, lo firma (XAdES-BES) y lo somete a la API de
 * recepción, sin intermediarios de terceros.
 */
class CostaRicaElectronicInvoiceService
{
    public function __construct(
        protected HaciendaAuthService $authService,
        protected ClaveGenerator $claveGenerator,
        protected InvoiceXmlBuilder $xmlBuilder,
        protected XmlSignerInterface $signer,
        protected ReceptionClient $receptionClient,
    ) {
    }

    public function haciendaStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'queued' => 'En cola',
            'sent' => 'Enviada',
            'accepted' => 'Aceptada',
            'rejected' => 'Rechazada',
            'error' => 'Error',
        ];
    }

    /**
     * Guarda el certificado .p12 del arrendador en almacenamiento privado (cifrado en disco
     * por la configuración del filesystem) y registra el PIN. Reemplaza la subida a un
     * proveedor externo: el certificado se usa localmente para firmar cada comprobante.
     */
    public function storeLessorCertificate(Lessor $lessor, ?UploadedFile $certificateFile, ?string $certificatePin): array
    {
        $result = ['certificate_uploaded' => false];

        if (!$certificateFile) {
            return $result;
        }

        $pinToUse = $certificatePin ?: $lessor->certificate_pin;

        if (!$pinToUse) {
            throw new RuntimeException('Debes indicar el PIN del certificado para subir el archivo .p12.');
        }

        $path = $certificateFile->storeAs('hacienda-certificates', "lessor-{$lessor->id}.p12", ['disk' => 'local']);

        $lessor->forceFill([
            'certificate_code' => $path,
            'certificate_pin' => $pinToUse,
            'certificate_uploaded_at' => now(),
        ])->save();

        $result['certificate_uploaded'] = true;

        return $result;
    }

    /**
     * $forceNewKey se usa en reintentos: Hacienda no permite reenviar una clave que ya
     * procesó (aceptada o rechazada — devuelve "el comprobante ya fue recibido
     * anteriormente"), así que un reintento real necesita clave y consecutivo nuevos.
     */
    public function sendVoucher(Invoice $invoice, bool $forceNewKey = false): void
    {
        $invoice->loadMissing(['lessor.user', 'roomer.user', 'agreement.property', 'electronicDetail', 'items']);

        $detail = $invoice->electronicDetail;
        if (!$detail) {
            throw new RuntimeException('La factura no posee detalle electrónico para enviar a Hacienda.');
        }

        $lessor = $invoice->lessor;
        if (!$lessor) {
            throw new RuntimeException('No se encontró el arrendador emisor de la factura.');
        }

        $this->ensureIssuerIsReady($lessor);
        $this->ensureInvoiceKey($invoice, $detail, $lessor, $forceNewKey);

        try {
            $token = $this->authService->tokenFor($lessor);

            $unsignedXml = $this->xmlBuilder->build($invoice, $detail->hacienda_key, $detail->hacienda_consecutive);
            $p12Path = Storage::disk('local')->path($lessor->certificate_code);
            $signedXml = $this->signer->sign($unsignedXml, $p12Path, $lessor->certificate_pin);

            $response = $this->receptionClient->send([
                'clave' => $detail->hacienda_key,
                'fecha' => $this->formatIssueDate($invoice),
                'emisor' => [
                    'tipoIdentificacion' => Catalogs::identificationTypeCode($lessor->identification_type, (string) $lessor->id_number),
                    'numeroIdentificacion' => preg_replace('/\D+/', '', (string) $lessor->id_number),
                ],
                'receptor' => [
                    'tipoIdentificacion' => Catalogs::identificationTypeCode($invoice->roomer?->identification_type, (string) $invoice->roomer?->id_number),
                    'numeroIdentificacion' => preg_replace('/\D+/', '', (string) $invoice->roomer?->id_number),
                ],
                'comprobanteXml' => base64_encode($signedXml),
            ], $token);

            $this->recordExchangeMetadata($detail, [
                'response_payload' => ['send' => $response],
                'xml_content' => $signedXml,
            ]);
        } catch (RuntimeException $exception) {
            $this->recordExchangeMetadata($detail, [
                'error_code' => $this->extractErrorCode($exception),
                'response_payload' => ['message' => $exception->getMessage()],
            ]);

            throw $exception;
        }
    }

    public function getVoucherStatus(InvoiceElectronicDetail $detail): array
    {
        $detail->loadMissing('invoice.lessor.user');
        $invoice = $detail->invoice;
        $lessor = $invoice?->lessor;

        if (!$invoice || !$lessor) {
            throw new RuntimeException('No se encontró el contexto de la factura para consultar el estado electrónico.');
        }

        if (!$detail->hacienda_key) {
            throw new RuntimeException('No se encontró clave para consultar estado en Hacienda.');
        }

        try {
            $token = $this->authService->tokenFor($lessor);
            $payload = $this->receptionClient->getStatus($detail->hacienda_key, $token);

            $this->recordExchangeMetadata($detail, ['response_payload' => $payload]);

            return $payload;
        } catch (RuntimeException $exception) {
            $this->recordExchangeMetadata($detail, [
                'error_code' => $this->extractErrorCode($exception),
                'response_payload' => ['message' => $exception->getMessage()],
            ]);

            throw $exception;
        }
    }

    public function recordExchangeMetadata(InvoiceElectronicDetail $detail, array $metadata): void
    {
        $xmlContent = $metadata['xml_content'] ?? $detail->xml_content;

        $detail->forceFill([
            'error_code' => $metadata['error_code'] ?? null,
            'ptec_response' => $metadata['response_payload'] ?? $detail->ptec_response,
            'xml_content' => $xmlContent,
            'xml_hash' => $xmlContent ? hash('sha256', (string) $xmlContent) : $detail->xml_hash,
        ])->save();
    }

    /**
     * El consecutivo de 20 dígitos normalmente ya se generó y quedó guardado en
     * hacienda_consecutive (y como invoice_number) al crear la factura — ver
     * InvoiceController::store(). En un reintento ($forceNewKey), Hacienda ya "quemó" la
     * clave anterior (aceptada o rechazada), así que aquí se pide una nueva y se actualiza
     * también el invoice_number visible para que sigan siendo el mismo valor.
     */
    protected function ensureInvoiceKey(Invoice $invoice, InvoiceElectronicDetail $detail, Lessor $lessor, bool $forceNewKey = false): void
    {
        if ($detail->hacienda_key && !$forceNewKey) {
            return;
        }

        $sucursal = $detail->sucursal ?: (string) config('services.cr_einvoice.branch', '001');
        $terminal = $detail->terminal ?: (string) config('services.cr_einvoice.terminal', '00001');
        $documentType = $detail->document_type ?: '01';

        $consecutivo = (!$forceNewKey && $detail->hacienda_consecutive)
            ? $detail->hacienda_consecutive
            : $this->claveGenerator->nextConsecutivo($lessor->id, $sucursal, $terminal, $documentType);

        $clave = $this->claveGenerator->clave((string) $lessor->id_number, $consecutivo);

        $detail->forceFill([
            'hacienda_key' => $clave,
            'hacienda_consecutive' => $consecutivo,
            'sucursal' => $sucursal,
            'terminal' => $terminal,
            'internal_number' => str_pad((string) $invoice->id, 10, '0', STR_PAD_LEFT),
        ])->save();

        if ($invoice->invoice_number !== $consecutivo) {
            $invoice->forceFill(['invoice_number' => $consecutivo])->save();
        }
    }

    protected function ensureIssuerIsReady(Lessor $lessor): void
    {
        $missing = [];

        foreach ([
            'id_number' => $lessor->id_number,
            'legal_name' => $lessor->legal_name,
            'phone' => $lessor->phone,
            'economic_activity_code' => $lessor->economic_activity_code,
            'certificate_code' => $lessor->certificate_code,
            'certificate_pin' => $lessor->certificate_pin,
            'hacienda_username' => $lessor->hacienda_username,
            'hacienda_password' => $lessor->hacienda_password,
        ] as $field => $value) {
            if (!$value) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Faltan datos del arrendador para facturación electrónica: ' . implode(', ', $missing) . '.');
        }
    }

    protected function formatIssueDate(Invoice $invoice): string
    {
        return ($invoice->issued_at ?? now())
            ->copy()
            ->timezone('America/Costa_Rica')
            ->format('Y-m-d\TH:i:sP');
    }

    protected function extractErrorCode(RuntimeException $exception): string
    {
        return 'HACIENDA_' . ($exception->getCode() ?: 'UNKNOWN');
    }
}
