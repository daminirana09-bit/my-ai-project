<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowupLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['followup_rule_id', 'lead_id', 'status', 'wamid', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function rule(): BelongsTo { return $this->belongsTo(FollowupRule::class, 'followup_rule_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
}
