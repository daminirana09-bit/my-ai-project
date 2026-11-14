<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'in_progress' => 'In progress', 'done' => 'Complete'];
    public const PRIORITIES = ['high' => 'High', 'normal' => 'Normal', 'low' => 'Low'];

    protected $fillable = ['title', 'details', 'assigned_to', 'created_by', 'lead_id', 'due_on', 'priority', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'completed_at' => 'datetime'];
    }

    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }

    /** Tasks of people this user may see (own for a rep, team for a manager, all for admin). */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        $ids = $user->visibleUserIds();

        return $ids === null ? $q : $q->where(fn ($w) => $w->whereIn('assigned_to', $ids)->orWhere('created_by', $user->id));
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->canSeeUser($this->assigned_to) || (int) $this->created_by === $user->id;
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_on && $this->due_on->lt(today());
    }
}
