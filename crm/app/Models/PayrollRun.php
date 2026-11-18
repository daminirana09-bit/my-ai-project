<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use Concerns\PlainDates;

    protected array $plainDates = ['month'];

    protected $fillable = ['month', 'status', 'created_by', 'finalized_at'];

    protected function casts(): array
    {
        return ['month' => 'date', 'finalized_at' => 'datetime'];
    }

    public function payslips(): HasMany { return $this->hasMany(Payslip::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }
}
