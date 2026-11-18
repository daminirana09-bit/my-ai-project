<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\EmployeeProfile;
use App\Models\ExpenseClaim;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\AttendanceService;
use App\Services\Hr\PayrollCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Leave, holidays, expense claims, employee master and payroll. */
class HrTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-02 10:00:00')); // Monday; 1 Nov 2026 is a Sunday (weekly off)
        Holiday::forget();
    }

    private function type(string $code): LeaveType
    {
        return LeaveType::where('code', $code)->firstOrFail();
    }

    private function apply(array $extra = [])
    {
        return $this->actingAs($this->repA)->post('/hr/leaves', $extra + ['leave_type_id' => $this->type('CL')->id, 'reason' => 'Family function']);
    }

    // ---------- leave ----------

    public function test_leave_days_skip_weekly_off_and_holidays_and_the_manager_is_told(): void
    {
        Holiday::create(['date' => '2026-11-10', 'name' => 'Diwali', 'type' => 'festival']);
        // Sat 7 → Wed 11: Sun 8 off, Tue 10 holiday → 3 working days
        $this->apply(['from_date' => '2026-11-07', 'to_date' => '2026-11-11'])->assertSessionHas('ok');
        $r = LeaveRequest::sole();
        $this->assertSame(['3.0', 'pending'], [(string) $r->days, $r->status]);
        $this->assertSame('leave_request', $this->manager->notifications()->first()->data['kind']);
        $this->assertNotSame('Family function', DB::table('leave_requests')->value('reason'), 'reason stored encrypted');

        // Overlap, half day over two dates, only holidays, quota
        $this->apply(['from_date' => '2026-11-11', 'to_date' => '2026-11-12'])->assertSessionHasErrors('from_date');
        $this->apply(['from_date' => '2026-11-16', 'to_date' => '2026-11-17', 'half_day' => 1])->assertSessionHasErrors('from_date');
        $this->apply(['from_date' => '2026-11-15', 'to_date' => '2026-11-15'])->assertSessionHasErrors('from_date');
        $this->apply(['from_date' => '2026-11-16', 'to_date' => '2026-11-27'])->assertSessionHasErrors('from_date'); // 10 days > 12 − 3
        $this->apply(['from_date' => '2026-11-16', 'to_date' => '2026-11-16', 'half_day' => 1])->assertSessionHas('ok');
        $this->assertSame('0.5', (string) LeaveRequest::latest('id')->first()->days);
        $this->get('/hr/me')->assertOk()->assertSee('3.5 of 12 used this year');
    }

    public function test_approval_marks_attendance_and_only_the_right_people_can_approve(): void
    {
        $this->apply(['from_date' => '2026-11-04', 'to_date' => '2026-11-05'])->assertSessionHas('ok');
        $r = LeaveRequest::sole();
        $this->actingAs($this->repA)->post("/hr/leaves/{$r->id}/decide", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->repB)->post("/hr/leaves/{$r->id}/decide", ['decision' => 'approve'])->assertForbidden();
        $other = \App\Models\User::find($this->otherTeamRep->manager_id);
        $this->actingAs($other)->post("/hr/leaves/{$r->id}/decide", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->manager)->get('/hr/approvals')->assertOk()->assertSee('Family function');
        $this->post("/hr/leaves/{$r->id}/decide", ['decision' => 'approve', 'note' => 'Enjoy'])->assertSessionHas('ok');
        $this->assertSame('approved', $r->fresh()->status);
        $this->assertSame('leave_decided', $this->repA->notifications()->latest()->first()->data['kind']);
        foreach (['2026-11-04', '2026-11-05'] as $d) {
            $this->assertSame('leave', AttendanceDay::where('user_id', $this->repA->id)->whereDate('date', $d)->value('status'));
        }
        // End of day never turns approved leave into absent
        $this->travelTo(Carbon::parse('2026-11-06 23:55'));
        app(AttendanceService::class)->closeDay(Carbon::parse('2026-11-04'));
        $this->assertSame('leave', AttendanceDay::where('user_id', $this->repA->id)->whereDate('date', '2026-11-04')->value('status'));
        // Decided once only; taking it back puts attendance back
        $this->post("/hr/leaves/{$r->id}/decide", ['decision' => 'reject'])->assertSessionHasErrors('leave');
        $this->post("/hr/leaves/{$r->id}/cancel")->assertSessionHas('ok');
        $this->assertSame('absent', AttendanceDay::where('user_id', $this->repA->id)->whereDate('date', '2026-11-04')->value('status'));
    }

    public function test_managers_own_leave_goes_to_admin_and_new_joiners_get_their_share(): void
    {
        $this->actingAs($this->manager)->post('/hr/leaves', ['leave_type_id' => $this->type('SL')->id, 'from_date' => '2026-11-03', 'to_date' => '2026-11-03', 'reason' => 'Fever']);
        $this->assertSame('leave_request', $this->admin->notifications()->first()->data['kind']);
        $this->actingAs($this->manager)->post('/hr/leaves/'.LeaveRequest::sole()->id.'/decide', ['decision' => 'approve'])->assertForbidden();

        // The only admin may approve their own leave; with a second admin, the other one does
        $this->actingAs($this->admin)->post('/hr/leaves', ['leave_type_id' => $this->type('CL')->id, 'from_date' => '2026-11-09', 'to_date' => '2026-11-09', 'reason' => 'Own']);
        $own = LeaveRequest::where('user_id', $this->admin->id)->sole();
        $this->post("/hr/leaves/{$own->id}/decide", ['decision' => 'approve'])->assertSessionHas('ok');
        $second = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->post('/hr/leaves', ['leave_type_id' => $this->type('CL')->id, 'from_date' => '2026-11-10', 'to_date' => '2026-11-10', 'reason' => 'Own 2']);
        $own2 = LeaveRequest::where('user_id', $this->admin->id)->where('status', 'pending')->sole();
        $this->post("/hr/leaves/{$own2->id}/decide", ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('leave_request', $second->notifications()->first()->data['kind']);

        EmployeeProfile::create(['user_id' => $this->repB->id, 'date_of_joining' => '2026-10-15', 'status' => 'active']);
        $q = app(\App\Services\Hr\LeaveService::class)->quota($this->repB->fresh(), $this->type('CL'), 2026);
        $this->assertSame(3.0, $q); // 12 × 3/12 (Oct, Nov, Dec)
    }

    public function test_holidays_are_never_absent(): void
    {
        Holiday::create(['date' => '2026-11-03', 'name' => 'Local festival', 'type' => 'festival']);
        AttendancePunch::create(['user_id' => $this->repA->id, 'type' => 'in', 'punched_at' => '2026-10-30 09:30:00']);
        $this->travelTo(Carbon::parse('2026-11-04 08:00'));
        app(AttendanceService::class)->closeDay(Carbon::parse('2026-11-03'));
        $this->assertSame('holiday', AttendanceDay::where('user_id', $this->repA->id)->whereDate('date', '2026-11-03')->value('status'));
        // Admin manages the list; everyone sees it
        $this->actingAs($this->repA)->post('/hr/holidays', ['date' => '2026-11-20', 'name' => 'X', 'type' => 'festival'])->assertForbidden();
        $this->actingAs($this->admin)->post('/hr/holidays', ['date' => '2026-11-20', 'name' => 'Guru Nanak Jayanti', 'type' => 'festival'])->assertSessionHas('ok');
        $this->post('/hr/holidays', ['date' => '2026-11-20', 'name' => 'Dup', 'type' => 'festival'])->assertSessionHasErrors('date');
        $this->post('/hr/holidays/national', ['year' => 2028])->assertSessionHas('ok');
        $this->assertTrue(Holiday::isHoliday('2028-01-26'));
        $this->actingAs($this->repA)->get('/hr/me')->assertSee('Guru Nanak Jayanti');
    }

    // ---------- claims ----------

    public function test_claim_amounts_come_from_company_rates_and_receipts_stay_private(): void
    {
        $this->actingAs($this->repA)->post('/hr/claims', ['date' => '2026-11-02', 'kind' => 'da', 'area' => 'exhq', 'amount' => 99999])->assertSessionHas('ok');
        $this->assertSame('250.00', (string) ExpenseClaim::sole()->amount, 'typed amount ignored for DA');
        $this->post('/hr/claims', ['date' => '2026-11-02', 'kind' => 'da', 'area' => 'hq'])->assertSessionHasErrors('date');
        $this->post('/hr/claims', ['date' => '2026-11-02', 'kind' => 'travel', 'vehicle' => 'bike', 'km' => 42, 'from_place' => 'Indore', 'to_place' => 'Dewas'])->assertSessionHas('ok');
        $this->assertSame('147.00', (string) ExpenseClaim::where('kind', 'travel')->value('amount'));
        $this->post('/hr/claims', ['date' => '2026-11-02', 'kind' => 'hotel', 'amount' => 1200,
            'receipt' => UploadedFile::fake()->createWithContent('bill.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n")])->assertSessionHas('ok');
        $this->post('/hr/claims', ['date' => '2026-11-02', 'kind' => 'other', 'amount' => 50, 'receipt' => UploadedFile::fake()->create('x.html', 1, 'text/html')])->assertSessionHasErrors('receipt');
        $this->post('/hr/claims', ['date' => '2026-12-30', 'kind' => 'other', 'amount' => 50])->assertSessionHasErrors('date');
        $hotel = ExpenseClaim::where('kind', 'hotel')->sole();
        $this->get("/hr/claims/{$hotel->id}/receipt")->assertOk();
        $this->actingAs($this->repB)->get("/hr/claims/{$hotel->id}/receipt")->assertNotFound();
        $this->actingAs($this->manager)->get("/hr/claims/{$hotel->id}/receipt")->assertOk();

        $this->actingAs($this->repB)->post("/hr/claims/{$hotel->id}/decide", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->manager)->post("/hr/claims/{$hotel->id}/decide", ['decision' => 'approve'])->assertSessionHas('ok');
        $this->assertSame('approved', $hotel->fresh()->status);
        $this->actingAs($this->repA)->post("/hr/claims/{$hotel->id}/cancel")->assertForbidden();
        $da = ExpenseClaim::where('kind', 'da')->sole();
        $this->post("/hr/claims/{$da->id}/cancel")->assertSessionHas('ok');
        $this->assertNull($da->fresh());
    }

    // ---------- employees ----------

    public function test_employee_details_are_encrypted_and_admin_only(): void
    {
        $this->actingAs($this->repA)->get('/hr/employees')->assertForbidden();
        $this->actingAs($this->manager)->get("/hr/employees/{$this->repA->id}")->assertForbidden();
        $form = ['emp_code' => 'MR-007', 'designation' => 'Medical Representative', 'headquarter' => 'Indore', 'employment_type' => 'permanent', 'status' => 'active',
            'date_of_joining' => '2025-04-01', 'date_of_birth' => '1995-11-20',
            'bank' => ['account_name' => 'Amit Kumar', 'account_no' => '123456789012', 'ifsc' => 'sbin0001234', 'bank_name' => 'SBI'],
            'ids' => ['pan' => 'abcde1234f', 'uan' => '100200300400', 'aadhaar_last4' => '4321'],
            'salary' => ['basic' => 15000, 'hra' => 6000, 'special' => 4000, 'pf' => 1, 'pt' => 200]];
        $this->actingAs($this->admin)->put("/hr/employees/{$this->repA->id}", $form)->assertSessionHas('ok');
        $p = EmployeeProfile::where('user_id', $this->repA->id)->sole();
        $this->assertSame(['ABCDE1234F', 'SBIN0001234', 25000.0], [$p->field('ids', 'pan'), $p->field('bank', 'ifsc'), $p->monthlyGross()]);
        $raw = json_encode(DB::table('employee_profiles')->first());
        foreach (['123456789012', 'ABCDE1234F', '15000', 'Amit Kumar'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }
        // Saved numbers are never shown again, and an empty box keeps them
        $this->get("/hr/employees/{$this->repA->id}")->assertOk()->assertDontSee('123456789012')->assertSee('••••••••9012');
        $form['bank']['account_no'] = '';
        $form['ids']['pan'] = '';
        $this->put("/hr/employees/{$this->repA->id}", $form)->assertSessionHas('ok');
        $this->assertSame(['123456789012', 'ABCDE1234F'], [$p->fresh()->field('bank', 'account_no'), $p->fresh()->field('ids', 'pan')]);
        $this->put("/hr/employees/{$this->repA->id}", ['ids' => ['pan' => 'BAD'], 'bank' => ['ifsc' => 'X1'], 'employment_type' => 'permanent', 'status' => 'active'])
            ->assertSessionHasErrors(['ids.pan', 'bank.ifsc']);
        $this->put("/hr/employees/{$this->repB->id}", ['emp_code' => 'MR-007', 'employment_type' => 'permanent', 'status' => 'active'])->assertSessionHasErrors('emp_code');

        // Documents
        $this->post("/hr/employees/{$this->repA->id}/documents", ['title' => 'Offer letter', 'file' => UploadedFile::fake()->createWithContent('offer.pdf', '%PDF-1.4 x')])->assertSessionHas('ok');
        $doc = \App\Models\HrDocument::sole();
        $this->get("/hr/documents/{$doc->id}")->assertOk();
        $this->actingAs($this->repA)->get("/hr/documents/{$doc->id}")->assertForbidden();
        $this->post("/hr/employees/{$this->repA->id}/documents", ['title' => 'x', 'file' => UploadedFile::fake()->create('a.php', 1)])->assertForbidden();
    }

    // ---------- payroll ----------

    private function salary(): void
    {
        EmployeeProfile::create(['user_id' => $this->repA->id, 'emp_code' => 'MR-007', 'designation' => 'MR', 'date_of_joining' => '2025-04-01', 'status' => 'active',
            'bank' => ['account_no' => '123456789012', 'ifsc' => 'SBIN0001234', 'bank_name' => 'SBI'], 'ids' => ['pan' => 'ABCDE1234F'],
            'salary' => ['basic' => 15000, 'hra' => 6000, 'special' => 4000, 'pf' => true, 'esi' => true, 'pt' => 200]]);
    }

    public function test_salary_cuts_absent_days_and_unpaid_leave_and_adds_claims(): void
    {
        $this->salary();
        // November 2026: Amit uses attendance; 2 absent, 1 half day, 1 day leave without pay
        AttendancePunch::create(['user_id' => $this->repA->id, 'type' => 'in', 'punched_at' => '2026-11-02 09:30:00']);
        foreach (['2026-11-03' => 'absent', '2026-11-04' => 'absent', '2026-11-05' => 'half_day', '2026-11-06' => 'full_day', '2026-11-08' => 'weekly_off'] as $d => $s) {
            AttendanceDay::create(['user_id' => $this->repA->id, 'date' => $d, 'status' => $s, 'worked_minutes' => 0]);
        }
        $lwp = LeaveRequest::create(['user_id' => $this->repA->id, 'leave_type_id' => $this->type('LWP')->id, 'from_date' => '2026-11-12', 'to_date' => '2026-11-12', 'days' => 1, 'reason' => 'x', 'status' => 'pending']);
        app(\App\Services\Hr\LeaveService::class)->approve($lwp, $this->admin, null);
        ExpenseClaim::create(['user_id' => $this->repA->id, 'date' => '2026-11-06', 'kind' => 'da', 'amount' => 250, 'status' => 'approved']);
        ExpenseClaim::create(['user_id' => $this->repA->id, 'date' => '2026-11-07', 'kind' => 'da', 'amount' => 250, 'status' => 'pending']);

        $this->travelTo(Carbon::parse('2026-12-02 10:00'));
        $this->actingAs($this->admin)->post('/hr/payroll', ['month' => '2026-11'])->assertSessionHas('ok');
        $run = PayrollRun::sole();
        $s = Payslip::where('user_id', $this->repA->id)->sole();
        $this->assertSame([30, 3.5, 26.5], [$s->v('days_in_month'), (float) $s->v('lop_days'), (float) $s->v('paid_days')]);
        $this->assertSame(['basic' => 13250, 'hra' => 5300, 'conveyance' => 0, 'special' => 3533, 'other' => 0], $s->v('earnings'));
        $this->assertSame(22083, $s->v('gross'));
        $this->assertSame(['pf' => 1590, 'esi' => 0, 'pt' => 200, 'tds' => 0, 'other' => 0], $s->v('deductions'), 'no ESI above ₹21,000 gross');
        $this->assertSame(250, $s->v('reimbursement'));
        $this->assertSame(22083 + 250 - 1790, $s->v('net'));
        $this->assertSame(1, Payslip::count(), 'people without salary details are left out');

        // Incentive and other deduction, then final
        $this->post("/hr/payroll/{$run->id}/payslips/{$s->id}", ['incentive' => 2000, 'deduction' => 500, 'note' => 'Target bonus'])->assertSessionHas('ok');
        $this->assertSame(22083 + 250 + 2000 - 1790 - 500, $s->fresh()->v('net'));
        $this->actingAs($this->repA)->get("/hr/payslips/{$s->id}")->assertNotFound(); // draft: not yet for the employee
        $this->actingAs($this->admin)->post("/hr/payroll/{$run->id}/finalize")->assertSessionHas('ok');
        $this->assertSame('paid', ExpenseClaim::where('status', '!=', 'pending')->value('status'));
        $this->assertSame('payslip', $this->repA->notifications()->first()->data['kind']);
        $this->post("/hr/payroll/{$run->id}/payslips/{$s->id}", ['incentive' => 1])->assertForbidden();
        $this->post("/hr/payroll/{$run->id}/recalculate")->assertForbidden();
        $this->post('/hr/payroll', ['month' => '2026-11'])->assertSessionHasErrors('run');

        $pdf = $this->actingAs($this->repA)->get("/hr/payslips/{$s->id}")->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($this->repB)->get("/hr/payslips/{$s->id}")->assertNotFound();
        $this->actingAs($this->repA)->get('/hr/me')->assertSee('November 2026');

        $this->actingAs($this->admin);
        foreach (['register', 'bank'] as $type) {
            $x = $this->get("/hr/payroll/{$run->id}/export?type={$type}")->assertOk();
            $this->assertStringStartsWith('PK', file_get_contents($x->baseResponse->getFile()->getPathname()));
        }
        $this->actingAs($this->repA)->get("/hr/payroll/{$run->id}/export?type=bank")->assertForbidden();

        // Opening a file never becomes the page that Save/Approve returns to
        $this->actingAs($this->admin)->get("/hr/payroll/{$run->id}");
        $this->get("/hr/payslips/{$s->id}")->assertOk();
        $this->get("/hr/payroll/{$run->id}/export?type=register")->assertOk();
        $this->post('/hr/holidays', ['date' => '2026-12-25', 'name' => 'Christmas', 'type' => 'festival'])->assertRedirect(route('hr.settings', ['year' => 2026]));
        $this->post('/hr/leave-types', ['code' => 'CO', 'name' => 'Comp off', 'paid' => 1])->assertRedirect("/hr/payroll/{$run->id}");
    }

    public function test_no_punches_means_full_pay_and_new_joiners_are_paid_from_joining(): void
    {
        $this->salary();
        EmployeeProfile::where('user_id', $this->repA->id)->update(['date_of_joining' => '2026-11-16']);
        AttendanceDay::create(['user_id' => $this->repA->id, 'date' => '2026-11-17', 'status' => 'absent', 'worked_minutes' => 0]); // marked by the nightly close, but no punches at all
        $d = app(PayrollCalculator::class)->calculate($this->repA->fresh(), \Carbon\CarbonImmutable::parse('2026-11-01'));
        $this->assertSame([15, 0.0, 15.0, false], [$d['employed_days'], (float) $d['lop_days'], (float) $d['paid_days'], $d['uses_attendance']]);
        $this->assertSame(7500, $d['earnings']['basic']);
        $this->assertSame(['pf' => 900, 'esi' => 0], ['pf' => $d['deductions']['pf'], 'esi' => $d['deductions']['esi']]);
        // ESI applies when monthly gross is ₹21,000 or less (rounded up)
        EmployeeProfile::where('user_id', $this->repA->id)->first()->update(['salary' => ['basic' => 12000, 'hra' => 4000, 'pf' => true, 'esi' => true, 'pt' => 200]]);
        $d = app(PayrollCalculator::class)->calculate($this->repA->fresh(), \Carbon\CarbonImmutable::parse('2026-11-01'));
        $this->assertSame([8000, 60, 720], [$d['gross'], $d['deductions']['esi'], $d['deductions']['pf']]);
        $this->assertSame('Twenty Two Thousand Five Hundred Forty Three Rupees Only', PayrollCalculator::inWords(22543));
    }

    // ---------- pages ----------

    public function test_pages_open_for_the_right_people(): void
    {
        $this->salary();
        foreach (['/hr/me', '/hr/approvals', '/hr', '/hr/employees', "/hr/employees/{$this->repA->id}", '/hr/settings', '/hr/payroll'] as $u) {
            $this->actingAs($this->admin)->get($u)->assertOk();
        }
        foreach (['/hr/me' => 200, '/hr/approvals' => 200, '/hr' => 200, '/hr/employees' => 403, '/hr/payroll' => 403, '/hr/settings' => 403] as $u => $code) {
            $this->actingAs($this->manager)->get($u)->assertStatus($code);
        }
        foreach (['/hr/me' => 200, '/hr/approvals' => 403, '/hr' => 403, '/hr/payroll' => 403] as $u => $code) {
            $this->actingAs($this->repA)->get($u)->assertStatus($code);
        }
        $this->actingAs($this->admin)->post('/hr/settings', ['da_hq' => 175] + \App\Models\CompanySetting::HR_DEFAULTS)->assertSessionHas('ok');
        \App\Models\CompanySetting::forget();
        $this->assertSame(175.0, \App\Models\CompanySetting::current()->hr('da_hq'));
        $this->get('/hr/settings')->assertOk()->assertSee('name="punch_location_required"', false);
        $this->post('/hr/settings', ['punch_location_required' => 1] + \App\Models\CompanySetting::HR_DEFAULTS)->assertSessionHas('ok');
        \App\Models\CompanySetting::forget();
        $this->assertSame(1.0, \App\Models\CompanySetting::current()->hr('punch_location_required'));
        $this->post('/hr/leave-types', ['code' => 'ml', 'name' => 'Maternity leave', 'yearly_quota' => 182, 'paid' => 1])->assertSessionHas('ok');
        $this->assertTrue(LeaveType::where('code', 'ML')->value('paid'));
        $this->get('/hr')->assertSee('People');
    }
}
