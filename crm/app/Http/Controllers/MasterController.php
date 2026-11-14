<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MasterOption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin edits the company's own lists (lead sources, client types, lost reasons, product categories). */
class MasterController extends Controller
{
    public function index()
    {
        return view('masters', ['options' => MasterOption::orderBy('sort')->orderBy('value')->get()->groupBy('type')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(MasterOption::TYPES))],
            'value' => ['required', 'string', 'max:100', Rule::unique('master_options')->where('type', $request->type)],
        ]);
        MasterOption::create($data + ['sort' => (int) MasterOption::where('type', $data['type'])->max('sort') + 1]);
        MasterOption::forget();
        AuditLog::record('master.add', $data['type'], ['value' => $data['value']]);

        return back()->with('ok', __('Added.'));
    }

    public function update(Request $request, MasterOption $option)
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:100', Rule::unique('master_options')->where('type', $option->type)->ignore($option->id)],
        ]);
        $option->update($data + ['active' => $request->boolean('active')]);
        MasterOption::forget();
        AuditLog::record('master.update', $option->type, ['id' => $option->id]);

        return back()->with('ok', __('Saved.'));
    }
}
