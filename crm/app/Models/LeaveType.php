<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = ['code', 'name', 'yearly_quota', 'paid', 'active', 'sort'];

    protected function casts(): array
    {
        return ['yearly_quota' => 'decimal:1', 'paid' => 'boolean', 'active' => 'boolean'];
    }

    public static function ordered()
    {
        return self::orderBy('sort')->orderBy('id');
    }
}
