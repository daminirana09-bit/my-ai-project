<?php

namespace App\Http\Controllers;

use App\Jobs\SendCampaign;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Lead;
use App\Models\MasterOption;
use App\Models\ProductFile;
use App\Models\Sale;
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
                'recipients as replied' => fn ($q) => $q->whereNotNull('replied_at'),
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
            'tags' => $this->tags($me), 'files' => ProductFile::whereIn('extension', ['jpg', 'jpeg', 'png', 'pdf'])->orderBy('title')->get(['id', 'title', 'extension']),
            'remaining' => Campaign::remainingToday(), 'limit' => Campaign::dailyLimit(),
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
            'scheduled_at' => ['nullable', 'date', 'after:now', 'before:+60 days'],
            'header_file_id' => ['nullable', 'integer'],
            'header_url' => ['nullable', 'url:https', 'max:500'],
            'header_type' => ['nullable', Rule::in(['image', 'document'])],
        ]);
        [$headerType, $headerUrl] = $this->header($data);
        if (($data['header_file_id'] ?? null) || ($data['header_url'] ?? null)) {
            if (! $headerType) {
                return back()->withInput()->withErrors(['header_url' => __('Use a JPG/PNG photo or a PDF on an https address (the CRM itself must open on https).')]);
            }
        }
        $number = WhatsappNumber::visibleTo($me)->where('active', true)->findOrFail((int) $data['whatsapp_number_id']);
        $leadIds = $this->audience($request, $me)->pluck('id');
        if ($leadIds->isEmpty()) {
            return back()->withInput()->withErrors(['name' => __('No clients match these filters.')]);
        }
        $at = filled($data['scheduled_at'] ?? null) ? \Illuminate\Support\Carbon::parse($data['scheduled_at']) : null;
        $campaign = Campaign::create([
            'name' => $data['name'], 'whatsapp_number_id' => $number->id, 'template_name' => $data['template_name'], 'template_language' => $data['template_language'],
            'filters' => $this->filters($request), 'variables' => array_values(array_filter($data['var'] ?? [])), 'status' => $at ? 'scheduled' : 'sending',
            'total' => $leadIds->count(), 'created_by' => $me->id, 'started_at' => $at ?? now(), 'scheduled_at' => $at,
            'header_type' => $headerType, 'header_url' => $headerUrl,
        ]);
        foreach ($leadIds->chunk(500) as $chunk) {
            CampaignRecipient::insert($chunk->map(fn ($id) => ['campaign_id' => $campaign->id, 'lead_id' => $id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()])->all());
        }
        if (! $at) {
            SendCampaign::dispatch($campaign->id); // a scheduled one is started by the minute cron
        }
        AuditLog::record('campaign.start', "campaign:{$campaign->id}", ['recipients' => $leadIds->count(), 'at' => $at?->toIso8601String()]);

        return redirect()->route('campaigns.show', $campaign)->with('ok', $at
            ? __('Campaign for :n clients will start on :d.', ['n' => $leadIds->count(), 'd' => $at->format('d M Y, g:i A')])
            : __('Campaign started for :n clients. Messages go out in the background.', ['n' => $leadIds->count()]));
    }

    public function show(Request $request, Campaign $campaign)
    {
        $me = $request->user();
        abort_unless($me->isAdmin() || $campaign->created_by === $me->id, 404);

        $since = ($campaign->started_at ?? $campaign->created_at)->toDateString();
        $leadIds = $campaign->recipients()->whereIn('status', ['sent', 'delivered', 'read'])->select('lead_id');
        $sales = Sale::where('status', 'confirmed')->where('sale_date', '>=', $since)->whereIn('lead_id', $leadIds);

        return view('campaigns.show', [
            'campaign' => $campaign->load('number'),
            'counts' => $campaign->recipients()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'replied' => $campaign->recipients()->whereNotNull('replied_at')->count(),
            'converted' => (clone $sales)->distinct()->count('lead_id'),
            'revenue' => (float) (clone $sales)->sum('amount'),
            'remaining' => Campaign::remainingToday(),
            'rows' => $campaign->recipients()->with('lead:id,firm_name,city')->when($request->query('show') === 'replied', fn ($q) => $q->whereNotNull('replied_at'))
                ->when($request->query('show') === 'failed', fn ($q) => $q->whereIn('status', ['failed', 'skipped']))->latest('updated_at')->limit(200)->get(),
        ]);
    }

    /** Pause, resume, cancel, or send again to those that failed. */
    public function control(Request $request, Campaign $campaign, string $action)
    {
        $me = $request->user();
        abort_unless($me->allows('send_campaigns') && ($me->isAdmin() || $campaign->created_by === $me->id), 404);
        $msg = match ($action) {
            'pause' => $campaign->isRunning() ? tap(__('Campaign paused.'), fn () => $campaign->update(['status' => 'paused'])) : null,
            'resume' => $campaign->status === 'paused' ? tap(__('Campaign resumed.'), function () use ($campaign) {
                $campaign->update(['status' => $campaign->scheduled_at?->isFuture() ? 'scheduled' : 'sending']);
                SendCampaign::dispatch($campaign->id);
            }) : null,
            'cancel' => in_array($campaign->status, ['scheduled', 'sending', 'limit', 'paused'], true) ? tap(__('Campaign cancelled. Clients not reached yet will not get it.'), function () use ($campaign) {
                $campaign->update(['status' => 'cancelled', 'finished_at' => now()]);
                $campaign->recipients()->where('status', 'pending')->update(['status' => 'skipped', 'error' => 'Cancelled']);
            }) : null,
            'retry' => $campaign->recipients()->where('status', 'failed')->exists() && ! in_array($campaign->status, ['cancelled'], true) ? tap(__('Sending again to those that failed.'), function () use ($campaign) {
                $campaign->recipients()->where('status', 'failed')->update(['status' => 'pending', 'error' => null, 'wamid' => null]);
                $campaign->update(['status' => 'sending', 'finished_at' => null]);
                SendCampaign::dispatch($campaign->id);
            }) : null,
            default => abort(404),
        };
        if ($msg === null) {
            return back()->withErrors(['campaign' => __('Not possible in this state.')]);
        }
        AuditLog::record('campaign.'.$action, "campaign:{$campaign->id}");

        return back()->with('ok', $msg);
    }

    private function filters(Request $request): array
    {
        return array_filter($request->only(['status', 'temperature', 'client_type', 'source', 'owner_id', 'no_order_days', 'tag', 'city', 'language']), fn ($v) => filled($v));
    }

    /** Lead tags in use, for the "who gets it" filter. */
    private function tags(User $me): array
    {
        return Lead::visibleTo($me)->whereNotNull('tags')->limit(5000)->pluck('tags')->flatten()->filter(fn ($t) => is_string($t) && $t !== '')
            ->countBy()->sortDesc()->keys()->take(100)->sort()->values()->all();
    }

    /** @return array{0:?string,1:?string} the header type and its https address */
    private function header(array $data): array
    {
        if ($id = $data['header_file_id'] ?? null) {
            $file = ProductFile::whereIn('extension', ['jpg', 'jpeg', 'png', 'pdf'])->find($id);
            if (! $file) {
                return [null, null];
            }
            if (! $file->shareActive() || $file->share_expires_at->lt(now()->addDays(25))) {
                $file->share_token = $file->shareActive() ? $file->share_token : \Illuminate\Support\Str::random(40);
                $file->share_expires_at = now()->addDays(30); // WhatsApp fetches it while sending
                $file->save();
            }

            $url = route('catalogue.shared', $file->share_token);

            return str_starts_with($url, 'https://') ? [$file->extension === 'pdf' ? 'document' : 'image', $url] : [null, null]; // WhatsApp fetches only https
        }
        if ($url = $data['header_url'] ?? null) {
            $type = $data['header_type'] ?? (preg_match('/\.pdf($|\?)/i', $url) ? 'document' : (preg_match('/\.(jpe?g|png)($|\?)/i', $url) ? 'image' : null));

            return [$type, $type ? $url : null];
        }

        return [null, null];
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
            ->when(isset($f['tag']), fn ($q) => $q->whereJsonContains('tags', (string) $f['tag']))
            ->when(isset($f['city']), fn ($q) => $q->where('city', 'like', mb_substr((string) $f['city'], 0, 60)))
            ->when(isset($f['language']) && isset(\App\Services\WhatsApp\LanguageDetector::NAMES[$f['language']]), fn ($q) => $q->where('language', $f['language']))
            ->when(isset($f['no_order_days']) && (int) $f['no_order_days'] > 0, fn ($q) => $q->where('status', 'won')
                ->whereDoesntHave('sales', fn ($s) => $s->where('status', 'confirmed')->where('sale_date', '>=', now()->subDays((int) $f['no_order_days'])->toDateString())));
    }
}
