<?php

namespace App\Compliance;

use App\Enums\SupplierStatus;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Works out which required certificates each active supplier is missing.
 * Uses two queries however many suppliers are checked.
 */
class RequiredCertificates
{
    /**
     * @param  Collection<int, Supplier>  $suppliers
     * @return array<int, list<string>> Missing certificate type names, keyed by supplier ID.
     */
    public function missingFor(Collection $suppliers): array
    {
        $activeSupplierIds = $suppliers
            ->filter(fn (Supplier $supplier) => $supplier->status === SupplierStatus::Active)
            ->map(fn (Supplier $supplier) => $supplier->id)
            ->values()
            ->all();

        $requiredTypes = CertificateType::query()->where('is_required', true)->orderBy('name')->get(['id', 'name']);

        if ($activeSupplierIds === [] || $requiredTypes->isEmpty()) {
            return [];
        }

        $validTypeIdsBySupplier = Certificate::query()
            ->currentlyValid()
            ->whereIn('supplier_id', $activeSupplierIds)
            ->whereIn('certificate_type_id', $requiredTypes->pluck('id'))
            ->get(['supplier_id', 'certificate_type_id'])
            ->groupBy('supplier_id')
            ->map(fn (Collection $certificates) => $certificates->pluck('certificate_type_id')->all());

        $missing = [];

        foreach ($activeSupplierIds as $supplierId) {
            $validTypeIds = $validTypeIdsBySupplier->get($supplierId, []);
            $missingNames = array_values($requiredTypes
                ->reject(fn (CertificateType $type) => in_array($type->id, $validTypeIds, true))
                ->map(fn (CertificateType $type) => $type->name)
                ->all());

            if ($missingNames !== []) {
                $missing[$supplierId] = $missingNames;
            }
        }

        return $missing;
    }
}
