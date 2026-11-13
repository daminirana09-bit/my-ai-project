<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    public const STATUSES = ['confirmed' => 'Confirmed', 'pending' => 'Pending'];

    protected $fillable = ['user_id', 'lead_id', 'invoice_no', 'amount', 'sale_date', 'status', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'sale_date' => 'date'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
}
