<?php

namespace App\Http\Controllers;

use App\Models\AttendancePunch;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\User;
use App\Models\WaMessage;
use App\Support\Geo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Team tracking: what each person did in a day and where (only the places they checked in from:
 * punch in/out and client visits; the CRM never follows anyone in the background).
 * Each person sees their own day; managers their team; admins everyone.
 */
class TrackingController extends Controller
{
    /** A check-in further than this from the client's saved place is flagged. */
    public const FAR_METRES = 300;

    public function checkin(Request $request, Lead $lead)
    {
        $me = $request->user();
        abort_unless($lead->isVisibleTo($me), 404);
        $data = $request->validate(AttendanceController::locationRules() + ['note' => ['nullable', 'string', 'max:1000']]);
        if (! isset($data['lat'], $data['lng'])) {
            return back()->withErrors(['lat' => __('Location not received. Allow location for this site in your phone\'s browser, then check in again.')]);
        }
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];
        $acc = isset($data['accuracy']) ? (int) $data['accuracy'] : null;
        $distance = null;
        if ($lead->lat !== null && $lead->lng !== null) {
            $distance = Geo::metres((float) $lead->lat, (float) $lead->lng, $lat, $lng);
        } elseif ($acc === null || $acc <= 200) {
            $lead->forceFill(['lat' => $lat, 'lng' => $lng])->save(); // first good check-in: this is the client's place
        }
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $me->id, 'type' => 'visit', 'note' => $data['note'] ?? __('Visit check-in'),
            'occurred_at' => now(), 'lat' => $lat, 'lng' => $lng, 'accuracy' => $acc, 'distance_m' => $distance]);
        $lead->forceFill(['last_contact_at' => now()])->save();

        return back()->with($distance !== null && $distance > self::FAR_METRES ? 'warn' : 'ok', $distance !== null && $distance > self::FAR_METRES
            ? __('Checked in, but :d away from this client\'s saved place.', ['d' => self::distanceLabel($distance)])
            : __('Visit checked in.'));
    }

    /** Managers clear a wrongly saved client place; the next check-in saves it again. */
    public function resetPlace(Request $request, Lead $lead)
    {
        $me = $request->user();
        abort_unless($me->canManage() && $lead->isVisibleTo($me), 403);
        $lead->forceFill(['lat' => null, 'lng' => null])->save();
        AuditLog::record('lead.place_reset', "lead:{$lead->id}");

        return back()->with('ok', __('Client\'s place cleared. The next visit check-in saves it again.'));
    }

    public function team(Request $request)
    {
        $me = $request->user();
        abort_unless($me->canManage(), 403);
        $day = $this->day($request);
        $people = User::active()->visibleTo($me)->whereIn('role', ['rep', 'manager'])->orderBy('name')->get();
        $rows = $people->map(fn (User $u) => (object) (['user' => $u] + $this->summary($u, $day)));

        return view('tracking.team', ['day' => $day, 'rows' => $rows]);
    }

    public function person(Request $request, User $user)
    {
        $me = $request->user();
        abort_unless($me->id === $user->id || ($me->canManage() && $me->canSeeUser($user->id)), 404);
        $day = $this->day($request);
        $events = $this->events($user, $day);
        $points = $events->filter(fn ($e) => $e->lat !== null)->map(fn ($e) => [(float) $e->lat, (float) $e->lng])->values()->all();

        return view('tracking.person', ['person' => $user, 'day' => $day, 'events' => $events, 's' => $this->summary($user, $day),
            'route' => Geo::routeLink($points), 'km' => Geo::pathKm($points)]);
    }

    private function day(Request $request): CarbonImmutable
    {
        $d = (string) $request->query('date', today()->toDateString());

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? CarbonImmutable::parse($d)->startOfDay() : CarbonImmutable::today();
    }

    /** One person's numbers for a day. */
    private function summary(User $u, CarbonImmutable $day): array
    {
        $range = [$day, $day->endOfDay()];
        $punches = AttendancePunch::where('user_id', $u->id)->whereBetween('punched_at', $range)->orderBy('punched_at')->get();
        $acts = LeadActivity::where('user_id', $u->id)->whereBetween('occurred_at', $range)->get(['type', 'occurred_at', 'lat', 'lng', 'distance_m']);
        $visits = $acts->where('type', 'visit');
        $points = collect([$punches->first(), ...$visits->sortBy('occurred_at')->all(), $punches->where('type', 'out')->last()])
            ->filter(fn ($x) => $x && $x->lat !== null)->map(fn ($x) => [(float) $x->lat, (float) $x->lng])->values()->all();
        $times = collect([...$punches->pluck('punched_at'), ...$acts->pluck('occurred_at')])->filter();

        return [
            'in' => $punches->firstWhere('type', 'in'),
            'out' => $punches->where('type', 'out')->last(),
            'calls' => $acts->where('type', 'call')->count(),
            'visits' => $visits->count(),
            'far' => $visits->filter(fn ($v) => $v->distance_m !== null && $v->distance_m > self::FAR_METRES)->count(),
            'whatsapp' => WaMessage::where('user_id', $u->id)->where('direction', 'out')->whereBetween('sent_at', $range)->count(),
            'updates' => $acts->whereIn('type', ['note', 'status', 'whatsapp'])->count(),
            'leads' => Lead::where('created_by', $u->id)->whereBetween('created_at', $range)->count(),
            'quotes' => Quote::where('user_id', $u->id)->whereBetween('created_at', $range)->count(),
            'orders' => (float) Sale::where('user_id', $u->id)->whereDate('sale_date', $day->toDateString())->sum('amount'),
            'last' => $times->max(),
            'km' => Geo::pathKm($points),
        ];
    }

    /** Everything the person did, in time order, with places where known. */
    private function events(User $u, CarbonImmutable $day)
    {
        $range = [$day, $day->endOfDay()];
        $ev = collect();
        foreach (AttendancePunch::where('user_id', $u->id)->whereBetween('punched_at', $range)->get() as $p) {
            $ev->push((object) ['at' => $p->punched_at, 'kind' => $p->type === 'in' ? 'punch_in' : 'punch_out', 'title' => $p->type === 'in' ? __('Punched in') : __('Punched out'),
                'detail' => null, 'lead' => null, 'lat' => $p->lat, 'lng' => $p->lng, 'accuracy' => $p->accuracy, 'distance' => null]);
        }
        foreach (LeadActivity::with('lead:id,firm_name,city')->where('user_id', $u->id)->whereBetween('occurred_at', $range)->get() as $a) {
            $ev->push((object) ['at' => $a->occurred_at, 'kind' => $a->type, 'title' => __(LeadActivity::TYPES[$a->type] ?? $a->type).($a->outcome ? ' · '.__(LeadActivity::CALL_OUTCOMES[$a->outcome] ?? $a->outcome) : ''),
                'detail' => $a->note, 'lead' => $a->lead, 'lat' => $a->lat, 'lng' => $a->lng, 'accuracy' => $a->accuracy, 'distance' => $a->distance_m]);
        }
        foreach (WaMessage::with('conversation.lead:id,firm_name,city')->where('user_id', $u->id)->where('direction', 'out')->whereBetween('sent_at', $range)->get() as $m) {
            $ev->push((object) ['at' => $m->sent_at, 'kind' => 'wa', 'title' => __('WhatsApp sent'), 'detail' => mb_strimwidth((string) $m->body, 0, 120, '…'),
                'lead' => $m->conversation?->lead, 'lat' => null, 'lng' => null, 'accuracy' => null, 'distance' => null]);
        }
        foreach (Lead::where('created_by', $u->id)->whereBetween('created_at', $range)->get(['id', 'firm_name', 'city', 'created_at']) as $l) {
            $ev->push((object) ['at' => $l->created_at, 'kind' => 'lead', 'title' => __('New lead added'), 'detail' => null, 'lead' => $l, 'lat' => null, 'lng' => null, 'accuracy' => null, 'distance' => null]);
        }
        foreach (Quote::with('lead:id,firm_name,city')->where('user_id', $u->id)->whereBetween('created_at', $range)->get() as $q) {
            $ev->push((object) ['at' => $q->created_at, 'kind' => 'quote', 'title' => __('Quotation :n', ['n' => $q->number]), 'detail' => inr($q->grand_total), 'lead' => $q->lead, 'lat' => null, 'lng' => null, 'accuracy' => null, 'distance' => null]);
        }

        return $ev->sortBy(fn ($e) => $e->at->timestamp)->values();
    }

    public static function distanceLabel(?int $m): string
    {
        return $m === null ? '—' : ($m >= 1000 ? round($m / 1000, 1).' km' : $m.' m');
    }
}
