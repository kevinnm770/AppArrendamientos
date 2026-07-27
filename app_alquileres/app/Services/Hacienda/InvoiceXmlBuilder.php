<?php

namespace App\Services\Hacienda;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Arma el XML del comprobante electrónico v4.4 (sin firmar) a partir de la factura y sus
 * líneas ya persistidas. Estructura basada en la documentación pública de Hacienda para
 * Factura Electrónica v4.4; el namespace y algún detalle fino de catálogo (unidad de
 * medida, código de tarifa IVA por caso especial) debe confirmarse contra el XSD oficial
 * una vez descargado del ATV — ver nota en Fase 1 del plan.
 */
class InvoiceXmlBuilder
{
    protected const NAMESPACE_BASE = 'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/v4.4';

    /**
     * $clave y $consecutivo ya deben venir generados por ClaveGenerator; la situación y el
     * código de seguridad viajan codificados dentro de $clave, no como elementos del XML.
     */
    public function build(Invoice $invoice, string $clave, string $consecutivo): string
    {
        $invoice->loadMissing(['lessor.user', 'roomer.user', 'items', 'electronicDetail', 'referenceInvoice.electronicDetail']);

        $lessor = $invoice->lessor;
        $roomer = $invoice->roomer;
        $items = $invoice->items;
        $documentTypeCode = $invoice->electronicDetail->document_type ?? '01';

        if (!in_array($documentTypeCode, ['01', '03'], true)) {
            throw new RuntimeException("InvoiceXmlBuilder solo tiene implementados los tipos de documento 01 (Factura Electrónica) y 03 (Nota de Crédito); recibido: {$documentTypeCode}.");
        }

        if (!$lessor || !$roomer) {
            throw new RuntimeException('Faltan datos de emisor o receptor para armar el XML del comprobante.');
        }

        if ($items->isEmpty()) {
            throw new RuntimeException('La factura no tiene líneas de detalle; no se puede armar el comprobante.');
        }

        if ($documentTypeCode === '03' && !$invoice->referenceInvoice?->electronicDetail?->hacienda_key) {
            throw new RuntimeException('La nota de crédito no tiene una factura de referencia con clave de Hacienda válida.');
        }

        $rootName = Catalogs::rootElementName($documentTypeCode);
        $namespace = self::NAMESPACE_BASE . '/facturaElectronica';

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS($namespace, $rootName);
        $doc->appendChild($root);

        $root->appendChild($doc->createElement('Clave', $clave));

        // Obligatorio en v4.4 (rechazo real: "One of ProveedorSistemas is expected") y de tipo
        // SIMPLE, no una estructura anidada (segundo rechazo real: "is a simple type, so it
        // must have no element information item [children]") — solo texto. Como el sistema es
        // propio (no de un tercero), se usa la cédula del propio arrendador como valor.
        $root->appendChild($doc->createElement('ProveedorSistemas', preg_replace('/\D+/', '', (string) $lessor->id_number) ?? ''));

        $root->appendChild($doc->createElement('CodigoActividadEmisor', (string) ($lessor->economic_activity_code ?: '')));
        $root->appendChild($doc->createElement('NumeroConsecutivo', $consecutivo));
        $root->appendChild($doc->createElement('FechaEmision', $this->formatIssueDate($invoice)));

        $root->appendChild($this->buildParty($doc, 'Emisor', $lessor->legal_name, $lessor->commercial_name, $lessor->identification_type, (string) $lessor->id_number, $lessor->province, $lessor->canton, $lessor->district, $lessor->barrio, $lessor->other_signs ?: $lessor->address, $lessor->phone, $lessor->email ?: $lessor->user?->email));

        $root->appendChild($this->buildParty($doc, 'Receptor', $roomer->legal_name, null, $roomer->identification_type, (string) $roomer->id_number, null, null, null, null, null, $roomer->phone, $roomer->user?->email));

        $root->appendChild($doc->createElement('CondicionVenta', Catalogs::saleConditionCode((string) $invoice->sale_condition)));

        if ($invoice->sale_condition === 'credit') {
            $root->appendChild($doc->createElement('PlazoCredito', '30'));
        }

        $root->appendChild($this->buildDetalleServicio($doc, $items));

        $root->appendChild($this->buildResumenFactura($doc, $invoice, $items));

        if ($documentTypeCode === '03') {
            $root->appendChild($this->buildInformacionReferencia($doc, $invoice));
        }

        return $doc->saveXML();
    }

