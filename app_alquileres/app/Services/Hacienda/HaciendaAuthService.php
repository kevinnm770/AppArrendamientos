<?php

namespace App\Services\Hacienda;

use App\Models\Lessor;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Autenticación OIDC directa contra el IDP de Hacienda (Keycloak), sin intermediarios.
 * Usa las credenciales de ATV del propio arrendador (grant_type=password) y cachea el
 * access/refresh token en el modelo Lessor para no pedir credenciales en cada envío.
 */
class HaciendaAuthService
{
    public function tokenFor(Lessor $lessor): string
    {
        if (!$lessor->hacienda_username || !$lessor->hacienda_password) {
            throw new RuntimeException('El arrendador no tiene configurado el usuario y contraseña de Hacienda (ATV).');
        }

        if ($lessor->hacienda_access_token && $lessor->hacienda_token_expires_at?->gt(now()->addSeconds(15))) {
            return $lessor->hacienda_access_token;
        }

        if ($lessor->hacienda_refresh_token && $lessor->hacienda_refresh_expires_at?->gt(now()->addSeconds(15))) {
            $payload = $this->request([
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId(),
                'refresh_token' => $lessor->hacienda_refresh_token,
            ]);
        } else {
            $payload = $this->request([
                'grant_type' => 'password',
                'client_id' => $this->clientId(),
                'username' => $lessor->hacienda_username,
                'password' => $lessor->hacienda_password,
            ]);
        }

        $accessToken = $payload['access_token'] ?? null;

        if (!$accessToken) {
            throw new RuntimeException('Hacienda no devolvió un access token válido.');
        }

        $lessor->forceFill([
            'hacienda_access_token' => $accessToken,
            'hacienda_refresh_token' => $payload['refresh_token'] ?? $lessor->hacienda_refresh_token,
            'hacienda_token_expires_at' => Carbon::now()->addSeconds((int) ($payload['expires_in'] ?? 300)),
            'hacienda_refresh_expires_at' => Carbon::now()->addSeconds((int) ($payload['refresh_expires_in'] ?? 1800)),
        ])->save();

        return $accessToken;
    }

    protected function request(array $formParams): array
    {
        try {
            $response = Http::asForm()
                ->timeout((int) config('services.hacienda.timeout', 25))
                ->connectTimeout((int) config('services.hacienda.connect_timeout', 10))
                ->post($this->idpUrl(), $formParams);

            $response->throw();

            return $response->json() ?? [];
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No se pudo conectar con el IDP de Hacienda (timeout o red).', 0, $exception);
        } catch (RequestException $exception) {
            $body = $exception->response?->json();
            $message = $body['error_description'] ?? $body['error'] ?? 'Error de autenticación con Hacienda.';

            throw new RuntimeException("Hacienda IDP respondió con error: {$message}", $exception->response?->status() ?? 0, $exception);
        }
    }

    protected function idpUrl(): string
    {
        return $this->isProduction()
            ? (string) config('services.hacienda.idp_url_prod')
            : (string) config('services.hacienda.idp_url_stag');
    }

    protected function clientId(): string
    {
        return $this->isProduction()
            ? (string) config('services.hacienda.client_id_prod')
            : (string) config('services.hacienda.client_id_stag');
    }

    protected function isProduction(): bool
    {
        return config('services.hacienda.environment') === 'prod';
    }
}
