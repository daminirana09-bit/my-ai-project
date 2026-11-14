<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function edit()
    {
        return view('automations', ['c' => CompanySetting::current(), 'reps' => User::active()->where('role', 'rep')->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request)
    {
        $data = [];
        foreach (array_keys(CompanySetting::AUTOMATIONS) as $k) {
            $data[$k] = $request->boolean($k);
        }
        $data['assign_pool'] = collect($request->input('assign_pool', []))->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => User::active()->where('role', 'rep')->whereKey($id)->exists())->values()->all();
        CompanySetting::current()->update(['automations' => $data]);
        CompanySetting::forget();
        AuditLog::record('automations.update', null, $data);

        return back()->with('ok', __('Automations saved.'));
    }
}
