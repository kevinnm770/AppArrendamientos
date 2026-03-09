<?php

namespace Tests\Unit;

use App\Services\CostaRicaKeyGenerator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CostaRicaKeyGeneratorTest extends TestCase
{
    public function test_it_generates_valid_consecutive_and_key_with_components(): void
    {
        $generator = new CostaRicaKeyGenerator();

        $generated = $generator->generate(
            issuerIdNumber: '3-101-123456',
            internalSequence: 42,
            branch: '001',
            terminal: '00001',
            documentType: '01',
        );

        $this->assertSame('001', $generated['branch']);
        $this->assertSame('00001', $generated['terminal']);
        $this->assertSame('01', $generated['document_type']);
        $this->assertSame('0000000042', $generated['internal_number']);
        $this->assertMatchesRegularExpression('/^[0-9]{20}$/', $generated['consecutive']);
        $this->assertMatchesRegularExpression('/^[0-9]{50}$/', $generated['key']);
    }

    public function test_it_throws_validation_exception_for_invalid_identifiers(): void
    {
        $generator = new CostaRicaKeyGenerator();

        $this->expectException(ValidationException::class);

        $generator->validateIdentifiers('ABC', '123');
    }
}
