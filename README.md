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
| **Proxies** | Buy static residential IPs from VMOS, see what you own, attach a proxy to one or many devices, delete |
| **API diagnostics** | Raw VMOS responses for read-only endpoints — use this when a sync returns nothing, and to confirm whether prices are quoted in cents |

### Customer device controls

Each cloud phone has its own control panel (**My cloud phones → Manage**):

- **Phone number & SIM** — generate a fresh number, IMEI, IMSI and carrier for any supported country
- **GPS** — set the coordinates apps see
- **Language & timezone**
- **Proxy** — apply a SOCKS5/HTTP proxy (with a test-before-apply button) or disable it
- **Apps** — install by APK URL, then start / stop / uninstall installed apps
- **ADB** — enable remote debugging and get the `adb connect` command
- **One-key new device** — wipe and regenerate a completely new hardware identity
- **Factory reset**, restart, and live screenshot

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
