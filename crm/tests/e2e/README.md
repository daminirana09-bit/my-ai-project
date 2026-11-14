# Browser end-to-end test

Runs the whole CRM through a real browser, starting from an empty database:
setup → team → targets → Hindi rep punches in, adds a lead, logs a call, records a sale →
second rep hits a lead clash and imports an Excel file → manager resolves the clash,
confirms the sale, exports → admin deactivates a rep and moves their leads → checks the
credit footer, error pages, mobile layout and that nothing breaks. Then WhatsApp: admin saves
Meta keys and a rep's number, the webhook verification and signature check are tried, a website
enquiry becomes a lead, the rep goes on leave, a Hindi message arrives (auto-reply, duplicate
ignored), the rep comes back, sees Hindi ready replies, replies, makes a quotation and its PDF. Finally the admin switches on JustDial, Google Ads and "other apps", sends a lead to each, and checks the source tags and the tag and source filters.
Messages to Meta use a fake token, so they are recorded as failed; that path is tested too.

```sh
# needs MySQL/MariaDB, a .env.e2e pointing at an empty test database, Node + Playwright
php tests/e2e/make-import-file.php /tmp/e2e/e2e-import.xlsx
for run in 1 2 3 4 5; do
  APP_ENV=e2e php artisan migrate:fresh --force
  node tests/e2e/e2e.js /tmp/e2e $run      # server: APP_ENV=e2e php artisan serve --port=8124
done
```
