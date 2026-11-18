<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\ExpenseClaim;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Sale;
use App\Models\Target;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Deals (pipeline, won, lost) and the monthly sales report per salesperson. */
class Reports
{
    public const OPEN = ['new', 'contacted', 'interested', 'quote_sent', 'negotiation'];

    /** Chance of winning by stage, for the weighted pipeline. */
    public const CHANCE = ['new' => 10, 'contacted' => 20, 'interested' => 40, 'quote_sent' => 60, 'negotiation' => 80];

    public function deals(Builder $leads, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $range = [$from->startOfDay(), $to->endOfDay()];
        $open = (clone $leads)->whereIn('status', self::OPEN)->get(['id', 'firm_name', 'city', 'status', 'expected_value', 'owner_id', 'source', 'created_at', 'last_contact_at', 'updated_at']);
        $won = (clone $leads)->where('status', 'won')->whereBetween('won_at', $range)->get(['id', 'expected_value', 'owner_id', 'source', 'created_at', 'won_at']);
        $lost = (clone $leads)->where('status', 'lost')->whereBetween('lost_at', $range)->get(['id', 'expected_value', 'owner_id', 'source', 'lost_reason']);
        $created = (clone $leads)->whereBetween('created_at', $range)->get(['id', 'status', 'source', 'expected_value']);
        $days = fn ($rows) => $rows->count() ? round($rows->avg(fn ($l) => $l->created_at->diffInDays($l->won_at)), 1) : null;
        $rate = fn ($w, $l) => ($w + $l) ? (int) round($w * 100 / ($w + $l)) : null;
        $orders = (float) Sale::where('status', 'confirmed')->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('lead_id', (clone $leads)->select('id'))->sum('amount');

        $stages = collect(self::OPEN)->mapWithKeys(fn ($s) => [$s => (object) [
            'count' => $open->where('status', $s)->count(),
            'value' => (float) $open->where('status', $s)->sum('expected_value'),
            'weighted' => (float) $open->where('status', $s)->sum('expected_value') * self::CHANCE[$s] / 100,
        ]]);
        $owners = User::whereIn('id', $open->pluck('owner_id')->merge($won->pluck('owner_id'))->merge($lost->pluck('owner_id'))->filter()->unique())->orderBy('name')->get(['id', 'name']);
        $byPerson = $owners->map(fn ($u) => (object) [
            'user' => $u,
            'open' => $open->where('owner_id', $u->id)->count(), 'open_value' => (float) $open->where('owner_id', $u->id)->sum('expected_value'),
            'won' => $won->where('owner_id', $u->id)->count(), 'won_value' => (float) $won->where('owner_id', $u->id)->sum('expected_value'),
            'lost' => $lost->where('owner_id', $u->id)->count(),
            'rate' => $rate($won->where('owner_id', $u->id)->count(), $lost->where('owner_id', $u->id)->count()),
            'days' => $days($won->where('owner_id', $u->id)),
        ]);
        $sources = $created->groupBy(fn ($l) => $l->source ?: '—')->map(fn ($rows, $src) => (object) [
            'source' => $src, 'leads' => $rows->count(), 'won' => $rows->where('status', 'won')->count(),
            'rate' => $rows->count() ? round($rows->where('status', 'won')->count() * 100 / $rows->count()) : 0,
            'value' => (float) $rows->where('status', 'won')->sum('expected_value'),
        ])->sortByDesc('leads')->values();

        return [
            'open_count' => $open->count(), 'open_value' => (float) $open->sum('expected_value'), 'weighted' => (float) $stages->sum('weighted'),
            'won_count' => $won->count(), 'won_value' => (float) $won->sum('expected_value'),
            'lost_count' => $lost->count(), 'lost_value' => (float) $lost->sum('expected_value'),
            'created' => $created->count(), 'rate' => $rate($won->count(), $lost->count()),
            'avg_deal' => $won->count() ? (float) $won->sum('expected_value') / $won->count() : null,
            'avg_days' => $days($won), 'orders' => $orders,
            'stages' => $stages, 'people' => $byPerson, 'sources' => $sources,
            'reasons' => $lost->groupBy(fn ($l) => $l->lost_reason ?: __('Not given'))->map->count()->sortDesc(),
            'stale' => $open->filter(fn ($l) => ($l->last_contact_at ?? $l->created_at)->lt(now()->subDays(30)))
                ->sortByDesc(fn ($l) => (float) $l->expected_value)->take(20)->values(),
        ];
    }

