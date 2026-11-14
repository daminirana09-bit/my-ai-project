<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CompanySetting;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WhatsappNumber;
use App\Services\Sheets;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Catalogue files, every spreadsheet format, PDF price list, company links and review requests. */
class CatalogueShareTest extends CrmTestCase
{
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        CompanySetting::current()->update(['whatsapp_config' => ['access_token' => 'tok', 'app_secret' => 'sec', 'verify_token' => 'vt', 'api_version' => 'v23.0']]);
        CompanySetting::forget();
        $no = WhatsappNumber::create(['label' => 'Amit', 'display_phone' => '+91 90000 00001', 'phone_number_id' => '100000000000001', 'user_id' => $this->repA->id, 'active' => true]);
        $this->lead = Lead::create(['firm_name' => 'Ravi Medicos', 'contact_name' => 'Ravi', 'phone' => '9876500001', 'status' => 'won', 'owner_id' => $this->repA->id]);
        WaConversation::create(['whatsapp_number_id' => $no->id, 'wa_id_hash' => Lead::hashPhone('919876500001'), 'wa_id' => '919876500001',
            'lead_id' => $this->lead->id, 'last_inbound_at' => now()->subHour(), 'last_message_at' => now()->subHour()]);
        Http::preventStrayRequests();
        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'media-1']),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.X'.uniqid()]]]),
        ]);
    }

    private function pdf(string $name = 'price-list.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    public function test_upload_is_private_seen_by_team_and_only_safe_types_are_allowed(): void
    {
        $this->actingAs($this->repA)->post('/catalogue', ['file' => $this->pdf()])->assertForbidden();
        $this->actingAs($this->admin)->post('/catalogue', ['file' => $this->pdf(), 'title' => 'Price list Nov', 'category' => 'Price lists', 'tell_team' => 1])->assertSessionHas('ok');
        $f = ProductFile::sole();
        $this->assertSame(['Price list Nov', 'pdf', 'Price lists', 1], [$f->title, $f->extension, $f->category, $f->version]);
        Storage::disk('local')->assertExists($f->path);
        $this->assertStringStartsWith('catalogue/', $f->path);
        $this->assertSame(1, $this->repB->notifications()->where('data->kind', 'catalogue')->count());
        $this->assertSame(0, $this->admin->notifications()->where('data->kind', 'catalogue')->count(), 'the uploader is not alerted');

        // Every salesperson sees and downloads it
        $this->actingAs($this->repB)->get('/catalogue')->assertOk()->assertSee('Price list Nov');
        $res = $this->get("/catalogue/{$f->id}/download")->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
        $this->assertStringContainsString('Price list Nov.pdf', $res->headers->get('content-disposition'));
        $this->assertStringContainsString('inline', $this->get("/catalogue/{$f->id}/download?open=1")->headers->get('content-disposition'));
        $this->get('/notifications')->assertSee('New file for the team: Price list Nov');

        // Web pages, pictures with code and programs are refused, even when renamed
        $this->actingAs($this->admin);
        foreach ([['x.html', '<script>alert(1)</script>'], ['x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script/></svg>'],
            ['x.php', '<?php echo 1;'], ['fake.pdf', '<?php system($_GET["c"]);'], ['x.exe', "MZ\x90\x00"]] as [$n, $c]) {
            $tmp = tempnam(sys_get_temp_dir(), 'up');
            file_put_contents($tmp, $c);
            $this->post('/catalogue', ['file' => new UploadedFile($tmp, $n, null, null, true)])->assertSessionHasErrors('file'); // real content check, like a real upload
        }
        $this->assertSame(1, ProductFile::count());
    }

    public function test_new_version_replaces_for_everyone_and_share_link_expires(): void
    {
        $this->actingAs($this->admin)->post('/catalogue', ['file' => $this->pdf()]);
        $f = ProductFile::sole();
        $oldPath = $f->path;

        // Share link: works without login, ends after its days, can be stopped
        $this->actingAs($this->repA)->post("/catalogue/{$f->id}/share", ['days' => 7])->assertSessionHas('ok');
        $token = $f->fresh()->share_token;
        $this->assertSame(40, strlen($token));
        auth()->logout();
        $this->get("/f/{$token}")->assertOk();
        $this->get('/f/'.str_repeat('a', 40))->assertNotFound();

        $this->actingAs($this->admin)->post("/catalogue/{$f->id}/replace", ['file' => $this->pdf('price-list-v2.pdf')])->assertSessionHas('ok');
        $f->refresh();
        $this->assertSame(2, $f->version);
        $this->assertSame($token, $f->share_token, 'the link the clients have keeps working');
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertSame(1, $this->repA->notifications()->where('data->kind', 'catalogue')->where('data->updated', true)->count());

        $this->travel(8)->days();
        auth()->logout();
        $this->get("/f/{$token}")->assertNotFound();
        $this->travelBack();
        $this->actingAs($this->repA)->post("/catalogue/{$f->id}/share", ['days' => 30]);
        $this->actingAs($this->repA)->post("/catalogue/{$f->id}/unshare")->assertForbidden();
        $this->actingAs($this->admin)->post("/catalogue/{$f->id}/unshare")->assertSessionHas('ok');
        auth()->logout();
        $this->get('/f/'.$token)->assertNotFound();

        $this->actingAs($this->admin)->delete("/catalogue/{$f->id}")->assertSessionHas('ok');
        Storage::disk('local')->assertMissing($f->path);
    }

    public function test_spreadsheets_in_every_format_fill_the_product_list(): void
    {
        $this->actingAs($this->admin);
        $header = ['Product Name', 'Composition', 'Packing', 'PTR', 'MOQ', 'GST %', 'Category'];
        $xlsx = Sheets::write('xlsx', $header, [['Pan-D', 'Pantoprazole 40 + Domperidone 30', '10x10', '₹ 1,250.50', '10000', '12', 'Gastro']]);
        $this->post('/products/import', ['file' => new UploadedFile($xlsx, 'list.xlsx', null, null, true)])->assertSessionHas('ok');
        $ods = Sheets::write('ods', $header, [['Azee 500', 'Azithromycin 500', '3x10', '95', '5000', '12', 'Antibiotic']]);
        $this->post('/products/import', ['file' => new UploadedFile($ods, 'list.ods', null, null, true)])->assertSessionHas('ok');
        $csv = tempnam(sys_get_temp_dir(), 'c');
        file_put_contents($csv, "\xEF\xBB\xBFName;Packing;Rate\nCalci-D;1x15;45,00\nPan-D;10x10;1300\n"); // Excel in many countries: semicolon + UTF-8 mark
        $this->post('/products/import', ['file' => new UploadedFile($csv, 'list.csv', 'text/csv', null, true)])->assertSessionHas('ok');
        $this->assertSame(3, Product::count());
        $pan = Product::where('name', 'Pan-D')->sole();
        $this->assertSame(['1300.00', 10000, 'Gastro'], [$pan->rate, $pan->moq, $pan->category]);
        $this->assertSame('95.00', Product::where('name', 'Azee 500')->value('rate'));
        $this->post('/products/import', ['file' => UploadedFile::fake()->createWithContent('list.pdf', "%PDF-1.4\n")])->assertSessionHasErrors('file');

        // A spreadsheet uploaded to the catalogue also fills the list
        $more = Sheets::write('xlsx', ['Name', 'Rate'], [['Vit-C', '20']]);
        $this->post('/catalogue', ['file' => new UploadedFile($more, 'more.xlsx', null, null, true)]);
        $this->post('/catalogue/'.ProductFile::sole()->id.'/import')->assertRedirect('/products');
        $this->assertSame(4, Product::count());
    }

    public function test_exports_in_every_format_and_pdf_price_list_for_everyone(): void
    {
        Product::create(['name' => 'Pan-D', 'packing' => '10x10', 'rate' => 125, 'gst_percent' => 12, 'category' => 'Gastro', 'active' => true]);
        Product::create(['name' => 'Old item', 'rate' => 1, 'active' => false]);
        $this->actingAs($this->admin);
        foreach (['xlsx', 'csv', 'ods'] as $fmt) {
            $res = $this->get('/products/export?format='.$fmt)->assertOk();
            $this->assertStringContainsString('.'.$fmt, $res->headers->get('content-disposition'));
            $rows = Sheets::rows($res->getFile()->getPathname(), $fmt);
            $this->assertSame('Name', $rows[0][0]);
            $this->assertContains('Pan-D', array_column($rows, 0));
        }
        $this->actingAs($this->repA)->get('/products/export?format=xlsx')->assertForbidden();
        $pdf = $this->get('/products/export?format=pdf')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Leads too: Excel, CSV and ODS
        $this->actingAs($this->admin);
        $ods = $this->get('/leads/export?format=ods')->assertOk();
        $this->assertContains('Ravi Medicos', array_column(Sheets::rows($ods->getFile()->getPathname(), 'ods'), 0));
    }

    public function test_send_catalogue_file_to_a_client_on_whatsapp(): void
    {
        $this->actingAs($this->admin)->post('/catalogue', ['file' => $this->pdf(), 'title' => 'Catalogue 2026']);
        $this->post('/catalogue', ['file' => UploadedFile::fake()->image('visual.jpg'), 'title' => 'Visualiser']);
        $this->post('/catalogue', ['file' => new UploadedFile(Sheets::write('ods', ['Name'], [['A']]), 'x.ods', null, null, true), 'title' => 'Sheet']);
        [$pdf, $img, $ods] = ProductFile::orderBy('id')->get()->all();

        $this->actingAs($this->repA)->post("/leads/{$this->lead->id}/send-file", ['file_id' => $pdf->id])->assertSessionHas('ok');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/messages') && $r['type'] === 'document' && $r['document']['filename'] === 'Catalogue 2026.pdf');
        $this->post("/leads/{$this->lead->id}/send-file", ['file_id' => $img->id])->assertSessionHas('ok');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/messages') && $r['type'] === 'image');
        $this->post("/leads/{$this->lead->id}/send-file", ['file_id' => $ods->id])->assertSessionHasErrors('share');
        $this->assertSame(2, WaMessage::where('direction', 'out')->count());
        $this->assertSame(2, LeadActivity::where('lead_id', $this->lead->id)->where('note', 'like', 'Sent:%')->count());

        // Not this rep's client: not allowed
        $this->actingAs($this->repB)->post("/leads/{$this->lead->id}/send-file", ['file_id' => $pdf->id])->assertNotFound();
        // More than 24 hours since the client wrote: WhatsApp would refuse
        WaConversation::query()->update(['last_inbound_at' => now()->subDays(2)]);
        $this->actingAs($this->repA)->post("/leads/{$this->lead->id}/send-file", ['file_id' => $pdf->id])->assertSessionHasErrors('share');
    }

    public function test_company_links_profile_and_google_review_requests(): void
    {
        $this->actingAs($this->repA)->post('/share', ['link_website' => 'https://x.in'])->assertForbidden();
        $this->actingAs($this->admin)->post('/share', ['link_website' => 'not a link'])->assertSessionHasErrors('link_website');
        $this->post('/share', ['link_website' => 'https://dermain.example', 'link_facebook' => 'https://facebook.com/dermain', 'link_instagram' => 'https://instagram.com/dermain',
            'website_phone' => '+91 90000 11111', 'website_email' => 'info@dermain.example'])->assertSessionHas('ok');
        CompanySetting::forget();

        // Every salesperson sees the company card
        $this->actingAs($this->repB)->get('/share')->assertOk()->assertSee('https://facebook.com/dermain')->assertSee('info@dermain.example');

        // Company profile goes from Amit's number; the review request needs the Google link first
        $this->actingAs($this->repA)->post("/leads/{$this->lead->id}/share/profile")->assertSessionHas('ok');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/messages') && str_contains($r['text']['body'] ?? '', 'Website: https://dermain.example')
            && str_contains($r['text']['body'], 'Facebook page: https://facebook.com/dermain') && str_contains($r['text']['body'], 'Namaste Ravi'));
        $this->post("/leads/{$this->lead->id}/share/review")->assertSessionHasErrors('share');

        $this->actingAs($this->admin)->post('/share', ['link_google_review' => 'https://g.page/r/abc123/review', 'link_website' => 'https://dermain.example',
            'msg_review' => "Hi {name}, please review {company}: {review}\nCall {phone}"]);
        CompanySetting::forget();
        $this->actingAs($this->repA)->post("/leads/{$this->lead->id}/share/review")->assertSessionHas('ok');
        Http::assertSent(fn (HttpRequest $r) => ($r['text']['body'] ?? '') === "Hi Ravi, please review Test Pharma Pvt Ltd: https://g.page/r/abc123/review\nCall +91 90000 11111");
        $this->assertNotNull($this->lead->fresh()->review_requested_at);
        $this->get("/leads/{$this->lead->id}")->assertSee('Review asked');

        // Outside the 24-hour window: open the salesperson's own WhatsApp with the message ready
        WaConversation::query()->update(['last_inbound_at' => now()->subDays(3)]);
        $this->post("/leads/{$this->lead->id}/share/review")->assertSessionHasErrors('share');
        $res = $this->post("/leads/{$this->lead->id}/share/review", ['own' => 1]);
        $this->assertStringStartsWith('https://wa.me/919876500001?text=Hi%20Ravi', $res->headers->get('location'));

        // A client who said STOP is never messaged
        $this->lead->update(['whatsapp_opt_out' => true]);
        WaConversation::query()->update(['last_inbound_at' => now()]);
        $this->post("/leads/{$this->lead->id}/share/profile")->assertSessionHasErrors('share');
        $this->actingAs($this->repB)->post("/leads/{$this->lead->id}/share/profile")->assertNotFound();
    }

    public function test_review_request_to_all_customers_by_campaign_fills_the_link(): void
    {
        CompanySetting::current()->update(['links' => ['google_review' => 'https://g.page/r/abc123/review']]);
        CompanySetting::forget();
        $c = Campaign::create(['name' => 'Reviews', 'whatsapp_number_id' => WhatsappNumber::first()->id, 'template_name' => 'review_request', 'template_language' => 'en',
            'variables' => ['contact_name', 'review_link'], 'status' => 'sending', 'total' => 1, 'created_by' => $this->admin->id, 'started_at' => now()]);
        CampaignRecipient::create(['campaign_id' => $c->id, 'lead_id' => $this->lead->id]);
        (new \App\Jobs\SendCampaign($c->id))->handle(app(\App\Services\WhatsApp\Client::class));
        Http::assertSent(fn (HttpRequest $r) => ($r['template']['components'][0]['parameters'][1]['text'] ?? '') === 'https://g.page/r/abc123/review');
        $this->assertNotNull($this->lead->fresh()->review_requested_at);
        $this->actingAs($this->admin)->get('/share')->assertSee('template_name=review_request', false);
    }
}
