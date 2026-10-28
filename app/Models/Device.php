<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    public const CAMPUSES = ['SLSU-Bontoc', 'SLSU-Hinunangan', 'SLSU-Maasin', 'SLSU-Main', 'SLSU-SanJuan', 'SLSU-TO'];

    protected $fillable = ['device_uuid', 'campus_name', 'computer_number', 'name', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