    /**
     * Bloque obligatorio en Notas de Crédito/Débito: identifica el comprobante que se
     * corrige/anula. NOTA: la posición exacta de este elemento dentro de la secuencia del
     * XSD v4.4 (antes/después de ResumenFactura) debe confirmarse contra el esquema oficial;
     * aquí va al final, que es donde aparece en la mayoría de versiones anteriores (v4.3).
     */
    protected function buildInformacionReferencia(DOMDocument $doc, Invoice $invoice): DOMElement
    {
        $referenceInvoice = $invoice->referenceInvoice;
        $referenceDetail = $referenceInvoice->electronicDetail;

        // "Numero" se llena aquí con la Clave (50 dígitos) del comprobante referenciado —
        // confirmar contra el XSD si en v4.4 espera la Clave completa o el Consecutivo (20).
        $informacionReferencia = $doc->createElement('InformacionReferencia');
        $informacionReferencia->appendChild($doc->createElement('TipoDoc', $referenceDetail->document_type ?: '01'));
        $informacionReferencia->appendChild($doc->createElement('Numero', (string) $referenceDetail->hacienda_key));
        $informacionReferencia->appendChild($doc->createElement('FechaEmision', $this->formatIssueDate($referenceInvoice)));
        $informacionReferencia->appendChild($doc->createElement('Codigo', (string) $invoice->credit_note_reason_code));
        $informacionReferencia->appendChild($doc->createElement('Razon', (string) $invoice->credit_note_reason_text));

        return $informacionReferencia;
    }

    protected function buildParty(
        DOMDocument $doc,
        string $tag,
        ?string $name,
        ?string $commercialName,
        ?string $identificationType,
        string $idNumber,
        ?string $province,
        ?string $canton,
        ?string $district,
        ?string $barrio,
        ?string $otherSigns,
        ?string $phone,
        ?string $email,
    ): DOMElement {
        $party = $doc->createElement($tag);
        $party->appendChild($doc->createElement('Nombre', (string) $name));

        $identification = $doc->createElement('Identificacion');
        $identification->appendChild($doc->createElement('Tipo', Catalogs::identificationTypeCode($identificationType, $idNumber)));
        $identification->appendChild($doc->createElement('Numero', preg_replace('/\D+/', '', $idNumber) ?? ''));
        $party->appendChild($identification);

        if ($commercialName) {
            $party->appendChild($doc->createElement('NombreComercial', $commercialName));
        }

        if ($province && $canton && $district) {
            $ubicacion = $doc->createElement('Ubicacion');
            $ubicacion->appendChild($doc->createElement('Provincia', $province));
            $ubicacion->appendChild($doc->createElement('Canton', $canton));
            $ubicacion->appendChild($doc->createElement('Distrito', $district));

            // Barrio exige mínimo 5 caracteres en el XSD (rechazo real: "cvc-minLength-valid ...
            // minLength '5'"); los códigos cortos tipo provincia/cantón/distrito (ej. "01") que
            // hoy captura el perfil del arrendador no cumplen, así que se omite en ese caso en
            // vez de mandar un valor inválido. Pendiente: cargar el código de barrio real del
            // catálogo de Hacienda (más largo) en el perfil del arrendador.
            if ($barrio && strlen($barrio) >= 5) {
                $ubicacion->appendChild($doc->createElement('Barrio', $barrio));
            }

            $ubicacion->appendChild($doc->createElement('OtrasSenas', $otherSigns ?: 'No indicado'));
            $party->appendChild($ubicacion);
        }

        if ($phone) {
            $telefono = $doc->createElement('Telefono');
            $telefono->appendChild($doc->createElement('CodigoPais', '506'));
            $telefono->appendChild($doc->createElement('NumTelefono', preg_replace('/\D+/', '', $phone) ?? ''));
            $party->appendChild($telefono);
        }

        if ($email) {
            $party->appendChild($doc->createElement('CorreoElectronico', $email));
        }

        return $party;
    }

