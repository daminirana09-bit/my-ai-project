<?php

namespace App\Services\WhatsApp;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\User;

/**
 * Gives a new lead (WhatsApp company number, website form, IndiaMART, Facebook…) to the rep
 * with the fewest new ones today.
 */
class AutoAssigner
{
    /**
     * @param  array|null  $pool  reps chosen for one platform (e.g. IndiaMART leads only to Amit and Bhavna).
     *                            A chosen pool is used even when general auto-assign is off.
     */
    public function pick(?array $pool = null): ?User
    {
        $pool = collect($pool ?? [])->map(fn ($v) => (int) $v)->filter();
        if ($pool->isEmpty()) {
            $c = CompanySetting::current();
            if (! $c?->automation('auto_assign_new_leads')) {
                return null;
            }
            $pool = collect($c->automations['assign_pool'] ?? [])->map(fn ($v) => (int) $v)->filter();
        }
        $reps = User::active()->whereIn('role', ['rep', 'manager'])
            ->when($pool->isNotEmpty(), fn ($q) => $q->whereIn('id', $pool), fn ($q) => $q->where('role', 'rep'))
            ->orderBy('id')->get();
        if ($reps->isEmpty()) {
            return null;
        }
        $today = Lead::whereIn('owner_id', $reps->pluck('id'))->whereIn('created_via', ['whatsapp', 'website', 'integration'])->where('created_at', '>=', today())
            ->selectRaw('owner_id, COUNT(*) as n')->groupBy('owner_id')->pluck('n', 'owner_id');

        return $reps->sortBy(fn (User $u) => [(int) ($today[$u->id] ?? 0), $u->id])->first();
    }
}
