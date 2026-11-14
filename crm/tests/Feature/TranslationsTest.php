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
            array_values(\App\Models\LeadClash::REASONS), ['strip', 'bottle', 'box', 'piece', 'kg', 'litre']);
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
