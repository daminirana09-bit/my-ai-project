<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $list = $user->notifications()->latest()->limit(100)->get();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return view('notifications', ['list' => $list]);
    }
}
