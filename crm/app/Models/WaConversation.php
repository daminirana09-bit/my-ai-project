<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaConversation extends Model
{
    protected $fillable = ['whatsapp_number_id', 'wa_id_hash', 'wa_id', 'contact_name', 'lead_id', 'language', 'last_intent',
        'last_inbound_at', 'last_message_at', 'unread', 'auto_replied_at', 'waiting_callback', 'urgent',
        'assigned_to', 'status', 'closed_at', 'bot_step', 'bot_paused_until', 'bot_replied_at'];

    protected function casts(): array
    {
        return ['wa_id' => 'encrypted', 'last_inbound_at' => 'datetime', 'last_message_at' => 'datetime', 'auto_replied_at' => 'datetime',
            'waiting_callback' => 'boolean', 'urgent' => 'boolean', 'closed_at' => 'datetime', 'bot_paused_until' => 'datetime', 'bot_replied_at' => 'datetime'];
    }

    public function number(): BelongsTo { return $this->belongsTo(WhatsappNumber::class, 'whatsapp_number_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages(): HasMany { return $this->hasMany(WaMessage::class)->orderBy('sent_at')->orderBy('id'); }

    /** Chats on numbers this person may see, plus chats given to them (or to their team) on a shared number. */
    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        $ids = $u->visibleUserIds();

        return $q->where(fn ($w) => $w->whereIn('whatsapp_number_id', WhatsappNumber::visibleTo($u)->select('id'))
            ->when($ids !== null, fn ($x) => $x->orWhereIn('assigned_to', $ids)));
    }

    public function isVisibleTo(User $u): bool
    {
        return self::visibleTo($u)->whereKey($this->id)->exists();
    }

    /** True while the chatbot may answer this chat. */
    public function botMayReply(): bool
    {
        return $this->bot_paused_until === null || $this->bot_paused_until->isPast();
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
