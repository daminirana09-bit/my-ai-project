<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $fillable = ['quote_id', 'product_id', 'description', 'packing', 'hsn', 'qty', 'rate', 'discount_percent', 'gst_percent', 'amount'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'discount_percent' => 'decimal:2', 'gst_percent' => 'decimal:2', 'amount' => 'decimal:2'];
    }
}
