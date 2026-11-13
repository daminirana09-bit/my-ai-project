<?php

namespace Database\Seeders;

use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Sale;
use App\Models\Target;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\ClashDetector;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/** Sample data for a local demo only. Refuses to run in production. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder does not run in production.');

            return;
        }
        CompanySetting::create(['company_name' => 'Demo Pharma Pvt. Ltd.', 'gstin' => '03AAAAA0000A1Z5', 'phone' => '0000000000']);
        $pw = 'demo12345pass';
        $admin = User::create(['name' => 'Rakesh Kumar', 'email' => 'admin@demo.local', 'password' => $pw, 'role' => 'admin']);
        $mgr = User::create(['name' => 'Neha Kulkarni', 'email' => 'manager@demo.local', 'password' => $pw, 'role' => 'manager', 'locale' => 'hing']);
        $reps = collect([
            ['Amit Verma', 'amit@demo.local', 'hing'], ['Sunita Das', 'sunita@demo.local', 'hi'], ['Rahul Sharma', 'rahul@demo.local', 'en'], ['Priya Nair', 'priya@demo.local', 'en'],
        ])->map(fn ($r) => User::create(['name' => $r[0], 'email' => $r[1], 'password' => $pw, 'role' => 'rep', 'manager_id' => $mgr->id, 'locale' => $r[2]]));

        $firms = [['Shree Pharma', 'Indore', 'PCD Franchise'], ['Om Healthcare', 'Ahmedabad', 'Third-party'], ['Sai Distributors', 'Nagpur', 'Distributor'], ['Kerala Medi Agencies', 'Kochi', 'Distributor'],
            ['Zenlife Formulations', 'Hyderabad', 'Third-party'], ['Ganga Pharmaceuticals', 'Patna', 'PCD Franchise'], ['City Care Hospital', 'Jaipur', 'Hospital'], ['Bengal Medicorp', 'Kolkata', 'Distributor'],
            ['Nilgiri Life Sciences', 'Coimbatore', 'Third-party'], ['Healwell Biotech', 'Pune', 'Third-party'], ['Vivek Pharma', 'Jaipur', 'PCD Franchise'], ['Unity Healthcare', 'Guwahati', 'Distributor']];
        $statuses = array_keys(Lead::STATUSES);
        foreach ($firms as $i => [$firm, $city, $type]) {
            $owner = $reps[$i % 4];
            $lead = Lead::create(['firm_name' => $firm, 'contact_name' => 'Contact '.($i + 1), 'phone' => '90000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'city' => $city,
                'client_type' => $type, 'source' => Lead::SOURCES[$i % 7], 'status' => $statuses[$i % 7], 'expected_value' => 100000 * ($i + 1), 'owner_id' => $owner->id,
                'created_by' => $owner->id, 'next_follow_up_on' => today()->addDays(($i % 4) - 1), 'requirement' => 'Demo requirement '.($i + 1)]);
            foreach (range(1, ($i % 3) + 1) as $k) {
                LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $owner->id, 'type' => 'call', 'outcome' => 'connected', 'note' => 'Demo call', 'occurred_at' => now()->subHours($k * 5)]);
            }
        }
        app(ClashDetector::class)->report(Lead::where('firm_name', 'Shree Pharma')->first(), $reps[1], 'added_again');

        $month = now()->format('Y-m');
        foreach ($reps as $i => $r) {
            Target::create(['user_id' => $r->id, 'month' => $month, 'amount' => [3500000, 2200000, 3000000, 2800000][$i]]);
            Sale::create(['user_id' => $r->id, 'amount' => [2140000, 1380000, 2380000, 1520000][$i], 'sale_date' => today(), 'status' => 'confirmed', 'invoice_no' => 'INV-'.(100 + $i)]);
            Sale::create(['user_id' => $r->id, 'amount' => 150000, 'sale_date' => today(), 'status' => 'pending']);
        }

        $svc = app(AttendanceService::class);
        foreach (range(1, 6) as $back) {
            $d = CarbonImmutable::today()->subDays($back);
            foreach ($reps as $i => $r) {
                $pattern = [['09:25', '18:40'], ['09:50', '14:10'], ['09:20', null], ['10:05', '18:35']][($i + $back) % 4];
                if ($d->dayOfWeek === 0) {
                    continue;
                }
                AttendancePunch::create(['user_id' => $r->id, 'type' => 'in', 'punched_at' => $d->setTimeFromTimeString($pattern[0])]);
                if ($pattern[1]) {
                    AttendancePunch::create(['user_id' => $r->id, 'type' => 'out', 'punched_at' => $d->setTimeFromTimeString($pattern[1])]);
                }
            }
            $svc->closeDay($d);
        }
        AttendancePunch::create(['user_id' => $reps[0]->id, 'type' => 'in', 'punched_at' => today()->setTime(9, 28)]);
        $svc->recalculate($reps[0], today());
    }
}
