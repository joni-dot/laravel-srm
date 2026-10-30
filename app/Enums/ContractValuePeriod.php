<?php

namespace App\Enums;

enum ContractValuePeriod: string
{
    case Total = 'total';
    case Yearly = 'yearly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Total => 'in total',
            self::Yearly => 'per year',
            self::Monthly => 'per month',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $period) => ['value' => $period->value, 'label' => $period->label()],
            self::cases(),
        );
    }
}
