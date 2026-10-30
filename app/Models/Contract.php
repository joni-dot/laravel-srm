<?php

namespace App\Models;

use App\Enums\ContractHealth;
use App\Enums\ContractStatus;
use App\Enums\ContractValuePeriod;
use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Database\Factories\ContractFactory;
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
 * @property int|null $owner_user_id
 * @property string $title
 * @property string|null $contract_number
 * @property int $contract_type_id
 * @property ContractStatus $status
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property bool $auto_renews
 * @property int|null $renewal_term_months
 * @property int|null $notice_period_days
 * @property string|null $value
 * @property Currency|null $currency_code
 * @property ContractValuePeriod|null $value_period
 * @property string|null $notes
 * @property CarbonImmutable|null $action_by Calculated by the database; read-only.
 */
#[Fillable([
    'owner_user_id', 'title', 'contract_number', 'contract_type_id', 'status', 'starts_on', 'ends_on',
    'auto_renews', 'renewal_term_months', 'notice_period_days',
    'value', 'currency_code', 'value_period', 'notes',
])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'action_by' => 'date',
            'auto_renews' => 'boolean',
            'value' => 'decimal:2',
            'currency_code' => Currency::class,
            'value_period' => ContractValuePeriod::class,
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
     * @return BelongsTo<ContractType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<ContractReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(ContractReminder::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function health(): ContractHealth
    {
        return match (true) {
            $this->status === ContractStatus::Draft => ContractHealth::Draft,
            $this->status === ContractStatus::Terminated => ContractHealth::Terminated,
            $this->action_by === null => ContractHealth::Active,
            $this->action_by->isBefore(today()) => $this->auto_renews ? ContractHealth::Active : ContractHealth::Expired,
            $this->action_by->isBefore(today()->addDays(ContractHealth::ACTION_SOON_DAYS)) => ContractHealth::ActionSoon,
            default => ContractHealth::Active,
        };
    }

    /**
     * Active contracts someone must act on within the coming days. Agrees with health().
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function needingActionSoon(Builder $query): void
    {
        $query
            ->where('status', ContractStatus::Active)
            ->where('action_by', '>=', today())
            ->where('action_by', '<', today()->addDays(ContractHealth::ACTION_SOON_DAYS));
    }
}
