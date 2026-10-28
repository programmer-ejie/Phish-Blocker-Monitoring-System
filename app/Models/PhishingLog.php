<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhishingLog extends Model
{
    protected $fillable = [
        'device_id', 'campus_id', 'url', 'score', 'risk_level', 'status', 'reason',
        'computer_number', 'campus_name', 'metadata', 'detected_at',
    ];

    protected function casts(): array
    {
        return ['score' => 'float', 'metadata' => 'array'];
    }

    // Keep detection times in UTC; PostgreSQL also needs an explicit offset.
    protected function detectedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : CarbonImmutable::parse($value, 'UTC'),
            set: fn ($value) => $value === null ? null : CarbonImmutable::parse($value)->utc()->format(
                $this->getConnection()->getDriverName() === 'pgsql' ? 'Y-m-d H:i:s.uP' : 'Y-m-d H:i:s.u'
            ),
        );
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }
}
