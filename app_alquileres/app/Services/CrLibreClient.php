<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CrLibreClient
{
    public function createUser(array $payload): array
    {
        return $this->request([
            'w' => 'users',
            'r' => 'users_register',
            ...$payload,
        ]);
    }

    public function login(array $payload = []): array
    {
        return $this->request([
            'w' => 'users',
            'r' => 'users_log_me_in',
            ...$payload,
        ]);
    }

    public function logout(array $payload = []): array
    {
        return $this->request([
            'w' => 'users',
            'r' => 'users_log_me_out',
            ...$payload,
        ]);
    }

    public function uploadCertificate(string $iam, string $sessionKey, UploadedFile $file): array
    {
        try {
            $response = $this->httpClient()
                ->attach('fileToUpload', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post('', [
                    'w' => 'fileUploader',
                    'r' => 'subir_certif',
                    'iam' => $iam,
                    'sessionKey' => $sessionKey,
                ]);

            $response->throw();

            return $this->validateResponse($response);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No se pudo conectar con CRLibre al subir el certificado.', 0, $exception);
        } catch (RequestException $exception) {
            $response = $exception->response;
            $body = $response?->json() ?? ['raw' => $response?->body()];
            $message = Arr::get($body, 'message')
                ?? Arr::get($body, 'resp.message')
                ?? Arr::get($body, 'raw')
                ?? 'Error al subir certificado en CRLibre.';

            throw new RuntimeException($message, $response?->status() ?? 0, $exception);
        }
    }

    public function createVoucherKey(array $payload): array
    {
        return $this->request([
            'w' => 'clave',
            'r' => 'clave',
            ...$payload,
        ]);
    }

    public function getToken(array $payload): array
    {
        return $this->request([
            'w' => 'token',
            'r' => 'gettoken',
            ...$payload,
        ]);
    }

    public function refreshToken(array $payload): array
    {
        return $this->request([
            'w' => 'token',
            'r' => 'refresh',
            ...$payload,
        ]);
    }

    public function generateInvoiceXml(array $payload): array
    {
        return $this->request([
            'w' => 'genXML',
            'r' => 'gen_xml_fe',
            ...$payload,
        ]);
    }

    public function signXml(array $payload): array
    {
        return $this->request([
            'w' => 'signXML',
            'r' => 'signFE',
            ...$payload,
        ]);
    }

    public function sendVoucher(array $payload): array
    {
        return $this->request([
            'w' => 'send',
            'r' => 'json',
            ...$payload,
        ]);
    }

    public function getVoucherStatus(string $identifier, string $token, string $clientId): array
    {
        return $this->request([
            'w' => 'consultar',
            'r' => 'consultarCom',
            'clave' => $identifier,
            'token' => $token,
            'client_id' => $clientId,
        ]);
    }

    protected function request(array $payload): array
    {
        try {
            $response = $this->httpClient()
                ->asForm()
                ->post('', array_filter($payload, static fn ($value) => $value !== null && $value !== ''));

            $response->throw();

            return $this->validateResponse($response);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('No se pudo conectar con CRLibre (timeout o red).', 0, $exception);
        } catch (RequestException $exception) {
            $response = $exception->response;
            $body = $response?->json() ?? ['raw' => $response?->body()];
            $statusCode = $response?->status();
            $message = Arr::get($body, 'message')
                ?? Arr::get($body, 'resp.message')
                ?? Arr::get($body, 'text')
                ?? Arr::get($body, 'raw')
                ?? 'Error HTTP en CRLibre.';

            throw new RuntimeException(
                "CRLibre respondió con error HTTP {$statusCode}: {$message}",
                $statusCode ?? 0,
                $exception,
            );
        }
    }

    protected function httpClient(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.crlibre.base_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('services.crlibre.timeout', 20))
            ->connectTimeout((int) config('services.crlibre.connect_timeout', 10));
    }

    protected function validateResponse(Response $response): array
    {
        $data = $response->json();

        if (!is_array($data)) {
            $data = [
                'raw' => $response->body(),
            ];
        }

        return [
            'status_code' => $response->status(),
            'request_id' => $response->header('X-Request-Id')
                ?? Arr::get($data, 'request_id')
                ?? Arr::get($data, 'requestId'),
            'error_code' => Arr::get($data, 'error.code')
                ?? Arr::get($data, 'code')
                ?? Arr::get($data, 'resp.code'),
            'payload' => $data,
        ];
    }
}
