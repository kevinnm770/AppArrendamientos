<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceElectronicDetail;
use App\Models\Lessor;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class CostaRicaElectronicInvoiceService
{
    public function __construct(
        protected CrLibreClient $crLibreClient,
    ) {
    }

    public function providers(): array
    {
        return [
            [
                'name' => 'CRLibre',
                'type' => 'API comunitaria',
                'price' => 'Plan gratuito + planes económicos',
                'notes' => 'Registro técnico, certificado, token, XML, firma y envío desde Laravel.',
            ],
        ];
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

    public function syncLessorCrLibreSetup(Lessor $lessor, ?UploadedFile $certificateFile = null, ?string $certificatePin = null): array
    {
        $session = $this->ensureCrLibreSession($lessor);
        $result = [
            'account_created' => $session['created'],
            'session_synced' => true,
            'certificate_uploaded' => false,
        ];

        if ($certificateFile) {
            $pinToUse = $certificatePin ?: $lessor->certificate_pin;

            if (!$pinToUse) {
                throw new RuntimeException('Debes indicar el PIN del certificado para subir el archivo .p12.');
            }

            $upload = $this->crLibreClient->uploadCertificate($session['iam'], $session['session_key'], $certificateFile);
            $downloadCode = $this->extractResponseValue($upload['payload'], ['resp.downloadCode', 'downloadCode']);

            if (!$downloadCode) {
                throw new RuntimeException('CRLibre no devolvió el downloadCode del certificado.');
            }

            $lessor->forceFill([
                'certificate_code' => $downloadCode,
                'certificate_pin' => $pinToUse,
                'certificate_uploaded_at' => now(),
            ])->save();

            $result['certificate_uploaded'] = true;
        }

        return $result;
    }

    public function sendVoucher(Invoice $invoice): array
    {
        $invoice->loadMissing(['lessor.user', 'roomer.user', 'agreement.property', 'electronicDetail']);

        $detail = $invoice->electronicDetail;
        if (!$detail) {
            throw new RuntimeException('La factura no posee detalle electrónico para enviar a CRLibre.');
        }

        $lessor = $invoice->lessor;
        if (!$lessor) {
            throw new RuntimeException('No se encontró el arrendador emisor de la factura.');
        }

        $this->ensureIssuerIsReady($lessor);
        $this->ensureInvoiceKey($invoice, $detail, $lessor);

        try {
            $tokenData = $this->requestHaciendaToken($lessor);
            $clientId = $tokenData['client_id'];
            $token = $tokenData['access_token'];

            $xmlResponse = $this->crLibreClient->generateInvoiceXml($this->buildInvoiceXmlPayload($invoice));
            $xml = $this->extractResponseValue($xmlResponse['payload'], ['resp.xml', 'xml', 'resp']);
            if (!$xml) {
                throw new RuntimeException('CRLibre no devolvió el XML de la factura electrónica.');
            }

            $signedResponse = $this->crLibreClient->signXml([
                'p12Url' => $lessor->certificate_code,
                'inXml' => base64_encode((string) $xml),
                'pinP12' => $lessor->certificate_pin,
                'tipodoc' => $this->resolveDocumentCode($detail->document_type ?: '01'),
            ]);

            $signedXml = $this->extractResponseValue($signedResponse['payload'], ['resp.xmlFirmado', 'resp.xml', 'xmlFirmado', 'xml']);
            if (!$signedXml) {
                throw new RuntimeException('CRLibre no devolvió el XML firmado.');
            }

            $response = $this->crLibreClient->sendVoucher([
                'token' => $token,
                'clave' => $detail->hacienda_key,
                'fecha' => $this->formatIssueDate($invoice),
                'emi_tipoIdentificacion' => $this->identificationTypeCode($lessor->identification_type, (string) $lessor->id_number),
                'emi_numeroIdentificacion' => preg_replace('/\D+/', '', (string) $lessor->id_number),
                'recp_tipoIdentificacion' => $this->inferReceiverIdentificationType($invoice),
                'recp_numeroIdentificacion' => preg_replace('/\D+/', '', (string) $invoice->roomer?->id_number),
                'comprobanteXml' => $signedXml,
                'client_id' => $clientId,
            ]);

            $this->recordExchangeMetadata($detail, [
                'request_id' => $response['request_id'] ?? null,
                'error_code' => $response['error_code'] ?? null,
                'response_payload' => [
                    'token' => ['client_id' => $clientId],
                    'xml' => $xmlResponse['payload'] ?? [],
                    'sign' => $signedResponse['payload'] ?? [],
                    'send' => $response['payload'] ?? [],
                ],
                'xml_content' => $signedXml,
            ]);

            return $response;
        } catch (RuntimeException $exception) {
            $this->recordExchangeMetadata($detail, [
                'error_code' => $this->extractErrorCode($exception),
                'response_payload' => [
                    'message' => $exception->getMessage(),
                ],
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

        $tokenData = $this->requestHaciendaToken($lessor);
        $identifier = $detail->hacienda_key ?: $detail->hacienda_consecutive;

        if (!$identifier) {
            throw new RuntimeException('No se encontró clave ni consecutivo para consultar estado en CRLibre.');
        }

        try {
            $response = $this->crLibreClient->getVoucherStatus($identifier, $tokenData['access_token'], $tokenData['client_id']);

            $this->recordExchangeMetadata($detail, [
                'request_id' => $response['request_id'] ?? null,
                'error_code' => $response['error_code'] ?? null,
                'response_payload' => $response['payload'] ?? null,
            ]);

            return $response;
        } catch (RuntimeException $exception) {
            $this->recordExchangeMetadata($detail, [
                'error_code' => $this->extractErrorCode($exception),
                'response_payload' => [
                    'message' => $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }
    }

    public function getVoucherStatusByTrackId(InvoiceElectronicDetail $detail, string $trackId): array
    {
        return $this->getVoucherStatus($detail);
    }

    public function recordExchangeMetadata(InvoiceElectronicDetail $detail, array $metadata): void
    {
        $xmlContent = $metadata['xml_content'] ?? $detail->xml_content;

        $detail->forceFill([
            'request_id' => $metadata['request_id'] ?? $detail->request_id,
            'error_code' => $metadata['error_code'] ?? null,
            'ptec_response' => $metadata['response_payload'] ?? $detail->ptec_response,
            'xml_content' => $xmlContent,
            'xml_hash' => $xmlContent ? hash('sha256', (string) $xmlContent) : $detail->xml_hash,
        ])->save();
    }

    protected function buildInvoiceXmlPayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['lessor.user', 'roomer.user', 'electronicDetail']);
        $lessor = $invoice->lessor;
        $roomer = $invoice->roomer;
        $detail = $invoice->electronicDetail;

        if (!$lessor || !$roomer || !$detail) {
            throw new RuntimeException('Faltan datos para construir el XML de factura electrónica.');
        }

        $line = [
            'cantidad' => '1',
            'unidadMedida' => 'Unid',
            'detalle' => $invoice->description,
            'precioUnitario' => $this->money($invoice->subtotal),
            'montoTotal' => $this->money($invoice->subtotal),
            'subtotal' => $this->money($invoice->subtotal - $invoice->discount_total),
            'montoTotalLinea' => $this->money($invoice->total - $invoice->late_fee_total),
        ];

        if ((float) $invoice->discount_total > 0) {
            $line['montoDescuento'] = $this->money($invoice->discount_total);
            $line['naturalezaDescuento'] = 'Descuento aplicado';
        }

        if ((float) $invoice->tax_total > 0) {
            $line['impuesto'] = [
                '1' => [
                    'codigo' => '01',
                    'tarifa' => $this->money($invoice->tax_percent),
                    'monto' => $this->money($invoice->tax_total),
                ],
            ];
        }

        $isTaxed = (float) $invoice->tax_total > 0;
        $netSubtotal = (float) $invoice->subtotal - (float) $invoice->discount_total;

        return [
            'clave' => $detail->hacienda_key,
            'consecutivo' => $detail->hacienda_consecutive,
            'fecha_emision' => $this->formatIssueDate($invoice),
            'emisor_nombre' => $lessor->legal_name,
            'emisor_tipo_indetif' => $this->identificationTypeCode($lessor->identification_type, (string) $lessor->id_number),
            'emisor_num_identif' => preg_replace('/\D+/', '', (string) $lessor->id_number),
            'nombre_comercial' => $lessor->commercial_name ?: $lessor->legal_name,
            'emisor_provincia' => $lessor->province ?: '1',
            'emisor_canton' => $lessor->canton ?: '01',
            'emisor_distrito' => $lessor->district ?: '01',
            'emisor_barrio' => $lessor->barrio ?: '01',
            'emisor_otras_senas' => $lessor->other_signs ?: ($lessor->address ?: 'No indicado'),
            'emisor_cod_pais_tel' => '506',
            'emisor_tel' => preg_replace('/\D+/', '', (string) $lessor->phone),
            'emisor_email' => $lessor->email ?: $lessor->user?->email,
            'receptor_nombre' => $roomer->legal_name,
            'receptor_tipo_identif' => $this->inferReceiverIdentificationType($invoice),
            'receptor_num_identif' => preg_replace('/\D+/', '', (string) $roomer->id_number),
            'receptor_email' => $roomer->user?->email,
            'condicion_venta' => $this->saleConditionCode((string) $invoice->sale_condition),
            'plazo_credito' => $invoice->sale_condition === 'credit' ? '30' : '0',
            'medio_pago' => $this->paymentMethodCode((string) $invoice->payment_method),
            'cod_moneda' => $invoice->currency,
            'tipo_cambio' => $invoice->currency === 'CRC' ? '1.0000' : $this->money($invoice->exchange_rate ?: 1),
            'total_serv_gravados' => $isTaxed ? $this->money($netSubtotal) : '0.00',
            'total_serv_exentos' => $isTaxed ? '0.00' : $this->money($netSubtotal),
            'total_merc_gravada' => '0.00',
            'total_merc_exenta' => '0.00',
            'total_gravados' => $isTaxed ? $this->money($netSubtotal) : '0.00',
            'total_exentos' => $isTaxed ? '0.00' : $this->money($netSubtotal),
            'total_ventas' => $this->money($invoice->subtotal),
            'total_descuentos' => $this->money($invoice->discount_total),
            'total_ventas_neta' => $this->money($netSubtotal),
            'total_impuestos' => $this->money($invoice->tax_total),
            'total_comprobante' => $this->money($invoice->total),
            'otros' => $invoice->notes ?: $invoice->description,
            'otrosType' => 'OtroTexto',
            'detalles' => json_encode(['1' => $line], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    protected function ensureCrLibreSession(Lessor $lessor): array
    {
        if (!$lessor->hasCrLibreAccount()) {
            return $this->registerLessorOnCrLibre($lessor);
        }

        $response = $this->crLibreClient->login([
            'userName' => $lessor->crlibre_username,
            'pwd' => $lessor->crlibre_password,
        ]);

        $sessionKey = $this->extractResponseValue($response['payload'], ['resp.sessionKey', 'sessionKey']);
        if (!$sessionKey) {
            throw new RuntimeException('CRLibre no devolvió sessionKey al iniciar sesión.');
        }

        $lessor->forceFill([
            'crlibre_session_key' => $sessionKey,
            'crlibre_session_obtained_at' => now(),
        ])->save();

        return [
            'created' => false,
            'iam' => $lessor->crlibre_username,
            'session_key' => $sessionKey,
        ];
    }

    protected function registerLessorOnCrLibre(Lessor $lessor): array
    {
        $email = $lessor->email ?: $lessor->user?->email;
        if (!$email) {
            throw new RuntimeException('El arrendador necesita un correo para registrarse automáticamente en CRLibre.');
        }

        if (!$lessor->legal_name) {
            throw new RuntimeException('El arrendador necesita un nombre legal para registrarse automáticamente en CRLibre.');
        }

        $generatedUsername = $this->generateCrLibreUsername($lessor);
        $generatedPassword = Str::random(24);

        $response = $this->crLibreClient->createUser([
            'fullName' => $lessor->legal_name,
            'userName' => $generatedUsername,
            'email' => $email,
            'about' => 'Arrendador registrado desde app_alquileres',
            'country' => 'CR',
            'pwd' => $generatedPassword,
        ]);

        $sessionKey = $this->extractResponseValue($response['payload'], ['resp.sessionKey', 'sessionKey']);
        $userName = $this->extractResponseValue($response['payload'], ['resp.userName', 'userName']) ?: $generatedUsername;

        if (!$sessionKey) {
            throw new RuntimeException('CRLibre no devolvió sessionKey al registrar el arrendador.');
        }

        $lessor->forceFill([
            'crlibre_username' => $userName,
            'crlibre_password' => $generatedPassword,
            'crlibre_session_key' => $sessionKey,
            'crlibre_session_obtained_at' => now(),
        ])->save();

        return [
            'created' => true,
            'iam' => $userName,
            'session_key' => $sessionKey,
        ];
    }

    protected function requestHaciendaToken(Lessor $lessor): array
    {
        if (!$lessor->hacienda_username || !$lessor->hacienda_password) {
            throw new RuntimeException('El arrendador no tiene configurado el usuario y contraseña de Hacienda.');
        }

        $clientId = config('services.crlibre.environment') === 'prod'
            ? config('services.crlibre.client_id_prod', 'api-prod')
            : config('services.crlibre.client_id_stage', 'api-stag');

        if ($lessor->hacienda_access_token && $lessor->hacienda_token_expires_at?->gt(now()->addSeconds(15))) {
            return [
                'access_token' => $lessor->hacienda_access_token,
                'client_id' => $clientId,
                'payload' => [],
            ];
        }

        if ($lessor->hacienda_refresh_token && $lessor->hacienda_refresh_expires_at?->gt(now()->addSeconds(15))) {
            $response = $this->crLibreClient->refreshToken([
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'refresh_token' => $lessor->hacienda_refresh_token,
            ]);
        } else {
            $response = $this->crLibreClient->getToken([
                'grant_type' => 'password',
                'client_id' => $clientId,
                'username' => $lessor->hacienda_username,
                'password' => $lessor->hacienda_password,
            ]);
        }

        $accessToken = $this->extractResponseValue($response['payload'], ['resp.access_token', 'access_token']);
        $refreshToken = $this->extractResponseValue($response['payload'], ['resp.refresh_token', 'refresh_token']);
        $expiresIn = (int) ($this->extractResponseValue($response['payload'], ['resp.expires_in', 'expires_in']) ?? 300);
        $refreshExpiresIn = (int) ($this->extractResponseValue($response['payload'], ['resp.refresh_expires_in', 'refresh_expires_in']) ?? 36000);

        if (!$accessToken) {
            throw new RuntimeException('CRLibre no devolvió un access token de Hacienda.');
        }

        $lessor->forceFill([
            'hacienda_access_token' => $accessToken,
            'hacienda_refresh_token' => $refreshToken ?: $lessor->hacienda_refresh_token,
            'hacienda_token_expires_at' => Carbon::now()->addSeconds($expiresIn),
            'hacienda_refresh_expires_at' => Carbon::now()->addSeconds($refreshExpiresIn),
        ])->save();

        return [
            'access_token' => $accessToken,
            'client_id' => $clientId,
            'payload' => $response['payload'],
        ];
    }

    protected function ensureInvoiceKey(Invoice $invoice, InvoiceElectronicDetail $detail, Lessor $lessor): void
    {
        if ($detail->hacienda_key && $detail->hacienda_consecutive) {
            return;
        }

        $internalNumber = str_pad((string) $invoice->id, 10, '0', STR_PAD_LEFT);
        $response = $this->crLibreClient->createVoucherKey([
            'tipoCedula' => $lessor->identification_type ?: 'fisico',
            'cedula' => preg_replace('/\D+/', '', (string) $lessor->id_number),
            'codigoPais' => '506',
            'consecutivo' => $internalNumber,
            'situacion' => 'normal',
            'codigoSeguridad' => str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'tipoDocumento' => $this->resolveDocumentCode($detail->document_type ?: '01'),
            'terminal' => (string) config('services.cr_einvoice.terminal', '00001'),
            'sucursal' => (string) config('services.cr_einvoice.branch', '001'),
        ]);

        $key = $this->extractResponseValue($response['payload'], ['resp.clave', 'clave']);
        $consecutive = $this->extractResponseValue($response['payload'], ['resp.consecutivo', 'consecutivo']);

        if (!$key || !$consecutive) {
            throw new RuntimeException('CRLibre no devolvió clave y consecutivo para el comprobante.');
        }

        $detail->forceFill([
            'hacienda_key' => $key,
            'hacienda_consecutive' => $consecutive,
            'sucursal' => (string) config('services.cr_einvoice.branch', '001'),
            'terminal' => (string) config('services.cr_einvoice.terminal', '00001'),
            'internal_number' => $internalNumber,
        ])->save();
    }

    protected function ensureIssuerIsReady(Lessor $lessor): void
    {
        $missing = [];

        foreach ([
            'id_number' => $lessor->id_number,
            'legal_name' => $lessor->legal_name,
            'phone' => $lessor->phone,
            'crlibre_username' => $lessor->crlibre_username,
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

    protected function generateCrLibreUsername(Lessor $lessor): string
    {
        $base = Str::lower(Str::slug($lessor->legal_name ?: 'lessor', ''));
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?: 'lessor';

        return substr($base, 0, 12) . $lessor->id . Str::lower(Str::random(4));
    }

    protected function extractResponseValue(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = Arr::get($payload, $path);
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    protected function formatIssueDate(Invoice $invoice): string
    {
        return ($invoice->issued_at ?? now())
            ->copy()
            ->timezone('America/Costa_Rica')
            ->format('Y-m-d\TH:i:sP');
    }

    protected function identificationTypeCode(?string $type, string $idNumber): string
    {
        return match ($type) {
            'fisico' => '01',
            'juridico' => '02',
            'dimex' => '03',
            'nite' => '04',
            default => $this->guessIdentificationTypeCode($idNumber),
        };
    }

    protected function inferReceiverIdentificationType(Invoice $invoice): string
    {
        return $this->guessIdentificationTypeCode((string) $invoice->roomer?->id_number);
    }

    protected function guessIdentificationTypeCode(string $idNumber): string
    {
        $digits = preg_replace('/\D+/', '', $idNumber);
        $length = strlen($digits);

        return match (true) {
            $length === 9 => '01',
            $length === 10 => '02',
            $length >= 11 && $length <= 12 => '03',
            default => '04',
        };
    }

    protected function saleConditionCode(string $value): string
    {
        return match ($value) {
            'cash' => '01',
            'credit' => '02',
            'consignment' => '03',
            'layaway' => '04',
            'service' => '05',
            default => '01',
        };
    }

    protected function paymentMethodCode(string $value): string
    {
        return match ($value) {
            'cash' => '01',
            'card' => '02',
            'transfer' => '03',
            'check' => '04',
            'collection' => '05',
            'other' => '99',
            default => '03',
        };
    }

    protected function resolveDocumentCode(string $value): string
    {
        return match ($value) {
            '01' => 'FE',
            '02' => 'NC',
            '03' => 'ND',
            '04' => 'TE',
            default => 'FE',
        };
    }

    protected function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    protected function extractErrorCode(RuntimeException $exception): string
    {
        return 'CRLIBRE_' . ($exception->getCode() ?: 'UNKNOWN');
    }
}
