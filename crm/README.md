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

**Install for a company:** see `../docs/INSTALL-HOSTINGER.md`.
