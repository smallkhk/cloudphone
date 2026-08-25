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

## Branding — "Modova", never "VMOS", to customers or in errors

The site is white-labeled as **Modova** (brand name driven by `APP_NAME` in
`.env`, with `Setting::get('site_name')` as an admin-editable override — set
both on the live server; `.env` isn't touched by `git pull`). "VMOS" must
never appear in the public site, the customer dashboard, or in ANY error
message shown to a user (customer or admin) — a real incident happened where
a raw `"...from VMOS: Instance not found"` error leaked the vendor name.
`VmosApiException`'s own message text was reworded to say "the cloud phone
provider" instead of "VMOS" so this can't leak from one central place again,
and every controller that used to prepend `'VMOS ...: '.$e->getMessage()`
either uses generic wording now or drops the raw exception text entirely
(logging it instead) — customer-facing catches in particular never echo
`$e->getMessage()` raw, since that string ultimately comes from VMOS's own
API and isn't under our control. The live chat system prompt
(`SiteKnowledge::rules()`) also explicitly tells the model never to name the
underlying provider even if a customer asks directly.

**Admin panel is the one exception** — Settings, Diagnostics, and the Proxies
page still say "VMOS" on purpose, since the admin genuinely needs to know
that's the real upstream service to go get API credentials from their
console. Internal class/service names (`VmosCloudPhoneService`, `VmosClient`,
`Vmos*` folders, `proxy_mode = 'vmos'`, log entries, code comments) are also
untouched — none of that is user-facing. If a new customer-facing error path
gets added later, keep following the same rule: never echo a raw upstream
exception message to a non-admin.

## Desktop app access codes (not yet used by anything)

Added for a **desktop app the owner is building separately** — this repo
doesn't contain it, only the two things it will call. The website itself has
**no lock**; it stays reachable at its normal URL for browser visitors. The
lock is meant to live entirely in the desktop app, which is expected to
prompt for a code on launch and only navigate to the site once it checks out
— keeping the actual URL out of the app's visible UI is the desktop app's
job, not something this repo can enforce.

- Admin → **Access codes** (`/admin/access-keys`) generates/revokes/deletes
  codes (`App\Models\AccessKey`, format `XXXX-XXXX-XXXX-XXXX`, unambiguous
  charset). Optional label and expiry; reusable (not one-time) until revoked
  or expired.
- `POST /api/access-keys/verify` (unauthenticated, rate-limited
  `throttle:20,1`) is what the desktop app is expected to call with
  `{"code": "..."}`. Returns `{"valid": true}` (200) or `{"valid": false}`
  (401). Records `used_count`/`last_used_at` on every successful check —
  purely informational, doesn't limit reuse.

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

**Plans page filters** — Android version and Device model are button/pill
rows now (`x-pill-filter` component), not dropdowns. There's also a Region
pill row, but it does NOT filter which devices show — checked VMOS's API
first and it doesn't support filtering the catalogue by region
(`getCloudGoodList` only takes `androidVersion`/`goodIds`; `createOrder`
takes `countryCode` as an independent parameter). Picking a region just
pre-selects it on every device's "Buy now" form below.

The checkout region list itself (`VmosRegionCatalog::purchaseOptions()` /
`PURCHASE_REGIONS`) is a **fixed list, not live-pulled** — HK, PH, US, JP,
KR, BR, DE, SG, ID, TW, confirmed from the owner's own VMOS console
screenshot. The live `…/padApi/country` list (`options()`) is real but
broader than what VMOS's purchase flow actually accepts (it's meant for SIM
regeneration on an already-owned device, a separate feature that does
support more countries) — using it for checkout showed the customer regions
VMOS's own buy page doesn't offer, which is exactly what got reported. If
VMOS ever exposes a real "regions available to buy in" endpoint, swap
`PURCHASE_REGIONS` for that; until then, update the constant by hand if VMOS
adds a region to their console.

**Cloud Drive** (added, NOT yet tested against a live VMOS account): a new
tab on the device control panel (`My cloud phones → Manage → Cloud Drive`).
Storage capacity, file upload/list/delete (by URL, same pattern as APK
install), and whole-disk backups are customer-facing; buying more storage is
**admin-only** (charges the VMOS account balance, same as buying a proxy —
see Admin → Proxies for the same pattern). VMOS's docs don't publish field
names for these endpoints (`getVcStorageGoods`, `getRenewStorageInfo`,
`selectFiles`, `uploadFile`, `deleteOssFiles`, `addBackup`,
`queryBackupBatch`) the way they do for the phone-plan ones, so
`VmosCloudPhoneService`'s Cloud Drive section and the view's field-name
guesses (`used`/`usedSize`, `fileId`/`id`, etc.) are best-effort. **Check
Admin → Diagnostics (`storage_goods` probe) and a real device's Cloud Drive
tab against your VMOS account before relying on it.**

Also investigated VMOS's "Automation + AI" console feature — the reseller
API only exposes `asyncCmd` (run a shell/ADB command) plus result polling
(`executeScriptInfo`, `padTaskDetail`), not a flow builder or any AI
decision-making. Not built — the owner explicitly said skip it for now given
what it actually is.

Checked VMOS's separate "VMOS AI" console feature too (AI image/video
generation, cutout, watermark remover, upscaling, its own points/credits
system) — confirmed via the same doc sources this is **not exposed anywhere
in the reseller OpenAPI** (no tag, no endpoint). Same category as VMOS Magic
Box: a bundled consumer product, not something an API key can drive. Not
built, and shouldn't be faked — if VMOS confirms a real reseller endpoint
for it later, build against that.

**SIM/carrier auto-applied from checkout region** — `SimProvisioner`
(`app/Services/Provisioning/SimProvisioner.php`) calls `updateSim()` with the
order's `country_code` the moment `SyncCloudInstances` discovers the
device's padCode (same hook as the proxy auto-apply), so the customer's
checkout region becomes their device's actual SIM/carrier without them
having to open "Phone number & SIM" and pick it again. Guarded by an
existing `update_sim` InstanceTask so it only ever runs once per device.
This exists because VMOS's `createMoneyOrder countryCode` isn't confirmed to
guarantee the SIM matches on its own — calling `updateSim()` explicitly
makes it certain either way.

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
