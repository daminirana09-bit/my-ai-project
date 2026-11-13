<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadClash extends Model
{
    public const REASONS = [
        'added_again' => 'Added the same client again',
        'imported_again' => 'Imported the same client again',
        'contacted' => 'Contacted a client owned by someone else',
    ];

    protected $fillable = ['lead_id', 'existing_owner_id', 'other_user_id', 'reason', 'detail', 'status', 'resolved_by', 'assigned_to', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function existingOwner(): BelongsTo { return $this->belongsTo(User::class, 'existing_owner_id'); }
    public function otherUser(): BelongsTo { return $this->belongsTo(User::class, 'other_user_id'); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
}
