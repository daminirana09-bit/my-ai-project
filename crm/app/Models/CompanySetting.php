<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name', 'logo_path', 'address', 'gstin', 'drug_licence_no', 'phone', 'email',
        'office_start', 'office_end', 'lunch_start', 'lunch_end', 'late_grace_minutes',
        'full_day_minutes', 'half_day_minutes', 'weekly_off', 'reps_can_export_own_leads',
    ];

    protected function casts(): array
    {
        return ['reps_can_export_own_leads' => 'boolean'];
    }

    private static ?CompanySetting $cached = null;

    /** The single settings row, or null before setup has run. */
    public static function current(): ?self
    {
        return self::$cached ??= self::query()->first();
    }

    public static function forget(): void { self::$cached = null; }

    /** @return int[] weekday numbers (0 = Sunday) that are weekly offs */
    public function weeklyOffDays(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->weekly_off)), fn ($d) => $d >= 0 && $d <= 6));
    }
}
