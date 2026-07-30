<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Consulta el nombre/razón social y actividades económicas de una identificación
 * costarricense (física, jurídica, DIMEX o NITE) contra apis.gometa.org/cedulas —
 * un servicio nacional público independiente, no la API de Hacienda. Se usa desde el
 * backend para autocompletar y bloquear el nombre legal, de modo que ningún usuario
 * pueda escribir un nombre distinto al registrado bajo su identificación.
 */
class NationalIdentityLookupService
{
    /**
     * Mapa del campo "tipoIdentificacion" que devuelve el servicio (mismo catálogo que
     * usa Hacienda: 01 Física, 02 Jurídica, 03 DIMEX, 04 NITE) a los valores internos
     * que ya usa la app en lessors.identification_type / roomers.identification_type.
     */
    private const TYPE_MAP = [
        '01' => 'fisico',
        '02' => 'juridico',
        '03' => 'dimex',
        '04' => 'nite',
    ];

    public function lookup(string $identification): array
    {
        $identification = preg_replace('/\D+/', '', $identification) ?? '';

        if (strlen($identification) < 9) {
            return ['found' => false, 'message' => 'Número de identificación incompleto.'];
        }

        try {
            $response = Http::timeout(10)->get("https://apis.gometa.org/cedulas/{$identification}");
        } catch (Throwable) {
            return ['found' => false, 'message' => 'No se pudo conectar con el servicio de identificación.'];
        }

        if (!$response->successful()) {
            return ['found' => false, 'message' => 'No se pudo consultar el servicio de identificación.'];
        }

        $data = $response->json() ?? [];

        if (empty($data['resultcount']) || empty($data['nombre'])) {
            return ['found' => false, 'message' => 'No se encontró ningún registro para esa identificación.'];
        }

        return [
            'found' => true,
            'name' => $data['nombre'],
            'identification_type' => self::TYPE_MAP[$data['tipoIdentificacion'] ?? null] ?? null,
            'activities' => collect($data['actividades'] ?? [])
                ->map(fn (array $activity) => [
                    'code' => $activity['codigo'] ?? ($activity['ciiu3']['codigo'] ?? null),
                    'description' => $activity['descripcion'] ?? ($activity['ciiu3']['descripcion'] ?? null),
                ])
                ->filter(fn (array $activity) => filled($activity['code']))
                ->values()
                ->all(),
        ];
    }
}
