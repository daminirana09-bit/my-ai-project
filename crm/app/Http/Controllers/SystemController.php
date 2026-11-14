<?php

namespace App\Http\Controllers;

use App\Services\SystemCheck;

class SystemController extends Controller
{
    public function __invoke(SystemCheck $check)
    {
        return view('system', ['checks' => $check->run()]);
    }
}
