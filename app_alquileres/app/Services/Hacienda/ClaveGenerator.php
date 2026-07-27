<?php

namespace App\Services\Hacienda;

use App\Models\Invoice;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Genera la clave numérica de 50 dígitos y el consecutivo de 20 dígitos que exige
 * Hacienda para todo comprobante electrónico (independiente del proveedor). Estructura:
 *
 * Clave (50):      país(3) + día(2) + mes(2) + año(2) + cédula emisor(12) + consecutivo(20) + situación(1) + código de seguridad(8)
 * Consecutivo (20): sucursal(3) + terminal(5) + tipo documento(2) + numeración(10)
 */
class ClaveGenerator
{
    public const SITUATION_NORMAL = '1';
    public const SITUATION_CONTINGENCY = '2';
    public const SITUATION_WITHOUT_INTERNET = '3';

    public function consecutivo(string $sucursal, string $terminal, string $documentTypeCode, int $sequenceNumber): string
    {
        $sucursal = str_pad(substr($sucursal, 0, 3), 3, '0', STR_PAD_LEFT);
        $terminal = str_pad(substr($terminal, 0, 5), 5, '0', STR_PAD_LEFT);
        $documentTypeCode = str_pad(substr($documentTypeCode, 0, 2), 2, '0', STR_PAD_LEFT);
        $numeracion = str_pad((string) $sequenceNumber, 10, '0', STR_PAD_LEFT);

        return $sucursal . $terminal . $documentTypeCode . $numeracion;
    }

    public function clave(
        string $issuerIdNumber,
        string $consecutivo,
        ?Carbon $date = null,
        string $situation = self::SITUATION_NORMAL,
        ?string $securityCode = null,
        string $countryCode = '506',
    ): string {
        if (strlen($consecutivo) !== 20) {
            throw new InvalidArgumentException('El consecutivo debe tener exactamente 20 dígitos.');
        }

        $date = $date ?? Carbon::now('America/Costa_Rica');
        $countryCode = str_pad(substr($countryCode, 0, 3), 3, '0', STR_PAD_LEFT);
        $datePart = $date->format('dmy');
        $issuerId = $this->padIssuerId($issuerIdNumber);
        $security = $securityCode ?? str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        return $countryCode . $datePart . $issuerId . $consecutivo . $situation . $security;
    }

    /**
     * Siguiente consecutivo disponible para un arrendador + sucursal + terminal + tipo de
     * documento. Hacienda exige numeración correlativa POR tipo de documento (una Factura y
     * una Nota de Crédito llevan cada una su propia secuencia) y NO permite reenviar una
     * clave ya recibida (aceptada o rechazada) — por eso un reintento real necesita pedir
     * un consecutivo nuevo aquí, no reusar el de un envío previo.
     * Con lockForUpdate() para evitar que dos envíos simultáneos generen el mismo número.
     */
    public function nextConsecutivo(int $lessorId, string $sucursal, string $terminal, string $documentTypeCode): string
    {
        $prefix = $this->consecutivo($sucursal, $terminal, $documentTypeCode, 0);
        $prefix = substr($prefix, 0, 10);

        $lastNumber = Invoice::where('lessor_id', $lessorId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->max('invoice_number');

        $lastSequence = $lastNumber ? (int) substr((string) $lastNumber, 10, 10) : 0;

        return $this->consecutivo($sucursal, $terminal, $documentTypeCode, $lastSequence + 1);
    }

    protected function padIssuerId(string $idNumber): string
    {
        $digits = preg_replace('/\D+/', '', $idNumber) ?? '';

        return str_pad(substr($digits, 0, 12), 12, '0', STR_PAD_LEFT);
    }
}
