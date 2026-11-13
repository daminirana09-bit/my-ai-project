<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    protected $fillable = ['user_id', 'type', 'punched_at', 'ip'];

    protected function casts(): array
    {
        return ['punched_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
