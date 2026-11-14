<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaMessage extends Model
{
    protected $fillable = ['wa_conversation_id', 'direction', 'body', 'type', 'wamid', 'status', 'error', 'user_id', 'language', 'intent', 'sent_at'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'sent_at' => 'datetime'];
    }

    public function conversation(): BelongsTo { return $this->belongsTo(WaConversation::class, 'wa_conversation_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
