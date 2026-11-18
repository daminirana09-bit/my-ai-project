<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    /** What can fill a template's {{1}}, {{2}}… */
    public const VARIABLES = ['contact_name' => 'Contact name', 'firm_name' => 'Firm name', 'city' => 'City', 'owner_name' => 'Salesperson name', 'company' => 'Your company name', 'review_link' => 'Google review link', 'website' => 'Website'];

    /** scheduled → sending → done; can be paused, cancelled, or wait for WhatsApp's daily limit ("limit"). */
    public const STATUSES = ['scheduled' => 'Scheduled', 'sending' => 'Sending', 'limit' => 'Waiting for daily limit', 'paused' => 'Paused', 'cancelled' => 'Cancelled', 'done' => 'Finished'];

    protected $fillable = ['name', 'whatsapp_number_id', 'template_name', 'template_language', 'filters', 'variables', 'status', 'total', 'created_by', 'started_at',
        'scheduled_at', 'header_type', 'header_url', 'finished_at'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'variables' => 'array', 'started_at' => 'datetime', 'scheduled_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function number(): BelongsTo { return $this->belongsTo(WhatsappNumber::class, 'whatsapp_number_id'); }
    public function recipients(): HasMany { return $this->hasMany(CampaignRecipient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** How many business-started messages WhatsApp allows in 24 hours (set under WhatsApp settings; 0 = no limit). */
    public static function dailyLimit(): int
    {
        return max(0, (int) (CompanySetting::current()?->wa('daily_limit', 1000) ?? 1000));
    }

    /** Messages that may still go out in the next 24 hours, across all campaigns. */
    public static function remainingToday(): ?int
    {
        $limit = self::dailyLimit();

        return $limit === 0 ? null : max(0, $limit - CampaignRecipient::whereIn('status', ['sent', 'delivered', 'read'])->whereNotNull('sent_at')->where('sent_at', '>=', now()->subDay())->count());
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['scheduled', 'sending', 'limit'], true);
    }
}
