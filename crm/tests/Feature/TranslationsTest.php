<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MasterOption;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Every sentence shown in the app must exist in Hinglish and Hindi, so no screen is half-translated. */
class TranslationsTest extends TestCase
{
    public function test_every_string_has_hinglish_and_hindi(): void
    {
        $keys = [];
        $files = array_merge(File::allFiles(resource_path('views')), File::allFiles(app_path()));
        foreach ($files as $f) {
            preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $f->getContents(), $m);
            foreach ($m[1] as $k) {
                $keys[str_replace("\\'", "'", $k)] = true;
            }
        }
        // Labels that reach __() through a constant.
        $labels = array_merge(array_values(Lead::STATUSES), array_values(Lead::TEMPERATURES), array_values(LeadActivity::TYPES), array_values(LeadActivity::CALL_OUTCOMES),
            array_values(Task::STATUSES), array_values(Task::PRIORITIES), array_values(User::ROLES), array_values(MasterOption::TYPES),
            array_map(fn ($p) => $p[0], CompanySetting::PERMISSIONS), array_values(\App\Models\AttendanceDay::STATUSES), array_values(\App\Models\Sale::STATUSES),
            array_values(\App\Models\LeadClash::REASONS), ['strip', 'bottle', 'box', 'piece', 'kg', 'litre'],
            array_values(\App\Models\Campaign::VARIABLES), array_values(\App\Models\ReplyTemplate::INTENTS), array_values(User::AWAY_TYPES), array_values(User::THEMES),
            array_values(\App\Models\Quote::STATUSES), array_column(\App\Models\LeadIntegration::PLATFORMS, 'name'),
            collect(\App\Models\LeadIntegration::PLATFORMS)->pluck('fields')->flatMap(fn ($f) => array_column($f, 0))->all(), ['Sent', 'Delivered', 'Read', 'Failed', 'Received', 'Queued', 'Pending', 'Skipped', 'All', 'Unread', 'Waiting', 'Other', 'Waiting for call', 'Urgent',
                'WhatsApp', 'Auto-reply when the salesperson is away', 'On half day, leave or meeting, clients get a reply in their language and wait for a call.', 'Auto-reply outside office hours', 'After office time (for example after 6 PM), before it starts and on weekly off. Each person\'s own timing is used.', 'Auto-reply when the salesperson is not at work', 'During office hours: not punched in an hour after start time, or punched out and gone (lunch is not counted).', 'Alert on urgent messages', 'URGENT or complaint messages alert the backup person and managers.', 'Give new leads out automatically', 'Leads from the company WhatsApp number or the website form go to the rep with the fewest new leads today.', 'Task for leads not updated in 30 days', 'Every morning the owner gets a follow-up task.', 'Tell managers about late follow-ups', 'Every morning, managers see who has follow-ups more than 2 days late.']);
        foreach ($labels as $l) {
            $keys[$l] = true;
        }
        foreach (['hing', 'hi'] as $loc) {
            $dict = json_decode(file_get_contents(lang_path("$loc.json")), true);
            $missing = array_values(array_diff(array_keys($keys), array_keys($dict)));
            $this->assertSame([], $missing, "Missing in $loc.json");
            foreach ($dict as $en => $tr) {
                preg_match_all('/:[a-z]+/', $en, $a);
                preg_match_all('/:[a-z]+/', $tr, $b);
                sort($a[0]);
                sort($b[0]);
                $this->assertSame($a[0], $b[0], "Placeholders differ in $loc.json for: $en");
            }
        }
    }
}
