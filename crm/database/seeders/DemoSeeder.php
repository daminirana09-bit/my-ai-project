<?php

namespace Database\Seeders;

use App\Models\AttendancePunch;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Target;
use App\Models\Task;
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
                'client_type' => $type, 'source' => ['IndiaMART', 'Website', 'WhatsApp', 'Referral', 'Call', 'TradeIndia', 'Email'][$i % 7], 'temperature' => ['hot', 'warm', 'cold'][$i % 3], 'status' => $statuses[$i % 7], 'expected_value' => 100000 * ($i + 1), 'owner_id' => $owner->id,
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

        foreach ([['PCM 650', 'Paracetamol 650 mg', 'Tablet', '10×10 Blister', 'Analgesic', 9.80, 10000], ['Aceclo-P', 'Aceclofenac 100 mg + Paracetamol 325 mg', 'Tablet', '10×10 Alu-Alu', 'Analgesic', 14.50, 10000],
            ['Pan-D', 'Pantoprazole 40 mg + Domperidone 30 mg SR', 'Capsule', '10×10 Alu-Alu', 'Gastro', 22.40, 10000], ['Cefixime 200', 'Cefixime 200 mg', 'Tablet', '10×10 Alu-Alu', 'Antibiotic', 38.00, 5000],
            ['Monte-LC', 'Montelukast 10 mg + Levocetirizine 5 mg', 'Tablet', '10×10 Alu-Alu', 'Respiratory', 16.80, 10000], ['D3 60K', 'Cholecalciferol 60,000 IU', 'Softgel', '4 caps blister', 'Nutraceutical', 19.00, 20000]] as $p) {
            Product::create(['name' => $p[0], 'composition' => $p[1], 'form' => $p[2], 'packing' => $p[3], 'category' => $p[4], 'rate' => $p[5], 'moq' => $p[6], 'unit' => 'strip', 'hsn' => '3004', 'gst_percent' => 5]);
        }
        foreach ([['Send rate list to Shree Pharma', 0, -1, 'high'], ['Collect payment from Sai Distributors', 0, 0, 'high'], ['Courier D3 60K samples', 1, 0, 'normal'], ['Call Healwell about Pan-D quote', 1, 2, 'normal'], ['Update GST on invoices', 2, 1, 'low']] as [$title, $who, $days, $prio]) {
            Task::create(['title' => $title, 'assigned_to' => $reps[$who]->id, 'created_by' => $mgr->id, 'due_on' => today()->addDays($days), 'priority' => $prio, 'status' => $days > 1 ? 'in_progress' : 'pending']);
        }
        Task::create(['title' => 'Monthly review with team', 'assigned_to' => $mgr->id, 'created_by' => $admin->id, 'due_on' => today(), 'priority' => 'normal', 'status' => 'done', 'completed_at' => now()]);
        Lead::where('firm_name', 'Nilgiri Life Sciences')->first()->forceFill(['created_at' => now()->subDays(40), 'last_contact_at' => now()->subDays(35), 'status' => 'contacted'])->save();

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
