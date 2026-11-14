<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use Illuminate\Http\Request;

/** Admin decides what managers and sales reps may do. */
class RoleController extends Controller
{
    public function edit()
    {
        return view('roles', ['c' => CompanySetting::current()]);
    }

    public function update(Request $request)
    {
        $matrix = [];
        foreach (['manager', 'rep'] as $role) {
            foreach (array_keys(CompanySetting::PERMISSIONS) as $perm) {
                $matrix[$role][$perm] = $request->boolean("perm.$role.$perm");
            }
        }
        CompanySetting::current()->update(['permissions' => $matrix, 'reps_can_export_own_leads' => $matrix['rep']['export_leads']]);
        CompanySetting::forget();
        AuditLog::record('roles.update', null, $matrix);

        return back()->with('ok', __('Permissions saved.'));
    }
}
