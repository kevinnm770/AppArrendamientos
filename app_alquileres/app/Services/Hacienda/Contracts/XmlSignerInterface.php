<?php

namespace App\Services\Hacienda\Contracts;

interface XmlSignerInterface
{
    /**
     * Firma un XML de comprobante electrónico con XAdES-BES usando el certificado
     * criptográfico (.p12) del arrendador, y devuelve el XML firmado completo.
     *
     * @param string $xml XML sin firmar del comprobante (UTF-8).
     * @param string $p12Path Ruta absoluta al archivo .p12 del arrendador.
     * @param string $pin PIN del certificado.
     */
    public function sign(string $xml, string $p12Path, string $pin): string;
}
