<?php

namespace App\Services;

use App\Models\Invoice;

class CostaRicaElectronicInvoiceService
{
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
            'accepted' => 'Aceptada',
            'rejected' => 'Rechazada',
            'error' => 'Error',
        ];
    }

    public function buildCrLibrePayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['lessor', 'roomer', 'agreement.property', 'electronicDetail']);

        return [
            'tipoDocumento' => $invoice->electronicDetail?->document_type,
            'clave' => $invoice->electronicDetail?->electronic_key,
            'consecutivo' => $invoice->electronicDetail?->consecutive_number,
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
}
