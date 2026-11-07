<?php

namespace App\Enums;

/**
 * What an API client may read. A client gets only the scopes an admin ticked for it.
 */
enum ApiScope: string
{
    case SuppliersRead = 'suppliers:read';

    public function label(): string
    {
        return match ($this) {
            self::SuppliersRead => 'Read suppliers',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuppliersRead => 'Supplier details, categories, latest rating and risk level. Not contacts, notes, contracts or spend.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        return array_column(
            array_map(fn (self $scope) => ['id' => $scope->value, 'description' => $scope->description()], self::cases()),
            'description',
            'id',
        );
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $scope) => ['value' => $scope->value, 'label' => $scope->label(), 'description' => $scope->description()],
            self::cases(),
        );
    }
}
