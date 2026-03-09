<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceElectronicDetail;
use RuntimeException;

class CostaRicaElectronicInvoiceService
{
    public function __construct(
        protected CrLibreClient $crLibreClient,
        protected CostaRicaKeyGenerator $keyGenerator,
    ) {
    }

    public function providers(): array
    {
        return [
            [
                'name' => 'CRLibre',
                'type' => 'API comunitaria',
                'price' => 'Plan gratuito + planes económicos',
                'notes' => 'Buena opción para iniciar y validar integración de Hacienda.',
            ],
            [
                'name' => 'Facturador propio',
                'type' => 'Integración directa',
                'price' => 'Costo variable (certificado + desarrollo)',
                'notes' => 'Más control, pero mayor costo inicial y mantenimiento.',
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

    public function createCrLibreUser(array $data): array
    {
        return $this->crLibreClient->createUser($data);
    }

    public function loginCrLibre(array $credentials = []): array
    {
        return $this->crLibreClient->login($credentials);
    }

    public function logoutCrLibre(?string $token = null): array
    {
        return $this->crLibreClient->logout($token);
    }

    public function sendVoucher(Invoice $invoice, ?string $token = null): array
    {
        $invoice->loadMissing('electronicDetail');

        $detail = $invoice->electronicDetail;

        if (!$detail) {
            throw new RuntimeException('La factura no posee detalle electrónico para enviar a CRLibre.');
        }

        $this->keyGenerator->validateIdentifiers(
            (string) $detail->hacienda_consecutive,
            (string) $detail->hacienda_key,
        );

        try {
            $response = $this->crLibreClient->sendVoucher($this->buildCrLibrePayload($invoice), $token);

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

    public function getVoucherStatus(InvoiceElectronicDetail $detail, ?string $token = null): array
    {
        $identifier = $detail->hacienda_key ?: $detail->hacienda_consecutive;

        if (!$identifier) {
            throw new RuntimeException('No se encontró clave ni consecutivo para consultar estado en CRLibre.');
        }

        try {
            $response = $this->crLibreClient->getVoucherStatus($identifier, $token);

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

    public function getVoucherStatusByTrackId(InvoiceElectronicDetail $detail, string $trackId, ?string $token = null): array
    {
        try {
            $response = $this->crLibreClient->getVoucherStatusByTrackId($trackId, $token);

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

    public function recordExchangeMetadata(InvoiceElectronicDetail $detail, array $metadata): void
    {
        $detail->forceFill([
            'request_id' => $metadata['request_id'] ?? $detail->request_id,
            'error_code' => $metadata['error_code'] ?? null,
            'ptec_response' => $metadata['response_payload'] ?? $detail->ptec_response,
        ])->save();
    }

    public function buildCrLibrePayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['lessor', 'roomer', 'agreement.property', 'electronicDetail']);

        return [
            'tipoDocumento' => '01',
            'clave' => $invoice->electronicDetail?->hacienda_key,
            'consecutivo' => $invoice->electronicDetail?->hacienda_consecutive,
            'fechaEmision' => optional($invoice->issued_at)->toIso8601String(),
            'emisor' => [
                'nombre' => $invoice->lessor?->legal_name,
                'identificacion' => $invoice->lessor?->id_number,
                'telefono' => $invoice->lessor?->phone,
                'direccion' => $invoice->lessor?->address,
            ],
            'receptor' => [
                'nombre' => $invoice->roomer?->legal_name,
                'identificacion' => $invoice->roomer?->id_number,
                'telefono' => $invoice->roomer?->phone,
            ],
            'resumen' => [
                'codigoMoneda' => $invoice->currency,
                'tipoCambio' => $invoice->exchange_rate,
                'totalServGravados' => (float) $invoice->subtotal,
                'totalDescuentos' => (float) $invoice->discount_total,
                'totalImpuesto' => (float) $invoice->tax_total,
                'totalComprobante' => (float) $invoice->total,
            ],
            'condicionVenta' => $invoice->sale_condition,
            'medioPago' => $invoice->payment_method,
            'detalle' => [
                [
                    'numeroLinea' => 1,
                    'detalle' => $invoice->description,
                    'cantidad' => 1,
                    'unidadMedida' => 'Unid',
                    'precioUnitario' => (float) $invoice->subtotal,
                ],
            ],
        ];
    }

    protected function extractErrorCode(RuntimeException $exception): string
    {
        return 'CRLIBRE_' . ($exception->getCode() ?: 'UNKNOWN');
    }
}
