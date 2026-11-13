# Browser end-to-end test

Runs the whole CRM through a real browser, starting from an empty database:
setup → team → targets → Hindi rep punches in, adds a lead, logs a call, records a sale →
second rep hits a lead clash and imports an Excel file → manager resolves the clash,
confirms the sale, exports → admin deactivates a rep and moves their leads → checks the
credit footer, error pages, mobile layout and that nothing breaks.

```sh
# needs MySQL/MariaDB, a .env.e2e pointing at an empty test database, Node + Playwright
php tests/e2e/make-import-file.php /tmp/e2e/e2e-import.xlsx
for run in 1 2 3 4 5; do
  APP_ENV=e2e php artisan migrate:fresh --force
  node tests/e2e/e2e.js /tmp/e2e $run      # server: APP_ENV=e2e php artisan serve --port=8124
done
```
