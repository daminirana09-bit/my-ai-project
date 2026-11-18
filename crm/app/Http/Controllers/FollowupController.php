<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\FollowupLog;
use App\Models\FollowupRule;
use App\Services\WhatsApp\FollowupRunner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Automatic WhatsApp follow-up rules (people who may send campaigns). */
class FollowupController extends Controller
{
    private function authorizeUse(Request $request): void
    {
        abort_unless($request->user()->canManage() && $request->user()->allows('send_campaigns'), 403);
    }

    public function index(Request $request, FollowupRunner $runner)
    {
        $this->authorizeUse($request);
        $rules = FollowupRule::withCount(['logs as sent30' => fn ($q) => $q->where('status', 'sent')->where('sent_at', '>=', now()->subDays(30)),
            'logs as failed30' => fn ($q) => $q->where('status', 'failed')->where('sent_at', '>=', now()->subDays(30))])->orderBy('id')->get();

        return view('followups', [
            'rules' => $rules,
            'waiting' => $rules->mapWithKeys(fn ($r) => [$r->id => $r->active ? $runner->due($r)->count() : 0]),
            'replies' => $rules->mapWithKeys(fn ($r) => [$r->id => FollowupRunner::replies($r)]),
            'log' => FollowupLog::with('rule:id,name', 'lead:id,firm_name,city')->latest('sent_at')->limit(50)->get(),
            'open' => FollowupRunner::officeOpen(),
            'remaining' => Campaign::remainingToday(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeUse($request);
        $r = FollowupRule::create($this->validated($request) + ['created_by' => $request->user()->id, 'active' => true]);
        AuditLog::record('followup.add', "rule:{$r->id}");

        return redirect()->route('followups')->with('ok', __('Follow-up rule added. It runs every 15 minutes during office hours.'));
    }

    public function update(Request $request, FollowupRule $rule)
    {
        $this->authorizeUse($request);
        $rule->update($this->validated($request) + ['active' => $request->boolean('active')]);
        AuditLog::record('followup.update', "rule:{$rule->id}");

        return redirect()->route('followups')->with('ok', __('Saved.'));
    }

    public function destroy(Request $request, FollowupRule $rule)
    {
        $this->authorizeUse($request);
        $rule->delete();
        AuditLog::record('followup.delete', "rule:{$rule->id}");

        return redirect()->route('followups')->with('ok', __('Follow-up rule deleted.'));
    }

    public function runNow(Request $request, FollowupRunner $runner)
    {
        $this->authorizeUse($request);
        if (! FollowupRunner::officeOpen()) {
            return redirect()->route('followups')->withErrors(['run' => __('Follow-ups go out only during office hours on working days.')]);
        }
        $r = $runner->run();
        AuditLog::record('followup.run', null, $r);

        return redirect()->route('followups')->with('ok', __('Sent :s, failed :f, skipped :k.', ['s' => $r['sent'], 'f' => $r['failed'], 'k' => $r['skipped']]));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'trigger' => ['required', Rule::in(array_keys(FollowupRule::TRIGGERS))],
            'after' => ['required', 'integer', 'min:0', 'max:365'],
            'repeat_days' => ['required', 'integer', 'min:1', 'max:365'],
            'template_name' => ['nullable', 'regex:/^[a-z0-9_]{1,120}$/'],
            'template_language' => ['nullable', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'var' => ['array', 'max:5'],
            'var.*' => ['nullable', Rule::in(array_keys(Campaign::VARIABLES))],
            'text' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! filled($data['template_name'] ?? null) && ! filled($data['text'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['template_name' => __('Give an approved template, a message, or both.')]);
        }

        return ['name' => $data['name'], 'trigger' => $data['trigger'], 'after' => (int) $data['after'], 'repeat_days' => (int) $data['repeat_days'],
            'template_name' => $data['template_name'] ?? null, 'template_language' => $data['template_language'] ?? 'en',
            'variables' => array_values(array_filter($data['var'] ?? [])), 'text' => $data['text'] ?? null];
    }
}
