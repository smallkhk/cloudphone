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

**Email verification accounts** and **phone verification numbers** (added,
NOT yet tested against a live VMOS account): both reuse the existing
Sku/Order/CryptoPayment/OrderController pipeline rather than a parallel one —
`Sku.type` is `cloud_phone`, `email_account`, or `phone_number`, and
`OrderProvisioner` dispatches a paid order to `CloudPhoneProvisioner`,
`EmailAccountProvisioner`, or `PhoneNumberProvisioner` based on that.
Storefronts: `/email-accounts`, `/phone-numbers`. Admin: **Plans & pricing**
has *Email accounts* and *Phone numbers* tabs (`?type=email_account` /
`?type=phone_number`). Sync with `vmos:sync-email-skus` /
`vmos:sync-sms-skus` (both hourly).

VMOS sells both under one bundle in their own console, branded **"Captcha
Service"** — a temporary email OR phone number that receives a real
verification code for app/service registrations. A previous version of this
note said VMOS had no phone-number product; that was wrong (confirmed
straight from the VMOS console UI) and led to a real "you fucked up the
site" moment with the owner — don't repeat it. Verify claims like this
against the actual product/console, not just the reseller API docs, before
writing them down here as fact.

Important caveats before relying on either:

- **Email** (higher confidence): the VMOS OpenAPI spec's "Email Verification
  Service" tag documents five real endpoints — `getEmailServiceList`,
  `getEmailTypeList`, `createEmailOrder`, `getEmailOrder`, `getEmailCode` —
  confirmed to exist, but VMOS still doesn't publish full request/response
  field names for them (unlike the phone-plan endpoints). `VmosCloudPhoneService`'s
  email methods and `EmailAccountProvisioner`'s parsing are best-effort.
  **Check Admin → Diagnostics (`email_services`/`email_types`/`my_emails`
  probes) against a real account before trusting a live purchase.**
- **Phone numbers** (low confidence — do not enable for real customers
  without checking this first): unlike email, there is NO tag or endpoint
  for this in VMOS's published OpenAPI docs, llms.txt quick reference, or raw
  spec tag list — the "Captcha Service" SMS side is sold through VMOS's own
  console but isn't (yet?) exposed to resellers as far as the docs show.
  `VmosCloudPhoneService`'s `sms*`/`getSms*` methods are a guess, mirroring
  the confirmed email endpoint names 1:1 (`getSmsServiceList`,
  `getSmsTypeList`, `createSmsOrder`, `getSmsOrder`, `getSmsCode`). Because of
  this, `vmos:sync-sms-skus` seeds new phone-number SKUs **hidden**
  (`active=false`) — nothing reaches a real customer until an admin
  confirms real data comes back in Admin → Diagnostics (`sms_services`/
  `sms_types`/`my_sms` probes) and deliberately flips a SKU live. If VMOS
  support can confirm the real endpoint names/paths, update
  `VmosCloudPhoneService`'s SMS section and this note together.
- Either way, every raw purchase-response entry is kept in
  `email_accounts.raw_payload` / `phone_numbers.raw_payload`, so a wrong
  field-name guess is fixable without re-buying.
- No real customer has completed a crypto purchase, so
  `TronUsdtVerifier` is untested against a live transaction.
- `demo@example.com` / `password` was seeded as an **admin**. Confirm it's gone
  from the live database — this repo is public.

**Proxy add-on at checkout** (added, NOT yet tested against a live VMOS
account): the "Buy now" form on `/plans` now has a Proxy section — either the
customer's own proxy (free, `Order.proxy_mode = 'custom'`) or a VMOS
residential proxy bought alongside the device (`'vmos'`, priced at cost +
markup like a SKU, combined into one USDT total via `Order.proxy_price`).
Config lives in `Order.proxy_config` (JSON); `Order.proxy_status` tracks
`pending → purchased (vmos only) → attached`, or `failed`.

Two-step because VMOS's API forces it — `app/Services/Provisioning/ProxyProvisioner.php`
has the full reasoning in its docblock:
1. `purchase()` runs at provisioning time (`CloudPhoneProvisioner`, right
   after the device purchase) — buying a proxy doesn't need a padCode. A
   failure here does NOT fail the device order.
2. `apply()` runs once `SyncCloudInstances` discovers the device's real
   padCode (same hook point padCode itself gets filled in), since both
   `setCustomProxy` and `attachProxies` require one.

The `custom` path is fully deterministic (the customer's own IP/port, applied
directly) — trust that one. The `vmos` path is not: `createProxyOrder`
doesn't hand back a usable proxy ID (confirmed — Admin → Proxies' own
existing purchase flow never uses one either, it just re-lists owned proxies
afterward), so `apply()` has to *find* the just-bought proxy in
`listStaticProxies()` by matching country + unused. If a customer buys two
VMOS proxies in the same country close together, or another admin-side
purchase lands in between, that match can be ambiguous. Rather than guess
wrong on a paid purchase, it deliberately does NOT attach in that case — it
marks the order `proxy_status = 'failed'` with a clear message and points to
Admin → Proxies to attach by hand (visible on both the admin and customer
order pages). **Watch Admin → Orders for a few real vmos-mode purchases
before treating the auto-match as reliable.**

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
