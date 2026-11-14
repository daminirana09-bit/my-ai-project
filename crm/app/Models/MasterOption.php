<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Company-editable lists: lead sources, client types, lost reasons, product categories. */
class MasterOption extends Model
{
    public const TYPES = [
        'source' => 'Lead sources',
        'client_type' => 'Client types',
        'lost_reason' => 'Reasons a lead is lost',
        'product_category' => 'Product categories',
    ];

    protected $fillable = ['type', 'value', 'sort', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    private static array $cache = [];

    /** Active values of one list, in the company's order. */
    public static function values(string $type): array
    {
        return self::$cache[$type] ??= self::where('type', $type)->where('active', true)->orderBy('sort')->orderBy('value')->pluck('value')->all();
    }

    public static function forget(): void
    {
        self::$cache = [];
    }
}
