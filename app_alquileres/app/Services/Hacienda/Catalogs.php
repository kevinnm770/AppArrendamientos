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

    /**
     * Catálogo real "Condición de venta" (Nota 5, Anexos y Estructuras v4.4 de Hacienda,
     * verificado contra el PDF oficial). Los códigos 09 y 11 se omiten a propósito: el propio
     * anexo aclara que solo aplican a un Recibo Electrónico de Pago que cancela una factura
     * con código 08/10, documento que esta app no emite. No existe código dedicado a "cobro
     * de servicio" genérico, así que 'service' cae en 99 Otros.
     */
    public static function saleConditionCode(string $value): string
    {
        return match ($value) {
            'cash' => '01',
            'credit' => '02',
            'consignment' => '03',
            'layaway' => '04',
            'lease_purchase_option' => '05',
            'lease_finance_function' => '06',
            'third_party_collection' => '07',
            'state_services' => '08',
            'credit_90_days' => '10',
            'non_nationalized_goods' => '12',
            'used_goods_non_taxpayer' => '13',
            'operating_lease' => '14',
            'finance_lease' => '15',
            'service', 'other' => '99',
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
     * Confirmado contra el XSD oficial v4.4 — no existe código "03"; el catálogo real es
     * 01, 02, 04, 05, 99 (no consecutivo).
     */
    public static function creditNoteReasonOptions(): array
    {
        return [
            '01' => 'Anula documento de referencia',
            '02' => 'Corrige texto de documento de referencia',
            '04' => 'Referencia a otro documento',
            '05' => 'Sustituye comprobante provisional por contingencia',
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
     * Catálogo real "Tipo de Transacciones" (Nota 22, Anexos y Estructuras v4.4 de Hacienda,
     * verificado contra el PDF oficial). Campo opcional del LineaDetalle, va en <TipoTransaccion>
     * justo después de <UnidadMedida>.
     */
    public static function transactionTypeOptions(): array
    {
        return [
            '01' => 'Venta normal de bienes y servicios (transacción general)',
            '02' => 'Mercancía de autoconsumo exento',
            '03' => 'Mercancía de autoconsumo gravado',
            '04' => 'Servicio de autoconsumo exento',
            '05' => 'Servicio de autoconsumo gravado',
            '06' => 'Cuota de afiliación',
            '07' => 'Cuota de afiliación exenta',
            '08' => 'Bienes de capital para el emisor',
            '09' => 'Bienes de capital para el receptor',
            '10' => 'Bienes de capital para el emisor y el receptor',
            '11' => 'Bienes de capital de autoconsumo exento para el emisor',
            '12' => 'Bienes de capital sin contraprestación a terceros exento para el emisor',
            '13' => 'Sin contraprestación a terceros',
        ];
    }

    /**
     * Subconjunto curado del catálogo real "Unidad de medida" (Nota 15, Anexos y Estructuras
     * v4.4 de Hacienda, verificado contra el PDF oficial — el catálogo completo tiene ~80
     * símbolos, en su mayoría de física/química, irrelevantes aquí). Los símbolos son
     * case-sensitive ("Cm" Comisiones ≠ "cm" centímetro) y son justamente los valores que hay
     * que enviar en <UnidadMedida>; el select del formulario muestra la descripción legible.
     */
    public static function commonUnitsOfMeasure(): array
    {
        return [
            '1/m' => '1 por metro',
            'Alc' => 'Alquiler de uso comercial',
            'Al' => 'Alquiler de uso habitacional',
            'cm' => 'Centímetro',
            'Cm' => 'Comisiones',
            'D' => 'Día',
            'Gal' => 'Galón',
            'G' => 'Gramo',
            'h' => 'Hora',
            'I' => 'Intereses',
            'Kg' => 'Kilogramo',
            'kWh' => 'Kilovatios por hora',
            'L' => 'Litro',
            'M' => 'Metro',
            'm²' => 'Metro cuadrado',
            'm³' => 'Metro cúbico',
            'Mm' => 'Milímetro',
            'Min' => 'Minuto',
        ];
    }

    /**
     * CABYS más usuales para facturar arrendamiento (subcategoría 7211/7222/7329 del
     * catálogo oficial CABYS). El formulario también permite buscar/escribir cualquier
     * otro código fuera de esta lista curada.
     */
    public static function leaseCabysOptions(): array
    {
        return [
            '7211100000100' => 'Servicios de alquiler residencial, con monto de alquiler mensual inferior o igual a 1,5 salarios base',
            '7211100000200' => 'Servicios de alquiler residencial, con monto de alquiler mensual superior a 1,5 salarios base',
            '7211200000100' => 'Servicios de alquiler de inmuebles con fines comerciales, industriales o administrativos, con monto de alquiler mensual inferior o igual a 1,5 salarios base (aplica para micro y pequeñas empresas, debidamente inscritas)',
            '7211200000300' => 'Servicios de alquiler de inmueble con fines comerciales, industriales o administrativos, n.c.p.',
            '7222100000000' => 'Venta o alquiler de bienes inmuebles residenciales (excepto para propiedades de tiempo compartido), prestados a comisión o por contrato (bienes que son propiedad de otros)',
            '7222200000000' => 'Venta o alquiler de bienes inmuebles no residenciales, prestados a comisión o por contrato (bienes que son propiedad de otros)',
            '7329000000000' => 'Servicios de arrendamiento o alquiler de bienes, n.c.p.',
        ];
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
