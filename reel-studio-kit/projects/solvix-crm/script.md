# solvix-crm — promo for "Pharma Sales CRM by Solvix Web"

Structure: 6 problems of a pharma sales team -> each fixed on REAL CRM screens.
Every feature named in the voice is in the CRM README (branch claude/zen-feynman-pkptd6, crm/README.md).

How the screens were made (reproducible):
- CRM run locally from branch claude/zen-feynman-pkptd6 with SQLite and `php artisan db:seed --class=DemoSeeder`
  (demo company "Demo Pharma Pvt. Ltd.", demo users, no real client data).
- Features performed with Playwright on a 430x932 mobile viewport @2x: filter leads by IndiaMART, log a call with
  next follow-up, view lead clash + clashes page, set rep away (leave), create a GST quotation (rate/GST filled from
  the product list) and download its PDF, targets / attendance / analytics, admin vs rep lead lists.
- WhatsApp: two client messages pushed through the CRM's own Inbound service (language + intent detection, Hindi
  away auto-reply, urgent alert, waiting-for-call). Only the call to Meta was stubbed (no WhatsApp account locally).
Screens: public/reels/solvix-crm/shots. Logo: CRM public/brand/solvix-logo.png.

Problems -> fixes
1 Leads scattered (IndiaMART, JustDial, Facebook) -> lead integrations, platform tag on every lead
2 Forgotten follow-ups -> today / overdue / next-7-days follow-ups, call log with next follow-up
3 Two reps on one client -> lead clash by phone/GSTIN, admin + managers decide
4 Rep on leave, client messages -> away cover: auto reply in the client's language, urgent alert to manager/backup
5 Slow quotations -> GST quotation from the product list, PDF, send on WhatsApp
6 No visibility -> targets, attendance, analytics; each rep sees only own data
CTA: comment CRM -> demo.
Voice: George (free premade), sped up 10% to stay under 60 s. Music/SFX: Mixkit free licences.
