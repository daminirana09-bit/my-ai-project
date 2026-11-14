<?php

namespace App\Services\WhatsApp;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\User;

/** Gives a new lead (from a shared WhatsApp number or the website form) to the rep with the fewest new ones today. */
class AutoAssigner
{
    public function pick(): ?User
    {
        $c = CompanySetting::current();
        if (! $c?->automation('auto_assign_new_leads')) {
            return null;
        }
        $pool = collect($c->automations['assign_pool'] ?? [])->map(fn ($v) => (int) $v)->filter();
        $reps = User::active()->where('role', 'rep')->when($pool->isNotEmpty(), fn ($q) => $q->whereIn('id', $pool))->orderBy('id')->get();
        if ($reps->isEmpty()) {
            return null;
        }
        $today = Lead::whereIn('owner_id', $reps->pluck('id'))->whereIn('created_via', ['whatsapp', 'website'])->where('created_at', '>=', today())
            ->selectRaw('owner_id, COUNT(*) as n')->groupBy('owner_id')->pluck('n', 'owner_id');

        return $reps->sortBy(fn (User $u) => [(int) ($today[$u->id] ?? 0), $u->id])->first();
    }
}
