<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name', 'logo_path', 'address', 'gstin', 'drug_licence_no', 'phone', 'email',
        'office_start', 'office_end', 'lunch_start', 'lunch_end', 'late_grace_minutes',
        'full_day_minutes', 'half_day_minutes', 'weekly_off', 'reps_can_export_own_leads', 'permissions',
    ];

    /** What managers and sales reps may do. Admins can always do everything. [label, manager default, rep default] */
    public const PERMISSIONS = [
        'import_leads' => ['Import leads from Excel/CSV', true, true],
        'export_leads' => ['Export leads to Excel/CSV', true, false],
        'record_sales' => ['Record sales / orders', true, true],
        'edit_products' => ['Add and edit products', true, false],
        'assign_tasks' => ['Give tasks to other people', true, false],
        'view_analytics' => ['See analytics', true, true],
    ];

    protected function casts(): array
    {
        return ['reps_can_export_own_leads' => 'boolean', 'permissions' => 'array'];
    }

    private static ?CompanySetting $cached = null;

    /** The single settings row, or null before setup has run. */
    public static function current(): ?self
    {
        return self::$cached ??= self::query()->first();
    }

    public static function forget(): void { self::$cached = null; }

    public function roleAllows(string $role, string $permission): bool
    {
        if ($role === 'admin') {
            return true;
        }
        if (! isset(self::PERMISSIONS[$permission])) {
            return false;
        }
        $saved = $this->permissions[$role][$permission] ?? null;
        if ($saved !== null) {
            return (bool) $saved;
        }
        // Before anyone saved the matrix, the older export switch still decides for reps.
        if ($permission === 'export_leads' && $role === 'rep') {
            return (bool) $this->reps_can_export_own_leads;
        }

        return self::PERMISSIONS[$permission][$role === 'manager' ? 1 : 2];
    }

    /** @return int[] weekday numbers (0 = Sunday) that are weekly offs */
    public function weeklyOffDays(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->weekly_off)), fn ($d) => $d >= 0 && $d <= 6));
    }
}
