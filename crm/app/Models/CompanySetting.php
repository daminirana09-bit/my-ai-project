<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name', 'logo_path', 'address', 'gstin', 'drug_licence_no', 'phone', 'email',
        'office_start', 'office_end', 'lunch_start', 'lunch_end', 'late_grace_minutes',
        'full_day_minutes', 'half_day_minutes', 'weekly_off', 'reps_can_export_own_leads', 'permissions',
        'whatsapp_config', 'automations', 'lead_capture_token', 'links', 'share_messages', 'mail_config', 'translation_config', 'bot_config', 'hr_config',
    ];

    /** Automation switches and their defaults. */
    public const AUTOMATIONS = [
        'away_auto_reply' => true,        // reply on WhatsApp when the salesperson is away
        'after_hours_auto_reply' => true,  // also reply outside office hours and on weekly off
        'absent_auto_reply' => true,       // reply when the salesperson has not punched in, or has punched out and left
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
        'approve_leaves' => ['Approve leave and expense claims of their team', true, false],
    ];

    protected function casts(): array
    {
        return ['reps_can_export_own_leads' => 'boolean', 'permissions' => 'array', 'whatsapp_config' => 'encrypted:array', 'automations' => 'array', 'links' => 'array', 'share_messages' => 'array', 'mail_config' => 'encrypted:array', 'translation_config' => 'encrypted:array', 'bot_config' => 'array', 'hr_config' => 'array'];
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
    /** The company's own pages the team shares with clients. key => [label, example] */
    public const LINKS = [
        'website' => ['Website', 'https://www.yourcompany.com'],
        'google_review' => ['Google review link', 'https://g.page/r/…/review'],
        'google_maps' => ['Google Maps / Business profile', 'https://maps.app.goo.gl/…'],
        'facebook' => ['Facebook page', 'https://facebook.com/…'],
        'instagram' => ['Instagram', 'https://instagram.com/…'],
        'linkedin' => ['LinkedIn', 'https://linkedin.com/company/…'],
        'youtube' => ['YouTube', 'https://youtube.com/@…'],
        'x' => ['X (Twitter)', 'https://x.com/…'],
        'whatsapp_channel' => ['WhatsApp channel / catalogue', 'https://whatsapp.com/channel/…'],
        'indiamart' => ['IndiaMART store', 'https://www.indiamart.com/…'],
        'tradeindia' => ['TradeIndia store', 'https://www.tradeindia.com/…'],
        'justdial' => ['JustDial page', 'https://www.justdial.com/…'],
        'other' => ['Other link', 'https://…'],
    ];

    /** Ready messages; {name} = client, {company}, {rep}, {links}, {review}, {website}, {phone}, {email}. */
    public const SHARE_MESSAGES = [
        'profile' => "Namaste {name}, thank you for your interest in {company}.\nWebsite: {website}\nPhone: {phone}\nEmail: {email}\n\nFollow us for new products and offers:\n{links}\n\n– {rep}, {company}",
        'review' => "Namaste {name}, thank you for choosing {company}. If you are happy with our products and service, please give us a Google review. It takes one minute:\n{review}\n\nThank you!\n– {rep}, {company}",
    ];

    /** HR rules: allowances, statutory limits. Amounts in rupees. */
    public const HR_DEFAULTS = [
        'da_hq' => 150, 'da_exhq' => 250, 'da_outstation' => 400,   // daily allowance by where the person worked
        'km_bike' => 3.5, 'km_car' => 8,                            // per km
        'pf_ceiling' => 15000, 'pf_rate' => 12,                     // PF: 12% of basic up to ₹15,000
        'esi_ceiling' => 21000, 'esi_rate' => 0.75, 'esi_employer_rate' => 3.25,
        'pt_default' => 200,                                         // professional tax per month (state-wise)
        'punch_location_required' => 0,                             // 1 = punch only with the phone's location
    ];

    public function hr(string $key): float
    {
        $v = ($this->hr_config ?? [])[$key] ?? null;

        return (float) (($v === null || $v === '') ? self::HR_DEFAULTS[$key] : $v);
    }

    public function tr(string $key, mixed $default = null): mixed
    {
        $v = ($this->translation_config ?? [])[$key] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    public function mail(string $key, mixed $default = null): mixed
    {
        $v = ($this->mail_config ?? [])[$key] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    public function link(string $key): ?string
    {
        $v = ($this->links ?? [])[$key] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    public function shareMessage(string $key): string
    {
        $v = ($this->share_messages ?? [])[$key] ?? null;

        return is_string($v) && trim($v) !== '' ? $v : self::SHARE_MESSAGES[$key];
    }

    public function weeklyOffDays(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->weekly_off)), fn ($d) => $d >= 0 && $d <= 6));
    }
}
