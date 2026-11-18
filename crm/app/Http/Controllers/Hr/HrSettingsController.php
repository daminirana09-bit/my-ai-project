<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Holiday;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Holidays, leave types, allowances and statutory rates (admins). */
class HrSettingsController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        return view('hr.settings', ['co' => CompanySetting::current(), 'types' => LeaveType::ordered()->get(), 'year' => $year,
            'holidays' => Holiday::whereYear('date', $year)->orderBy('date')->get()]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (array_keys(CompanySetting::HR_DEFAULTS) as $k) {
            $rules[$k] = ['required', 'numeric', 'min:0', 'max:1000000'];
        }
        $data = $request->validate($rules);
        CompanySetting::current()->update(['hr_config' => array_map('floatval', $data)]);
        CompanySetting::forget();
        AuditLog::record('hr.settings');

        return back()->with('ok', __('HR settings saved.'));
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate(['date' => ['required', 'date', 'unique:holidays,date'], 'name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(array_keys(Holiday::TYPES))]]);
        Holiday::create($data);
        AuditLog::record('hr.holiday.add', $data['date']);

        return redirect()->route('hr.settings', ['year' => substr($data['date'], 0, 4)])->with('ok', __('Holiday added.'));
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();
        AuditLog::record('hr.holiday.delete', $holiday->date->toDateString());

        return back()->with('ok', __('Holiday removed.'));
    }

    /** National holidays for a year (fixed dates); festivals are added by hand as they change every year. */
    public function seedYear(Request $request)
    {
        $year = (int) $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100']])['year'];
        foreach (['01-26' => 'Republic Day', '08-15' => 'Independence Day', '10-02' => 'Gandhi Jayanti'] as $md => $name) {
            Holiday::firstOrCreate(['date' => "$year-$md"], ['name' => $name, 'type' => 'national']);
        }

        return redirect()->route('hr.settings', ['year' => $year])->with('ok', __('National holidays added for :y.', ['y' => $year]));
    }

    public function saveLeaveType(Request $request, ?LeaveType $type = null)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]+$/', Rule::unique('leave_types', 'code')->ignore($type?->id)],
            'name' => ['required', 'string', 'max:60'],
            'yearly_quota' => ['nullable', 'numeric', 'min:0', 'max:365'],
        ]);
        $values = ['code' => strtoupper($data['code']), 'name' => $data['name'], 'yearly_quota' => $data['yearly_quota'] ?? null,
            'paid' => $request->boolean('paid'), 'active' => $type ? $request->boolean('active') : true];
        $type ? $type->update($values) : LeaveType::create($values + ['sort' => (int) LeaveType::max('sort') + 1]);
        AuditLog::record('hr.leave_type', $values['code']);

        return back()->with('ok', __('Leave type saved.'));
    }
}
