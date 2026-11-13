# Installing the Sales CRM on Hostinger shared hosting

Each pharma company gets its **own** installation, on its own hosting, with its own database.

## What the hosting needs

- A plan with **SSH access** (Hostinger Premium or Business; the cheapest plan has no SSH).
- PHP **8.3** (hPanel → Advanced → PHP Configuration).
- One MySQL database (hPanel → Databases → Management).
- HTTPS on the domain or sub-domain (hPanel → Security → SSL; Hostinger's free SSL is fine).

## Steps

1. **Sub-domain.** Create e.g. `crm.company.com` in hPanel → Domains → Subdomains.
2. **Upload the code** to a folder *outside* the public web folder, e.g. `~/crm-app`
   (Git deploy, SFTP, or the release zip).
3. **Point the sub-domain at `~/crm-app/public`.** In hPanel set the sub-domain's
   document root to that folder. If that is not possible, keep the app in `~/crm-app`,
   copy only the contents of `public/` into the sub-domain folder and change the two
   paths at the top of its `index.php` to `../crm-app/...`.
4. **Install** over SSH:
   ```sh
   cd ~/crm-app
   composer install --no-dev --optimize-autoloader
   cp .env.example .env        # then edit .env: DB_*, APP_URL
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   chmod 600 .env
   ```
5. **Cron** (hPanel → Advanced → Cron Jobs), every minute:
   ```
   cd ~/crm-app && php artisan schedule:run >> /dev/null 2>&1
   ```
   This closes each day's attendance (absent, single swipe) at night.
6. **Open the sub-domain.** The first visit shows the setup page: the company enters its
   own name, GSTIN, office and lunch timing, and creates the first admin.
7. **Lock it down (Rule 1):**
   - Each admin and manager turns on **2-step login** under *My profile*.
   - Put the office's fixed IP (and VPN IP, if any) in `CRM_ALLOWED_IPS` in `.env`,
     then run `php artisan config:cache`.
   - Keep *"Allow sales reps to export their own leads"* off unless really needed.

## Backups

- hPanel takes daily backups. Also download a database backup every week and keep it
  somewhere only the company controls.
- `APP_KEY` in `.env` decrypts phone numbers and emails. **Keep a copy of `.env` in a safe
  place.** Without that key the encrypted fields cannot be read, even from a backup.

## Updating

```sh
cd ~/crm-app
php artisan down
git pull                       # or upload the new release
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## Never

- Never run `php artisan db:seed --class=DemoSeeder` on a real server (it refuses to run
  in production anyway).
- Never set `APP_DEBUG=true` on a live server: error pages would show internal details.
- Never commit `.env` to Git.
