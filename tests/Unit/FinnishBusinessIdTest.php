<?php

namespace Tests\Unit;

use App\Rules\FinnishBusinessId;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FinnishBusinessIdTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function businessIds(): array
    {
        return [
            'valid (Nokia)' => ['0112038-9', true],
            'valid (check digit 0)' => ['1572860-0', true],
            'wrong check digit' => ['0112038-8', false],
            'missing dash' => ['01120389', false],
            'too short' => ['112038-9', false],
            'letters' => ['A112038-9', false],
        ];
    }

    #[DataProvider('businessIds')]
    public function test_it_checks_format_and_check_digit(string $businessId, bool $isValid)
    {
        $validator = Validator::make(['business_id' => $businessId], ['business_id' => [new FinnishBusinessId]]);

        $this->assertSame($isValid, $validator->passes());
    }
}
