<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Outside the "web" group: no session, no CSRF, not limited to office IPs. Each door checks its own secret.
Route::get('/webhook/whatsapp', [WebhookController::class, 'verify'])->middleware('throttle:30,1');
Route::post('/webhook/whatsapp', [WebhookController::class, 'receive'])->middleware('throttle:600,1');
Route::post('/webhook/lead/{token}', [WebhookController::class, 'leadCapture'])->middleware('throttle:20,1')->where('token', '[A-Za-z0-9]{20,64}');
