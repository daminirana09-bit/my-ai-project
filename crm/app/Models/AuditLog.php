<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject', 'meta', 'ip'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function record(string $action, ?string $subject = null, array $meta = []): void
    {
        self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject' => $subject,
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
