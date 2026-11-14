<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Sale;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/** Numbers for the dashboard. Every query is limited to what the viewer may see. */
class DashboardStats
{
    public function kpis(User $viewer): object
    {
        $leads = Lead::visibleTo($viewer);
        $ids = $viewer->visibleUserIds();
        $sales = Sale::query()->when($ids !== null, fn ($q) => $q->whereIn('user_id', $ids))
            ->whereBetween('sale_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);

        return (object) [
            'leads' => (clone $leads)->count(),
            'customers' => (clone $leads)->where('status', 'won')->count(),
            'orders' => (clone $sales)->count(),
            'sales' => (float) (clone $sales)->where('status', 'confirmed')->sum('amount'),
            'stale' => (clone $leads)->stale()->count(),
            'dead' => (clone $leads)->where('status', 'lost')->count(),
            'mature' => (clone $leads)->where('status', 'won')->count(),
            'hot' => (clone $leads)->open()->where('temperature', 'hot')->count(),
            'warm' => (clone $leads)->open()->where('temperature', 'warm')->count(),
            'cold' => (clone $leads)->open()->where('temperature', 'cold')->count(),
            'follow_up' => (clone $leads)->open()->whereNotNull('next_follow_up_on')->count(),
        ];
    }

    /** Per salesperson: total, mature, hot, warm, cold, follow-up, dead. */
    public function teamReport(Collection $users): Collection
    {
        $ids = $users->pluck('id');
        $byStatus = Lead::whereIn('owner_id', $ids)->selectRaw('owner_id, status, COUNT(*) as n')->groupBy('owner_id', 'status')->get()->groupBy('owner_id');
        $byTemp = Lead::whereIn('owner_id', $ids)->open()->whereNotNull('temperature')->selectRaw('owner_id, temperature, COUNT(*) as n')->groupBy('owner_id', 'temperature')->get()->groupBy('owner_id');
        $follow = Lead::whereIn('owner_id', $ids)->open()->whereNotNull('next_follow_up_on')->selectRaw('owner_id, COUNT(*) as n')->groupBy('owner_id')->pluck('n', 'owner_id');

        return $users->map(function (User $u) use ($byStatus, $byTemp, $follow) {
            $s = $byStatus->get($u->id, collect())->pluck('n', 'status');
            $t = $byTemp->get($u->id, collect())->pluck('n', 'temperature');

            return (object) [
                'user' => $u,
                'total' => (int) $s->sum(),
                'mature' => (int) ($s['won'] ?? 0),
                'hot' => (int) ($t['hot'] ?? 0),
                'warm' => (int) ($t['warm'] ?? 0),
                'cold' => (int) ($t['cold'] ?? 0),
                'follow_up' => (int) ($follow[$u->id] ?? 0),
                'dead' => (int) ($s['lost'] ?? 0),
            ];
        })->values();
    }

    /** Per salesperson: follow-ups pending, overdue, today, next 7 days, and leads updated today. */
    public function followUps(Collection $users): Collection
    {
        $ids = $users->pluck('id');
        $today = today()->toDateString();
        $week = today()->addDays(7)->toDateString();
        $rows = Lead::whereIn('owner_id', $ids)->open()->whereNotNull('next_follow_up_on')
            ->selectRaw('owner_id,
                COUNT(*) as total,
                SUM(CASE WHEN DATE(next_follow_up_on) < ? THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN DATE(next_follow_up_on) = ? THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN DATE(next_follow_up_on) > ? AND DATE(next_follow_up_on) <= ? THEN 1 ELSE 0 END) as upcoming', [$today, $today, $today, $week])
            ->groupBy('owner_id')->get()->keyBy('owner_id');
        $updated = LeadActivity::whereIn('user_id', $ids)->where('occurred_at', '>=', today())
            ->selectRaw('user_id, COUNT(DISTINCT lead_id) as n')->groupBy('user_id')->pluck('n', 'user_id');

        return $users->map(function (User $u) use ($rows, $updated) {
            $r = $rows->get($u->id);

            return (object) [
                'user' => $u,
                'total' => (int) ($r->total ?? 0),
                'overdue' => (int) ($r->overdue ?? 0),
                'today' => (int) ($r->today ?? 0),
                'upcoming' => (int) ($r->upcoming ?? 0),
                'updated_today' => (int) ($updated[$u->id] ?? 0),
            ];
        })->values();
    }

    public function latestLeads(User $viewer, int $n = 6): Collection
    {
        return Lead::visibleTo($viewer)->with('owner:id,name')->latest('id')->limit($n)->get();
    }

    /** Task counts by status: the viewer's own, and the team they can see. */
    public function taskCounts(User $viewer): array
    {
        $count = fn ($q) => $q->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $self = $count(Task::where('assigned_to', $viewer->id));
        $team = $viewer->canManage() ? $count(Task::visibleTo($viewer)) : $self;
        $out = [];
        foreach (array_keys(Task::STATUSES) as $s) {
            $out[$s] = ['self' => (int) ($self[$s] ?? 0), 'team' => (int) ($team[$s] ?? 0)];
        }

        return $out;
    }

    /** What needs the viewer today: their tasks due or overdue, and their follow-ups due. */
    public function reminders(User $viewer): Collection
    {
        $tasks = Task::where('assigned_to', $viewer->id)->where('status', '!=', 'done')->whereNotNull('due_on')
            ->whereDate('due_on', '<=', today())->orderBy('due_on')->limit(8)->get()
            ->map(fn (Task $t) => (object) ['kind' => 'task', 'title' => $t->title, 'date' => $t->due_on, 'url' => route('tasks.index', ['focus' => $t->id]), 'overdue' => $t->isOverdue()]);
        $follow = Lead::where('owner_id', $viewer->id)->open()->whereDate('next_follow_up_on', '<=', today())
            ->orderBy('next_follow_up_on')->limit(8)->get()
            ->map(fn (Lead $l) => (object) ['kind' => 'follow_up', 'title' => $l->firm_name, 'date' => $l->next_follow_up_on, 'url' => route('leads.show', $l), 'overdue' => $l->next_follow_up_on->lt(today())]);

        $chats = \App\Models\WaConversation::whereHas('number', fn ($q) => $q->where('user_id', $viewer->id))
            ->where(fn ($q) => $q->where('waiting_callback', true)->orWhere('urgent', true))->with('lead:id,firm_name')->limit(8)->get()
            ->map(fn ($c) => (object) ['kind' => $c->urgent ? 'urgent_chat' : 'callback', 'title' => $c->lead?->firm_name ?? $c->contact_name ?? 'WhatsApp',
                'date' => $c->last_inbound_at ?? now(), 'url' => route('inbox.show', $c), 'overdue' => (bool) $c->urgent]);

        return $chats->concat($tasks->concat($follow)->sortBy('date'))->values();
    }
}
