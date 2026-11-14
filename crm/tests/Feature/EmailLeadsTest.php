<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadIntegration;
use App\Services\Leads\EmailLeadParser;
use App\Services\Leads\ImapMailbox;
use App\Services\Leads\MimeMessage;
use App\Services\Leads\Normalizer;
use App\Services\Leads\PullFailed;

/** Enquiries that come by email: portals, forwards and clients writing directly. The mailbox is faked. */
class EmailLeadsTest extends CrmTestCase
{
    private const BOX = 'leads@dermain.test';

    private function parse(string $raw, string $rules = ''): array
    {
        return (new EmailLeadParser(new Normalizer))->parse(MimeMessage::parse($raw), self::BOX, $rules);
    }

    public static function indiamartMail(string $id = '<im-1@indiamart.com>'): string
    {
        $html = '<html><body><p>Dear Supplier,</p><table><tr><td>Buyer Name</td><td>Ravi Kumar</td></tr>'
            .'<tr><td>Mobile</td><td>+91-98765-00001</td></tr><tr><td>Email</td><td>ravi.k@example.in</td></tr>'
            .'<tr><td>Company</td><td>Ravi Medicos</td></tr><tr><td>City</td><td>Lucknow</td></tr>'
            .'<tr><td>Requirement</td><td>Need PCD franchise for gastro range &amp; antibiotics</td></tr></table>'
            .'<p>Call IndiaMART at 096-9696-9696</p></body></html>';

        return "From: IndiaMART <buyleads@indiamart.com>\r\nTo: ".self::BOX."\r\nSubject: =?UTF-8?B?".base64_encode('Enquiry for Pantoprazole Tablets')."?=\r\n"
            ."Message-ID: {$id}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"b1\"\r\n\r\n"
            ."This is a multi-part message\r\n--b1\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            .chunk_split(base64_encode($html))."--b1--\r\nepilogue text\r\n";
    }

    public function test_portal_mail_with_html_table(): void
    {
        [$d, $why] = $this->parse(self::indiamartMail());
        $this->assertSame('', $why);
        $this->assertSame(['IndiaMART', 'Ravi Kumar', '+919876500001', 'ravi.k@example.in', 'Ravi Medicos', 'Lucknow'],
            [$d->source, $d->name, $d->phone, $d->email, $d->firm, $d->city]);
        $this->assertSame('Need PCD franchise for gastro range & antibiotics', $d->message);
        $this->assertSame('Enquiry for Pantoprazole Tablets', $d->product);
        $this->assertSame(['IndiaMART', 'Email'], $d->tags);
        $this->assertStringStartsWith('m:', $d->externalId);
    }

    public function test_pharma_portal_plain_text_quoted_printable_with_admin_rule(): void
    {
        $raw = "From: PharmaFranchiseMart <noreply@pharmafranchisemart.com>\nSubject: New PCD enquiry\nMessage-ID: <p1@pfm>\n"
            ."Content-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: quoted-printable\n\n"
            ."Hello,=0A=0AContact Person : Sunita Verma=0AMobile No. : 9811122233=0AState : Bihar=0ACity: Patna=0A"
            ."Products Interested: Cardiac + Diabetic range=0AMessage: Looking for monopoly rights in Patna=\n district=0A";
        [$d] = $this->parse($raw, 'pharmafranchisemart = PharmaFranchiseMart');
        $this->assertSame(['PharmaFranchiseMart', 'Sunita Verma', '9811122233', 'Patna', 'Bihar', 'Cardiac + Diabetic range'],
            [$d->source, $d->name, $d->phone, $d->city, $d->state, $d->product]);
        $this->assertSame('Looking for monopoly rights in Patna district', $d->message);
        // Without the rule: the sender is a no-reply address and not a known portal, so it is not trusted
        $this->assertNull($this->parse($raw)[0]);
    }

