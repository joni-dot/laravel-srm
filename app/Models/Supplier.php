<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DeliveryTerm;
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
