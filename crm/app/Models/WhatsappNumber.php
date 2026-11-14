<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A company WhatsApp Business number. With user_id it is that salesperson's own number. */
class WhatsappNumber extends Model
{
    protected $fillable = ['label', 'display_phone', 'phone_number_id', 'user_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Admin: all numbers. Manager: own team's numbers and shared company numbers. Rep: only their own. */
    public function scopeVisibleTo(Builder $q, User $u): Builder
    {
        if ($u->isAdmin()) {
            return $q;
        }
        $ids = $u->visibleUserIds();

        return $q->where(fn ($w) => $w->whereIn('user_id', $ids)->when($u->isManager(), fn ($x) => $x->orWhereNull('user_id')));
    }
}
