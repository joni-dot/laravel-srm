<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $certificate_id
 * @property int $days_before
 * @property CarbonImmutable $remind_on
 * @property CarbonImmutable|null $sent_at
 */
#[Fillable(['days_before', 'remind_on'])]
class CertificateReminder extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remind_on' => 'date:Y-m-d',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Certificate, $this>
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
