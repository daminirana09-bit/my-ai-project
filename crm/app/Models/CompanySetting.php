<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name', 'logo_path', 'address', 'gstin', 'drug_licence_no', 'phone', 'email',
        'office_start', 'office_end', 'lunch_start', 'lunch_end', 'late_grace_minutes',
        'full_day_minutes', 'half_day_minutes', 'weekly_off', 'reps_can_export_own_leads', 'permissions',
        'whatsapp_config', 'automations', 'lead_capture_token',
    ];

    /** Automation switches and their defaults. */
    public const AUTOMATIONS = [
        'away_auto_reply' => true,        // reply on WhatsApp when the salesperson is away
        'after_hours_auto_reply' => false, // also reply outside office hours and on weekly off
        'auto_assign_new_leads' => true,   // leads from WhatsApp company numbers / website form go round-robin
        'stale_lead_tasks' => true,        // daily task for leads not updated in STALE_DAYS
        'overdue_follow_up_alert' => true, // tell the manager when a follow-up is 2+ days late
        'urgent_alert' => true,            // URGENT / complaint messages alert backup + managers
    ];

    /** What managers and sales reps may do. Admins can always do everything. [label, manager default, rep default] */
    public const PERMISSIONS = [
        'import_leads' => ['Import leads from Excel/CSV', true, true],
        'export_leads' => ['Export leads to Excel/CSV', true, false],
        'record_sales' => ['Record sales / orders', true, true],
        'edit_products' => ['Add and edit products', true, false],
        'assign_tasks' => ['Give tasks to other people', true, false],
        'view_analytics' => ['See analytics', true, true],
        'send_campaigns' => ['Send WhatsApp marketing campaigns', true, false],
    ];

    protected function casts(): array
    {
        return ['reps_can_export_own_leads' => 'boolean', 'permissions' => 'array', 'whatsapp_config' => 'encrypted:array', 'automations' => 'array'];
    }

    private static ?CompanySetting $cached = null;

    /** The single settings row, or null before setup has run. */
    public static function current(): ?self
    {
        return self::$cached ??= self::query()->first();
    }

    public static function forget(): void { self::$cached = null; }

    public function automation(string $key): bool
    {
        return (bool) ($this->automations[$key] ?? self::AUTOMATIONS[$key] ?? false);
    }

    /** WhatsApp Cloud API settings (stored encrypted). */
    public function wa(string $key, $default = null)
    {
        return ($this->whatsapp_config ?? [])[$key] ?? $default;
    }

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
