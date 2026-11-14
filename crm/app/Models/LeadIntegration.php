<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A lead platform the company connects: IndiaMART, TradeIndia, Facebook lead ads…
 * Leads either arrive at our receiving link (push) or the CRM fetches them with the API key (pull).
 */
class LeadIntegration extends Model
{
    /**
     * name, the lead source it sets, whether the CRM can fetch (pull), and the settings it needs:
     * key => [label, secret?]. Secrets are never shown again after saving.
     */
    public const PLATFORMS = [
        'indiamart' => ['name' => 'IndiaMART', 'source' => 'IndiaMART', 'pull' => true,
            'fields' => ['crm_key' => ['CRM key (Lead Manager → CRM integration)', true]]],
        'tradeindia' => ['name' => 'TradeIndia', 'source' => 'TradeIndia', 'pull' => true,
            'fields' => ['user_id' => ['User ID', false], 'profile_id' => ['Profile ID', false], 'key' => ['API key', true]]],
        'exportersindia' => ['name' => 'ExportersIndia', 'source' => 'ExportersIndia', 'pull' => true,
            'fields' => ['api_url' => ['API link given by the portal', true]]],
        'justdial' => ['name' => 'JustDial', 'source' => 'JustDial', 'pull' => false, 'fields' => []],
        'facebook' => ['name' => 'Facebook & Instagram lead ads', 'source' => 'Facebook Ads', 'pull' => false,
            'fields' => ['page_id' => ['Facebook Page ID', false], 'page_token' => ['Page access token (System user)', true], 'app_secret' => ['App secret', true]]],
        'google_ads' => ['name' => 'Google Ads lead forms', 'source' => 'Google Ads', 'pull' => false, 'fields' => []],
        'other_portal' => ['name' => 'Any other portal (API link)', 'source' => 'Other apps', 'pull' => true,
            'fields' => ['source_name' => ['Name to show as source', false], 'api_url' => ['API link given by the portal', true]]],
        'custom' => ['name' => 'Other apps (Zapier, Pabbly, LinkedIn, IVR…)', 'source' => 'Other apps', 'pull' => false, 'fields' => []],
    ];

    protected $fillable = ['platform', 'enabled', 'config', 'token', 'assign_to', 'extra_tags', 'last_pulled_at', 'last_success_at',
        'last_lead_at', 'last_error', 'last_error_at', 'last_fields', 'received'];

    protected $hidden = ['config', 'token'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'encrypted:array',
            'assign_to' => 'array',
            'last_pulled_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_lead_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    /** The row for a platform, created (switched off) the first time it is needed. */
    public static function for(string $platform): self
    {
        abort_unless(isset(self::PLATFORMS[$platform]), 404);

        return self::firstOrCreate(['platform' => $platform], [
            'enabled' => false,
            'token' => Str::random(40),
            'config' => match ($platform) {
                'facebook' => ['verify_token' => Str::random(32)],
                'google_ads' => ['google_key' => Str::random(32)],
                default => [],
            },
        ]);
    }

    public function info(string $key, mixed $default = null): mixed
    {
        return self::PLATFORMS[$this->platform][$key] ?? $default;
    }

    public function name(): string
    {
        return $this->info('name', $this->platform);
    }

    public function conf(string $key, mixed $default = null): mixed
    {
        $v = ($this->config ?? [])[$key] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    /** Lead source label for leads from this platform. */
    public function sourceLabel(): string
    {
        return $this->platform === 'other_portal' ? (string) $this->conf('source_name', 'Other apps') : $this->info('source', 'Other apps');
    }

    public function canPull(): bool
    {
        return (bool) $this->info('pull') && match ($this->platform) {
            'indiamart' => filled($this->conf('crm_key')),
            'tradeindia' => filled($this->conf('user_id')) && filled($this->conf('profile_id')) && filled($this->conf('key')),
            'exportersindia', 'other_portal' => filled($this->conf('api_url')),
            default => false,
        };
    }

    public function receiveUrl(): string
    {
        return url('/webhook/leads/'.$this->platform.'/'.$this->token);
    }

    /** Extra tags the admin wants on every lead from here. */
    public function extraTags(): array
    {
        return collect(explode(',', (string) $this->extra_tags))->map(fn ($t) => trim($t))->filter()->values()->all();
    }

    public function failed(string $error): void
    {
        $this->forceFill(['last_error' => mb_substr($error, 0, 500), 'last_error_at' => now()])->save();
    }
}