    public function test_client_writing_directly(): void
    {
        $raw = "From: \"Ravi Kumar\" <Ravi.Kumar@gmail.com>\nTo: ".self::BOX."\nSubject: Re: rate list\nMessage-ID: <c1@mail.gmail.com>\n"
            ."Content-Type: text/plain; charset=UTF-8\n\nPlease send the rate list of your tablets.\nMy number is 98765 43210.\n\nOn Mon, 10 Nov 2026 someone wrote:\n> old text\n";
        [$d] = $this->parse($raw);
        $this->assertSame(['Email', 'Ravi Kumar', 'ravi.kumar@gmail.com', '9876543210', 'rate list'], [$d->source, $d->name, $d->email, $d->phone, $d->product]);
        $this->assertSame("Please send the rate list of your tablets.\nMy number is 98765 43210.", $d->message);
    }

    public function test_mail_forwarded_from_the_company_uses_the_original_sender(): void
    {
        $raw = "From: Damini <damini@dermain.test>\nTo: ".self::BOX."\nSubject: Fwd: New enquiry\nMessage-ID: <f1@dermain.test>\n"
            ."Content-Type: text/plain; charset=UTF-8\n\nFYI\n\n---------- Forwarded message ---------\nFrom: Sulekha <enquiry@sulekha.com>\n"
            ."Date: Fri, 13 Nov 2026\nSubject: New enquiry\nTo: Damini <damini@dermain.test>\n\nName: Amit Shah\nPhone: 91234 56780\nCity: Surat\n";
        [$d] = $this->parse($raw);
        $this->assertSame(['Sulekha', 'Amit Shah', '9123456780', 'Surat'], [$d->source, $d->name, $d->phone, $d->city]);
        $this->assertNull($d->email, 'the company\'s own address is never taken as the client');
    }

    public function test_newsletters_own_mail_and_mails_without_contact_are_skipped(): void
    {
        $news = "From: Offers <offers@shop.example>\nList-Unsubscribe: <mailto:x@shop.example>\nSubject: Sale\nContent-Type: text/plain\n\nCall 9876543210 for 50% off";
        $this->assertSame('newsletter or automatic mail', $this->parse($news)[1]);
        $own = "From: ".self::BOX."\nSubject: test\nContent-Type: text/plain\n\nhello 9876543210";
        $this->assertSame('own mail', $this->parse($own)[1]);
        $bounce = "From: MAILER-DAEMON@mx.example\nSubject: Undelivered\nContent-Type: text/plain\n\nfailed";
        $this->assertNotSame('', $this->parse($bounce)[1]);
        $portalNoContact = "From: IndiaMART <buyleads@indiamart.com>\nSubject: Enquiry\nContent-Type: text/plain\n\nLog in to IndiaMART to see the buyer details.";
        $this->assertSame('no phone number or email found', $this->parse($portalNoContact)[1]);
    }

    public function test_other_character_sets_and_hindi_subject(): void
    {
        $raw = "From: =?UTF-8?B?".base64_encode('राकेश शर्मा')."?= <rakesh@example.in>\nSubject: =?UTF-8?Q?=E0=A4=A6=E0=A4=B5=E0=A4=BE_list?=\n"
            ."Content-Type: text/plain; charset=windows-1252\nContent-Transfer-Encoding: quoted-printable\n\nCaf=E9 owner. Mobile: 9000012345\n";
        [$d] = $this->parse($raw);
        $this->assertSame('राकेश शर्मा', $d->name);
        $this->assertSame('दवा list', $d->product);
        $this->assertStringContainsString('Café owner', $d->message);
        $this->assertSame('9000012345', $d->phone);
    }

