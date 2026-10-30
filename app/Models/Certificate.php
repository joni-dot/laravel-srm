<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int $certificate_type_id
 * @property string|null $reference
 * @property CarbonImmutable|null $issued_on
 * @property CarbonImmutable|null $expires_on
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $file_mime_type
 * @property int|null $file_size
 */
#[Fillable(['certificate_type_id', 'reference', 'issued_on', 'expires_on'])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date:Y-m-d',
            'expires_on' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<CertificateType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CertificateType::class, 'certificate_type_id');
    }

    /**
     * @return HasMany<CertificateReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(CertificateReminder::class);
    }

    public function status(): CertificateStatus
    {
        return CertificateStatus::forExpiryDate($this->expires_on);
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }

    /**
     * Certificates without an expiry date never expire.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function currentlyValid(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNull('expires_on')
            ->orWhere('expires_on', '>=', today()));
    }

    /**
     * Expired or expiring soon, with nothing of the same type lasting longer.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function expiringWithoutReplacement(Builder $query): void
    {
        $soon = today()->addDays(CertificateStatus::EXPIRING_SOON_DAYS);

        $query
            ->where('certificates.expires_on', '<', $soon)
            ->whereNotExists(self::query()
                ->from('certificates as replacement')
                ->whereColumn('replacement.supplier_id', 'certificates.supplier_id')
                ->whereColumn('replacement.certificate_type_id', 'certificates.certificate_type_id')
                ->where(fn (Builder $query) => $query
                    ->whereNull('replacement.expires_on')
                    ->orWhere('replacement.expires_on', '>=', $soon)));
    }
}
