<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CostaRicaKeyGenerator
{
    public const COUNTRY_CODE = '506';
    public const DEFAULT_DOCUMENT_TYPE = '01';
    public const DEFAULT_SITUATION = '1';

    public function generate(
        string $issuerIdNumber,
        int $internalSequence,
        ?CarbonInterface $issuedAt = null,
        string $branch = '001',
        string $terminal = '00001',
        string $documentType = self::DEFAULT_DOCUMENT_TYPE,
        string $situation = self::DEFAULT_SITUATION,
    ): array {
        $data = [
            'branch' => $branch,
            'terminal' => $terminal,
            'document_type' => $documentType,
            'internal_number' => str_pad((string) $internalSequence, 10, '0', STR_PAD_LEFT),
            'situation' => $situation,
            'security_code' => str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'issuer_id' => $this->normalizeIssuerId($issuerIdNumber),
            'date_segment' => ($issuedAt ?? now())->format('dmy'),
        ];

        $this->validateGeneratedData($data);

        $data['consecutive'] = $data['branch']
            . $data['terminal']
            . $data['document_type']
            . $data['internal_number'];

        $data['key'] = self::COUNTRY_CODE
            . $data['date_segment']
            . $data['issuer_id']
            . $data['consecutive']
            . $data['situation']
            . $data['security_code'];

        $this->validateIdentifiers($data['consecutive'], $data['key']);

        return $data;
    }

    public function validateIdentifiers(string $consecutive, string $key): void
    {
        $errors = [];

        if (!preg_match('/^[0-9]{20}$/', $consecutive)) {
            $errors['hacienda_consecutive'][] = 'El consecutivo debe contener exactamente 20 dígitos numéricos.';
        }

        if (!preg_match('/^[0-9]{50}$/', $key)) {
            $errors['hacienda_key'][] = 'La clave debe contener exactamente 50 dígitos numéricos.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function validateGeneratedData(array $data): void
    {
        $errors = [];

        $rules = [
            'branch' => '/^[0-9]{3}$/',
            'terminal' => '/^[0-9]{5}$/',
            'document_type' => '/^[0-9]{2}$/',
            'internal_number' => '/^[0-9]{10}$/',
            'situation' => '/^[0-9]{1}$/',
            'security_code' => '/^[0-9]{8}$/',
            'issuer_id' => '/^[0-9]{12}$/',
            'date_segment' => '/^[0-9]{6}$/',
        ];

        foreach ($rules as $field => $pattern) {
            if (!preg_match($pattern, (string) ($data[$field] ?? ''))) {
                $errors[$field][] = sprintf('Formato inválido para %s.', $field);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function normalizeIssuerId(string $issuerIdNumber): string
    {
        return str_pad(substr(preg_replace('/\D+/', '', $issuerIdNumber), 0, 12), 12, '0', STR_PAD_LEFT);
    }
}
