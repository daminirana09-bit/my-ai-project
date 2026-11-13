<?php

namespace Tests\Feature;

use App\Models\Lead;

/** Rule 1: a rep only ever sees their own leads; a manager sees only their team's. */
class LeadAccessTest extends CrmTestCase
{
    private function lead(int $owner, string $firm, string $phone): Lead
    {
        return Lead::create(['firm_name' => $firm, 'phone' => $phone, 'status' => 'new', 'owner_id' => $owner]);
    }

    public function test_rep_sees_only_own_leads(): void
    {
        $mine = $this->lead($this->repA->id, 'Shree Pharma', '9876500001');
        $theirs = $this->lead($this->repB->id, 'Om Healthcare', '9876500002');

        $this->actingAs($this->repA)->get('/leads')->assertOk()->assertSee('Shree Pharma')->assertDontSee('Om Healthcare');
        $this->actingAs($this->repA)->get("/leads/{$mine->id}")->assertOk();
        $this->actingAs($this->repA)->get("/leads/{$theirs->id}")->assertNotFound();
        $this->actingAs($this->repA)->put("/leads/{$theirs->id}", ['firm_name' => 'x', 'status' => 'won'])->assertNotFound();
        $this->actingAs($this->repA)->post("/leads/{$theirs->id}/activity", ['type' => 'call'])->assertNotFound();
    }

    public function test_manager_sees_team_but_not_other_teams(): void
    {
        $this->lead($this->repA->id, 'Shree Pharma', '9876500001');
        $this->lead($this->otherTeamRep->id, 'Bengal Medicorp', '9876500003');

        $this->actingAs($this->manager)->get('/leads')->assertSee('Shree Pharma')->assertDontSee('Bengal Medicorp');
        $this->actingAs($this->admin)->get('/leads')->assertSee('Shree Pharma')->assertSee('Bengal Medicorp');
    }

    public function test_rep_cannot_open_admin_or_manager_pages(): void
    {
        foreach (['/users', '/settings', '/audit', '/clashes', '/attendance/today', '/attendance/register'] as $url) {
            $this->actingAs($this->repA)->get($url)->assertForbidden();
        }
        $this->actingAs($this->manager)->get('/users')->assertForbidden();
        $this->actingAs($this->manager)->get('/clashes')->assertOk();
    }

    public function test_phone_is_encrypted_in_database(): void
    {
        $lead = $this->lead($this->repA->id, 'Shree Pharma', '9876500001');
        $raw = \DB::table('leads')->where('id', $lead->id)->value('phone');
        $this->assertStringNotContainsString('9876500001', $raw);
        $this->assertSame('9876500001', $lead->fresh()->phone);
    }

    public function test_rep_export_off_by_default_admin_can_export(): void
    {
        $this->lead($this->repA->id, 'Shree Pharma', '9876500001');
        $this->actingAs($this->repA)->get('/leads/export')->assertForbidden();
        $this->actingAs($this->admin)->get('/leads/export?format=csv')->assertOk()->assertDownload();
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.export', 'user_id' => $this->admin->id]);
    }

    public function test_security_headers_block_outside_resources(): void
    {
        $this->actingAs($this->repA)->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_office_ip_allow_list(): void
    {
        config(['crm.allowed_ips' => '10.1.1.0/24']);
        $this->actingAs($this->admin)->get('/leads')->assertForbidden();
        $this->actingAs($this->admin)->withServerVariables(['REMOTE_ADDR' => '10.1.1.25'])->get('/leads')->assertOk();
    }
}
