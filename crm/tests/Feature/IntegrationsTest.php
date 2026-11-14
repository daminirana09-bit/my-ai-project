<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadIntegration;
use App\Services\Leads\Normalizer;
use App\Services\Leads\WebsiteSource;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** IndiaMART, TradeIndia, portal API links, JustDial, Facebook, Google Ads, other apps and the website form. Portals are faked. */
class IntegrationsTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function on(string $platform, array $config = [], array $extra = []): LeadIntegration
    {
        $i = LeadIntegration::for($platform);
        $i->forceFill(['enabled' => true, 'config' => array_merge($i->config ?? [], $config)] + $extra)->save();

        return $i->fresh();
    }

    private function indiamartRow(string $id, string $mobile, string $type = 'W', string $product = 'Pantoprazole Tablets'): array
    {
        return ['UNIQUE_QUERY_ID' => $id, 'QUERY_TYPE' => $type, 'QUERY_TIME' => '2026-11-14 10:12:00', 'SENDER_NAME' => 'Ravi Kumar',
            'SENDER_MOBILE' => $mobile, 'SENDER_EMAIL' => "ravi{$id}@example.in", 'SUBJECT' => 'Requirement for '.$product,
            'SENDER_COMPANY' => 'Ravi Pharma', 'SENDER_CITY' => 'Lucknow', 'SENDER_STATE' => 'Uttar Pradesh',
            'QUERY_PRODUCT_NAME' => $product, 'QUERY_MESSAGE' => 'Need PCD franchise for <b>gastro</b> range', 'QUERY_MCAT_NAME' => 'Pharma Franchise'];
    }

    public function test_only_admin_opens_integrations_and_keys_are_stored_encrypted_and_never_shown(): void
    {
        $this->actingAs($this->repA)->get('/integrations')->assertForbidden();
        $this->actingAs($this->manager)->get('/integrations')->assertForbidden();
        $res = $this->actingAs($this->admin)->get('/integrations')->assertOk();
        foreach (['IndiaMART', 'TradeIndia', 'ExportersIndia', 'JustDial', 'Facebook &amp; Instagram lead ads', 'Google Ads lead forms', 'Website enquiry form'] as $name) {
            $res->assertSee($name, false);
        }

        $this->post('/integrations/indiamart', ['enabled' => 1, 'crm_key' => 'SECRET-CRM-KEY-123', 'extra_tags' => 'PCD, North zone'])->assertRedirect();
        $raw = DB::table('lead_integrations')->where('platform', 'indiamart')->value('config');
        $this->assertStringNotContainsString('SECRET-CRM-KEY-123', $raw);
        $this->assertSame('SECRET-CRM-KEY-123', LeadIntegration::for('indiamart')->conf('crm_key'));
        $this->get('/integrations')->assertDontSee('SECRET-CRM-KEY-123');

        // Saving again with the box empty keeps the key
        $this->post('/integrations/indiamart', ['enabled' => 1, 'crm_key' => ''])->assertRedirect();
        $this->assertSame('SECRET-CRM-KEY-123', LeadIntegration::for('indiamart')->conf('crm_key'));
        $this->assertNull(LeadIntegration::for('indiamart')->extra_tags);
    }

    public function test_indiamart_fetch_creates_tagged_assigned_leads_and_never_duplicates(): void
    {
        $this->on('indiamart', ['crm_key' => 'k1'], ['extra_tags' => 'PCD']);
        Http::fake(['mapi.indiamart.com/*' => Http::response(['CODE' => 200, 'STATUS' => 'SUCCESS', 'TOTAL_RECORDS' => 2, 'RESPONSE' => [
            $this->indiamartRow('111', '+91-9876500001'),
            $this->indiamartRow('112', '+91-9876500002', 'B', 'Azithromycin 500'),
        ]])]);

        $this->actingAs($this->admin)->post('/integrations/indiamart/fetch')->assertRedirect()->assertSessionHas('ok');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'glusr_crm_key=k1')
            && preg_match('/start_time=\d{2}-[A-Z][a-z]{2}-\d{4}\d{2}%3A\d{2}%3A\d{2}/', $r->url()) === 1);

        $leads = Lead::orderBy('id')->get();
        $this->assertCount(2, $leads);
        $a = $leads[0];
        $this->assertSame('IndiaMART', $a->source);
        $this->assertSame(['IndiaMART', 'Direct enquiry', 'PCD'], $a->tags);
        $this->assertContains('Buy-lead', $leads[1]->tags);
        $this->assertSame('Ravi Pharma', $a->firm_name);
        $this->assertSame('Pantoprazole Tablets', $a->requirement);
        $this->assertSame('Need PCD franchise for gastro range', $a->notes); // HTML removed
        $this->assertSame('111', $a->external_ref);
        $this->assertSame('integration', $a->created_via);
        $this->assertNotNull($a->owner_id);
        $this->assertNotSame($a->owner_id, $leads[1]->owner_id, 'shared out, fewest new leads first');
        $this->assertSame(1, $a->owner->notifications()->where('data->kind', 'new_lead')->count());

        // IndiaMART allows one fetch in 5 minutes
        $this->post('/integrations/indiamart/fetch')->assertSessionHasErrors('fetch');
        $this->travel(6)->minutes();
        $this->post('/integrations/indiamart/fetch')->assertSessionHas('ok');
        $this->assertSame(2, Lead::count(), 'the same enquiries again are ignored');
        $this->assertSame(2, LeadIntegration::for('indiamart')->received);
    }

    public function test_indiamart_error_is_shown_to_admin(): void
    {
        $this->on('indiamart', ['crm_key' => 'bad']);
        Http::fake(['mapi.indiamart.com/*' => Http::response(['CODE' => 401, 'STATUS' => 'FAILURE', 'MESSAGE' => 'Invalid key'])]);
        $this->actingAs($this->admin)->post('/integrations/indiamart/fetch')->assertSessionHasErrors('fetch');
        $this->assertSame('IndiaMART: Invalid key', LeadIntegration::for('indiamart')->last_error);
        $this->get('/integrations')->assertSee('IndiaMART: Invalid key')->assertSee('Needs attention');

        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['mapi.indiamart.com/*' => Http::response(['CODE' => 204, 'STATUS' => 'SUCCESS', 'MESSAGE' => 'There are no leads in the given time duration.'])]);
        $this->travel(6)->minutes();
        $this->post('/integrations/indiamart/fetch')->assertSessionHas('ok');
        $this->assertNull(LeadIntegration::for('indiamart')->last_error);
    }

    public function test_a_client_already_in_the_crm_gets_a_note_not_a_second_lead(): void
    {
        $lead = Lead::create(['firm_name' => 'Ravi Pharma', 'phone' => '9876500001', 'status' => 'contacted', 'owner_id' => $this->repB->id, 'source' => 'Reference']);
        $this->on('indiamart', ['crm_key' => 'k1']);
        Http::fake(['mapi.indiamart.com/*' => Http::response(['CODE' => 200, 'RESPONSE' => [$this->indiamartRow('500', '+91-98765-00001')]])]);
        $this->artisan('leads:pull')->assertSuccessful();

        $this->assertSame(1, Lead::count());
        $lead->refresh();
        $this->assertSame('Reference', $lead->source, 'the first source stays');
        $this->assertContains('IndiaMART', $lead->tags);
        $this->assertSame($this->repB->id, $lead->owner_id);
        $note = LeadActivity::where('lead_id', $lead->id)->where('type', 'enquiry')->first();
        $this->assertStringContainsString('IndiaMART: Pantoprazole Tablets', $note->note);
        $this->assertTrue((bool) $this->repB->notifications()->where('data->kind', 'new_lead')->first()->data['again']);
    }

    public function test_tradeindia_fetch(): void
    {
        $this->on('tradeindia', ['user_id' => 'u1', 'profile_id' => 'p1', 'key' => 'k1']);
        Http::fake(['www.tradeindia.com/*' => Http::response([
            ['rfi_id' => 'T1', 'sender_name' => 'Sunil', 'sender_mobile' => '9811122233', 'sender_co' => 'Sunil Medicos', 'sender_city' => 'Patna',
                'subject' => 'Paracetamol 650', 'message' => 'Rate for 10,000 strips', 'inquiry_type' => 'Direct'],
        ])]);
        $this->actingAs($this->admin)->post('/integrations/tradeindia/fetch')->assertSessionHas('ok');
        $l = Lead::sole();
        $this->assertSame(['TradeIndia', 'Sunil Medicos', 'Patna', 'Paracetamol 650'], [$l->source, $l->firm_name, $l->city, $l->requirement]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'userid=u1') && str_contains($r->url(), 'profile_id=p1') && str_contains($r->url(), 'key=k1'));
    }

    public function test_portal_api_link_must_be_public_https_and_is_fetched(): void
    {
        $this->actingAs($this->admin);
        $this->post('/integrations/exportersindia', ['enabled' => 1, 'api_url' => 'http://example.com/x'])->assertSessionHasErrors('api_url');
        $this->post('/integrations/exportersindia', ['enabled' => 1, 'api_url' => 'https://127.0.0.1/leads'])->assertSessionHasErrors('api_url');
        $this->post('/integrations/exportersindia', ['enabled' => 1, 'api_url' => 'https://10.0.0.5/leads'])->assertSessionHasErrors('api_url');
        $this->assertNull(LeadIntegration::for('exportersindia')->conf('api_url'));

        $this->post('/integrations/exportersindia', ['enabled' => 1, 'api_url' => 'https://93.184.216.34/api?key=abc&from={from}&to={to}'])->assertSessionHas('ok');
        Http::fake(['93.184.216.34/*' => Http::response(['status' => 'ok', 'data' => [
            ['inq_id' => 'E1', 'name' => 'Export Buyer', 'mobile' => '+971 50 123 4567', 'email' => 'buyer@example.ae', 'product_name' => 'Amoxicillin'],
        ]])]);
        $this->post('/integrations/exportersindia/fetch')->assertSessionHas('ok');
        $this->assertSame('ExportersIndia', Lead::sole()->source);
        Http::assertSent(fn (HttpRequest $r) => preg_match('/from=\d{4}-\d{2}-\d{2}&to=\d{4}-\d{2}-\d{2}/', $r->url()) === 1);
    }

    public function test_justdial_push_link(): void
    {
        $i = $this->on('justdial');
        $q = ['leadid' => 'JD99', 'prefix' => 'Mr', 'name' => 'Ajay', 'mobile' => '9822233344', 'email' => '', 'category' => 'Pharmaceutical Distributors', 'city' => 'Pune', 'area' => 'Kothrud'];
        $this->get("/webhook/leads/justdial/{$i->token}?".http_build_query($q))->assertOk()->assertSeeText('RECEIVED');
        $this->get("/webhook/leads/justdial/{$i->token}?".http_build_query($q))->assertOk();
        $l = Lead::sole();
        $this->assertSame(['JustDial', 'Ajay', 'Pune', 'Pharmaceutical Distributors'], [$l->source, $l->firm_name, $l->city, $l->requirement]);

        $this->get('/webhook/leads/justdial/'.str_repeat('x', 40).'?'.http_build_query($q))->assertNotFound();
        $i->update(['enabled' => false]);
        $this->get("/webhook/leads/justdial/{$i->token}?".http_build_query($q))->assertNotFound();
    }

    public function test_indiamart_push_and_old_link_stops_after_new_link(): void
    {
        $i = $this->on('indiamart');
        $this->postJson("/webhook/leads/indiamart/{$i->token}", ['CODE' => 200, 'STATUS' => 'SUCCESS', 'RESPONSE' => $this->indiamartRow('900', '+91-9000000900', 'P')])
            ->assertOk()->assertJson(['CODE' => 200]);
        $this->assertContains('Call (PNS)', Lead::sole()->tags);

        $this->actingAs($this->admin)->post('/integrations/indiamart/new-link')->assertSessionHas('ok');
        $this->postJson("/webhook/leads/indiamart/{$i->token}", ['RESPONSE' => $this->indiamartRow('901', '+91-9000000901')])->assertNotFound();
        $this->postJson('/webhook/leads/indiamart/'.LeadIntegration::for('indiamart')->token, ['RESPONSE' => $this->indiamartRow('901', '+91-9000000901')])->assertOk();
        $this->assertSame(2, Lead::count());
    }

    public function test_facebook_and_instagram_lead_ads(): void
    {
        $i = $this->on('facebook', ['page_id' => '123456789', 'page_token' => 'ptok', 'app_secret' => 'fbsecret']);
        $vt = $i->conf('verify_token');
        $this->get("/webhook/leads/facebook/{$i->token}?hub.mode=subscribe&hub.verify_token={$vt}&hub.challenge=777")->assertOk()->assertSeeText('777');
        $this->get("/webhook/leads/facebook/{$i->token}?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=777")->assertForbidden();

        Http::fake(['graph.facebook.com/*/5550001*' => Http::response(['id' => '5550001', 'platform' => 'ig', 'campaign_name' => 'Diwali PCD', 'form_id' => '9', 'field_data' => [
            ['name' => 'full_name', 'values' => ['Meena Shah']], ['name' => 'phone_number', 'values' => ['+919988776655']],
            ['name' => 'city', 'values' => ['Surat']], ['name' => 'which_range_do_you_need?', 'values' => ['Gastro']],
        ]]), 'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)]);

        $body = json_encode(['object' => 'page', 'entry' => [['id' => '123456789', 'changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => '5550001', 'page_id' => '123456789', 'form_id' => '9']]]]]]);
        $sig = 'sha256='.hash_hmac('sha256', $body, 'fbsecret');
        $this->call('POST', "/webhook/leads/facebook/{$i->token}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $body)->assertForbidden();
        $this->call('POST', "/webhook/leads/facebook/{$i->token}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $sig], $body)->assertOk();
        $this->call('POST', "/webhook/leads/facebook/{$i->token}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $sig], $body)->assertOk();
        $l = Lead::sole();
        $this->assertSame(['Instagram Ads', 'Meena Shah', 'Surat', 'Diwali PCD'], [$l->source, $l->firm_name, $l->city, $l->campaign]);
        $this->assertContains('Diwali PCD', $l->tags);
        $this->assertStringContainsString('Gastro', $l->notes);

        // A lead that cannot be read now: Facebook is told to try again; the error is shown to the admin
        $body2 = json_encode(['entry' => [['changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => '5550002', 'page_id' => '123456789']]]]]]);
        $this->call('POST', "/webhook/leads/facebook/{$i->token}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body2, 'fbsecret')], $body2)->assertStatus(503);
        $this->assertStringContainsString('Invalid OAuth access token', LeadIntegration::for('facebook')->last_error);
        // Another Facebook Page's leads are ignored
        $body3 = json_encode(['entry' => [['changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => '5550003', 'page_id' => '999']]]]]]);
        $this->call('POST', "/webhook/leads/facebook/{$i->token}", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body3, 'fbsecret')], $body3)->assertOk();
        $this->assertSame(1, Lead::count());
    }

    public function test_facebook_connect_page_button(): void
    {
        $this->on('facebook', ['page_id' => '123456789', 'page_token' => 'ptok', 'app_secret' => 's']);
        Http::fake(['graph.facebook.com/*/123456789/subscribed_apps' => Http::response(['success' => true])]);
        $this->actingAs($this->admin)->post('/integrations/facebook/subscribe')->assertSessionHas('ok');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r['subscribed_fields'] === 'leadgen' && $r->hasHeader('Authorization', 'Bearer ptok'));
    }

    public function test_google_ads_lead_form_webhook(): void
    {
        $i = $this->on('google_ads');
        $key = $i->conf('google_key');
        $lead = ['lead_id' => 'G-1', 'api_version' => '1.0', 'form_id' => 5, 'campaign_id' => 42, 'google_key' => $key, 'is_test' => false, 'gcl_id' => 'abc',
            'user_column_data' => [
                ['column_name' => 'Full Name', 'string_value' => 'Kiran Rao', 'column_id' => 'FULL_NAME'],
                ['column_name' => 'User Phone', 'string_value' => '+91 99000 11122', 'column_id' => 'PHONE_NUMBER'],
                ['column_name' => 'City', 'string_value' => 'Hyderabad', 'column_id' => 'CITY'],
                ['column_name' => 'Which products?', 'string_value' => 'Cardiac range', 'column_id' => 'OTHER'],
            ]];
        $this->postJson("/webhook/leads/google_ads/{$i->token}", ['google_key' => 'wrong'] + $lead)->assertForbidden();
        $this->postJson("/webhook/leads/google_ads/{$i->token}", ['is_test' => true, 'lead_id' => 'TEST1'] + $lead)->assertOk();
        $this->assertSame(0, Lead::count(), '"Send test data" proves the link works without making a lead');
        $this->assertNotNull(LeadIntegration::for('google_ads')->last_success_at);

        $this->postJson("/webhook/leads/google_ads/{$i->token}", $lead)->assertOk();
        $l = Lead::sole();
        $this->assertSame(['Google Ads', 'Kiran Rao', 'Hyderabad', 'Campaign 42'], [$l->source, $l->firm_name, $l->city, $l->campaign]);
        $this->assertStringContainsString('Which products?: Cardiac range', $l->notes);
    }

    public function test_other_apps_can_set_source_and_tags_but_portals_cannot(): void
    {
        $c = $this->on('custom');
        $this->postJson("/webhook/leads/custom/{$c->token}", ['name' => 'Linked Lead', 'phone' => '9000011111', 'company' => 'LL Pharma', 'source' => 'LinkedIn Ads', 'tags' => ['Webinar', 'Export']])
            ->assertOk()->assertJson(['ok' => true, 'new' => 1]);
        $l = Lead::sole();
        $this->assertSame('LinkedIn Ads', $l->source);
        $this->assertSame(['LinkedIn Ads', 'Webinar', 'Export'], $l->tags);

        $t = $this->on('tradeindia');
        $this->post("/webhook/leads/tradeindia/{$t->token}", ['sender_name' => 'X', 'sender_mobile' => '9000022222', 'source' => 'Fake'])->assertOk();
        $this->assertSame('TradeIndia', Lead::latest('id')->first()->source);
        $this->get("/webhook/leads/tradeindia/{$t->token}?sender_mobile=9000033333")->assertStatus(405);
    }

    public function test_enquiry_without_phone_or_email_is_skipped_and_field_names_shown(): void
    {
        $c = $this->on('custom');
        $this->postJson("/webhook/leads/custom/{$c->token}", ['naam' => 'Someone', 'number_x' => '12'])->assertOk();
        $this->assertSame(0, Lead::count());
        $c->refresh();
        $this->assertStringContainsString('naam, number_x', $c->last_error);
        $this->assertSame('naam, number_x', $c->last_fields);
        $this->assertStringNotContainsString('Someone', $c->last_error.$c->last_fields, 'only field names are kept, never values');
    }

    public function test_choose_who_gets_leads_per_platform_and_unassigned_goes_to_admin(): void
    {
        $j = $this->on('justdial', [], ['assign_to' => [$this->repB->id]]);
        CompanySetting::current()->update(['automations' => ['auto_assign_new_leads' => false]]);
        CompanySetting::forget();
        $this->get("/webhook/leads/justdial/{$j->token}?leadid=1&name=A&mobile=9000044441")->assertOk();
        $this->assertSame($this->repB->id, Lead::sole()->owner_id);

        $c = $this->on('custom');
        $this->postJson("/webhook/leads/custom/{$c->token}", ['name' => 'B', 'phone' => '9000044442'])->assertOk();
        $l = Lead::latest('id')->first();
        $this->assertNull($l->owner_id);
        $this->assertSame(1, $this->admin->notifications()->where('data->kind', 'new_lead')->count());
        $this->actingAs($this->admin)->get('/notifications')->assertOk()->assertSee('New lead: B');
    }

    public function test_website_form_tags_google_ads_google_search_and_facebook(): void
    {
        $this->assertSame('Google Ads', WebsiteSource::classify(['gclid' => 'x'])[0]);
        $this->assertSame('Google Ads', WebsiteSource::classify(['utm_source' => 'google', 'utm_medium' => 'cpc'])[0]);
        $this->assertSame('Google (search)', WebsiteSource::classify(['referrer' => 'https://www.google.co.in/'])[0]);
        $this->assertSame('Facebook Ads', WebsiteSource::classify(['fbclid' => 'x'])[0]);
        $this->assertSame('Instagram Ads', WebsiteSource::classify(['utm_source' => 'instagram', 'utm_medium' => 'paid'])[0]);
        $this->assertSame('Website', WebsiteSource::classify(['referrer' => 'https://example.com/'])[0]);

        CompanySetting::current()->update(['lead_capture_token' => str_repeat('w', 40)]);
        CompanySetting::forget();
        $this->post('/webhook/lead/'.str_repeat('w', 40), ['name' => 'Gaurav', 'phone' => '9000055555', 'message' => 'Need list', 'gclid' => 'abc', 'utm_campaign' => 'Brand search'])->assertOk();
        $l = Lead::sole();
        $this->assertSame('Google Ads', $l->source);
        $this->assertSame(['Google Ads', 'Website', 'Brand search'], $l->tags);
        $this->post('/webhook/lead/'.str_repeat('w', 40), ['name' => 'Gaurav', 'phone' => '9000055555', 'message' => 'Need list'])->assertOk();
        $this->assertSame(1, Lead::count(), 'the same form sent twice is one lead');
    }

    public function test_team_adds_tags_and_reference_and_filters_by_tag(): void
    {
        $this->actingAs($this->repA)->post('/leads', ['firm_name' => 'Ref Firm', 'phone' => '9000066666', 'status' => 'new', 'source' => 'Reference',
            'referred_by' => 'Dr. Mehta, Indore', 'tags' => 'PCD, North zone, pcd'])->assertRedirect();
        $l = Lead::sole();
        $this->assertSame(['PCD', 'North zone'], $l->tags);
        $this->assertSame('Dr. Mehta, Indore', $l->referred_by);
        $this->get('/leads?tag=PCD')->assertSee('Ref Firm');
        $this->get('/leads?tag=Other')->assertDontSee('Ref Firm');
        $this->get("/leads/{$l->id}")->assertSee('Dr. Mehta, Indore')->assertSee('North zone');
        $this->actingAs($this->repB)->get('/leads?tag=PCD')->assertDontSee('Ref Firm');

        $this->actingAs($this->admin)->get('/leads/export?format=csv')->assertOk();
    }

    public function test_normalizer_reads_many_field_names(): void
    {
        $n = new Normalizer;
        $d = $n->generic(['Contact Person' => 'x', 'Mobile_Number' => '98 765 43210', 'EMAIL_ID' => 'A@B.IN', 'Firm_Name' => 'F', 'location' => 'Goa', 'id' => 7]);
        $this->assertSame(['9876543210', 'a@b.in', 'F', 'Goa', '7'], [$d->phone, $d->email, $d->firm, $d->city, $d->externalId]);
        $this->assertNull($n->generic(['phone' => '12', 'email' => 'not-an-email'])->phone);
        $this->assertSame([['a' => 1]], $n->records(['RESPONSE' => ['a' => 1]]));
        $this->assertSame([['a' => 1], ['a' => 2]], $n->records(['data' => [['a' => 1], ['a' => 2]]]));
    }
}
