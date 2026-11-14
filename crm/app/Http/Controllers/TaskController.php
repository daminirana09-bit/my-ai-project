<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $view = $request->query('view', 'mine');
        $status = $request->query('status');
        $tasks = Task::visibleTo($me)
            ->when($view === 'mine' || ! $me->canManage(), fn ($q) => $q->where('assigned_to', $me->id))
            ->when($view === 'given', fn ($q) => $q->where('created_by', $me->id)->where('assigned_to', '!=', $me->id))
            ->when(isset(Task::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->with(['assignee:id,name', 'creator:id,name', 'lead:id,firm_name'])
            ->orderByRaw("CASE status WHEN 'done' THEN 1 ELSE 0 END")->orderByRaw('due_on IS NULL')->orderBy('due_on')->latest('id')
            ->paginate(40)->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks, 'view' => $me->canManage() ? $view : 'mine', 'status' => $status,
            'people' => $this->assignable($me),
            'leads' => Lead::visibleTo($me)->open()->orderBy('firm_name')->limit(500)->get(['id', 'firm_name']),
            'focus' => (int) $request->query('focus'),
        ]);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $this->validated($request);
        $data['assigned_to'] = $this->assigneeFor($me, $data['assigned_to'] ?? null);
        if (! empty($data['lead_id'])) {
            abort_unless(Lead::visibleTo($me)->whereKey($data['lead_id'])->exists(), 404);
        }
        $task = Task::create($data + ['created_by' => $me->id, 'status' => 'pending']);
        AuditLog::record('task.create', "task:{$task->id}");

        return back()->with('ok', __('Task added.'));
    }

    public function status(Request $request, Task $task)
    {
        $me = $request->user();
        abort_unless($task->isVisibleTo($me), 404);
        $s = $request->validate(['status' => ['required', Rule::in(array_keys(Task::STATUSES))]])['status'];
        $task->update(['status' => $s, 'completed_at' => $s === 'done' ? now() : null]);

        return back()->with('ok', __('Task updated.'));
    }

    public function destroy(Request $request, Task $task)
    {
        $me = $request->user();
        abort_unless($task->isVisibleTo($me) && ($me->isAdmin() || (int) $task->created_by === $me->id), 403);
        $task->delete();
        AuditLog::record('task.delete', "task:{$task->id}");

        return back()->with('ok', __('Task deleted.'));
    }

    /** Without the "assign_tasks" permission a person can only give tasks to themselves. */
    private function assigneeFor(User $me, $id): int
    {
        if (! $id || (int) $id === $me->id || ! $me->allows('assign_tasks')) {
            return $me->id;
        }
        abort_unless($me->canSeeUser($id) && User::active()->whereKey($id)->exists(), 404);

        return (int) $id;
    }

    private function assignable(User $me)
    {
        return $me->allows('assign_tasks') ? User::active()->visibleTo($me)->orderBy('name')->get(['id', 'name']) : collect([$me]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'due_on' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],
        ]);
    }
}
