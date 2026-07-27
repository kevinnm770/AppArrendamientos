<?php

namespace App\Services\Hacienda;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente REST directo de la API de recepción de comprobantes de Hacienda
 * (https://api.comprobanteselectronicos.go.cr/recepcion/v1). Sin intermediarios.
 */
class ReceptionClient
{
    public function send(array $payload, string $accessToken): array
    {
        try {
            $response = $this->client($accessToken)->post('/recepcion', $payload);

            // Hacienda responde 201 sin body; el estado real se consulta después con getStatus().
            if ($response->status() !== 201) {
                $this->logExchange('POST /recepcion', $payload, $response->status(), $response->headers(), $response->body());
                $response->throw();
            }

            return [
                'status_code' => $response->status(),
                'location' => $response->header('Location'),
            ];
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No se pudo conectar con la API de recepción de Hacienda.', 0, $exception);
        } catch (RequestException $exception) {
            throw $this->toRuntimeException($exception, 'Hacienda rechazó el envío del comprobante');
        }
    }

    public function getStatus(string $clave, string $accessToken): array
    {
        try {
            $response = $this->client($accessToken)->get("/recepcion/{$clave}");

            if (!$response->successful()) {
                $this->logExchange("GET /recepcion/{$clave}", [], $response->status(), $response->headers(), $response->body());
            }

            $response->throw();

            return $response->json() ?? [];
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No se pudo conectar con la API de consulta de Hacienda.', 0, $exception);
        } catch (RequestException $exception) {
            throw $this->toRuntimeException($exception, 'Hacienda rechazó la consulta de estado');
        }
    }

    protected function client(string $accessToken)
    {
        return Http::baseUrl(rtrim($this->apiUrl(), '/'))
            ->withToken($accessToken)
            ->acceptJson()
            ->timeout((int) config('services.hacienda.timeout', 25))
            ->connectTimeout((int) config('services.hacienda.connect_timeout', 10));
    }

    protected function apiUrl(): string
    {
        return config('services.hacienda.environment') === 'prod'
            ? (string) config('services.hacienda.api_url_prod')
            : (string) config('services.hacienda.api_url_stag');
    }

    /**
     * Registra el intercambio completo en el log para poder diagnosticar rechazos de Hacienda
     * que vienen sin cuerpo de error útil (como pasó con el primer 400 real que devolvió el
     * sandbox). El XML firmado se resume por tamaño/hash en vez de volcarse completo.
     */
    protected function logExchange(string $operation, array $payload, ?int $status, array $headers, ?string $body): void
    {
        if (isset($payload['comprobanteXml'])) {
            $xml = $payload['comprobanteXml'];
            $payload['comprobanteXml'] = '(' . strlen($xml) . ' bytes base64, sha256=' . hash('sha256', $xml) . ')';
        }

        Log::warning("Hacienda ReceptionClient: {$operation} no fue exitoso", [
            'request_payload' => $payload,
            'response_status' => $status,
            'response_headers' => $headers,
            'response_body' => $body,
        ]);
    }

    protected function toRuntimeException(RequestException $exception, string $prefix): RuntimeException
    {
        $body = $exception->response?->json();
        $status = $exception->response?->status();

        // No sabemos con certeza qué clave usa Hacienda para el detalle del error en /recepcion
        // (la doc pública no lo especifica), así que se prueban las más comunes. filter() descarta
        // null Y strings vacíos (un simple "??" no lo hace, y Hacienda a veces responde 400 con
        // cuerpo vacío) — sin eso el mensaje se corta en blanco.
        $candidates = [
            is_array($body) ? ($body['message'] ?? null) : null,
            is_array($body) ? ($body['error'] ?? null) : null,
            is_array($body) ? ($body['mensaje'] ?? null) : null,
            is_array($body) ? ($body['detail'] ?? null) : null,
            is_array($body) && $body !== [] ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
            $exception->response?->body(),
        ];

        $message = collect($candidates)->first(fn ($value) => filled($value))
            ?? "sin cuerpo de error (revisa storage/logs/laravel.log para el intercambio completo)";

        return new RuntimeException("{$prefix} (HTTP {$status}): {$message}", $status ?? 0, $exception);
    }
}
