<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidUkPostcode;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidUkPostcodeTest extends TestCase
{
    private function validate(string $value): bool
    {
        return !Validator::make(
            ['postcode' => $value],
            ['postcode' => new ValidUkPostcode()]
        )->fails();
    }

    #[Test]
    #[DataProvider('validPostcodes')]
    public function passes_valid_uk_postcodes(string $postcode): void
    {
        $this->assertTrue($this->validate($postcode), "Expected '{$postcode}' to be valid");
    }

    public static function validPostcodes(): array
    {
        return [
            'AN NAA format'              => ['W1A 1AA'],
            'ANN NAA format'             => ['SW1 1AA'],
            'AAN NAA format'             => ['SW1A 1AA'],
            'AANN NAA format'            => ['SW1A 2AA'],
            'without space'              => ['SW1A1AA'],
            'lowercase'                  => ['sw1a 1aa'],
            'mixed case'                 => ['Sw1A 1aA'],
            'numeric district sub'       => ['EC1A 1BB'],
            'two-digit numeric district' => ['W12 1AA'],
            'one letter area'            => ['M1 1AE'],
            'double letter no sub'       => ['CR2 6XH'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPostcodes')]
    public function rejects_invalid_postcodes(string $postcode): void
    {
        $this->assertFalse($this->validate($postcode), "Expected '{$postcode}' to be invalid");
    }

    public static function invalidPostcodes(): array
    {
        return [
            'digits only'            => ['12345678'],
            'letters only'           => ['ABCDEFGH'],
            'too short'              => ['SW1'],
            'missing inward letters' => ['SW1A 1'],
            'wrong sector char'      => ['SW1A ZAA'], // Z not a digit for sector
            'three area letters'     => ['SWX1 1AA'],
        ];
    }
}
