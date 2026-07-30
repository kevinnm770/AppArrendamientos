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

        $root->appendChild($this->buildParty($doc, 'Receptor', $roomer->legal_name, null, $roomer->identification_type, (string) $roomer->id_number, $roomer->province, $roomer->canton, $roomer->district, $roomer->barrio, null, $roomer->phone, $roomer->user?->email));

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
     * corrige/anula. Nombres y orden confirmados contra el XSD oficial v4.4: TipoDocIR,
     * TipoDocRefOTRO(opt), Numero(opt), FechaEmisionIR, Codigo(opt), CodigoReferenciaOTRO(opt),
     * Razon(opt) — los nombres "TipoDoc"/"FechaEmision" que se usaban antes no existen.
     */
    protected function buildInformacionReferencia(DOMDocument $doc, Invoice $invoice): DOMElement
    {
        $referenceInvoice = $invoice->referenceInvoice;
        $referenceDetail = $referenceInvoice->electronicDetail;

        $informacionReferencia = $doc->createElement('InformacionReferencia');
        $informacionReferencia->appendChild($doc->createElement('TipoDocIR', $referenceDetail->document_type ?: '01'));
        $informacionReferencia->appendChild($doc->createElement('Numero', (string) $referenceDetail->hacienda_key));
        $informacionReferencia->appendChild($doc->createElement('FechaEmisionIR', $this->formatIssueDate($referenceInvoice)));
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

            // Opcional (Nota 22); va justo después de UnidadMedida y antes de
            // UnidadMedidaComercial según el orden de campos del XSD v4.4.
            if ($item->transaction_type) {
                $linea->appendChild($doc->createElement('TipoTransaccion', $item->transaction_type));
            }

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

            // BaseImponible va ANTES de Impuesto (rechazo real: el validador esperaba
            // IVACobradoFabrica/BaseImponible justo en la posición donde antes ponía Impuesto
            // directamente). Es el monto sobre el que se calcula el impuesto de la línea.
            $linea->appendChild($doc->createElement('BaseImponible', $this->money($item->subtotal)));

            // Se declara el impuesto de la línea SIEMPRE, incluso exento/0 — rechazo real al
            // omitirlo por completo en una línea exenta (validador esperaba BaseImponible/
            // IVACobradoFabrica, es decir contenido de Impuesto, antes de aceptar ImpuestoNeto).
            $impuesto = $doc->createElement('Impuesto');
            $impuesto->appendChild($doc->createElement('Codigo', $item->tax_code ?: '01'));
            $impuesto->appendChild($doc->createElement('CodigoTarifaIVA', $this->ivaRateCode((float) $item->tax_rate)));
            $impuesto->appendChild($doc->createElement('Tarifa', $this->money($item->tax_rate)));
            $impuesto->appendChild($doc->createElement('Monto', $this->money($item->tax_total)));
            $linea->appendChild($impuesto);

            // Obligatorio en el XSD (sin minOccurs="0"), aunque la mayoría de líneas no
            // apliquen IVA cobrado a nivel de fábrica — se declara en 0.
            $linea->appendChild($doc->createElement('ImpuestoAsumidoEmisorFabrica', $this->money(0)));

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

        // TipoCambio es obligatorio siempre (rechazo real: "content of CodigoTipoMoneda is
        // not complete, TipoCambio is expected"), incluso en CRC — se usa 1 en ese caso.
        $codigoTipoMoneda->appendChild($doc->createElement('TipoCambio', $this->money($invoice->currency === 'CRC' ? 1 : ($invoice->exchange_rate ?: 1), 5)));

        $resumen->appendChild($codigoTipoMoneda);

        // Costa Rica distingue Gravado / Exento / No Sujeto como categorías legales
        // separadas (rechazo real: reportar una línea "No Sujeta" dentro de "Exentos" hace
        // que Hacienda diga que el resumen "carece del monto Total No Sujeto" y que el total
        // de exentos "no coincide"). tax_condition en cada línea decide el balde correcto.
        $servGravado = 0.0;
        $servExento = 0.0;
        $servNoSujeto = 0.0;
        $mercGravado = 0.0;
        $mercExento = 0.0;
        $mercNoSujeta = 0.0;

        foreach ($items as $item) {
            /** @var InvoiceItem $item */
            $isGoods = $item->item_type === 'goods';
            $amount = (float) $item->subtotal;

            $bucket = match ($item->tax_condition) {
                'no_sujeto' => 'no_sujeto',
                'exento' => 'exento',
                default => (float) $item->tax_total > 0 ? 'gravado' : 'exento',
            };

            if ($isGoods) {
                match ($bucket) {
                    'gravado' => $mercGravado += $amount,
                    'no_sujeto' => $mercNoSujeta += $amount,
                    default => $mercExento += $amount,
                };
            } else {
                match ($bucket) {
                    'gravado' => $servGravado += $amount,
                    'no_sujeto' => $servNoSujeto += $amount,
                    default => $servExento += $amount,
                };
            }
        }

        $resumen->appendChild($doc->createElement('TotalServGravados', $this->money($servGravado)));
        $resumen->appendChild($doc->createElement('TotalServExentos', $this->money($servExento)));
        $resumen->appendChild($doc->createElement('TotalServNoSujeto', $this->money($servNoSujeto)));
        $resumen->appendChild($doc->createElement('TotalMercanciasGravadas', $this->money($mercGravado)));
        $resumen->appendChild($doc->createElement('TotalMercanciasExentas', $this->money($mercExento)));
        $resumen->appendChild($doc->createElement('TotalMercNoSujeta', $this->money($mercNoSujeta)));
        $resumen->appendChild($doc->createElement('TotalGravado', $this->money($servGravado + $mercGravado)));
        $resumen->appendChild($doc->createElement('TotalExento', $this->money($servExento + $mercExento)));
        $resumen->appendChild($doc->createElement('TotalNoSujeto', $this->money($servNoSujeto + $mercNoSujeta)));
        $resumen->appendChild($doc->createElement('TotalVenta', $this->money($invoice->subtotal)));
        $resumen->appendChild($doc->createElement('TotalDescuentos', $this->money($invoice->discount_total)));
        $resumen->appendChild($doc->createElement('TotalVentaNeta', $this->money((float) $invoice->subtotal - (float) $invoice->discount_total)));

        // Obligatorio cuando las líneas tienen detalle de Impuesto (rechazo real: "El
        // documento posee detalle de Impuesto pero carece del campo Total Desglose
        // Impuestos") — un TotalDesgloseImpuesto por cada combinación Código/CodigoTarifaIVA
        // realmente usada en las líneas, va justo antes de TotalImpuesto.
        $desgloses = [];

        foreach ($items as $item) {
            /** @var InvoiceItem $item */
            $codigo = $item->tax_code ?: '01';
            $codigoTarifa = $this->ivaRateCode((float) $item->tax_rate);
            $key = $codigo . '|' . $codigoTarifa;

            if (!isset($desgloses[$key])) {
                $desgloses[$key] = ['codigo' => $codigo, 'codigo_tarifa' => $codigoTarifa, 'monto' => 0.0];
            }

            $desgloses[$key]['monto'] += (float) $item->tax_total;
        }

        foreach ($desgloses as $desglose) {
            $totalDesglose = $doc->createElement('TotalDesgloseImpuesto');
            $totalDesglose->appendChild($doc->createElement('Codigo', $desglose['codigo']));
            $totalDesglose->appendChild($doc->createElement('CodigoTarifaIVA', $desglose['codigo_tarifa']));
            $totalDesglose->appendChild($doc->createElement('TotalMontoImpuesto', $this->money($desglose['monto'])));
            $resumen->appendChild($totalDesglose);
        }

        $resumen->appendChild($doc->createElement('TotalImpuesto', $this->money($invoice->tax_total)));

        // MedioPago va aquí, justo antes de TotalComprobante y después de todos los totales
        // (rechazo real: al ponerlo justo después de CodigoTipoMoneda, el validador esperaba
        // TotalServGravados/.../TotalVenta en esa posición) — es una estructura compleja con
        // TipoMedioPago anidado. Hacienda permite hasta 4 (pago dividido); TotalMedioPago es
        // obligatorio si hay más de uno.
        $paymentMethods = (array) ($invoice->payment_methods ?: []);

        // PENDIENTE: el formulario no captura cuánto se pagó por cada método individualmente,
        // solo cuáles se usaron. Con un solo método no hace falta (TotalMedioPago es opcional
        // ahí). Con varios, Hacienda exige el desglose — se reparte el total en partes iguales
        // como aproximación honesta hasta que el formulario capture montos reales por método.
        $evenShare = count($paymentMethods) > 1 ? round((float) $invoice->total / count($paymentMethods), 2) : null;

        foreach ($paymentMethods as $method) {
            $medioPago = $doc->createElement('MedioPago');
            $medioPago->appendChild($doc->createElement('TipoMedioPago', Catalogs::paymentMethodCode((string) $method)));

            // Campo real del XSD (hermano de TipoMedioPago, verificado contra el PDF oficial)
            // para describir el medio de pago cuando se marca "Otros" (código 99).
            if ($method === 'other' && $invoice->payment_method_other_description) {
                $medioPago->appendChild($doc->createElement('MedioPagoOtros', $invoice->payment_method_other_description));
            }

            if ($evenShare !== null) {
                $medioPago->appendChild($doc->createElement('TotalMedioPago', $this->money($evenShare)));
            }

            $resumen->appendChild($medioPago);
        }

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
