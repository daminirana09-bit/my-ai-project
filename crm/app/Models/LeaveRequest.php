<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use Concerns\PlainDates;

    protected array $plainDates = ['from_date', 'to_date'];

    public const STATUSES = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];

    protected $fillable = ['user_id', 'leave_type_id', 'from_date', 'to_date', 'half_day', 'days', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'half_day' => 'boolean', 'days' => 'decimal:1', 'reason' => 'encrypted', 'decided_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function type(): BelongsTo { return $this->belongsTo(LeaveType::class, 'leave_type_id'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
