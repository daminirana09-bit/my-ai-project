<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Digest;

/** Runs once each morning from the scheduler. */
class DailyAutomations
{
    /** @return array{stale_tasks:int, overdue_alerts:int} */
    public function run(): array
    {
        $c = CompanySetting::current();
        $out = ['stale_tasks' => 0, 'overdue_alerts' => 0];
        if (! $c) {
            return $out;
        }

        if ($c->automation('stale_lead_tasks')) {
            Lead::stale()->whereNotNull('owner_id')->whereHas('owner', fn ($q) => $q->where('active', true))
                ->whereDoesntHave('tasks', fn ($q) => $q->where('status', '!=', 'done')->where('title', 'like', 'Follow up:%'))
                ->limit(500)->get()->each(function (Lead $l) use (&$out) {
                    Task::create(['title' => 'Follow up: '.$l->firm_name, 'details' => 'No update in '.Lead::STALE_DAYS.' days (created automatically).',
                        'assigned_to' => $l->owner_id, 'lead_id' => $l->id, 'due_on' => today(), 'priority' => 'normal', 'status' => 'pending']);
                    $out['stale_tasks']++;
                });
        }

        if ($c->automation('overdue_follow_up_alert')) {
            $late = Lead::open()->whereNotNull('owner_id')->whereDate('next_follow_up_on', '<', today()->subDays(2))
                ->selectRaw('owner_id, COUNT(*) as n')->groupBy('owner_id')->pluck('n', 'owner_id');
            if ($late->isNotEmpty()) {
                $owners = User::whereIn('id', $late->keys())->get();
                foreach ($owners->groupBy('manager_id') as $managerId => $team) {
                    $to = User::active()->where(fn ($q) => $q->whereKey($managerId)->orWhere('role', 'admin'))->get();
                    $lines = $team->map(fn ($u) => $u->name.': '.$late[$u->id])->all();
                    foreach ($to as $m) {
                        $m->notify(new Digest('overdue_follow_ups', ['lines' => $lines]));
                        $out['overdue_alerts']++;
                    }
                }
            }
        }

        return $out;
    }
}