    /** One row per salesperson for a month. @param Collection<int,User> $people */
    public function monthly(Collection $people, CarbonImmutable $month): Collection
    {
        $ids = $people->pluck('id');
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();
        $prevStart = $start->subMonthNoOverflow()->startOfMonth();
        $span = [$start->toDateString(), $end->toDateString()];
        $dt = [$start, $end->endOfDay()];

        $targets = Target::whereIn('user_id', $ids)->where('month', $start->format('Y-m'))->pluck('amount', 'user_id');
        $sales = Sale::whereIn('user_id', $ids)->whereBetween('sale_date', $span)->get(['user_id', 'lead_id', 'amount', 'status', 'sale_date']);
        $prev = Sale::whereIn('user_id', $ids)->where('status', 'confirmed')->whereBetween('sale_date', [$prevStart->toDateString(), $prevStart->endOfMonth()->toDateString()])
            ->selectRaw('user_id, SUM(amount) as total')->groupBy('user_id')->pluck('total', 'user_id');
        // Clients who had a confirmed order before this month are "repeat" customers.
        $earlier = Sale::where('status', 'confirmed')->where('sale_date', '<', $start->toDateString())->whereIn('lead_id', $sales->pluck('lead_id')->filter()->unique())
            ->distinct()->pluck('lead_id')->flip();
        $acts = LeadActivity::whereIn('user_id', $ids)->whereBetween('occurred_at', $dt)->selectRaw('user_id, type, COUNT(*) as n')->groupBy('user_id', 'type')->get()->groupBy('user_id');
        $added = Lead::whereIn('owner_id', $ids)->whereBetween('created_at', $dt)->selectRaw('owner_id, COUNT(*) as n')->groupBy('owner_id')->pluck('n', 'owner_id');
        $won = Lead::whereIn('owner_id', $ids)->whereBetween('won_at', $dt)->selectRaw('owner_id, COUNT(*) as n')->groupBy('owner_id')->pluck('n', 'owner_id');
        $lost = Lead::whereIn('owner_id', $ids)->whereBetween('lost_at', $dt)->selectRaw('owner_id, COUNT(*) as n')->groupBy('owner_id')->pluck('n', 'owner_id');
        $days = AttendanceDay::whereIn('user_id', $ids)->whereBetween('date', [$span[0], $span[1].' 23:59:59'])->get(['user_id', 'status'])->groupBy('user_id');
        $claims = ExpenseClaim::whereIn('user_id', $ids)->whereIn('status', ['approved', 'paid'])->whereBetween('date', $span)->selectRaw('user_id, SUM(amount) as total')->groupBy('user_id')->pluck('total', 'user_id');

        return $people->map(function (User $u) use ($targets, $sales, $prev, $earlier, $acts, $added, $won, $lost, $days, $claims) {
            $mine = $sales->where('user_id', $u->id);
            $confirmed = $mine->where('status', 'confirmed');
            $achieved = (float) $confirmed->sum('amount');
            $target = (float) ($targets[$u->id] ?? 0);
            $clients = $confirmed->pluck('lead_id')->filter()->unique();
            $last = (float) ($prev[$u->id] ?? 0);
            $a = $acts->get($u->id, collect())->pluck('n', 'type');
            $d = $days->get($u->id, collect());

            return (object) [
                'user' => $u, 'target' => $target, 'achieved' => $achieved,
                'percent' => $target > 0 ? round($achieved * 100 / $target) : null, 'gap' => max(0, $target - $achieved),
                'orders' => $confirmed->count(), 'pending' => (float) $mine->where('status', 'pending')->sum('amount'),
                'avg_order' => $confirmed->count() ? $achieved / $confirmed->count() : 0,
                'new_customers' => $clients->reject(fn ($id) => isset($earlier[$id]))->count(),
                'repeat_customers' => $clients->filter(fn ($id) => isset($earlier[$id]))->count(),
                'new_value' => (float) $confirmed->reject(fn ($s) => isset($earlier[$s->lead_id]))->sum('amount'),
                'repeat_value' => (float) $confirmed->filter(fn ($s) => isset($earlier[$s->lead_id]))->sum('amount'),
                'last_month' => $last, 'growth' => $last > 0 ? round(($achieved - $last) * 100 / $last) : null,
                'leads_added' => (int) ($added[$u->id] ?? 0), 'won' => (int) ($won[$u->id] ?? 0), 'lost' => (int) ($lost[$u->id] ?? 0),
                'calls' => (int) ($a['call'] ?? 0), 'visits' => (int) ($a['visit'] ?? 0),
                'present' => $d->whereIn('status', ['full_day', 'present'])->count() + $d->where('status', 'half_day')->count() / 2,
                'claims' => (float) ($claims[$u->id] ?? 0),
            ];
        })->values();
    }
}
