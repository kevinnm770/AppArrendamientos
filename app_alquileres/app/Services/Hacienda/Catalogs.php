<?php

namespace App\Services\Hacienda;

/**
 * Mapeos a los catálogos oficiales de Hacienda para comprobantes electrónicos v4.4.
 * Centralizados aquí porque los usan tanto el armador de XML como el orquestador de envío.
 */
class Catalogs
{
    public static function identificationTypeCode(?string $type, string $idNumber): string
    {
        return match ($type) {
            'fisico' => '01',
            'juridico' => '02',
            'dimex' => '03',
            'nite' => '04',
            default => self::guessIdentificationTypeCode($idNumber),
        };
    }

    public static function guessIdentificationTypeCode(string $idNumber): string
    {
        $digits = preg_replace('/\D+/', '', $idNumber) ?? '';
        $length = strlen($digits);

        return match (true) {
            $length === 9 => '01',
            $length === 10 => '02',
            $length >= 11 && $length <= 12 => '03',
            default => '04',
        };
    }

    public static function saleConditionCode(string $value): string
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

    public static function paymentMethodCode(string $value): string
    {
        return match ($value) {
            'cash' => '01',
            'card' => '02',
            'check' => '03',
            'transfer' => '04',
            'collection' => '05',
            'sinpe_movil' => '06',
            'digital_platform' => '07',
            'other' => '99',
            default => '04',
        };
    }

    /**
     * Catálogo "Código de referencia" de Hacienda: motivo de una Nota de Crédito/Débito.
     */
    public static function creditNoteReasonOptions(): array
    {
        return [
            '01' => 'Anula documento de referencia',
            '02' => 'Corrige monto',
            '03' => 'Corrige otros datos',
            '04' => 'Referencia a otro documento',
            '05' => 'Sustituye comprobante provisional por definitivo',
            '99' => 'Otros',
        ];
    }

    /**
     * Catálogo "Tipo de código" del CodigoComercial opcional por línea.
     */
    public static function commercialCodeTypeOptions(): array
    {
        return [
            '01' => 'Código del artículo del vendedor',
            '02' => 'Código del artículo del comprador',
            '03' => 'Código del artículo asignado por la industria',
            '04' => 'Código interno',
        ];
    }

    /**
     * Unidades de medida más usuales para arrendamiento (catálogo completo de Hacienda es
     * mucho más grande; el campo en el formulario admite texto libre, esto es solo sugerencia).
     */
    public static function commonUnitsOfMeasure(): array
    {
        return ['Unid', 'Sp', 'St', 'Os', 'mes', 'día', 'h', 'kg', 'm', 'm2', 'm3', 'L', 'Nd'];
    }

    /**
     * Nombre del elemento raíz del XML según el tipo de documento (catálogo 01 de Hacienda).
     * Solo "01" (Factura Electrónica) y "03" (Nota de Crédito) tienen armador de contenido
     * implementado por ahora — son los únicos relevantes para corregir/anular alquileres.
     */
    public static function rootElementName(string $documentTypeCode): string
    {
        return match ($documentTypeCode) {
            '01' => 'FacturaElectronica',
            '02' => 'NotaDebitoElectronica',
            '03' => 'NotaCreditoElectronica',
            '04' => 'TiqueteElectronico',
            default => 'FacturaElectronica',
        };
    }
}
