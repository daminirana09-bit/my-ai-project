# Sales CRM for pharma manufacturers

© 2026 Solvix Web · Designed & developed by **Solvix Web** · Damini Rana · +91 82190 60177

One installation per company. Each company sets its own name, logo, timings and team.

**What it does today**

- **Leads:** status for every lead, filters, search, call/WhatsApp/visit log, follow-ups,
  one-click **Excel/CSV import and export** (Google Sheets via downloaded .xlsx/.csv).
- **Who sees what:** admin sees everything; a manager sees their own team; a sales rep sees
  only their own leads and numbers.
- **Lead clash:** if two salespeople add, import or work the same client (same phone or
  GSTIN), the lead is not duplicated and admin + managers get an alert to decide who keeps it.
- **Targets & sales:** monthly target per salesperson, sales done, still to achieve, pending
  orders, calls today and this week.
- **Attendance:** punch in / punch out in the CRM; company and personal office/lunch timing;
  full day, half day, absent, single swipe, late; monthly register; admin corrections.
- **Languages:** each user picks English, Hinglish or Hindi.
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

**Install for a company:** see `../docs/INSTALL-HOSTINGER.md`.
