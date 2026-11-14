<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    protected $fillable = ['campaign_id', 'lead_id', 'status', 'wamid', 'error'];

    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
}
