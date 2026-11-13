<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Sale;
use App\Models\Target;
use App\Services\SalesReport;

class TargetsTest extends CrmTestCase
{
    public function test_target_sales_pending_and_calls(): void
    {
        $month = now()->format('Y-m');
        Target::create(['user_id' => $this->repA->id, 'month' => $month, 'amount' => 1000000]);
        Sale::create(['user_id' => $this->repA->id, 'amount' => 300000, 'sale_date' => now(), 'status' => 'confirmed']);
        Sale::create(['user_id' => $this->repA->id, 'amount' => 50000, 'sale_date' => now(), 'status' => 'pending']);
        $lead = Lead::create(['firm_name' => 'A', 'status' => 'new', 'owner_id' => $this->repA->id]);
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $this->repA->id, 'type' => 'call', 'occurred_at' => now()]);
        LeadActivity::create(['lead_id' => $lead->id, 'user_id' => $this->repA->id, 'type' => 'call', 'occurred_at' => now()]);

        $r = app(SalesReport::class)->rows(collect([$this->repA]), $month)->first();
        $this->assertEquals(1000000, $r->target);
        $this->assertEquals(300000, $r->achieved);
        $this->assertEquals(700000, $r->remaining);
        $this->assertEquals(50000, $r->pending_orders);
        $this->assertSame(30.0, (float) $r->percent);
        $this->assertSame(2, $r->calls_today);
    }

    public function test_rep_sale_is_pending_and_rep_cannot_set_targets(): void
    {
        $this->actingAs($this->repA)->post('/sales', ['amount' => 5000, 'sale_date' => now()->toDateString(), 'status' => 'confirmed', 'user_id' => $this->repB->id]);
        $sale = Sale::first();
        $this->assertSame($this->repA->id, $sale->user_id);
        $this->assertSame('pending', $sale->status);
        $this->actingAs($this->repA)->post('/targets', ['targets' => [$this->repA->id => 999]])->assertForbidden();
    }

    public function test_manager_cannot_set_target_outside_team(): void
    {
        $this->actingAs($this->manager)->post('/targets?month='.now()->format('Y-m'), ['targets' => [$this->repA->id => 5000, $this->otherTeamRep->id => 7000]]);
        $this->assertDatabaseHas('targets', ['user_id' => $this->repA->id]);
        $this->assertDatabaseMissing('targets', ['user_id' => $this->otherTeamRep->id]);
    }

    public function test_pages_render_for_each_role(): void
    {
        foreach ([$this->admin, $this->manager, $this->repA] as $u) {
            foreach (['/', '/leads', '/leads/new', '/leads/import', '/targets', '/attendance/me', '/profile', '/notifications'] as $url) {
                $this->actingAs($u)->get($url)->assertOk();
            }
        }
        foreach (['/users', '/users/new', '/settings', '/audit', '/clashes', '/attendance/today', '/attendance/register'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }
}
