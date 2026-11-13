<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditController extends Controller
{
    public function __invoke()
    {
        return view('audit', ['logs' => AuditLog::with('user:id,name')->latest('id')->paginate(50)]);
    }
}