    public function test_mailbox_fetch_creates_tagged_leads_once_and_remembers_where_it_stopped(): void
    {
        $fake = new class extends ImapMailbox
        {
            public array $mail = [];

            public array $calls = [];

            public bool $fail = false;

            public function fetchNew(string $host, int $port, string $user, string $password, int $uidValidity, int $lastUid): array
            {
                $this->calls[] = [$host, $port, $user, $password, $uidValidity, $lastUid];
                if ($this->fail) {
                    throw new PullFailed('Could not open the mailbox: AUTHENTICATIONFAILED. Check the server, email address and password.');
                }
                $new = array_filter($this->mail, fn ($k) => $uidValidity !== 77 || $k > $lastUid, ARRAY_FILTER_USE_KEY);

                return ['uidvalidity' => 77, 'last_uid' => $new ? max(array_keys($new)) : $lastUid, 'messages' => $new];
            }
        };
        $this->app->instance(ImapMailbox::class, $fake);
        $fake->mail = [
            5 => self::indiamartMail(),
            6 => "From: Offers <offers@shop.example>\nList-Unsubscribe: <x>\nSubject: Sale\nContent-Type: text/plain\n\nbuy now 9876543210",
            7 => "From: Neha <neha@example.in>\nSubject: Franchise\nMessage-ID: <n1@x>\nContent-Type: text/plain\n\nInterested in PCD. 9988776655",
        ];
        $this->actingAs($this->admin)->post('/integrations/email', ['enabled' => 1, 'imap_host' => '93.184.216.34', 'imap_port' => '993',
            'imap_user' => self::BOX, 'imap_password' => 'mail-pass-123'])->assertSessionHas('ok');

        $this->post('/integrations/email/fetch')->assertSessionHas('ok');
        $this->assertSame([['93.184.216.34', 993, self::BOX, 'mail-pass-123', 0, 0]], $fake->calls);
        $this->assertSame(2, Lead::count());
        $im = Lead::where('source', 'IndiaMART')->sole();
        $this->assertSame(['IndiaMART', 'Email'], $im->tags);
        $this->assertSame('Email', Lead::where('firm_name', 'Neha')->sole()->source);
        $i = LeadIntegration::for('email');
        $this->assertSame([77, 7], [$i->conf('uidvalidity'), $i->conf('last_uid')]);

        // Next run continues after mail 7; the same mail again never makes a second lead
        $this->travel(6)->minutes();
        $fake->mail[8] = self::indiamartMail(); // same Message-ID re-delivered
        $this->post('/integrations/email/fetch')->assertSessionHas('ok');
        $this->assertSame(77, $fake->calls[1][4]);
        $this->assertSame(7, $fake->calls[1][5]);
        $this->assertSame(2, Lead::count());

        // Wrong password: shown on the card
        $this->travel(6)->minutes();
        $fake->fail = true;
        $this->post('/integrations/email/fetch')->assertSessionHasErrors('fetch');
        $this->assertStringContainsString('AUTHENTICATIONFAILED', LeadIntegration::for('email')->last_error);
        $this->get('/integrations')->assertSee('Needs attention')->assertDontSee('mail-pass-123');
    }

    public function test_mailbox_settings_are_checked(): void
    {
        $this->actingAs($this->admin);
        $this->post('/integrations/email', ['enabled' => 1, 'imap_host' => 'https://imap.example.com', 'imap_port' => '993'])->assertSessionHasErrors('api_url');
        $this->post('/integrations/email', ['enabled' => 1, 'imap_host' => '127.0.0.1', 'imap_port' => '993'])->assertSessionHasErrors('api_url');
        $this->post('/integrations/email', ['enabled' => 1, 'imap_host' => '93.184.216.34', 'imap_port' => '25'])->assertSessionHasErrors('api_url');
        $this->post('/integrations/email', ['enabled' => 1, 'imap_host' => '93.184.216.34', 'imap_port' => '993', 'imap_user' => 'not-an-email'])->assertSessionHasErrors('api_url');
    }

    public function test_website_and_ivr_field_names_are_understood(): void
    {
        $n = new Normalizer;
        $elementor = $n->generic(['Your Name' => 'Kiran', 'Mobile Number' => '98989 89898', 'Your Email' => 'k@x.in', 'Your Message' => 'Need list']);
        $this->assertSame(['Kiran', '9898989898', 'k@x.in', 'Need list'], [$elementor->name, $elementor->phone, $elementor->email, $elementor->message]);
        $cf7 = $n->generic(['your-name' => 'Asha', 'your-email' => 'asha@x.in', 'your-subject' => 'Rate', 'your-message' => 'Hi']);
        $this->assertSame(['Asha', 'asha@x.in', 'Rate'], [$cf7->name, $cf7->email, $cf7->product]);
        $ivr = $n->generic(['CallSid' => 'abc', 'CallFrom' => '09812345678', 'CallTo' => '0800']);
        $this->assertSame('09812345678', $ivr->phone);
    }
}
