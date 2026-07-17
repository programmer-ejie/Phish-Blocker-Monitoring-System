<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    protected $fillable = ['code', 'name', 'computer_count', 'uptime_percentage', 'status', 'last_sync_at', 'is_active'];

    protected function casts(): array
    {
        return ['last_sync_at' => 'datetime', 'uptime_percentage' => 'decimal:1', 'is_active' => 'boolean'];
    }

    public function phishingLogs(): HasMany
    {
        return $this->hasMany(PhishingLog::class);
    }
}
