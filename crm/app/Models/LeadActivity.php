<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    public const TYPES = ['call' => 'Call', 'whatsapp' => 'WhatsApp', 'visit' => 'Visit', 'note' => 'Note', 'status' => 'Status change', 'assign' => 'Assigned', 'enquiry' => 'New enquiry'];
    public const CALL_OUTCOMES = ['connected' => 'Connected', 'not_picked' => 'Not picked', 'busy' => 'Busy', 'switched_off' => 'Switched off', 'call_back' => 'Call back later'];

    protected $fillable = ['lead_id', 'user_id', 'type', 'outcome', 'duration_seconds', 'note', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
