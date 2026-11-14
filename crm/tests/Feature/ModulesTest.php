<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MasterOption;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Task;
use App\Services\DashboardStats;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Products, tasks, masters, roles, search, customers, analytics and dashboard numbers. */
class ModulesTest extends CrmTestCase
{
    protected function tearDown(): void
    {
        MasterOption::forget();
        parent::tearDown();
    }

    private function lead(array $a): Lead
    {
        return Lead::create($a + ['status' => 'new']);
    }

    // ---------- products ----------

    public function test_products_view_for_all_edit_only_with_permission(): void
    {
        $data = ['name' => 'Pan-D', 'composition' => 'Pantoprazole 40 + Domperidone 30', 'unit' => 'strip', 'gst_percent' => 5, 'rate' => 22.4, 'moq' => 10000, 'active' => 1];
        $this->actingAs($this->repA)->post('/products', $data)->assertForbidden();
        $this->actingAs($this->manager)->post('/products', $data)->assertRedirect('/products');
        $p = Product::first();
        $this->actingAs($this->repA)->get('/products')->assertOk()->assertSee('Pan-D')->assertDontSee(route('products.edit', $p));
        $this->actingAs($this->repA)->get("/products/{$p->id}/edit")->assertForbidden();
        $this->actingAs($this->manager)->put("/products/{$p->id}", array_merge($data, ['rate' => 23, 'active' => 0]))->assertRedirect();
        $this->assertFalse($p->fresh()->active);
        $this->actingAs($this->repA)->get('/products')->assertDontSee('Pan-D'); // inactive hidden
    }

    public function test_product_import_adds_and_updates(): void
    {
        Product::create(['name' => 'PCM 650', 'packing' => '10x10', 'rate' => 9, 'unit' => 'strip', 'gst_percent' => 5]);
        $path = tempnam(sys_get_temp_dir(), 'p').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        foreach ([['Name', 'Composition', 'Packing', 'Rate', 'MOQ'], ['PCM 650', 'Paracetamol 650', '10x10', '9.80', '10,000'], ['Cefixime 200', 'Cefixime 200 mg', '10x10', '38', '5000']] as $r) {
            $w->addRow(Row::fromValues($r));
        }
        $w->close();
        $this->actingAs($this->admin)->post('/products/import', ['file' => new UploadedFile($path, 'p.xlsx', null, null, true)])->assertRedirect('/products');
        $this->assertSame(2, Product::count());
        $this->assertEquals(9.80, (float) Product::where('name', 'PCM 650')->value('rate'));
        $this->assertSame(5000, Product::where('name', 'Cefixime 200')->value('moq'));
        $this->actingAs($this->admin)->get('/products/export')->assertOk()->assertDownload();
    }

    // ---------- tasks ----------

    public function test_rep_tasks_always_go_to_themselves(): void
    {
        $this->actingAs($this->repA)->post('/tasks', ['title' => 'Call Shree', 'priority' => 'high', 'assigned_to' => $this->repB->id, 'due_on' => today()->toDateString()])->assertRedirect();
        $this->assertSame($this->repA->id, Task::first()->assigned_to);
    }

    public function test_manager_assigns_to_team_only_and_visibility(): void
    {
        $this->actingAs($this->manager)->post('/tasks', ['title' => 'Collect payment', 'priority' => 'normal', 'assigned_to' => $this->repB->id])->assertRedirect();
        $this->actingAs($this->manager)->post('/tasks', ['title' => 'Outside', 'priority' => 'normal', 'assigned_to' => $this->otherTeamRep->id])->assertNotFound();
        $t = Task::first();
        $this->assertSame($this->repB->id, $t->assigned_to);
        $this->actingAs($this->repB)->get('/tasks')->assertSee('Collect payment');
        $this->actingAs($this->repA)->get('/tasks?view=team')->assertDontSee('Collect payment');
        $this->actingAs($this->repA)->patch("/tasks/{$t->id}/status", ['status' => 'done'])->assertNotFound();
        $this->actingAs($this->repB)->patch("/tasks/{$t->id}/status", ['status' => 'done'])->assertRedirect();
        $this->assertSame('done', $t->fresh()->status);
        $this->assertNotNull($t->fresh()->completed_at);
        $this->actingAs($this->repB)->delete("/tasks/{$t->id}")->assertForbidden(); // only creator or admin
        $this->actingAs($this->manager)->delete("/tasks/{$t->id}")->assertRedirect();
        $this->assertSame(0, Task::count());
    }

    public function test_task_on_someone_elses_lead_is_refused(): void
    {
        $other = $this->lead(['firm_name' => 'Not Mine', 'owner_id' => $this->repB->id]);
        $this->actingAs($this->repA)->post('/tasks', ['title' => 'Peek', 'priority' => 'normal', 'lead_id' => $other->id])->assertNotFound();
        $this->assertSame(0, Task::count());
    }

