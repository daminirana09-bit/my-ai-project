# Installing the Pharma Sales CRM on Hostinger

© Solvix Web · Damini Rana · +91 82190 60177

Each pharma company gets its **own** installation, on its own hosting, with its own database.

## 1. What the hosting needs

- A plan with **SSH** or at least **File Manager + Cron Jobs** (Hostinger Premium or Business).
- PHP **8.3** (hPanel → Advanced → PHP Configuration). Extensions: pdo_mysql, mbstring, openssl,
  gd, zip, xml, intl, fileinfo, curl (all on by default at Hostinger).
- One MySQL database (hPanel → Databases → Management).
- **HTTPS** on the sub-domain (hPanel → Security → SSL; the free SSL is fine).

## 2. Sub-domain and upload

1. hPanel → **Domains → Subdomains**: create one with a name that is hard to guess, e.g.
   `team-x7k.company.com` rather than `crm.company.com`. Keep the folder Hostinger suggests.
2. hPanel → **Security → SSL**: make sure the sub-domain has the free SSL (HTTPS). Wait until it shows *Active*.
3. hPanel → **Databases → Management**: create a database and a user with a strong password. Note the
   three values (database name, user, password); Hostinger adds a prefix like `u123456789_`.
4. **File Manager** → open the sub-domain's folder → delete the default `default.php` / `index.html`
   → upload `pharma-sales-crm-<version>.zip` → right-click → **Extract** into the same folder.
   (The zip already contains `vendor/`; Composer is not needed.)
5. The `.htaccess` in that folder sends every request into `public/`, so `.env`, `storage` and
   `vendor` can never be opened from a browser. (If your plan lets you set the sub-domain's document
   root to the `public` folder, that is even better.)

### "Not public": what is and is not possible

A sub-domain is on the internet, and it has to be: WhatsApp, IndiaMART, Facebook and the website
form must reach it, and salespeople in the field open it on mobile data. What the CRM does instead:

- Every page needs a login; no client data is shown to anyone without one (Rule 1).
- Search engines are told not to list it (`robots.txt` and a `noindex` header on every reply).
- Setup is locked with a code only you know (`CRM_SETUP_CODE`, step 3), so nobody who finds the
  address before you can make themselves admin.
- Wrong passwords lock that email for a while; 2-step login for admins and managers; secrets encrypted.
- Optional: `CRM_ALLOWED_IPS` lets only the office IP open the CRM. Use it only if everyone works
  from the office: it also blocks salespeople on mobile data. Webhooks keep working.

## 3. Settings file

1. File Manager → copy `.env.example` to `.env` → **Edit** `.env`:
   - `APP_URL=https://team-x7k.company.com`
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from step 2.3 (`DB_HOST=localhost` on Hostinger)
   - `CRM_SETUP_CODE=` any secret word of 8+ characters (asked once on the setup page)
2. Over SSH (hPanel → Advanced → SSH Access), in the sub-domain's folder, run:
   ```sh
   php artisan key:generate --force
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   chmod 600 .env
   ```
   Without SSH: hPanel → Advanced → Cron Jobs can run each command once as a one-time job
   (`cd /full/path/to/folder && php artisan key:generate --force`, and so on).
   After any later change to `.env`, run `php artisan config:cache` again.

**Keep a copy of `.env` somewhere safe.** `APP_KEY` inside it decrypts phone numbers, emails,
messages and the WhatsApp keys. Without it, even a backup cannot be read.

## 4. Cron (one line, every minute)

hPanel → Advanced → Cron Jobs → type **Custom** → Minute, Hour, Day, Month, Weekday all
"Every… (*)" → command (full paths; `USER` and the folder as File Manager shows them):
```
/opt/alt/php83/usr/bin/php /home/USER/domains/company.com/public_html/crm/artisan schedule:run >> /home/USER/domains/company.com/public_html/crm/storage/logs/cron.log 2>&1
```
This one line runs: WhatsApp campaigns in the background, fetching leads (IndiaMART, email…),
the nightly attendance close and the morning automations (follow-up tasks, overdue alerts).
Everything runs inside that one PHP process, so it works on hosting where `proc_open` is
turned off (Hostinger). **System check** shows "Cron runs every minute" and "Background tasks run"
in green within two minutes; errors are in `storage/logs/`.

## 5. First login

Open `https://team-x7k.company.com`. The setup page asks for the setup code from `.env`, the
company name, GSTIN, office timing and the first admin. Then, as admin:

1. **System check** (sidebar → Admin) must show no red rows.
   Also open `https://team-x7k.company.com/.env` in a browser: it must show an error page, not the file.
2. **My profile → 2-step login**: turn it on for every admin and manager.
3. **Users**: add the team (role, manager, language, personal timing).
4. **Masters, Products, Office hours, Roles & permissions, Automations**: set to your needs.

## 6. WhatsApp (Meta Cloud API)

In Meta Business Manager (business.facebook.com):

1. Verify the business. Create an app of type **Business** and add the **WhatsApp** product.
2. Add each salesperson's number to the WhatsApp Business Account (use Meta's *coexistence*
   option if they want to keep using the WhatsApp Business app on their phone).
3. Create a **System user** with a **permanent access token** (permissions:
   `whatsapp_business_messaging`, `whatsapp_business_management`).
4. App settings → Basic → copy the **App secret**.

In the CRM → **WhatsApp settings**:

1. Paste the access token and app secret, save. They are stored encrypted.
2. Copy the **Callback URL** and **Verify token** shown there into Meta → WhatsApp →
   Configuration → Webhook, and subscribe to **messages**.
