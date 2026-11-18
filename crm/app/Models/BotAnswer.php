<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A question the chatbot can answer: words to look for, and the answer to send. */
class BotAnswer extends Model
{
    protected $fillable = ['keywords', 'answer', 'active', 'hits', 'created_by'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return string[] lower-case words / phrases */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map(fn ($k) => mb_strtolower(trim($k)), preg_split('/[,\n;]+/u', (string) $this->keywords) ?: []), fn ($k) => mb_strlen($k) >= 2));
    }
}