    public function test_due_tasks_and_follow_ups_appear_in_reminders(): void
    {
        Task::create(['title' => 'Send COA', 'assigned_to' => $this->repA->id, 'created_by' => $this->repA->id, 'due_on' => today()->subDay(), 'priority' => 'high']);
        Task::create(['title' => 'Later thing', 'assigned_to' => $this->repA->id, 'created_by' => $this->repA->id, 'due_on' => today()->addDays(3), 'priority' => 'low']);
        $this->lead(['firm_name' => 'Due Today Firm', 'owner_id' => $this->repA->id, 'next_follow_up_on' => today()]);
        $r = app(DashboardStats::class)->reminders($this->repA);
        $this->assertSame(['Send COA', 'Due Today Firm'], $r->pluck('title')->all());
        $this->assertTrue($r->first()->overdue);
        $this->actingAs($this->repA)->get('/')->assertSee('Send COA')->assertSee('Due Today Firm')->assertDontSee('Later thing');
    }

    // ---------- masters ----------

    public function test_admin_manages_lists_and_lead_form_uses_them(): void
    {
        $this->actingAs($this->admin)->post('/masters', ['type' => 'source', 'value' => 'Pharma Expo 2026'])->assertRedirect();
        $this->actingAs($this->admin)->post('/masters', ['type' => 'source', 'value' => 'Pharma Expo 2026'])->assertSessionHasErrors('value');
        MasterOption::forget();
        $this->actingAs($this->repA)->get('/leads/new')->assertSee('Pharma Expo 2026');
        $o = MasterOption::where('value', 'Pharma Expo 2026')->first();
        $this->actingAs($this->admin)->put("/masters/{$o->id}", ['value' => 'Pharma Expo 2026'])->assertRedirect(); // unticked = inactive
        MasterOption::forget();
        $this->actingAs($this->repA)->get('/leads/new')->assertDontSee('Pharma Expo 2026');
        $this->actingAs($this->manager)->get('/masters')->assertForbidden();
    }

    // ---------- roles & permissions ----------

    public function test_permission_matrix_controls_actions(): void
    {
        $this->actingAs($this->repA)->get('/leads/export')->assertForbidden();
        $this->actingAs($this->admin)->post('/roles', ['perm' => ['rep' => ['export_leads' => 1, 'record_sales' => 1], 'manager' => ['import_leads' => 1, 'export_leads' => 1]]])->assertRedirect();
        CompanySetting::forget();
        $this->actingAs($this->repA)->get('/leads/export')->assertOk();
        $this->actingAs($this->repA)->get('/leads/import')->assertForbidden(); // import not ticked for reps now
        $this->actingAs($this->repA)->get('/analytics')->assertForbidden();
        $this->actingAs($this->manager)->get('/analytics')->assertForbidden();
        $this->actingAs($this->manager)->post('/products', ['name' => 'X', 'unit' => 'strip', 'gst_percent' => 5])->assertForbidden();
        $this->actingAs($this->admin)->get('/analytics')->assertOk(); // admin always
        $this->actingAs($this->manager)->post('/roles', [])->assertForbidden();
    }

    // ---------- search ----------

    public function test_search_by_name_phone_email_respects_roles(): void
    {
        $this->lead(['firm_name' => 'Sunrise Pharma', 'phone' => '9876501234', 'email' => 'Buy@Sunrise.in', 'owner_id' => $this->repA->id]);
        $this->lead(['firm_name' => 'Sunset Labs', 'phone' => '9876505678', 'owner_id' => $this->repB->id]);
        Product::create(['name' => 'Sun Vit D3', 'unit' => 'strip', 'gst_percent' => 5]);

        $this->actingAs($this->repA)->get('/search?q=Sun')->assertOk()->assertSee('Sunrise Pharma')->assertDontSee('Sunset Labs')->assertSee('Sun Vit D3');
        $this->actingAs($this->repA)->get('/search?q=%2B91%2098765%2001234')->assertSee('Sunrise Pharma');
        $this->actingAs($this->repA)->get('/search?q=buy@sunrise.in')->assertSee('Sunrise Pharma');
        $this->actingAs($this->repA)->get('/search?q=9876505678')->assertDontSee('Sunset Labs');
        $this->actingAs($this->manager)->get('/search?q=Sun')->assertSee('Sunrise Pharma')->assertSee('Sunset Labs');
        $this->actingAs($this->repA)->get('/leads?q=buy@sunrise.in')->assertSee('Sunrise Pharma');
        $this->actingAs($this->repA)->get('/search?q=a')->assertOk();
    }

    // ---------- customers ----------

    public function test_customers_are_won_leads_with_orders(): void
    {
        $won = $this->lead(['firm_name' => 'Won Client', 'status' => 'won', 'owner_id' => $this->repA->id]);
        $this->lead(['firm_name' => 'Open Client', 'owner_id' => $this->repA->id]);
        $this->lead(['firm_name' => 'Won Other', 'status' => 'won', 'owner_id' => $this->repB->id]);
        Sale::create(['user_id' => $this->repA->id, 'lead_id' => $won->id, 'amount' => 250000, 'sale_date' => today()->subDays(40), 'status' => 'confirmed']);
        $this->actingAs($this->repA)->get('/customers')->assertOk()->assertSee('Won Client')->assertDontSee('Open Client')->assertDontSee('Won Other')
            ->assertSee('₹2.50 L')->assertSee('40');
    }

