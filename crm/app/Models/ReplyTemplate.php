<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A ready reply for one kind of client request, in one language. {name}, {company}, {rep} are filled in. */
class ReplyTemplate extends Model
{
    public const INTENTS = [
        'greeting' => 'Greeting',
        'price' => 'Rate / price list',
        'order_status' => 'Order status / delivery',
        'new_order' => 'New order',
        'payment' => 'Payment',
        'sample' => 'Sample',
        'documents' => 'Documents (COA, GMP…)',
        'complaint' => 'Complaint / urgent',
        'away' => 'Auto-reply when salesperson is away',
        'other' => 'Other',
    ];

    protected $fillable = ['intent', 'language', 'body', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** Fill {placeholders} with real values; unknown ones are left out. */
    public static function render(string $body, array $vars): string
    {
        return trim(preg_replace('/\{[a-z_]+\}/', '', strtr($body, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all())));
    }
}
