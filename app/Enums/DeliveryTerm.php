<?php

namespace App\Enums;

/**
 * Incoterms 2020: the standard rules for who pays for and carries the risk of a delivery.
 */
enum DeliveryTerm: string
{
    case Exw = 'EXW';
    case Fca = 'FCA';
    case Cpt = 'CPT';
    case Cip = 'CIP';
    case Dap = 'DAP';
    case Dpu = 'DPU';
    case Ddp = 'DDP';
    case Fas = 'FAS';
    case Fob = 'FOB';
    case Cfr = 'CFR';
    case Cif = 'CIF';

    public function label(): string
    {
        $description = match ($this) {
            self::Exw => 'Ex Works',
            self::Fca => 'Free Carrier',
            self::Cpt => 'Carriage Paid To',
            self::Cip => 'Carriage and Insurance Paid To',
            self::Dap => 'Delivered at Place',
            self::Dpu => 'Delivered at Place Unloaded',
            self::Ddp => 'Delivered Duty Paid',
            self::Fas => 'Free Alongside Ship',
            self::Fob => 'Free on Board',
            self::Cfr => 'Cost and Freight',
            self::Cif => 'Cost, Insurance and Freight',
        };

        return "{$this->value} – {$description}";
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $term) => ['value' => $term->value, 'label' => $term->label()],
            self::cases(),
        );
    }
}
