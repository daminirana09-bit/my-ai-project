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

    public const SOURCES = ['IndiaMART', 'TradeIndia', 'Website', 'WhatsApp', 'Call', 'Email', 'Referral', 'Exhibition', 'Other'];
    public const CLIENT_TYPES = ['Third-party', 'PCD Franchise', 'Distributor', 'Hospital', 'Export', 'Other'];

    protected $fillable = [
        'firm_name', 'contact_name', 'phone', 'email', 'city', 'state', 'gstin', 'source', 'client_type',
        'requirement', 'status', 'expected_value', 'owner_id', 'created_by', 'created_via',
        'last_contact_at', 'next_follow_up_on', 'lost_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'expected_value' => 'decimal:2',
            'last_contact_at' => 'datetime',
            'next_follow_up_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Keep a keyed hash of the phone so the same client can be found again
        // without storing the number in readable form.
        static::saving(function (Lead $lead) {
            $lead->phone_hash = self::hashPhone($lead->phone);
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
        $ids = $user->visibleUserIds();
        return $ids === null || in_array((int) $this->owner_id, array_map('intval', $ids), true);
    }

    public function maskedPhone(): string
    {
        $n = self::normalizePhone($this->phone);
        return $n ? str_repeat('•', max(strlen($n) - 4, 0)).substr($n, -4) : '—';
    }
}
