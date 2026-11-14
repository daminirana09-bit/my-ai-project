<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const FORMS = ['Tablet', 'Capsule', 'Softgel', 'Syrup', 'Suspension', 'Drops', 'Injection', 'Cream', 'Ointment', 'Gel', 'Powder', 'Sachet', 'Other'];

    protected $fillable = ['name', 'composition', 'form', 'packing', 'category', 'rate', 'unit', 'moq', 'hsn', 'gst_percent', 'active', 'notes'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2', 'gst_percent' => 'decimal:2', 'active' => 'boolean', 'moq' => 'integer'];
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }
        $t = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('composition', 'like', $t)->orWhere('packing', 'like', $t)->orWhere('category', 'like', $t));
    }
}
