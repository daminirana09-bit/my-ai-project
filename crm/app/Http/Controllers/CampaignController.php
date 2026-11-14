<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaign;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Lead;
use App\Models\MasterOption;
use App\Models\User;
use App\Models\WhatsappNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Marketing: an approved WhatsApp template sent to a chosen group of clients. */
class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        abort_unless($me->allows('send_campaigns'), 403);

        return view('campaigns.index', [
            'campaigns' => Campaign::with(['number:id,label', 'creator:id,name'])->withCount([
                'recipients as sent' => fn ($q) => $q->whereIn('status', ['sent', 'delivered', 'read']),
                'recipients as read' => fn ($q) => $q->where('status', 'read'),
                'recipients as failed' => fn ($q) => $q->whereIn('status', ['failed', 'skipped']),
            ])->when(! $me->isAdmin(), fn ($q) => $q->where('created_by', $me->id))->latest()->paginate(20),
        ]);
    }

    public function create(Request $request)
    {
        $me = $request->user();
        abort_unless($me->allows('send_campaigns'), 403);

        return view('campaigns.form', [
            'numbers' => WhatsappNumber::visibleTo($me)->where('active', true)->orderBy('label')->get(),
            'owners' => User::active()->visibleTo($me)->orderBy('name')->get(['id', 'name']),
            'sources' => MasterOption::values('source'), 'types' => MasterOption::values('client_type'),
            'count' => $request->has('preview') ? $this->audience($request, $me)->count() : null,
        ]);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        abort_unless($me->allows('send_campaigns'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'whatsapp_number_id' => ['required', 'integer'],
            'template_name' => ['required', 'regex:/^[a-z0-9_]{1,120}$/'],
            'template_language' => ['required', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'var' => ['array', 'max:5'],
            'var.*' => ['nullable', Rule::in(array_keys(Campaign::VARIABLES))],
        ]);
        $number = WhatsappNumber::visibleTo($me)->where('active', true)->findOrFail((int) $data['whatsapp_number_id']);
        $leadIds = $this->audience($request, $me)->pluck('id');
        if ($leadIds->isEmpty()) {
            return back()->withInput()->withErrors(['name' => __('No clients match these filters.')]);
        }
        $campaign = Campaign::create([
            'name' => $data['name'], 'whatsapp_number_id' => $number->id, 'template_name' => $data['template_name'], 'template_language' => $data['template_language'],
            'filters' => $this->filters($request), 'variables' => array_values(array_filter($data['var'] ?? [])), 'status' => 'sending',
            'total' => $leadIds->count(), 'created_by' => $me->id, 'started_at' => now(),
        ]);
        foreach ($leadIds->chunk(500) as $chunk) {
            CampaignRecipient::insert($chunk->map(fn ($id) => ['campaign_id' => $campaign->id, 'lead_id' => $id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()])->all());
        }
        SendCampaign::dispatch($campaign->id);
        AuditLog::record('campaign.start', "campaign:{$campaign->id}", ['recipients' => $leadIds->count()]);

        return redirect()->route('campaigns.show', $campaign)->with('ok', __('Campaign started for :n clients. Messages go out in the background.', ['n' => $leadIds->count()]));
    }

    public function show(Request $request, Campaign $campaign)
    {
        $me = $request->user();
        abort_unless($me->isAdmin() || $campaign->created_by === $me->id, 404);

        return view('campaigns.show', [
            'campaign' => $campaign->load('number'),
            'counts' => $campaign->recipients()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'rows' => $campaign->recipients()->with('lead:id,firm_name,city')->latest('updated_at')->limit(200)->get(),
        ]);
    }

    private function filters(Request $request): array
    {
        return array_filter($request->only(['status', 'temperature', 'client_type', 'source', 'owner_id', 'no_order_days']), fn ($v) => filled($v));
    }

    /** Leads the sender may see that match the filters, have a phone number and have not opted out. */
    private function audience(Request $request, User $me): Builder
    {
        $f = $this->filters($request);

        return Lead::visibleTo($me)->whereNotNull('phone_hash')->where('whatsapp_opt_out', false)
            ->when(isset($f['status']) && isset(Lead::STATUSES[$f['status']]), fn ($q) => $q->where('status', $f['status']))
            ->when(isset($f['temperature']) && isset(Lead::TEMPERATURES[$f['temperature']]), fn ($q) => $q->where('temperature', $f['temperature']))
            ->when(isset($f['client_type']), fn ($q) => $q->where('client_type', $f['client_type']))
            ->when(isset($f['source']), fn ($q) => $q->where('source', $f['source']))
            ->when(isset($f['owner_id']), fn ($q) => $q->where('owner_id', (int) $f['owner_id']))
            ->when(isset($f['no_order_days']) && (int) $f['no_order_days'] > 0, fn ($q) => $q->where('status', 'won')
                ->whereDoesntHave('sales', fn ($s) => $s->where('status', 'confirmed')->where('sale_date', '>=', now()->subDays((int) $f['no_order_days'])->toDateString())));
    }
}
