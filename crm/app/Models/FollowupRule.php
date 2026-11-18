<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An automatic WhatsApp follow-up: when to send, and what. */
class FollowupRule extends Model
{
    /** trigger => [label, what "after" means] */
    public const TRIGGERS = [
        'follow_up_due' => ['Follow-up date reached and nobody contacted the client', 'days after the follow-up date (0 = same day)'],
        'quote_no_reply' => ['Quotation sent, no reply from the client', 'days after the quotation'],
        'no_reply' => ['Open lead, no contact for some days', 'days without contact'],
        'reorder' => ['Customer has not ordered again (reorder reminder)', 'days since the last order'],
        'new_lead' => ['New lead not contacted yet', 'hours after the lead came'],
    ];

    protected $fillable = ['name', 'trigger', 'after', 'repeat_days', 'template_name', 'template_language', 'variables', 'text', 'active', 'created_by', 'last_run_at'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'active' => 'boolean', 'last_run_at' => 'datetime'];
    }

    public function logs(): HasMany { return $this->hasMany(FollowupLog::class); }
}
