<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\RiskLevel;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lists every field on purpose, so new columns never reach other systems by accident.
 * Notes, contacts, the responsible buyer and override reasons stay inside the app.
 *
 * @property Supplier $resource
 */
class SupplierResource extends JsonResource
{
    public const array COLUMNS = [
        'id', 'supplier_number', 'name', 'status', 'country_code', 'business_id', 'vat_number',
        'street_address', 'postal_code', 'city', 'website', 'email', 'phone',
        'payment_terms', 'currency_code', 'delivery_term', 'delivery_place',
        'latest_rating_score', 'risk_override', 'archived_at', 'created_at', 'updated_at',
    ];

    public function __construct(Supplier $supplier, private readonly RiskLevel $riskLevel)
    {
        parent::__construct($supplier);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $supplier = $this->resource;

        return [
            'id' => $supplier->id,
            'supplier_number' => $supplier->supplier_number,
            'name' => $supplier->name,
            'status' => $supplier->status->value,
            'country_code' => $supplier->country_code,
            'business_id' => $supplier->business_id,
            'vat_number' => $supplier->vat_number,
            'address' => [
                'street' => $supplier->street_address,
                'postal_code' => $supplier->postal_code,
                'city' => $supplier->city,
            ],
            'website' => $supplier->website,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'payment_terms' => $supplier->payment_terms,
            'currency_code' => $supplier->currency_code?->value,
            'delivery_term' => $supplier->delivery_term?->value,
            'delivery_place' => $supplier->delivery_place,
            'categories' => $supplier->categories->pluck('name')->sort()->values(),
            'latest_rating_score' => $supplier->latest_rating_score === null ? null : (float) $supplier->latest_rating_score,
            'risk_level' => $this->riskLevel->value,
            'archived_at' => $supplier->archived_at?->toIso8601String(),
            'created_at' => $supplier->created_at?->toIso8601String(),
            'updated_at' => $supplier->updated_at?->toIso8601String(),
        ];
    }
}
