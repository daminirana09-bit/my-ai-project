<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadClash;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Illuminate\Http\UploadedFile;

class LeadClashTest extends CrmTestCase
{
    public function test_second_rep_adding_same_client_creates_clash_not_duplicate(): void
    {
        $lead = Lead::create(['firm_name' => 'Shree Pharma', 'phone' => '+91 98765 00001', 'status' => 'new', 'owner_id' => $this->repA->id]);

        $res = $this->actingAs($this->repB)->post('/leads', ['firm_name' => 'Shree Pharma Indore', 'phone' => '9876500001', 'status' => 'new']);
        $res->assertRedirect('/leads');
        $res->assertSessionHas('warn');
        $this->assertSame(1, Lead::count());
        $this->assertDatabaseHas('lead_clashes', ['lead_id' => $lead->id, 'existing_owner_id' => $this->repA->id, 'other_user_id' => $this->repB->id, 'status' => 'open']);

        // Admin and the manager are told; the reps are not.
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(1, $this->manager->notifications()->count());
        $this->assertSame(0, $this->repA->notifications()->count());

        // Rep B learns nothing about who owns it.
        $this->actingAs($this->repB)->get('/leads')->assertDontSee('Shree Pharma')->assertDontSee('Amit');
    }

    public function test_same_gstin_is_also_a_clash(): void
    {
        Lead::create(['firm_name' => 'Om Healthcare', 'gstin' => '24abcde1234f1z5', 'status' => 'new', 'owner_id' => $this->repA->id]);
        $this->actingAs($this->repB)->post('/leads', ['firm_name' => 'Om HC', 'gstin' => '24ABCDE1234F1Z5', 'status' => 'new']);
        $this->assertSame(1, LeadClash::count());
    }

    public function test_manager_resolves_clash_and_lead_moves(): void
    {
        $lead = Lead::create(['firm_name' => 'Shree Pharma', 'phone' => '9876500001', 'status' => 'new', 'owner_id' => $this->repA->id]);
        $this->actingAs($this->repB)->post('/leads', ['firm_name' => 'Shree', 'phone' => '9876500001', 'status' => 'new']);
        $clash = LeadClash::first();

        $this->actingAs($this->manager)->post("/clashes/{$clash->id}/resolve", ['assigned_to' => $this->repB->id])->assertRedirect();
        $this->assertSame($this->repB->id, $lead->fresh()->owner_id);
        $this->assertSame('resolved', $clash->fresh()->status);
        $this->actingAs($this->repB)->get("/leads/{$lead->id}")->assertOk();
        $this->actingAs($this->repA)->get("/leads/{$lead->id}")->assertNotFound();
    }

    public function test_manager_cannot_resolve_other_teams_clash(): void
    {
        $lead = Lead::create(['firm_name' => 'X', 'phone' => '9876500009', 'status' => 'new', 'owner_id' => $this->otherTeamRep->id]);
        $clash = LeadClash::create(['lead_id' => $lead->id, 'existing_owner_id' => $this->otherTeamRep->id, 'other_user_id' => $this->otherTeamRep->id, 'reason' => 'added_again']);
        $this->actingAs($this->manager)->post("/clashes/{$clash->id}/resolve", ['assigned_to' => $this->repA->id])->assertNotFound();
    }

    public function test_excel_import_adds_skips_and_flags_clashes(): void
    {
        Lead::create(['firm_name' => 'Shree Pharma', 'phone' => '9876500001', 'status' => 'new', 'owner_id' => $this->repA->id]);
        Lead::create(['firm_name' => 'Own Old', 'phone' => '9876500005', 'status' => 'new', 'owner_id' => $this->repB->id]);

        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        foreach ([
            ['Party Name', 'Mobile No', 'City', 'Status', 'Deal Value', 'GST No'],
            ['New Client One', '98765 00011', 'Pune', 'Interested', '1,50,000', ''],
            ['New Client Two', '9876500012', 'Surat', '', '', ''],
            ['Duplicate In File', '9876500012', 'Surat', '', '', ''],
            ['Shree Pharma', '+919876500001', 'Indore', '', '', ''],
            ['Own Old Again', '9876500005', 'Nagpur', '', '', ''],
            ['', '', '', '', '', ''],
        ] as $r) {
            $w->addRow(Row::fromValues($r));
        }
        $w->close();

        $file = new UploadedFile($path, 'leads.xlsx', null, null, true);
        $this->actingAs($this->repB)->post('/leads/import', ['file' => $file])->assertRedirect('/leads/import')
            ->assertSessionHas('import', fn ($r) => $r['created'] === 2 && $r['clashes'] === 1 && $r['already_yours'] === 1 && $r['skipped'] === 1); // blank rows are dropped by the reader itself

        $one = Lead::where('firm_name', 'New Client One')->first();
        $this->assertSame($this->repB->id, $one->owner_id);
        $this->assertSame('interested', $one->status);
        $this->assertEquals(150000, (float) $one->expected_value);
        $this->assertSame(1, LeadClash::where('reason', 'imported_again')->count());
    }

    public function test_csv_import_works_for_admin_with_salesperson_column(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'c').'.csv';
        file_put_contents($path, "Firm,Phone,Salesperson\nCsv Firm,9876500021,{$this->repA->email}\nNobody Firm,9876500022,ghost@example.com\n");
        $file = new UploadedFile($path, 'leads.csv', 'text/csv', null, true);
        $this->actingAs($this->admin)->post('/leads/import', ['file' => $file])
            ->assertSessionHas('import', fn ($r) => $r['created'] === 1 && count($r['errors']) === 1);
        $this->assertSame($this->repA->id, Lead::where('firm_name', 'Csv Firm')->value('owner_id'));
    }
}