    protected function buildDetalleServicio(DOMDocument $doc, iterable $items): DOMElement
    {
        $detalleServicio = $doc->createElement('DetalleServicio');
        $lineNumber = 1;

        /** @var InvoiceItem $item */
        foreach ($items as $item) {
            $linea = $doc->createElement('LineaDetalle');
            $linea->appendChild($doc->createElement('NumeroLinea', (string) $lineNumber));

            // CodigoActividad NO es un elemento válido dentro de LineaDetalle en este XSD
            // (rechazo real: tras CodigoCABYS solo se aceptan CodigoComercial o Cantidad) —
            // el código de actividad del emisor ya viaja una sola vez a nivel de encabezado
            // en CodigoActividadEmisor, así que aquí no se repite.
            if ($item->cabys_code) {
                $linea->appendChild($doc->createElement('CodigoCABYS', $item->cabys_code));
            }

            if ($item->commercial_code_type && $item->commercial_code) {
                $codigoComercial = $doc->createElement('CodigoComercial');
                $codigoComercial->appendChild($doc->createElement('Tipo', $item->commercial_code_type));
                $codigoComercial->appendChild($doc->createElement('Codigo', $item->commercial_code));
                $linea->appendChild($codigoComercial);
            }

            $linea->appendChild($doc->createElement('Cantidad', $this->money($item->quantity, 3)));
            $linea->appendChild($doc->createElement('UnidadMedida', $item->unit_of_measure ?: 'Unid'));

            if ($item->commercial_unit_of_measure) {
                $linea->appendChild($doc->createElement('UnidadMedidaComercial', $item->commercial_unit_of_measure));
            }

            $linea->appendChild($doc->createElement('Detalle', $item->description));
            $linea->appendChild($doc->createElement('PrecioUnitario', $this->money($item->unit_price)));

            $montoTotal = (float) $item->quantity * (float) $item->unit_price;
            $linea->appendChild($doc->createElement('MontoTotal', $this->money($montoTotal)));

            if ((float) $item->discount_total > 0) {
                $descuento = $doc->createElement('Descuento');
                $descuento->appendChild($doc->createElement('MontoDescuento', $this->money($item->discount_total)));
                $descuento->appendChild($doc->createElement('NaturalezaDescuento', 'Descuento aplicado'));
                $linea->appendChild($descuento);
            }

            $linea->appendChild($doc->createElement('SubTotal', $this->money($item->subtotal)));

            if ((float) $item->tax_total > 0) {
                $impuesto = $doc->createElement('Impuesto');
                $impuesto->appendChild($doc->createElement('Codigo', $item->tax_code ?: '01'));
                $impuesto->appendChild($doc->createElement('CodigoTarifaIVA', $this->ivaRateCode((float) $item->tax_rate)));
                $impuesto->appendChild($doc->createElement('Tarifa', $this->money($item->tax_rate)));
                $impuesto->appendChild($doc->createElement('Monto', $this->money($item->tax_total)));
                $linea->appendChild($impuesto);
            }

            $linea->appendChild($doc->createElement('ImpuestoNeto', $this->money($item->tax_total)));
            $linea->appendChild($doc->createElement('MontoTotalLinea', $this->money($item->line_total)));

            $detalleServicio->appendChild($linea);
            $lineNumber++;
        }

        return $detalleServicio;
    }

