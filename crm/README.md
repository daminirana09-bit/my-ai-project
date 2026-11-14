# Sales CRM for pharma manufacturers

© 2026 Solvix Web · Designed & developed by **Solvix Web** · Damini Rana · +91 82190 60177

One installation per company. Each company sets its own name, logo, timings and team.

**What it does today**

- **Dashboard:** leads, customers, orders, sales, leads not updated in 30 days, dead leads;
  monthly target strip; team report (total, mature, hot, warm, cold, follow-up, dead);
  follow-ups (pending, overdue, today, next 7 days, updated today); reminders; lead mix;
  task counts (self and team); latest leads; attendance today; open clashes.
- **Leads:** status + hot/warm/cold, search (name, city, GSTIN, full phone, full email),
  filters, call/WhatsApp/visit log, follow-ups, tasks per client, one-click **Excel/CSV
  import and export** (Google Sheets via downloaded .xlsx/.csv).
- **Customers:** won leads with orders, business and reorder alert (no order in 30 days).
- **Products:** composition, form, packing, category, rate, MOQ, HSN, GST; Excel import/export.
- **Tasks:** for self or team, priority, due date, status (pending / in progress / complete).
- **Targets & orders:** monthly target per salesperson, sales done, still to achieve, pending
  orders, calls today and this week.
- **Analytics:** leads and sales per month, source-wise and salesperson-wise conversion.
- **Lead clash:** same client (phone or GSTIN) with two salespeople → admin + managers decide.
- **Attendance:** punch in/out; company and personal office/lunch timing (Office hours);
  full day, half day, absent, single swipe, late; monthly register; admin corrections.
- **Admin:** users, roles & permissions, masters (sources, client types, lost reasons,
  product categories), company settings, activity log.
- **WhatsApp inbox (Meta Cloud API):** each salesperson their own number; chats come in
  through a signed webhook; the client's language and request are detected and ready replies
  are offered in that language (14 Indian languages, editable under Ready replies).
- **Away cover:** a rep marks half day, leave or meeting, or simply is not at work (no punch-in an
  hour after start, or punched out and gone), or it is after office hours / weekly off; clients who
  message get an automatic reply in their language with the time the rep is back, urgent messages alert the backup person and managers, and on return
  the rep sees who is waiting for a call first. STOP opts a client out of marketing.
- **Quotations:** GST quotation from the product list, PDF, sent on WhatsApp in one click.
- **Marketing:** approved WhatsApp templates to a filtered group of clients, sent in the background.
- **Automations:** auto-reply, after-hours reply, auto-assign new leads, daily tasks for
  stale leads, late follow-up alerts, urgent alerts (each can be switched off).
- **Lead integrations:** IndiaMART (API key fetch + push), TradeIndia, ExportersIndia, JustDial,
  Facebook & Instagram lead ads, Google Ads lead forms, any portal API link, and other apps
  (Zapier, Pabbly, LinkedIn, IVR). Every lead is tagged with its platform; repeats are ignored;
  a known client gets a note instead of a second lead.
- **Email inbox:** enquiry mails from any portal or client become leads (IMAP, read-only), tagged with the portal.
- **Light / dark theme:** each person picks light, dark or same as their device.
- **Website enquiry form:** a secret link; tags Google Ads, Google search, Facebook/Instagram ads.
- **Tags:** source and tags on every lead (reference, walk-in…), filter by source or tag, in export/import.
- **System check** page for hosting (PHP, extensions, HTTPS, cron, queue, WhatsApp, 2-step login).
- **Global search** in the top bar. **Languages:** English, Hinglish, Hindi per user.
- **Who sees what:** admin everything; manager own team; sales rep only own data.
- **Rule 1 (data never leaks):** see `../docs/RULE-1-DATA-PROTECTION.md`.

**Run locally**

```sh
composer install
cp .env.example .env && php artisan key:generate
# for a quick local try, use SQLite: set DB_CONNECTION=sqlite in .env and touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=DemoSeeder   # sample data, local only (password: demo12345pass)
php artisan serve
php artisan test
```

**Install for a company:** build the zip with `tools/make-release.sh` (includes `vendor/`, no
tests, no `.env`), then follow `../docs/INSTALL-HOSTINGER.md`.

**Tests:** `php artisan test` (114 feature tests) and the browser run in `tests/e2e/README.md`.
