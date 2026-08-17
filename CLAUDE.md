# Working notes

Facts about the live deployment that aren't obvious from the code. Keep this
current — it's the first thing to read before giving deploy instructions.

## Live deployment

- **App directory:** `~/cloud` on the cPanel host.
  (Moved here from `~/fair-red-whale.198-54-115-5.cpanel.site` — that path is
  dead, don't use it in any command.)
- **Site:** <https://cloud.eclipselivecam.online>, document root `~/cloud/public`.
  HTTPS is on and plain HTTP 301s to it.
- **Sibling site on the same account:** `eclipselivecam.online`. Unrelated to
  this project and must not be disturbed.
- **PHP:** the account default is *not* new enough for this app. PHP 8.4 is
  selected per-domain in **MultiPHP Manager**, and `~/bin-php84` is a wrapper
  used for CLI work (`~/bin-php84 artisan …`).

> **Never send the user to cPanel's "Select PHP Version" page.** On CloudLinux
> it changes the PHP version for the *whole account*, which has already broken
> their other site once. MultiPHP Manager is the per-domain equivalent and is
> the safe one.

## After moving or re-cloning the app directory

Three things live outside the repo and silently keep pointing at the old path:

1. **The cron entry.** One line drives everything — `crypto:verify-payments`
   every minute, `vmos:sync-instances` every 5, `vmos:sync-skus` hourly (see
   `routes/console.php`). If its path is stale, crypto payments stop being
   verified and paid orders never provision, with no error anywhere:
   ```
   * * * * * /home/USER/bin-php84 /home/USER/cloud/artisan schedule:run >> /dev/null 2>&1
   ```
2. **The domain's document root** — must be `~/cloud/public`.
3. **`.env`** — gitignored, so it does not travel with a fresh `git clone`.
   Losing it means a missing `APP_KEY` and a site-wide 500.

## .env

`.env` is gitignored and has been wiped twice by `cp .env.example .env`. Never
suggest that command. Suggest backing the file up before risky steps instead.

Database credentials deliberately stay in `.env`; everything else is editable
from the admin panel and stored encrypted in the `settings` table.

## Deploy

```bash
cd ~/cloud
git pull origin claude/reseller-website-api-b4x019
~/bin-php84 $(which composer) install --no-dev --optimize-autoloader   # only when composer.lock changed
~/bin-php84 artisan migrate --force                                     # only when a migration is new
~/bin-php84 artisan config:clear
```

Built CSS/JS is committed under `public/build`, so **npm is never needed on the
server**. Run `npm run build` locally and commit the result. When the bundle
hash changes, tell the user to hard-refresh — otherwise they test stale JS.

## Outstanding

- No real customer has completed a crypto purchase yet; the TronGrid verifier
  is untested against a live transaction.
- `demo@example.com` / `password` was seeded as an **admin**. Confirm it is
  gone from the live database — this repo is public.
