<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'rejected' => 'Rejected'];

    protected $fillable = ['number', 'lead_id', 'user_id', 'quote_date', 'valid_days', 'freight', 'terms', 'status', 'subtotal', 'gst_total', 'grand_total'];

    protected function casts(): array
    {
        return ['quote_date' => 'date', 'freight' => 'decimal:2', 'subtotal' => 'decimal:2', 'gst_total' => 'decimal:2', 'grand_total' => 'decimal:2'];
    }

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(QuoteItem::class); }

    public static function nextNumber(): string
    {
        $prefix = 'QT-'.now()->format('ym').'-';
        $last = self::where('number', 'like', $prefix.'%')->orderByDesc('id')->value('number');
        $n = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
}
