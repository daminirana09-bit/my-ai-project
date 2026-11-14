<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'interested' => 'Interested',
        'quote_sent' => 'Quote sent',
        'negotiation' => 'Negotiation',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    /** How warm the client is. "Mature" = won, "Dead" = lost (see STATUSES). */
    public const TEMPERATURES = ['hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold'];

    /** Days without any update after which an open lead counts as "not updated". */
    public const STALE_DAYS = 30;

    protected $fillable = [
        'firm_name', 'contact_name', 'phone', 'email', 'city', 'state', 'gstin', 'source', 'client_type',
        'requirement', 'status', 'expected_value', 'owner_id', 'created_by', 'created_via',
        'last_contact_at', 'next_follow_up_on', 'lost_reason', 'notes', 'temperature', 'whatsapp_opt_out', 'language',
        'tags', 'campaign', 'referred_by', 'external_ref',
    ];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'expected_value' => 'decimal:2',
            'last_contact_at' => 'datetime',
            'next_follow_up_on' => 'date',
            'whatsapp_opt_out' => 'boolean',
            'tags' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Keep a keyed hash of the phone so the same client can be found again
        // without storing the number in readable form.
        static::saving(function (Lead $lead) {
            $lead->phone_hash = self::hashPhone($lead->phone);
            $lead->email_hash = self::hashEmail($lead->email);
            if ($lead->gstin) {
                $lead->gstin = strtoupper(trim($lead->gstin));
            }
        });
    }

    /** Last 10 digits of an Indian mobile number; other numbers keep all digits. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        return strlen($digits) > 10 && str_starts_with($digits, '91') ? substr($digits, -10) : $digits;
    }

    public static function hashPhone(?string $phone): ?string
    {
        $n = self::normalizePhone($phone);
        return $n ? hash_hmac('sha256', $n, (string) config('app.key')) : null;
    }

    public static function hashEmail(?string $email): ?string
    {
        $e = strtolower(trim((string) $email));

        return $e !== '' ? hash_hmac('sha256', 'e:'.$e, (string) config('app.key')) : null;
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['won', 'lost']);
    }

    /** Open leads nobody has touched for STALE_DAYS days. */
    public function scopeStale(Builder $q): Builder
    {
        $cut = now()->subDays(self::STALE_DAYS);

        return $q->open()->where(fn ($w) => $w->where('last_contact_at', '<', $cut)->orWhere(fn ($x) => $x->whereNull('last_contact_at')->where('created_at', '<', $cut)));
    }

    public function quotes(): HasMany { return $this->hasMany(Quote::class)->latest('id'); }
    public function conversations(): HasMany { return $this->hasMany(WaConversation::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class)->latest(); }
    public function sales(): HasMany { return $this->hasMany(Sale::class)->latest('sale_date'); }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function activities(): HasMany { return $this->hasMany(LeadActivity::class)->latest('occurred_at'); }
    public function clashes(): HasMany { return $this->hasMany(LeadClash::class); }

    /** Rule 1: a rep only ever gets their own leads; a manager gets the team's. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        $ids = $user->visibleUserIds();
        return $ids === null ? $q : $q->whereIn('owner_id', $ids);
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isAdmin() || $user->canSeeUser($this->owner_id);
    }

    /** Tags shown on the lead: the source first, then the rest, no repeats. */
    public function tagList(): array
    {
        return collect([$this->source])->merge($this->tags ?? [])->filter()->unique(fn ($t) => mb_strtolower($t))->values()->all();
    }

    /** CSS colour class for a source / platform tag. */
    public static function tagClass(?string $tag): string
    {
        $k = \Illuminate\Support\Str::slug((string) $tag);
        $known = ['indiamart', 'tradeindia', 'exportersindia', 'justdial', 'facebook-ads', 'instagram-ads', 'google-ads', 'google-search',
            'google-maps-business', 'website', 'whatsapp', 'reference', 'exhibition', 'walk-in', 'cold-call', 'import', 'other-apps', 'existing-customer'];

        return in_array($k, $known, true) ? 'src src-'.$k : 'src';
    }

    public function maskedPhone(): string
    {
        $n = self::normalizePhone($this->phone);
        return $n ? str_repeat('•', max(strlen($n) - 4, 0)).substr($n, -4) : '—';
    }
}
