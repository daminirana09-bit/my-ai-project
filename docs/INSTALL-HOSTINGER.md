# Installing the Pharma Sales CRM on Hostinger

© Solvix Web · Damini Rana · +91 82190 60177

Each pharma company gets its **own** installation, on its own hosting, with its own database.

## 1. What the hosting needs

- A plan with **SSH** or at least **File Manager + Cron Jobs** (Hostinger Premium or Business).
- PHP **8.3** (hPanel → Advanced → PHP Configuration). Extensions: pdo_mysql, mbstring, openssl,
  gd, zip, xml, intl, fileinfo, curl (all on by default at Hostinger).
- One MySQL database (hPanel → Databases → Management).
- **HTTPS** on the sub-domain (hPanel → Security → SSL; the free SSL is fine).

## 2. Upload

Use the ready package `pharma-sales-crm-<version>.zip` (made with `crm/tools/make-release.sh`;
it already contains `vendor/`, so Composer is not needed on the server).

1. Create the sub-domain, e.g. `crm.company.com` (hPanel → Domains → Subdomains).
2. Upload the zip to the sub-domain's folder and extract it there.
3. **Best:** set the sub-domain's document root to the `public` folder inside it.
   **If that is not possible:** leave it as is. The `.htaccess` in the main folder sends every
   request into `public/`, so `.env`, `storage` and `vendor` can never be opened from a browser.

## 3. Settings file

1. Copy `.env.example` to `.env` (File Manager → Copy, or `cp .env.example .env`).
2. Fill in `APP_URL=https://crm.company.com` and `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
3. Over SSH run:
   ```sh
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   chmod 600 .env
   ```
   Without SSH: hPanel → Advanced → Cron Jobs can run each command once as a one-time job.

**Keep a copy of `.env` somewhere safe.** `APP_KEY` inside it decrypts phone numbers, emails,
messages and the WhatsApp keys. Without it, even a backup cannot be read.

## 4. Cron (one line, every minute)

hPanel → Advanced → Cron Jobs → every minute:
```
cd /home/USER/domains/crm.company.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```
This one line runs: WhatsApp campaigns in the background, the nightly attendance close,
and the morning automations (follow-up tasks, overdue alerts).

## 5. First login

Open `https://crm.company.com`. The setup page asks for the company name, GSTIN, office timing and
the first admin. Then, as admin:

1. **System check** (sidebar → Admin) must show no red rows.
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

## 7. Website enquiry form

WhatsApp settings shows a private link like `https://crm.company.com/webhook/lead/XXXX`.
Point the website's contact form at it (method POST) with fields `name`, `phone`, `email`, `firm`,
`city`, `message`. Optional `redirect` = thank-you page URL. Leave a hidden empty field named
`website` in the form: bots fill it and are ignored.

```html
<form method="post" action="https://crm.company.com/webhook/lead/XXXX">
  <input name="name" required> <input name="phone" required> <input name="email" type="email">
  <input name="firm"> <input name="city"> <textarea name="message"></textarea>
  <input name="website" style="display:none" tabindex="-1" autocomplete="off">
  <input type="hidden" name="redirect" value="https://company.com/thank-you">
  <button>Send</button>
</form>
```

## 8. Lock it down (Rule 1)

- Put the office's fixed IP (and VPN IP, if any) in `CRM_ALLOWED_IPS` in `.env`, then
  `php artisan config:cache`. The WhatsApp webhook and website form keep working from anywhere.
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
