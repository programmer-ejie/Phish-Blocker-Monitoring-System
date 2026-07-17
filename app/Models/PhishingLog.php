<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhishingLog extends Model
{
    protected $fillable = [
        'campus_id', 'url', 'score', 'risk_level', 'status', 'reason',
        'computer_number', 'campus_name', 'metadata',
    ];

    protected function casts(): array
    {
        return ['score' => 'float', 'metadata' => 'array'];
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }
}
