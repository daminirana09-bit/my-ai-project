<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Sale;
use App\Models\Target;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Target, sales done, pending and calls for each salesperson. */
class SalesReport
{
    /** @param Collection<int,User> $users */
    public function rows(Collection $users, string $month): Collection
    {
        $ids = $users->pluck('id');
        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $weekStart = CarbonImmutable::now()->startOfWeek();

        $targets = Target::whereIn('user_id', $ids)->where('month', $month)->pluck('amount', 'user_id');
        $sales = Sale::whereIn('user_id', $ids)->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('user_id, status, SUM(amount) as total, COUNT(*) as n')->groupBy('user_id', 'status')->get()
            ->groupBy('user_id');
        $calls = fn (CarbonImmutable $from) => LeadActivity::whereIn('user_id', $ids)->where('type', 'call')
            ->where('occurred_at', '>=', $from)->selectRaw('user_id, COUNT(*) as n')->groupBy('user_id')->pluck('n', 'user_id');
        $callsToday = $calls(CarbonImmutable::today());
        $callsWeek = $calls($weekStart);
        $leadCounts = Lead::whereIn('owner_id', $ids)->selectRaw('owner_id, status, COUNT(*) as n')->groupBy('owner_id', 'status')->get()->groupBy('owner_id');
        $followUps = Lead::whereIn('owner_id', $ids)->whereNotIn('status', ['won', 'lost'])->whereDate('next_follow_up_on', '<=', today())
            ->selectRaw('owner_id, COUNT(*) as n')->groupBy('owner_id')->pluck('n', 'owner_id');

        return $users->map(function (User $u) use ($targets, $sales, $callsToday, $callsWeek, $leadCounts, $followUps) {
            $s = $sales->get($u->id, collect());
            $target = (float) ($targets[$u->id] ?? 0);
            $achieved = (float) ($s->firstWhere('status', 'confirmed')->total ?? 0);
            $pendingOrders = (float) ($s->firstWhere('status', 'pending')->total ?? 0);
            $byStatus = $leadCounts->get($u->id, collect())->pluck('n', 'status');

            return (object) [
                'user' => $u,
                'target' => $target,
                'achieved' => $achieved,
                'pending_orders' => $pendingOrders,
                'remaining' => max($target - $achieved, 0),
                'percent' => $target > 0 ? round($achieved / $target * 100) : null,
                'calls_today' => (int) ($callsToday[$u->id] ?? 0),
                'calls_week' => (int) ($callsWeek[$u->id] ?? 0),
                'leads_total' => (int) $byStatus->sum(),
                'leads_open' => (int) $byStatus->except(['won', 'lost'])->sum(),
                'leads_won' => (int) ($byStatus['won'] ?? 0),
                'follow_ups_due' => (int) ($followUps[$u->id] ?? 0),
            ];
        })->values();
    }
}
