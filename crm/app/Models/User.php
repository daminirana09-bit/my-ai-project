<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const LOCALES = ['en' => 'English', 'hing' => 'Hinglish', 'hi' => 'हिन्दी'];
    public const ROLES = ['admin' => 'Admin', 'manager' => 'Manager', 'rep' => 'Sales rep'];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'manager_id', 'phone', 'active',
        'office_start', 'office_end', 'lunch_start', 'lunch_end', 'locale',
        'away_type', 'away_until', 'backup_user_id', 'salutation',
    ];

    public const AWAY_TYPES = ['half_day' => 'Half day', 'leave' => 'On leave', 'meeting' => 'Meeting / field visit'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'away_until' => 'datetime',
            'password' => 'hashed',
            'phone' => 'encrypted',
            'two_factor_secret' => 'encrypted',
            'active' => 'boolean',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isManager(): bool { return $this->role === 'manager'; }
    public function isRep(): bool { return $this->role === 'rep'; }
    /** Admin and managers see team data and get clash alerts. */
    public function canManage(): bool { return in_array($this->role, ['admin', 'manager'], true); }

    /** Role permission set by the admin on Roles & Permissions. */
    public function allows(string $permission): bool
    {
        return (bool) CompanySetting::current()?->roleAllows($this->role, $permission);
    }

    /** Away right now (half day, leave or meeting), so WhatsApp cover replies for them. */
    public function isAway(): bool
    {
        return $this->away_type !== null && $this->away_until !== null && $this->away_until->isFuture();
    }

    public function backup(): BelongsTo { return $this->belongsTo(User::class, 'backup_user_id'); }

    /** How clients hear this person's name: "Amit ji", "Amit Sir", "Amit Madam". */
    public function calledAs(): string
    {
        $first = explode(' ', trim($this->name))[0];

        $title = match ($this->salutation) {
            'sir' => 'Sir',
            'madam' => 'Madam',
            default => 'ji',
        };

        return $first.' '.$title;
    }

    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_id'); }
    public function team(): HasMany { return $this->hasMany(User::class, 'manager_id'); }
    public function leads(): HasMany { return $this->hasMany(Lead::class, 'owner_id'); }
    public function targets(): HasMany { return $this->hasMany(Target::class); }
    public function sales(): HasMany { return $this->hasMany(Sale::class); }

    /**
     * IDs of the people whose work this user may see:
     * admin → everyone, manager → self + own team, rep → only self.
     */
    public function visibleUserIds(): ?array
    {
        if ($this->isAdmin()) {
            return null; // no limit
        }
        if ($this->isManager()) {
            return User::where('manager_id', $this->id)->pluck('id')->push($this->id)->all();
        }
        return [$this->id];
    }

    /** True if this user may see the other person's data (self, own team, or anyone for admin). */
    public function canSeeUser(int|string|null $userId): bool
    {
        $ids = $this->visibleUserIds();

        return $userId !== null && ($ids === null || in_array((int) $userId, array_map('intval', $ids), true));
    }

    public function scopeActive(Builder $q): Builder { return $q->where('active', true); }

    /** Users this user may assign leads to or view in reports. */
    public function scopeVisibleTo(Builder $q, User $viewer): Builder
    {
        $ids = $viewer->visibleUserIds();
        return $ids === null ? $q : $q->whereIn('id', $ids);
    }

    /** The timing that applies to this person: their own if set, else the company's. */
    public function shift(CompanySetting $c): array
    {
        return [
            'office_start' => $this->office_start ?: $c->office_start,
            'office_end' => $this->office_end ?: $c->office_end,
            'lunch_start' => $this->lunch_start ?: $c->lunch_start,
            'lunch_end' => $this->lunch_end ?: $c->lunch_end,
        ];
    }
}
