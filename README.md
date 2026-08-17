# Cloud Phone Reseller

A Laravel storefront for reselling [VMOS Cloud](https://cloud.vmoscloud.com/) cloud
phones: customers browse plans, pay in USDT (TRC20), and get their own cloud phone
instances provisioned automatically. You set your own markup on top of VMOS's cost.

Built to run on cheap shared cPanel hosting (PHP + MySQL, no persistent Node/queue
workers required — a single cron entry drives everything).

## How it works

1. **Plans** (`skus` table) are synced from VMOS's `getCloudGoodList` API
   (`php artisan vmos:sync-skus`). New plans default to a 30% markup; adjust
   prices under **Admin → Plans**.
2. A customer buys a plan → an **Order** is created and a **USDT (TRC20)**
   payment quote is generated (shared wallet address, matched by tx hash).
3. Customer sends USDT and submits the transaction hash.
4. `php artisan crypto:verify-payments` (runs every minute via the scheduler)
   checks the tx hash against [TronGrid](https://www.trongrid.io/)'s public API.
   Once confirmed, the order is marked paid and the cloud phone is purchased via
   VMOS's `createMoneyOrder` API.
5. `php artisan vmos:sync-instances` fills in the `padCode` once VMOS finishes
   provisioning (also updated live via the `/api/vmos/callback` webhook, if
   configured in the VMOS console).
6. Customers manage their cloud phones (restart / reset / screenshot) from
   **My Cloud Phones**.

### Email & phone verification (VMOS "Captcha Service")

The same checkout pipeline also sells VMOS's **Captcha Service** bundle:
pre-made **email accounts** and temporary **phone numbers** for service
registrations (GitHub, TikTok, …), each with verification-code retrieval.
From **Email accounts** / **Phone numbers** a customer buys one; once paid,
`EmailAccountProvisioner` / `PhoneNumberProvisioner` buy it from VMOS and the
address or number appears on the order. A **Check for code** button polls
VMOS for the latest verification code.

Sync the catalogues with `php artisan vmos:sync-email-skus` /
`vmos:sync-sms-skus` (both hourly), and set prices under **Admin → Plans &
pricing → Email accounts / Phone numbers**.

> **Phone numbers are unverified.** VMOS's published API docs only document
> the email side of Captcha Service — the SMS/phone-number endpoints used
> here (`getSmsServiceList`, `getSmsTypeList`, `createSmsOrder`, `getSmsOrder`,
> `getSmsCode`) are a best-effort guess mirroring the confirmed email
> endpoints' naming, not something VMOS documents. New phone-number SKUs sync
> in **hidden** for exactly this reason — check **Admin → API diagnostics**
> (the `sms_*` probes) against your real VMOS account and confirm a real
> purchase works before making one live to customers.

### Proxy add-on at checkout

The "Buy now" form on the Plans page has an optional **Proxy** section — a
customer can either supply their **own proxy** (IP/port/credentials, free) or
**buy a VMOS residential proxy** alongside the device (priced at cost +
markup, combined into one USDT total). Once paid, `CloudPhoneProvisioner`
buys the VMOS proxy right away (doesn't need the device to be ready yet), and
`ProxyProvisioner` applies whichever proxy was chosen the moment the new
device's `padCode` becomes known — typically within a few minutes of payment,
not instantly, since that's how long VMOS takes to actually provision a
device. Progress shows on the order page: pending → (bought →) attached.

> **The VMOS-proxy path can need a human.** Buying a proxy from VMOS doesn't
> hand back a usable proxy ID, so attaching it means finding it afterward in
> your list of owned proxies (matched by country + unused) — the same way
> Admin → Proxies' existing manual flow already works. If that match is ever
> ambiguous, the order is flagged `proxy_status: failed` with a link to
> Admin → Proxies rather than risk attaching the wrong one. The customer's
> own proxy path has no such issue — it's applied directly, every time.

## Admin panel

Everything below is configurable from `/admin` — no SSH or file editing needed
after the first deploy.

| Section | What you can do |
| --- | --- |
| **Dashboard** | Revenue, orders, devices and customer stats, plus a setup checklist |
| **Orders** | Filter/search orders, mark an order paid manually, retry provisioning, cancel |
| **Devices** | Every cloud phone, who owns it, **allocate/reassign a device to a customer**, return to stock, and **import existing devices from your VMOS account** |
| **Users** | Create, edit, promote to admin, delete (their devices return to stock), see per-customer orders/devices/spend |
| **Plans & pricing** | Sync the catalogue from VMOS, set per-plan prices, bulk re-price at cost + markup%, toggle what's live |
| **Settings → Site** | Site name, tagline, URL, support links, default markup |
| **Settings → Payments** | USDT (TRC20) receiving wallet, payment window, underpayment tolerance, TronGrid key |
| **Settings → VMOS API** | Access/Secret key, callback token, plus a **Test connection** button |
| **Settings → Email** | SMTP host/port/credentials and a **Send test email** button |
| **Settings → AI live chat** | Turn the chat widget on, store your Anthropic API key, pick the model, cap messages per visitor per hour, and **teach it your business rules** |
| **Live chat** | Read every conversation the assistant has had, see token usage, delete transcripts |
| **Proxies** | Buy static residential IPs from VMOS, see what you own, attach a proxy to one or many devices, delete |
| **API diagnostics** | Raw VMOS responses for read-only endpoints — use this when a sync returns nothing, and to confirm whether prices are quoted in cents |

### Customer device controls

Each cloud phone has its own control panel (**My cloud phones → Manage**):

- **Live screen** — a real, interactive view of the phone in the browser: tap,
  swipe, type, plus Back/Home/Recents and sound. Streamed by VMOS's Web H5 SDK
  (`armcloud-rtc`); the session token is minted server-side by
  `stsTokenByPadCode` and is scoped to that one device, so API keys never reach
  the browser. The ~1.7MB player is a separate bundle loaded only when someone
  actually opens the screen.
- **Phone number & SIM** — generate a fresh number, IMEI, IMSI and carrier for any supported country
- **GPS** — set the coordinates apps see
- **Language & timezone**
- **Proxy** — apply a SOCKS5/HTTP proxy (with a test-before-apply button) or disable it
- **Apps** — install by APK URL, then start / stop / uninstall installed apps
- **Cloud Drive** — storage capacity, upload/list/delete files (by URL), and
  whole-disk backups. Buying more storage is admin-only (charges the VMOS
  balance, like buying a proxy).
- **ADB** — enable remote debugging and get the `adb connect` command
- **One-key new device** — wipe and regenerate a completely new hardware identity
- **Factory reset**, restart, and live screenshot

> **Cloud Drive field names are best-effort.** VMOS doesn't publish full
> request/response details for these endpoints — check a real device's Cloud
> Drive tab and **Admin → API diagnostics** (`storage_goods` probe) against
> your account before relying on it.

> The live screen connects to a *streaming* host, not the OpenAPI host. It
> defaults to `https://openapi-hk.armcloud.net` and is set with
> `VMOS_SDK_BASE_URL`. If everything else works but the screen won't connect,
> that's the value to confirm with VMOS support — the region varies by account.

### A note on prices

VMOS documents proxy prices in **cents**, and plan prices appear to use the same
minor unit — so a $4.99 plan arrives from the API as `499`. The sync divides by
100 by default. **Confirm this on the diagnostics page before trusting your
prices**, and flip *Settings → VMOS → Price unit* if your account quotes whole
units instead. Getting this wrong makes every price 100× off.

Secrets (API keys, mail password) are encrypted at rest with `APP_KEY` and are
never rendered back into the page — leave a secret field blank to keep the
stored value.

**Database credentials are the one thing that stays in `.env`** — the app needs
them to reach the settings table in the first place.

> Note: settings are cached. If you edit `.env` directly on the server, run
> `php artisan config:clear` so the change is picked up.

## Local development

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

SQLite is fine locally (`.env.example` defaults to it — switch to MySQL for
production per the cPanel steps below).

### Or test it in a GitHub Codespace (no local setup, no real credentials needed)

Open this repo in a Codespace (**Code → Codespaces → Create codespace**) and it
runs `.devcontainer/setup.sh` automatically (composer/npm install, sqlite db,
migrations). Once it finishes, run:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

and open the forwarded port 8000 in your browser. The setup script seeds 3 fake
`[DEMO]` plans and a demo login (`demo@example.com` / `password`, admin) so
there's something to click through. Without real `VMOS_ACCESS_KEY`/`SECRET_KEY`
and `CRYPTO_USDT_TRC20_ADDRESS` in `.env`, no real purchase will go through —
it's a UI/flow preview, not a functional store, until those are filled in. This
works entirely from a phone browser too — no PC required, everything runs on
GitHub's servers.

## Deploying to cPanel shared hosting

1. **PHP version**: In cPanel → *MultiPHP Manager*, set PHP 8.4+ for the domain.
2. **Database**: In cPanel → *MySQL® Databases*, create a database and user
   (cPanel prefixes both, e.g. `cpuser_cloudphone` / `cpuser_dbuser`), and add
   the user to the database with **all privileges**.
3. **Upload the code** outside `public_html` (e.g. into `~/cloudphone`), and
   point the domain's document root at `~/cloudphone/public` (cPanel →
   *Domains* → edit document root, or symlink `public_html` to `public/*` if
   your host doesn't allow changing the document root).
4. **Install dependencies** via cPanel's *Terminal* (or SSH if enabled):
   ```bash
   cd ~/cloudphone
   composer install --no-dev --optimize-autoloader
   npm install && npm run build   # only needed once, at deploy time — not at runtime
   ```
5. **Configure `.env`**: copy `.env.example` to `.env`, fill in `DB_*`,
   `APP_URL`, `VMOS_ACCESS_KEY` / `VMOS_SECRET_KEY` (from the VMOS console →
   Developer → API), and `CRYPTO_USDT_TRC20_ADDRESS` (your receiving wallet).
   Then run:
   ```bash
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache
   php artisan vmos:sync-skus
   ```
6. **Cron job**: cPanel → *Cron Jobs* → add one entry that runs every minute:
   ```
   * * * * * php /home/YOURUSER/cloudphone/artisan schedule:run >> /dev/null 2>&1
   ```
   This single entry drives `crypto:verify-payments` (every minute),
   `vmos:sync-instances` (every 5 minutes), and `vmos:sync-skus` (hourly) — see
   `routes/console.php`. No separate queue worker process is needed.
7. **VMOS callback (optional but recommended)**: in the VMOS console, set the
   callback URL to `https://your-domain.com/api/vmos/callback?token=<VMOS_WEBHOOK_TOKEN>`
   for near-instant instance status updates instead of waiting on the 5-minute sync.
8. **Make yourself an admin** (to set plan prices) via Terminal:
   ```bash
   php artisan tinker --execute="\App\Models\User::where('email','you@example.com')->update(['is_admin'=>true]);"
   ```

## AI live chat

A floating chat bubble on every page, answered by Claude via the official
[Anthropic PHP SDK](https://github.com/anthropics/anthropic-sdk-php). It is
**off by default** — turn it on under **Admin → Settings → AI live chat** and
paste an API key from <https://console.anthropic.com>.

It isn't trained or fine-tuned. On every message the system prompt is rebuilt
from the live database (`app/Services/Chat/SiteKnowledge.php`):

- your site name, tagline and support channels
- every plan on sale, with its durations and **your** prices
- how the USDT (TRC20) checkout works, including the payment window
- what a customer can do from the device control panel
- whatever you type into **What else should it know?** — refund policy,
  delivery times, what you don't support. This overrides everything else.
- for a signed-in customer: their own devices (pad code, status, expiry) and
  recent orders, so "where is my phone?" gets a real answer

So prices and stock are always current, with no retraining step.

**It costs money per message**, billed to your own Anthropic account and
separate from any Claude subscription. Three things keep that in check:

1. The static half of the prompt (the catalogue) carries a **prompt-cache
   breakpoint**, so repeat questions don't re-bill the whole catalogue.
2. **Messages per visitor per hour** is a hard cap (default 25).
3. Switching the model to Haiku costs a fraction of Opus and is plenty for
   routine questions.

Set a spend limit in the Anthropic console as well.

The assistant is told never to repeat a wallet address, quote a price that
isn't in the catalogue, promise refunds, or accept passwords and seed phrases —
and to ignore instructions inside a visitor's message. Treat those as
guardrails, not guarantees: read the transcripts under **Admin → Live chat**
for the first few days.

## Before accepting real payments

The crypto payment verifier (`app/Services/Payments/TronUsdtVerifier.php`) checks
a submitted transaction hash against TronGrid's public API for a matching TRC20
USDT transfer to your configured address. Test it end-to-end with a small real
transaction before relying on it — this is the part of the app that touches real
money, so verify it yourself rather than trusting it blindly.

## Key VMOS docs

- API reference: <https://cloud.vmoscloud.com/vmoscloud/doc/en/server/OpenAPI.html>
- Auth / signing walkthrough: <https://cloud.vmoscloud.com/vmoscloud/doc/en/server/example.html>
- Callback payloads: <https://cloud.vmoscloud.com/vmoscloud/doc/en/server/callback.html>
