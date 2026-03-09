<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CrLibreClient
{
    public function createUser(array $payload): array
    {
        return $this->request('POST', 'users', $payload);
    }

    public function login(array $payload = []): array
    {
        $credentials = array_filter([
            'username' => config('services.crlibre.username'),
            'api_key' => config('services.crlibre.api_key'),
            'password' => config('services.crlibre.password'),
        ]);

        return $this->request('POST', 'auth/login', array_merge($credentials, $payload));
    }

    public function logout(?string $token = null): array
    {
        return $this->request('POST', 'auth/logout', [], $token);
    }

    public function sendVoucher(array $payload, ?string $token = null): array
    {
        return $this->request('POST', 'comprobantes', $payload, $token);
    }

    public function getVoucherStatus(string $identifier, ?string $token = null): array
    {
        return $this->request('GET', sprintf('comprobantes/%s/estado', $identifier), [], $token);
    }

    public function getVoucherStatusByTrackId(string $trackId, ?string $token = null): array
    {
        return $this->request('GET', sprintf('comprobantes/track/%s', $trackId), [], $token);
    }

    protected function request(string $method, string $uri, array $payload = [], ?string $token = null): array
    {
        $request = $this->httpClient($token);

        try {
            $response = strtoupper($method) === 'GET'
                ? $request->get($uri, $payload)
                : $request->send($method, $uri, ['json' => $payload]);

            $response->throw();

            return $this->validateResponse($response);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No se pudo conectar con CRLibre (timeout o red).', 0, $exception);
        } catch (RequestException $exception) {
            $response = $exception->response;

            $statusCode = $response?->status();
            $errorCode = Arr::get($response?->json() ?? [], 'code', 'http_error');
            $message = Arr::get($response?->json() ?? [], 'message', 'Error HTTP en CRLibre.');

            throw new RuntimeException(
                "CRLibre respondió con error HTTP {$statusCode}: {$message} [{$errorCode}]",
                $statusCode ?? 0,
                $exception,
            );
        }
    }

    protected function httpClient(?string $token = null): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.crlibre.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.crlibre.timeout', 20))
            ->connectTimeout((int) config('services.crlibre.connect_timeout', 10));

        if ($token) {
            $request->withToken($token);
        }

        return $request;
    }

    protected function validateResponse(Response $response): array
    {
        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException('CRLibre devolvió una estructura inválida (se esperaba JSON objeto/arreglo).');
        }

        return [
            'status_code' => $response->status(),
            'request_id' => $response->header('X-Request-Id')
                ?? Arr::get($data, 'request_id')
                ?? Arr::get($data, 'requestId'),
            'error_code' => Arr::get($data, 'error.code')
                ?? Arr::get($data, 'code'),
            'payload' => $data,
        ];
    }
}
