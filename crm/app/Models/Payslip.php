<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = ['payroll_run_id', 'user_id', 'data', 'adjust'];

    protected function casts(): array
    {
        return ['data' => 'encrypted:array', 'adjust' => 'encrypted:array'];
    }

    public function run(): BelongsTo { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function v(string $key, mixed $default = 0): mixed
    {
        return ($this->data ?? [])[$key] ?? $default;
    }
}
