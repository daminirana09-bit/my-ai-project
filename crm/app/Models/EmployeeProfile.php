<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** HR details of one person. Personal, bank, ID and salary details are encrypted. */
class EmployeeProfile extends Model
{
    use Concerns\PlainDates;

    protected array $plainDates = ['date_of_joining', 'date_of_birth', 'exit_date'];

    public const TYPES = ['permanent' => 'Permanent', 'probation' => 'Probation', 'contract' => 'Contract', 'intern' => 'Intern'];

    public const STATUSES = ['active' => 'Working', 'notice' => 'On notice', 'left' => 'Left'];

    /** Monthly salary parts, in rupees. */
    public const EARNINGS = ['basic' => 'Basic', 'hra' => 'HRA', 'conveyance' => 'Conveyance', 'special' => 'Special allowance', 'other' => 'Other allowance'];

    protected $fillable = ['user_id', 'emp_code', 'designation', 'department', 'headquarter', 'employment_type', 'date_of_joining', 'date_of_birth',
        'gender', 'status', 'exit_date', 'personal', 'bank', 'ids', 'salary'];

    protected function casts(): array
    {
        return ['date_of_joining' => 'date', 'date_of_birth' => 'date', 'exit_date' => 'date',
            'personal' => 'encrypted:array', 'bank' => 'encrypted:array', 'ids' => 'encrypted:array', 'salary' => 'encrypted:array'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function for(User $u): self
    {
        return self::firstOrNew(['user_id' => $u->id]);
    }

    public function pay(string $key, mixed $default = null): mixed
    {
        $v = ($this->salary ?? [])[$key] ?? null;

        return $v === null || $v === '' ? $default : $v;
    }

    /** Gross monthly salary before deductions. */
    public function monthlyGross(): float
    {
        return array_sum(array_map(fn ($k) => (float) $this->pay($k, 0), array_keys(self::EARNINGS)));
    }

    public function field(string $group, string $key): ?string
    {
        $v = ($this->{$group} ?? [])[$key] ?? null;

        return $v === null || $v === '' ? null : (string) $v;
    }

    /** "XXXXXX1234": the account number never shown in full on lists. */
    public function maskedAccount(): string
    {
        $n = (string) $this->field('bank', 'account_no');

        return $n === '' ? '—' : str_repeat('•', max(0, strlen($n) - 4)).substr($n, -4);
    }
}
