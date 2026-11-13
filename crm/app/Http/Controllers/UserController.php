<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Admin adds the team, sets roles, managers and personal office timing. */
class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::with('manager:id,name')->withCount('leads')->orderByDesc('active')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'rep', 'active' => true, 'locale' => 'hing']), 'managers' => $this->managers()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $user = User::create($data);
        AuditLog::record('user.create', "user:{$user->id}", ['role' => $user->role]);

        return redirect()->route('users.index')->with('ok', __(':name added.', ['name' => $user->name]));
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user, 'managers' => $this->managers($user->id), 'others' => User::active()->where('id', '!=', $user->id)->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->id === $request->user()->id) {
            unset($data['role'], $data['active']); // nobody can lock themselves out
        }
        $user->update($data);

        // Someone leaving: their leads move to another person in one step. The rep takes nothing.
        if (! $user->active && $request->filled('move_leads_to')) {
            $to = User::active()->findOrFail((int) $request->move_leads_to);
            $n = Lead::where('owner_id', $user->id)->update(['owner_id' => $to->id]);
            AuditLog::record('user.leads_moved', "user:{$user->id}", ['to' => $to->id, 'leads' => $n]);
        }
        if ($request->boolean('reset_2fa')) {
            $user->forceFill(['two_factor_secret' => null])->save();
        }
        AuditLog::record('user.update', "user:{$user->id}");

        return redirect()->route('users.index')->with('ok', __('Saved.'));
    }

    private function managers(?int $except = null)
    {
        return User::active()->whereIn('role', ['admin', 'manager'])->when($except, fn ($q) => $q->where('id', '!=', $except))->orderBy('name')->get(['id', 'name']);
    }

    private function validated(Request $request, ?User $user): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'locale' => ['required', Rule::in(array_keys(User::LOCALES))],
            'active' => ['nullable', 'boolean'],
            'office_start' => ['nullable', 'date_format:H:i'],
            'office_end' => ['nullable', 'date_format:H:i'],
            'lunch_start' => ['nullable', 'date_format:H:i'],
            'lunch_end' => ['nullable', 'date_format:H:i'],
            'password' => [$user ? 'nullable' : 'required', Password::min(10)->letters()->numbers()],
        ]);
        $data['email'] = strtolower($data['email']);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
