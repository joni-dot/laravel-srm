<?php

namespace App\Enums;

enum SupplierStatus: string
{
    case Prospect = 'prospect';
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Prospect',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Blocked => 'Blocked',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
