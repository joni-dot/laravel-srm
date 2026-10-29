<?php

namespace App\Enums;

use Symfony\Component\Intl\Currencies;

/**
 * Currencies a supplier can invoice in (ISO 4217). Add a case to support a new one.
 */
enum Currency: string
{
    case Eur = 'EUR';
    case Usd = 'USD';
    case Gbp = 'GBP';
    case Sek = 'SEK';
    case Nok = 'NOK';
    case Dkk = 'DKK';
    case Chf = 'CHF';
    case Pln = 'PLN';
    case Czk = 'CZK';
    case Cny = 'CNY';
    case Jpy = 'JPY';

    public function label(): string
    {
        return "{$this->value} – ".Currencies::getName($this->value);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $currency) => ['value' => $currency->value, 'label' => $currency->label()],
            self::cases(),
        );
    }
}
