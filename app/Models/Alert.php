<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = ['campus_id', 'title', 'severity', 'owner', 'status'];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }
}
