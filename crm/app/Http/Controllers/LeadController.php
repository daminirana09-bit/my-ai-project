<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\ClashDetector;
use App\Services\LeadExporter;
use App\Services\LeadImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function __construct(private ClashDetector $clashes) {}

    private function filtered(Request $request)
    {
        $me = $request->user();

        return Lead::visibleTo($me)
            ->when($request->filled('status') && isset(Lead::STATUSES[$request->status]), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->source))
            ->when($request->filled('temperature') && isset(Lead::TEMPERATURES[$request->temperature]), fn ($q) => $q->where('temperature', $request->temperature))
            ->when($request->boolean('stale'), fn ($q) => $q->stale())
            ->when($me->canManage() && $request->filled('owner'), fn ($q) => $q->where('owner_id', (int) $request->owner))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->q).'%';
                $hash = Lead::hashPhone($request->q);
                $isEmail = str_contains($request->q, '@');
                $q->where(fn ($w) => $w->where('firm_name', 'like', $term)->orWhere('contact_name', 'like', $term)
                    ->orWhere('city', 'like', $term)->orWhere('gstin', 'like', $term)
                    ->when(! $isEmail && $hash && strlen((string) Lead::normalizePhone($request->q)) >= 10, fn ($x) => $x->orWhere('phone_hash', $hash))
                    ->when($isEmail, fn ($x) => $x->orWhere('email_hash', Lead::hashEmail($request->q))));
            })
            ->when($request->boolean('due'), fn ($q) => $q->whereNotIn('status', ['won', 'lost'])->whereDate('next_follow_up_on', '<=', today()));
    }

    public function index(Request $request)
    {
        $me = $request->user();
        $counts = Lead::visibleTo($me)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $temps = Lead::visibleTo($me)->open()->whereNotNull('temperature')->selectRaw('temperature, COUNT(*) as n')->groupBy('temperature')->pluck('n', 'temperature');

        return view('leads.index', [
            'leads' => $this->filtered($request)->with('owner:id,name')->latest('updated_at')->paginate(25)->withQueryString(),
            'counts' => $counts,
            'temps' => $temps,
            'owners' => $me->canManage() ? User::active()->visibleTo($me)->orderBy('name')->get(['id', 'name']) : collect(),
            'canExport' => $this->canExport($me),
        ]);
    }

    public function create(Request $request)
    {
        return view('leads.form', ['lead' => new Lead(['status' => 'new']), 'owners' => $this->assignable($request->user())]);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $this->validated($request);
        $owner = $this->ownerFrom($request, $me);

        if ($existing = $this->clashes->findExisting($data['phone'] ?? null, $data['gstin'] ?? null)) {
            return $this->handleDuplicate($existing, $owner, 'added_again');
        }

        $lead = Lead::create($data + ['owner_id' => $owner->id, 'created_by' => $me->id, 'created_via' => 'manual']);
        AuditLog::record('lead.create', "lead:{$lead->id}");

        return redirect()->route('leads.show', $lead)->with('ok', __('Lead added.'));
    }

    public function show(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);

        return view('leads.show', [
            'lead' => $lead->load(['owner', 'activities.user', 'tasks.assignee', 'sales', 'quotes', 'conversations', 'clashes' => fn ($q) => $q->where('status', 'open')->with('otherUser')]),
            'owners' => $this->assignable($request->user()),
        ]);
    }

    public function edit(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);

        return view('leads.form', ['lead' => $lead, 'owners' => $this->assignable($request->user())]);
    }

    public function update(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $data = $this->validated($request);

        // One client = one lead. Changing the phone/GSTIN to another lead's is never allowed;
        // if that lead belongs to someone else, it is also reported as a clash.
        $existing = $this->clashes->findExisting($data['phone'] ?? null, $data['gstin'] ?? null, $lead->id);
        if ($existing) {
            if ($existing->owner_id && (int) $existing->owner_id !== (int) $lead->owner_id && $lead->owner) {
                $this->clashes->report($existing, $lead->owner, 'added_again', __('Phone or GSTIN changed on another lead'));
            }

            return back()->withInput()->withErrors(['phone' => __('Another lead already has this phone number or GSTIN.')]);
        }
        $oldStatus = $lead->status;
        $lead->update($data);
        if ($oldStatus !== $lead->status) {
            $this->log($lead, $request->user(), 'status', null, Lead::STATUSES[$oldStatus].' → '.Lead::STATUSES[$lead->status]);
        }

        return redirect()->route('leads.show', $lead)->with('ok', __('Saved.'));
    }

    /** Log a call, WhatsApp, visit or note; optionally change status and next follow-up. */
    public function activity(Request $request, Lead $lead)
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validate([
            'type' => ['required', Rule::in(['call', 'whatsapp', 'visit', 'note'])],
            'outcome' => ['nullable', Rule::in(array_keys(LeadActivity::CALL_OUTCOMES))],
            'note' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(array_keys(Lead::STATUSES))],
            'next_follow_up_on' => ['nullable', 'date'],
        ]);
        $this->log($lead, $request->user(), $data['type'], $data['outcome'] ?? null, $data['note'] ?? null);

        $changes = ['last_contact_at' => now()];
        if (! empty($data['next_follow_up_on'])) {
            $changes['next_follow_up_on'] = $data['next_follow_up_on'];
        }
        if (! empty($data['status']) && $data['status'] !== $lead->status) {
            $this->log($lead, $request->user(), 'status', null, Lead::STATUSES[$lead->status].' → '.Lead::STATUSES[$data['status']]);
            $changes['status'] = $data['status'];
        }
        $lead->update($changes);

        return back()->with('ok', __('Saved.'));
    }

    public function assign(Request $request, Lead $lead)
    {
        $me = $request->user();
        abort_unless($me->canManage() && $lead->isVisibleTo($me), 403);
        $to = User::active()->visibleTo($me)->findOrFail((int) $request->input('owner_id'));
        $from = $lead->owner?->name;
        $lead->update(['owner_id' => $to->id]);
        $this->log($lead, $me, 'assign', null, ($from ?: '—').' → '.$to->name);
        AuditLog::record('lead.assign', "lead:{$lead->id}", ['to' => $to->id]);

        return back()->with('ok', __('Lead assigned to :name.', ['name' => $to->name]));
    }

    public function export(Request $request, LeadExporter $exporter)
    {
        $me = $request->user();
        abort_unless($this->canExport($me), 403, __('Export is turned off for your role. Ask your admin.'));
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $query = $this->filtered($request);
        $count = (clone $query)->count();
        AuditLog::record('lead.export', $format, ['rows' => $count, 'filters' => $request->only(['status', 'source', 'owner', 'q'])]);

        $name = 'leads-'.now()->format('Y-m-d-His').'.'.$format;

        return response()->download($exporter->write($query, $format), $name)->deleteFileAfterSend();
    }

    public function importForm(Request $request)
    {
        abort_unless($request->user()->allows('import_leads'), 403);
        return view('leads.import', ['owners' => $this->assignable($request->user())]);
    }

    public function import(Request $request, LeadImporter $importer)
    {
        $me = $request->user();
        abort_unless($me->allows('import_leads'), 403);
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt'],
            'owner_id' => ['nullable', 'integer'],
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $assignTo = $me->canManage() && $request->filled('owner_id')
            ? User::active()->visibleTo($me)->findOrFail((int) $request->owner_id) : null;

        $result = $importer->import($file->getRealPath(), $ext, $me, $assignTo);
        AuditLog::record('lead.import', $file->getClientOriginalName(), collect($result)->except('errors')->all());

        return redirect()->route('leads.import')->with('import', $result);
    }

    public function template(LeadExporter $exporter)
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        $w = new \OpenSpout\Writer\XLSX\Writer;
        $w->openToFile($path);
        $w->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Firm name', 'Contact name', 'Phone', 'Email', 'City', 'State', 'GSTIN', 'Source', 'Client type', 'Requirement', 'Status', 'Expected value', 'Salesperson', 'Next follow-up', 'Notes']));
        $w->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Sample Pharma', 'Ravi Kumar', '9876543210', '', 'Indore', 'Madhya Pradesh', '', 'IndiaMART', 'PCD Franchise', 'PCD for 40 products', 'New', 500000, '', '', 'Delete this sample row']));
        $w->close();

        return response()->download($path, 'leads-import-template.xlsx')->deleteFileAfterSend();
    }

    // ---- helpers ----

    private function handleDuplicate(Lead $existing, User $owner, string $reason)
    {
        if ((int) $existing->owner_id === $owner->id) {
            return redirect()->route('leads.show', $existing)->with('ok', __('This client is already in your leads.'));
        }
        if ($existing->owner_id === null) {
            $existing->update(['owner_id' => $owner->id]);

            return redirect()->route('leads.show', $existing)->with('ok', __('This client was unassigned. It is now yours.'));
        }
        $this->clashes->report($existing, $owner, $reason);
        if ($existing->isVisibleTo(request()->user())) {
            return redirect()->route('leads.show', $existing)->with('warn', __('This client already exists and belongs to :name. Admin and managers have been told about the clash.', ['name' => $existing->owner?->name]));
        }

        // Rule 1: a rep is not told whose client it is or anything about it.
        return redirect()->route('leads.index')->with('warn', __('Another salesperson is already handling this client. Your admin and manager have been told and will decide who keeps it.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'firm_name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'gstin' => ['nullable', 'string', 'max:20'],
            // Lists come from Masters; any earlier value stays valid.
            'source' => ['nullable', 'string', 'max:60'],
            'client_type' => ['nullable', 'string', 'max:60'],
            'temperature' => ['nullable', Rule::in(array_keys(Lead::TEMPERATURES))],
            'requirement' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Lead::STATUSES))],
            'expected_value' => ['nullable', 'numeric', 'min:0'],
            'next_follow_up_on' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function ownerFrom(Request $request, User $me): User
    {
        if ($me->isRep() || ! $request->filled('owner_id')) {
            return $me;
        }

        return User::active()->visibleTo($me)->findOrFail((int) $request->owner_id);
    }

    private function assignable(User $me)
    {
        return $me->canManage() ? User::active()->visibleTo($me)->orderBy('name')->get(['id', 'name']) : collect();
    }

    private function authorizeLead(Request $request, Lead $lead): void
    {
        abort_unless($lead->isVisibleTo($request->user()), 404); // 404, so a rep cannot even learn the lead exists
    }

    private function canExport(User $me): bool
    {
        return $me->allows('export_leads');
    }

    private function log(Lead $lead, User $by, string $type, ?string $outcome, ?string $note): void
    {
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $by->id, 'type' => $type, 'outcome' => $outcome, 'note' => $note, 'occurred_at' => now()]);
    }
}
