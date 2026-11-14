<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Where leads come from, how many convert, month by month, and per salesperson. */
class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        $me = $request->user();
        abort_unless($me->allows('view_analytics'), 403);
        $leads = Lead::visibleTo($me);

        $bySource = (clone $leads)->selectRaw("COALESCE(source, '—') as src, COUNT(*) as total, SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won")
            ->groupBy('src')->orderByDesc('total')->get();

        $months = collect(range(5, 0))->map(fn ($i) => CarbonImmutable::now()->startOfMonth()->subMonths($i));
        $ids = $me->visibleUserIds();
        $trend = $months->map(function (CarbonImmutable $m) use ($leads, $ids) {
            $range = [$m->toDateString(), $m->endOfMonth()->toDateString().' 23:59:59'];

            return (object) [
                'label' => $m->format('M'),
                'added' => (clone $leads)->whereBetween('created_at', $range)->count(),
                'sales' => (float) Sale::where('status', 'confirmed')->when($ids !== null, fn ($q) => $q->whereIn('user_id', $ids))
                    ->whereBetween('sale_date', [$m->toDateString(), $m->endOfMonth()->toDateString()])->sum('amount'),
            ];
        });

        $people = $me->isRep() ? collect([$me]) : User::active()->visibleTo($me)->orderBy('name')->get();
        $perUser = Lead::whereIn('owner_id', $people->pluck('id'))
            ->selectRaw("owner_id, COUNT(*) as total, SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won, SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost")
            ->groupBy('owner_id')->get()->keyBy('owner_id');

        return view('analytics', [
            'bySource' => $bySource,
            'trend' => $trend,
            'statusCounts' => (clone $leads)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'tempCounts' => (clone $leads)->open()->selectRaw("COALESCE(temperature, 'none') as t, COUNT(*) as n")->groupBy('t')->pluck('n', 't'),
            'people' => $people,
            'perUser' => $perUser,
        ]);
    }
}
