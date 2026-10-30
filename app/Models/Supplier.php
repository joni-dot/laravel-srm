<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DeliveryTerm;
use App\Enums\RiskLevel;
use App\Enums\SupplierStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $supplier_number
 * @property SupplierStatus $status
 * @property int|null $responsible_user_id
 * @property string $country_code
 * @property string|null $business_id
 * @property string|null $vat_number
 * @property string|null $street_address
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $website
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $payment_terms
 * @property Currency|null $currency_code
 * @property DeliveryTerm|null $delivery_term
 * @property string|null $delivery_place
 * @property string|null $notes
 * @property CarbonImmutable|null $archived_at
 * @property int $contracts_count
 * @property string|null $latest_rating_score
 * @property RiskLevel|null $risk_override
 * @property string|null $risk_override_reason
 * @property int|null $risk_overridden_by_user_id
 * @property CarbonImmutable|null $risk_overridden_at
 */
#[Fillable([
    'name', 'supplier_number', 'status', 'responsible_user_id', 'country_code', 'business_id', 'vat_number',
    'street_address', 'postal_code', 'city', 'website', 'email', 'phone',
    'payment_terms', 'currency_code', 'delivery_term', 'delivery_place', 'notes',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SupplierStatus::Prospect->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'currency_code' => Currency::class,
            'delivery_term' => DeliveryTerm::class,
            'archived_at' => 'datetime',
            'latest_rating_score' => 'decimal:2',
            'risk_override' => RiskLevel::class,
            'risk_overridden_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<SupplierRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(SupplierRating::class);
    }

    /**
     * @return HasMany<SupplierSpend, $this>
     */
    public function spends(): HasMany
    {
        return $this->hasMany(SupplierSpend::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function riskOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'risk_overridden_by_user_id');
    }

    public function refreshLatestRatingScore(): void
    {
        $this->latest_rating_score = $this->ratings()->latest('rated_on')->latest('id')->value('overall_score');
        $this->save();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Active suppliers that lack a currently valid certificate of at least one required type.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function missingRequiredCertificates(Builder $query): void
    {
        $query
            ->where('status', SupplierStatus::Active)
            ->whereExists(CertificateType::query()
                ->where('is_required', true)
                ->whereNotExists(Certificate::query()
                    ->currentlyValid()
                    ->whereColumn('certificates.supplier_id', 'suppliers.id')
                    ->whereColumn('certificates.certificate_type_id', 'certificate_types.id')));
    }

    /**
     * Suppliers at this risk level: an override wins, otherwise the calculated level counts.
     * Agrees with App\Compliance\SupplierRisk.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withRiskLevel(Builder $query, RiskLevel $level): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('risk_override', $level)
            ->orWhere(fn (Builder $query) => $query
                ->whereNull('risk_override')
                ->where(fn (Builder $query) => match ($level) {
                    RiskLevel::High => $query->calculatedHighRisk(),
                    RiskLevel::Medium => $query->calculatedAtLeastMediumRisk()->whereNot(fn (Builder $query) => $query->calculatedHighRisk()),
                    RiskLevel::Low => $query->whereNot(fn (Builder $query) => $query->calculatedAtLeastMediumRisk()),
                })));
    }

    /**
     * Unrated suppliers count as top-rated here, because having no rating is not a risk signal.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function calculatedHighRisk(Builder $query): void
    {
        $query
            ->where('status', SupplierStatus::Blocked)
            ->orWhereRaw('COALESCE(latest_rating_score, 5) < ?', [RiskLevel::HIGH_RISK_RATING_BELOW])
            ->orWhere(fn (Builder $query) => $query->missingRequiredCertificates());
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function calculatedAtLeastMediumRisk(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->calculatedHighRisk()
            ->orWhereRaw('COALESCE(latest_rating_score, 5) < ?', [RiskLevel::MEDIUM_RISK_RATING_BELOW])
            ->orWhere(fn (Builder $query) => $query->withExpiringCertificateNotReplaced()));
    }

    /**
     * Has a certificate that is expired or expiring soon, with nothing of the same type lasting longer.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withExpiringCertificateNotReplaced(Builder $query): void
    {
        $query->whereExists(Certificate::query()
            ->whereColumn('certificates.supplier_id', 'suppliers.id')
            ->expiringWithoutReplacement());
    }

    /**
     * Active suppliers never rated, or not rated recently enough.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ratingOverdue(Builder $query): void
    {
        $query
            ->where('status', SupplierStatus::Active)
            ->whereDoesntHave('ratings', fn (Builder $ratings) => $ratings
                ->where('rated_on', '>=', today()->subMonths(SupplierRating::OVERDUE_AFTER_MONTHS)));
    }

    /**
     * Match the term anywhere in the name, supplier number, business ID or VAT number.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where('search_text', 'ilike', '%'.addcslashes($term, '%_\\').'%');
    }
}