3. Add every number with its **Phone number ID** (Meta → WhatsApp → API setup) and choose which
   salesperson it belongs to. A number with no salesperson is a shared company number.
4. Send a test message to a number from your own phone: it must appear in **WhatsApp inbox**.

For **Marketing**, first create message templates in Meta WhatsApp Manager and wait for approval.
In the CRM use the exact template name and language code.

Charges for WhatsApp messages are billed by Meta to the payment method on the WhatsApp Business
Account (it can be your card). Replies within 24 hours of a client's message are free under Meta's
current pricing; templates are charged per message. Check Meta's current price list.

## 7. Lead integrations (IndiaMART, TradeIndia, JustDial, Facebook, Google Ads, website…)

Admin → **Lead integrations**. Each platform has a card: switch it on, paste the key, save.
Every lead is tagged with its platform (IndiaMART, Google Ads, Instagram Ads, Website,
Google (search), Reference…). The same enquiry never becomes two leads; a client already in
the CRM gets an "enquiry again" note and their salesperson is told. "Who gets these leads"
can send one platform's leads to chosen people; otherwise the Automations setting decides.

| Platform | What to do |
|---|---|
| IndiaMART | Seller panel → Lead Manager → CRM integration → generate the **CRM key**, paste it. Leads are fetched every 10 minutes (IndiaMART allows one fetch in 5 minutes). For instant leads also give IndiaMART the receiving link (Push API). |
| TradeIndia | My TradeIndia → Inquiries → CRM API: **User ID, Profile ID, key**. Fetched every 10 minutes. |
| ExportersIndia | Ask their support for the lead API link; paste it with `{from}` and `{to}` where the dates go. Or give them the receiving link. |
| JustDial | Send the receiving link to the JustDial account manager (they push leads to it). |
| Facebook / Instagram lead ads | Meta app → Webhooks → *Page* → field `leadgen`, with the callback URL and verify token shown. Enter Page ID, a Page access token (System user, `leads_retrieval`, `pages_manage_metadata`, `pages_show_list`) and the app secret, save, press **Connect Page**. Instagram leads are tagged Instagram Ads. |
| Google Ads lead forms | Lead form asset → Lead delivery → Webhook: paste URL and key, press *Send test data* (a test proves the link and makes no lead). |
| Any other portal | If a portal gives an API link returning JSON, paste it (with `{from}`/`{to}`) and the name to show as source. |
| Email inbox | For portals and clients that send enquiries by email (Sulekha, pharma franchise portals, Alibaba, IndiaMART mails…). hPanel → **Emails** → create a mailbox such as `leads@company.com`; forward enquiry mails to it (portal settings, or a Gmail filter "forward to"). In the card enter server `imap.hostinger.com`, port 993, the address and its password (Gmail: an app password). Read every 10 minutes; nothing in the mailbox is changed. Add your own portals as rules: `pharmafranchisemart = PharmaFranchiseMart`. |
| Website forms (Elementor, Contact Form 7, WPForms) | Use the website form code, or the form's "Webhook" action with the Other apps receiving link. Field names such as "Mobile Number" or `your-email` are understood. |
| Other apps | Zapier, Pabbly, LinkedIn lead forms (via Zapier), IVR / missed-call systems: POST `name`, `phone`, `email`, `firm`, `city`, `message`, optional `source` and `tags` to the receiving link. |

The **receiving links are secret** (like a password): "Create a new link" stops the old one.
If a platform sends fields with other names, the card shows "Fields received last time"
(names only, never values) so the mapping can be checked. Errors show on the card and in System check.

Reference, walk-in, exhibition, cold call and Google Maps leads are added by the team: pick the
source and, for a reference, fill "Referred by". Any lead can have tags (comma separated) and the
lead list filters by source and by tag.

### Website enquiry form

The Lead integrations page shows a private link like `https://crm.company.com/webhook/lead/XXXX`
and the full form code to paste on the website. Fields: `name`, `phone`, `email`, `firm`, `city`,
`message`; optional `redirect` = thank-you page. The hidden empty `website` field catches bots.
The small script in the code fills `utm_source`, `utm_medium`, `utm_campaign`, `gclid`, `fbclid`
and `referrer`, so a lead is tagged **Google Ads**, **Facebook Ads**, **Instagram Ads**,
**Google (search)** or **Website** correctly.

## 8. Company links, catalogue and reviews

- **Share & reviews** (admin): enter website, phone, email, social pages and the **Google review link**
  (Google Business Profile → "Ask for reviews"). The team then sends the company profile or a review
  request from any lead in one click. For one message to all customers, get a WhatsApp template such as
  `review_request` approved in Meta and use the "Review request to all won customers" button.
- **Catalogue & files**: upload price lists and catalogues (up to 25 MB). Files stay outside the website
  folder; share links end after the chosen days and can be stopped.

## 9. Lock it down (Rule 1)

- Put the office's fixed IP (and VPN IP, if any) in `CRM_ALLOWED_IPS` in `.env`, then
  `php artisan config:cache`. The WhatsApp webhook, lead integrations and website form keep working from anywhere.
- Keep *Export leads* off for sales reps (Roles & permissions) unless really needed.
- hPanel daily backups are on; also download a database backup weekly.

## Updating

```sh
php artisan down
# upload and extract the new zip over the old files (keep .env and storage/)
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Never

- Never set `APP_DEBUG=true` on a live server.
- Never run `php artisan db:seed --class=DemoSeeder` on a live server (it refuses in production).
- Never share `.env` or commit it to Git.
