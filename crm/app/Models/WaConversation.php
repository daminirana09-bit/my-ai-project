<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaConversation extends Model
{
    protected $fillable = ['whatsapp_number_id', 'wa_id_hash', 'wa_id', 'contact_name', 'lead_id', 'language', 'last_intent',
        'last_inbound_at', 'last_message_at', 'unread', 'auto_replied_at', 'waiting_callback', 'urgent'];

    protected function casts(): array
    {
        return ['wa_id' => 'encrypted', 'last_inbound_at' => 'datetime', 'last_message_at' => 'datetime', 'auto_replied_at' => 'datetime',
            'waiting_callback' => 'boolean', 'urgent' => 'boolean'];
    }

    public function number(): BelongsTo { return $this->belongsTo(WhatsappNumber::class, 'whatsapp_number_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function messages(): HasMany { return $this->hasMany(WaMessage::class)->orderBy('sent_at')->orderBy('id'); }

    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        return $q->whereIn('whatsapp_number_id', WhatsappNumber::visibleTo($u)->select('id'));
    }

    public function isVisibleTo(User $u): bool
    {
        return WhatsappNumber::visibleTo($u)->whereKey($this->whatsapp_number_id)->exists();
    }

    /** WhatsApp allows free-form replies only within 24 hours of the client's last message. */
    public function canReplyFreely(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->gt(now()->subHours(24));
    }

    public function maskedNumber(): string
    {
        $n = preg_replace('/\D+/', '', (string) $this->wa_id);

        return $n ? '+'.substr($n, 0, 2).' ••••• •'.substr($n, -4) : '—';
    }
}
