<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    /** What can fill a template's {{1}}, {{2}}… */
    public const VARIABLES = ['contact_name' => 'Contact name', 'firm_name' => 'Firm name', 'city' => 'City', 'owner_name' => 'Salesperson name', 'company' => 'Your company name'];

    protected $fillable = ['name', 'whatsapp_number_id', 'template_name', 'template_language', 'filters', 'variables', 'status', 'total', 'created_by', 'started_at'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'variables' => 'array', 'started_at' => 'datetime'];
    }

    public function number(): BelongsTo { return $this->belongsTo(WhatsappNumber::class, 'whatsapp_number_id'); }
    public function recipients(): HasMany { return $this->hasMany(CampaignRecipient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