    protected function buildResumenFactura(DOMDocument $doc, Invoice $invoice, iterable $items): DOMElement
    {
        $resumen = $doc->createElement('ResumenFactura');

        $codigoTipoMoneda = $doc->createElement('CodigoTipoMoneda');
        $codigoTipoMoneda->appendChild($doc->createElement('CodigoMoneda', $invoice->currency));

        if ($invoice->currency !== 'CRC') {
            $codigoTipoMoneda->appendChild($doc->createElement('TipoCambio', $this->money($invoice->exchange_rate ?: 1, 5)));
        }

        $resumen->appendChild($codigoTipoMoneda);

        // En v4.4 MedioPago vive dentro de ResumenFactura, no a nivel raíz junto a
        // CondicionVenta (rechazo real: el validador solo aceptaba CondicionVentaOtros/
        // PlazoCredito/DetalleServicio/OtrosCargos/ResumenFactura en esa posición). Hacienda
        // permite hasta 4 (pago dividido, ej. parte transferencia + parte efectivo).
        foreach ((array) ($invoice->payment_methods ?: []) as $method) {
            $resumen->appendChild($doc->createElement('MedioPago', Catalogs::paymentMethodCode((string) $method)));
        }

        $servGravado = 0.0;
        $servExento = 0.0;
        $mercGravado = 0.0;
        $mercExento = 0.0;

        foreach ($items as $item) {
            /** @var InvoiceItem $item */
            $isGravado = (float) $item->tax_total > 0;
            $isGoods = $item->item_type === 'goods';

            if ($isGoods) {
                $isGravado ? $mercGravado += (float) $item->subtotal : $mercExento += (float) $item->subtotal;
            } else {
                $isGravado ? $servGravado += (float) $item->subtotal : $servExento += (float) $item->subtotal;
            }
        }

        $resumen->appendChild($doc->createElement('TotalServGravados', $this->money($servGravado)));
        $resumen->appendChild($doc->createElement('TotalServExentos', $this->money($servExento)));
        $resumen->appendChild($doc->createElement('TotalMercanciasGravadas', $this->money($mercGravado)));
        $resumen->appendChild($doc->createElement('TotalMercanciasExentas', $this->money($mercExento)));
        $resumen->appendChild($doc->createElement('TotalGravado', $this->money($servGravado + $mercGravado)));
        $resumen->appendChild($doc->createElement('TotalExento', $this->money($servExento + $mercExento)));
        $resumen->appendChild($doc->createElement('TotalVenta', $this->money($invoice->subtotal)));
        $resumen->appendChild($doc->createElement('TotalDescuentos', $this->money($invoice->discount_total)));
        $resumen->appendChild($doc->createElement('TotalVentaNeta', $this->money((float) $invoice->subtotal - (float) $invoice->discount_total)));
        $resumen->appendChild($doc->createElement('TotalImpuesto', $this->money($invoice->tax_total)));
        $resumen->appendChild($doc->createElement('TotalComprobante', $this->money($invoice->total)));

        return $resumen;
    }

    /**
     * Catálogo "Código de tarifa de IVA": 01 exento/0%, 08 tarifa general 13%. Los tramos
     * reducidos (1%/2%/4%) no aplican al caso de arrendamiento y se omiten a propósito;
     * si en el futuro se factura algo con tarifa reducida, extender este mapeo.
     */
    protected function ivaRateCode(float $rate): string
    {
        return match (true) {
            $rate <= 0.0 => '01',
            $rate >= 13.0 => '08',
            default => '08',
        };
    }

    protected function formatIssueDate(Invoice $invoice): string
    {
        return ($invoice->issued_at ?? now())
            ->copy()
            ->timezone('America/Costa_Rica')
            ->format('Y-m-d\TH:i:sP');
    }

    protected function money(float|string $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }
}
