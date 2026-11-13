<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDay extends Model
{
    public const STATUSES = [
        'full_day' => 'Full day',
        'half_day' => 'Half day',
        'absent' => 'Absent',
        'single_swipe' => 'Single swipe',
        'weekly_off' => 'Weekly off',
        'leave' => 'Leave',
        'present' => 'Working now',
    ];

    protected $fillable = ['user_id', 'date', 'first_in', 'last_out', 'worked_minutes', 'status', 'late', 'manual', 'remark'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'first_in' => 'datetime', 'last_out' => 'datetime', 'late' => 'boolean', 'manual' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
