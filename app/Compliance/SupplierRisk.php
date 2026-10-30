<?php

namespace App\Compliance;

use App\Enums\CertificateStatus;
use App\Enums\RiskLevel;
use App\Enums\SupplierStatus;
use App\Models\Certificate;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Works out each supplier's risk level and the reasons for it.
 * Supplier::withRiskLevel() is the database version of the same rules, used for filtering.
 * Contracts are left out on purpose: viewers can see risk, but not contract details.
 */
class SupplierRisk
{
    public function __construct(private readonly RequiredCertificates $requiredCertificates) {}

    /**
     * @param  Collection<int, Supplier>  $suppliers
     * @return array<int, array{level: RiskLevel, calculated_level: RiskLevel, signals: list<array{level: RiskLevel, message: string}>, missing_certificates: list<string>}>
     */
    public function assess(Collection $suppliers): array
    {
        $missingCertificates = $this->requiredCertificates->missingFor($suppliers);
        $certificatesBySupplier = $this->certificatesFor($suppliers);
        $assessments = [];

        foreach ($suppliers as $supplier) {
            $missing = $missingCertificates[$supplier->id] ?? [];

            $signals = [
                ...$this->statusSignals($supplier),
                ...$this->ratingSignals($supplier),
                ...array_map(fn (string $type) => [
                    'level' => RiskLevel::High,
                    'message' => "Missing a valid required certificate: {$type}",
                ], $missing),
                ...$this->certificateSignals($certificatesBySupplier->get($supplier->id, collect()), $missing),
            ];

            $calculatedLevel = $this->highestLevel($signals);

            $assessments[$supplier->id] = [
                'level' => $supplier->risk_override ?? $calculatedLevel,
                'calculated_level' => $calculatedLevel,
                'signals' => $signals,
                'missing_certificates' => $missing,
            ];
        }

        return $assessments;
    }

    /**
     * @param  Collection<int, Supplier>  $suppliers
     * @return Collection<int, Collection<int, Certificate>>
     */
    private function certificatesFor(Collection $suppliers): Collection
    {
        return Certificate::query()
            ->whereIn('supplier_id', $suppliers->pluck('id'))
            ->with('type:id,name')
            ->get(['id', 'supplier_id', 'certificate_type_id', 'expires_on'])
            ->toBase()
            ->groupBy('supplier_id');
    }

    /**
     * @return list<array{level: RiskLevel, message: string}>
     */
    private function statusSignals(Supplier $supplier): array
    {
        return $supplier->status === SupplierStatus::Blocked
            ? [['level' => RiskLevel::High, 'message' => 'The supplier is blocked']]
            : [];
    }

    /**
     * @return list<array{level: RiskLevel, message: string}>
     */
    private function ratingSignals(Supplier $supplier): array
    {
        if ($supplier->latest_rating_score === null) {
            return [];
        }

        $score = (float) $supplier->latest_rating_score;
        $message = 'The latest rating is '.number_format($score, 2).' out of 5';

        return match (true) {
            $score < RiskLevel::HIGH_RISK_RATING_BELOW => [['level' => RiskLevel::High, 'message' => $message]],
            $score < RiskLevel::MEDIUM_RISK_RATING_BELOW => [['level' => RiskLevel::Medium, 'message' => $message]],
            default => [],
        };
    }

    /**
     * A certificate only counts if nothing of the same type replaces it.
     *
     * @param  Collection<int, Certificate>  $certificates
     * @param  list<string>  $alreadyReportedTypes
     * @return list<array{level: RiskLevel, message: string}>
     */
    private function certificateSignals(Collection $certificates, array $alreadyReportedTypes): array
    {
        $soon = today()->addDays(CertificateStatus::EXPIRING_SOON_DAYS);
        $signals = [];

        foreach ($certificates->groupBy('certificate_type_id') as $sameType) {
            $hasLastingCertificate = $sameType->contains(fn (Certificate $certificate) => $certificate->expires_on === null || ! $certificate->expires_on->isBefore($soon));
            $typeName = $sameType->first()->type->name;

            if ($hasLastingCertificate || in_array($typeName, $alreadyReportedTypes, true)) {
                continue;
            }

            $hasValidCertificate = $sameType->contains(fn (Certificate $certificate) => ! $certificate->expires_on?->isBefore(today()));

            $signals[] = [
                'level' => RiskLevel::Medium,
                'message' => $hasValidCertificate ? "Certificate expiring soon: {$typeName}" : "Certificate expired: {$typeName}",
            ];
        }

        return $signals;
    }

    /**
     * @param  list<array{level: RiskLevel, message: string}>  $signals
     */
    private function highestLevel(array $signals): RiskLevel
    {
        $levels = array_column($signals, 'level');

        return match (true) {
            in_array(RiskLevel::High, $levels, true) => RiskLevel::High,
            in_array(RiskLevel::Medium, $levels, true) => RiskLevel::Medium,
            default => RiskLevel::Low,
        };
    }
}
