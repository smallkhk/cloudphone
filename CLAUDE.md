# Working notes

Facts about this project that aren't obvious from the code. Read this first —
it exists so a new session doesn't have to re-derive any of it.

## What this is

A Laravel storefront reselling [VMOS Cloud](https://cloud.vmoscloud.com) cloud
Android phones at the owner's own markup. Customers pay in USDT (TRC20),
devices are provisioned automatically, and everything is administered from
`/admin` — no SSH needed after the first deploy. Owner is a solo operator, not
a developer: prefer instructions over jargon, and never assume a local dev
machine (they work from a phone or the cPanel terminal).

Branch: `claude/reseller-website-api-b4x019`. Full feature list is in README.md.

## Current state

Working in production: plan sync and pricing, crypto checkout, device
provisioning, the per-device control panel (SIM/GPS/locale/proxy/apps/ADB),
**live screen streaming** via the VMOS H5 SDK, admin panel, and **live chat**
(Claude or any OpenAI-compatible provider, with human takeover).

**Email verification accounts** (added, NOT yet tested against a live VMOS
account): reuses the existing Sku/Order/CryptoPayment/OrderController pipeline
rather than a parallel one — `Sku.type` is now `cloud_phone` or
`email_account`, and `OrderProvisioner` dispatches a paid order to
`CloudPhoneProvisioner` or the new `EmailAccountProvisioner` based on that.
Storefront: `/email-accounts` (`EmailAccountController`). Admin: **Plans &
pricing** now has an *Email accounts* tab (same page, `?type=email_account`).
Sync with `php artisan vmos:sync-email-skus` (hourly, like the phone SKUs).

Important caveats before relying on this:

- The VMOS OpenAPI docs list the four endpoints used here
  (`getEmailServiceList`, `getEmailTypeList`, `createEmailOrder`,
  `getEmailOrder`) but — unlike the phone-plan endpoints — don't publish full
  request/response field names. `VmosCloudPhoneService`'s email methods and
  `EmailAccountProvisioner`'s parsing of the purchase response are best-effort,
  matching naming conventions used elsewhere in the API. **Check Admin →
  Diagnostics (the three new `email_*` probes) against a real account before
  trusting a live purchase**, and compare the raw JSON there to what
  `EmailAccountProvisioner`/`EmailAccountController::refresh` expect
  (`app/Services/Provisioning/EmailAccountProvisioner.php`,
  `app/Http/Controllers/EmailAccountController.php`). Every raw entry is kept
  in `email_accounts.raw_payload` regardless, so a wrong field-name guess is
  fixable without re-buying.
- VMOS has **no** virtual phone number / SMS-receiving product —
  `simulateSendSms` only injects a fake SMS into a device you already own.
  Don't add a "phone verification" feature backed by VMOS; there's nothing to
  back it with. The storefront copy on `/email-accounts` says this explicitly.
- No real customer has completed a crypto purchase, so
  `TronUsdtVerifier` is untested against a live transaction.
- `demo@example.com` / `password` was seeded as an **admin**. Confirm it's gone
  from the live database — this repo is public.

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