    // ---------- dashboard numbers ----------

    public function test_team_report_and_kpis(): void
    {
        $old = now()->subDays(45);
        $this->lead(['firm_name' => 'H1', 'temperature' => 'hot', 'owner_id' => $this->repA->id, 'next_follow_up_on' => today()->subDay()]);
        $this->lead(['firm_name' => 'H2', 'temperature' => 'hot', 'owner_id' => $this->repA->id, 'next_follow_up_on' => today()]);
        $this->lead(['firm_name' => 'C1', 'temperature' => 'cold', 'owner_id' => $this->repA->id, 'next_follow_up_on' => today()->addDays(3)]);
        $this->lead(['firm_name' => 'W1', 'status' => 'won', 'temperature' => 'hot', 'owner_id' => $this->repA->id]);
        $this->lead(['firm_name' => 'D1', 'status' => 'lost', 'owner_id' => $this->repA->id]);
        $stale = $this->lead(['firm_name' => 'S1', 'owner_id' => $this->repB->id]);
        $stale->forceFill(['created_at' => $old])->save();
        $touched = $this->lead(['firm_name' => 'S2', 'owner_id' => $this->repB->id, 'last_contact_at' => now()->subDays(2)]);
        $touched->forceFill(['created_at' => $old])->save();
        LeadActivity::create(['lead_id' => $touched->id, 'user_id' => $this->repB->id, 'type' => 'call', 'occurred_at' => now()]);

        $s = app(DashboardStats::class);
        $a = $s->teamReport(collect([$this->repA]))->first();
        $this->assertSame([5, 1, 2, 0, 1, 3, 1], [$a->total, $a->mature, $a->hot, $a->warm, $a->cold, $a->follow_up, $a->dead]); // won lead is not counted as hot
        $f = $s->followUps(collect([$this->repA, $this->repB]));
        $this->assertSame([3, 1, 1, 1], [$f[0]->total, $f[0]->overdue, $f[0]->today, $f[0]->upcoming]);
        $this->assertSame(1, $f[1]->updated_today);

        $k = $s->kpis($this->manager);
        $this->assertSame(7, $k->leads);
        $this->assertSame(1, $k->customers);
        $this->assertSame(1, $k->stale);
        $this->assertSame(1, $k->dead);
        $this->assertSame(1, $s->kpis($this->repB)->stale);
        $this->assertSame(0, $s->kpis($this->repA)->stale);

        $this->actingAs($this->manager)->get('/')->assertOk()->assertSee('Amit')->assertSee('Bhavna');
        $this->actingAs($this->repA)->get('/')->assertOk()->assertDontSee('Team report');
    }

    public function test_lead_temperature_from_form_import_and_filter(): void
    {
        $this->actingAs($this->repA)->post('/leads', ['firm_name' => 'Hot One', 'status' => 'interested', 'temperature' => 'hot'])->assertRedirect();
        $this->actingAs($this->repA)->post('/leads', ['firm_name' => 'Bad Temp', 'status' => 'new', 'temperature' => 'boiling'])->assertSessionHasErrors('temperature');
        $path = tempnam(sys_get_temp_dir(), 't').'.csv';
        file_put_contents($path, "Firm,Phone,Temperature\nCold One,9000001111,Cold\n");
        $this->actingAs($this->repA)->post('/leads/import', ['file' => new UploadedFile($path, 't.csv', 'text/csv', null, true)]);
        $this->assertSame('cold', Lead::where('firm_name', 'Cold One')->value('temperature'));
        $this->actingAs($this->repA)->get('/leads?temperature=hot')->assertSee('Hot One')->assertDontSee('Cold One');
    }

    public function test_new_pages_render_in_all_languages(): void
    {
        $lead = $this->lead(['firm_name' => 'Page', 'owner_id' => $this->repA->id, 'status' => 'won']);
        Task::create(['title' => 'T', 'assigned_to' => $this->repA->id, 'created_by' => $this->manager->id, 'lead_id' => $lead->id, 'due_on' => today(), 'priority' => 'high']);
        Product::create(['name' => 'P', 'unit' => 'strip', 'gst_percent' => 5, 'rate' => 10]);
        foreach (['en', 'hing', 'hi'] as $loc) {
            foreach ([$this->repA, $this->manager, $this->admin] as $u) {
                $u->update(['locale' => $loc]);
                foreach (['/', '/customers', '/products', '/tasks', '/tasks?view=team', '/tasks?view=given&status=done', '/search?q=Page', "/leads/{$lead->id}", '/leads?temperature=hot&stale=1'] as $url) {
                    $this->actingAs($u->fresh())->get($url)->assertOk();
                }
            }
            foreach (['/analytics', '/office-hours', '/masters', '/roles', '/products/new', '/settings'] as $url) {
                $this->actingAs($this->admin->fresh())->get($url)->assertOk();
            }
        }
    }
}
