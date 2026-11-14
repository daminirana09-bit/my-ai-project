<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WaConversation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Half day, leave or meeting: while away, WhatsApp cover replies to clients on this person's number. */
class AvailabilityController extends Controller
{
    public function update(Request $request, ?User $user = null)
    {
        $me = $request->user();
        $user ??= $me;
        abort_unless($user->id === $me->id || ($me->canManage() && $me->canSeeUser($user->id)), 403);

        if ($request->boolean('back')) {
            $user->forceFill(['away_type' => null, 'away_until' => null])->save();
            AuditLog::record('availability.back', "user:{$user->id}");
            $waiting = WaConversation::whereHas('number', fn ($q) => $q->where('user_id', $user->id))->where('waiting_callback', true)->count();

            return back()->with('ok', $waiting ? __('Welcome back. :n clients are waiting for your call. They are at the top of your Inbox.', ['n' => $waiting]) : __('Welcome back.'));
        }

        $data = $request->validate([
            'away_type' => ['required', Rule::in(array_keys(User::AWAY_TYPES))],
            'back_time' => ['nullable', 'date_format:H:i'],
            'back_date' => ['nullable', 'date', 'after_or_equal:today'],
            'backup_user_id' => ['nullable', 'integer'],
            'salutation' => ['required', Rule::in(['ji', 'sir', 'madam'])],
        ]);
        $until = match ($data['away_type']) {
            'leave' => CarbonImmutable::parse($data['back_date'] ?? today()->toDateString())->setTimeFromTimeString($user->shift(\App\Models\CompanySetting::current())['office_start']),
            default => today()->toImmutable()->setTimeFromTimeString($data['back_time'] ?? '14:00'),
        };
        if ($until->lte(now())) {
            return back()->withErrors(['back_time' => __('The return time must be later than now.')]);
        }
        $backup = null;
        if (! empty($data['backup_user_id'])) {
            $backup = User::active()->whereKey($data['backup_user_id'])->where('id', '!=', $user->id)->first();
            abort_unless($backup, 404);
        }
        $user->forceFill(['away_type' => $data['away_type'], 'away_until' => $until, 'backup_user_id' => $backup?->id, 'salutation' => $data['salutation']])->save();
        AuditLog::record('availability.away', "user:{$user->id}", ['type' => $data['away_type'], 'until' => $until->toDateTimeString()]);

        return back()->with('ok', __('Saved. Until :t, clients who message you on WhatsApp get an automatic reply in their language.', ['t' => $until->format('d M, g:i A')]));
    }
}
