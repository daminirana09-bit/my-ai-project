<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmployeeProfile;
use App\Models\HrDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Employee master (admins): job details, personal, bank, IDs, salary and documents. */
class EmployeeController extends Controller
{
    /** Saved bank and ID numbers are never shown again; an empty box keeps them. */
    private const SECRET = ['bank' => ['account_no'], 'ids' => ['pan', 'uan', 'esic', 'aadhaar_last4']];

    public function index()
    {
        return view('hr.employees', ['people' => User::with('profile')->orderByDesc('active')->orderBy('name')->get()]);
    }

    public function edit(User $user)
    {
        return view('hr.employee', ['u' => $user, 'p' => EmployeeProfile::for($user), 'docs' => HrDocument::where('user_id', $user->id)->latest()->get(),
            'managers' => User::active()->whereIn('role', ['admin', 'manager'])->whereKeyNot($user->id)->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request, User $user)
    {
        $p = EmployeeProfile::for($user);
        $money = ['nullable', 'numeric', 'min:0', 'max:1000000'];
        $data = $request->validate([
            'emp_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\/]+$/', Rule::unique('employee_profiles', 'emp_code')->ignore($p->id)],
            'designation' => ['nullable', 'string', 'max:80'], 'department' => ['nullable', 'string', 'max:80'], 'headquarter' => ['nullable', 'string', 'max:80'],
            'employment_type' => ['required', Rule::in(array_keys(EmployeeProfile::TYPES))],
            'status' => ['required', Rule::in(array_keys(EmployeeProfile::STATUSES))],
            'date_of_joining' => ['nullable', 'date', 'before:+1 year'], 'date_of_birth' => ['nullable', 'date', 'before:-14 years'],
            'exit_date' => ['nullable', 'date', 'required_if:status,left'], 'gender' => ['nullable', Rule::in(['female', 'male', 'other'])],
            'personal.phone' => ['nullable', 'string', 'max:20'], 'personal.email' => ['nullable', 'email', 'max:120'], 'personal.address' => ['nullable', 'string', 'max:300'],
            'personal.emergency_name' => ['nullable', 'string', 'max:80'], 'personal.emergency_phone' => ['nullable', 'string', 'max:20'], 'personal.blood_group' => ['nullable', 'string', 'max:5'],
            'bank.account_name' => ['nullable', 'string', 'max:100'], 'bank.account_no' => ['nullable', 'regex:/^\d{6,20}$/'],
            'bank.ifsc' => ['nullable', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'], 'bank.bank_name' => ['nullable', 'string', 'max:80'],
            'ids.pan' => ['nullable', 'regex:/^[A-Za-z]{5}\d{4}[A-Za-z]$/'], 'ids.uan' => ['nullable', 'regex:/^\d{12}$/'], 'ids.esic' => ['nullable', 'regex:/^\d{10,17}$/'],
            'ids.aadhaar_last4' => ['nullable', 'regex:/^\d{4}$/'],
            'salary.basic' => $money, 'salary.hra' => $money, 'salary.conveyance' => $money, 'salary.special' => $money, 'salary.other' => $money,
            'salary.pt' => ['nullable', 'numeric', 'min:0', 'max:2500'], 'salary.tds' => $money,
            'manager_id' => ['nullable', 'integer'],
        ], ['bank.ifsc.regex' => __('IFSC looks like SBIN0001234.'), 'ids.pan.regex' => __('PAN looks like ABCDE1234F.'), 'ids.uan.regex' => __('UAN has 12 digits.'),
            'ids.aadhaar_last4.regex' => __('Only the last 4 digits of Aadhaar are kept.')]);

        $merge = function (string $group) use ($request, $p) {
            $old = $p->{$group} ?? [];
            $new = [];
            foreach ((array) $request->input($group, []) as $k => $v) {
                $v = is_string($v) ? trim($v) : $v;
                $new[$k] = in_array($k, self::SECRET[$group] ?? [], true) && ($v === null || $v === '') ? ($old[$k] ?? null) : ($v === '' ? null : $v);
            }

            return array_merge($old, $new);
        };
        $ids = $merge('ids');
        if (! empty($ids['pan'])) {
            $ids['pan'] = strtoupper($ids['pan']);
        }
        $bank = $merge('bank');
        if (! empty($bank['ifsc'])) {
            $bank['ifsc'] = strtoupper($bank['ifsc']);
        }
        $salary = [];
        foreach (['basic', 'hra', 'conveyance', 'special', 'other', 'pt', 'tds'] as $k) {
            $v = $request->input("salary.$k");
            $salary[$k] = $v === null || $v === '' ? null : round((float) $v, 2);
        }
        $salary['pf'] = $request->boolean('salary.pf');
        $salary['esi'] = $request->boolean('salary.esi');

        $p->fill([
            'emp_code' => $data['emp_code'] ?? null, 'designation' => $data['designation'] ?? null, 'department' => $data['department'] ?? null,
            'headquarter' => $data['headquarter'] ?? null, 'employment_type' => $data['employment_type'], 'status' => $data['status'],
            'date_of_joining' => $data['date_of_joining'] ?? null, 'date_of_birth' => $data['date_of_birth'] ?? null,
            'exit_date' => $data['exit_date'] ?? null, 'gender' => $data['gender'] ?? null,
            'personal' => $merge('personal'), 'bank' => $bank, 'ids' => $ids, 'salary' => $salary,
        ])->save();
        if ($request->has('manager_id')) {
            $mid = (int) $request->input('manager_id') ?: null;
            $user->forceFill(['manager_id' => $mid && User::whereIn('role', ['admin', 'manager'])->whereKey($mid)->whereKeyNot($user->id)->exists() ? $mid : null])->save();
        }
        AuditLog::record('hr.employee.update', "user:{$user->id}");

        return back()->with('ok', __('Employee details saved.'));
    }

    public function storeDocument(Request $request, User $user)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120'], 'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx']]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        abort_unless(isset(HrDocument::TYPES[$ext]), 422);
        $path = $file->storeAs('hr/docs', Str::random(40).'.'.$ext, 'local');
        HrDocument::create(['user_id' => $user->id, 'title' => $data['title'], 'path' => $path, 'extension' => $ext, 'size' => $file->getSize(), 'uploaded_by' => $request->user()->id]);
        AuditLog::record('hr.document.add', "user:{$user->id}");

        return back()->with('ok', __('Document saved.'));
    }

    public function document(HrDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        AuditLog::record('hr.document.open', "user:{$document->user_id}");

        return response()->download(Storage::disk('local')->path($document->path), Str::slug($document->title).'.'.$document->extension,
            ['Content-Type' => HrDocument::TYPES[$document->extension] ?? 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroyDocument(HrDocument $document)
    {
        Storage::disk('local')->delete($document->path);
        $document->delete();
        AuditLog::record('hr.document.delete', "user:{$document->user_id}");

        return back()->with('ok', __('Document deleted.'));
    }
}
