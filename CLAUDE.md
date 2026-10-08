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

## Proxy testing goes through VMOS's checkIP after all

Both "Test proxy" buttons (checkout and the device panel) call VMOS's
`checkIP` endpoint. This was briefly replaced with a self-hosted checker
(`App\Services\ProxyChecker`, since removed) that connected through the
proxy directly from our own server to a geolocation API — built because the
owner found VMOS's checkIP reporting the wrong exit location for a proxy
that geolocated correctly on another platform. That replacement made things
*worse*: our cPanel host got flat-out "Connection refused" from a proxy that
both VMOS's own console and another tester could reach, most likely because
the proxy provider IP-allowlists which servers may connect and our host's
IP was never on it. Since the cloud phone that will actually *use* the
proxy is hosted on VMOS's own network, VMOS's reachability result is a
better predictor of "will this work once applied" than a check from our own
unrelated web host — so we went back to VMOS for reachability, and just
accepted the risk that its location field is occasionally wrong. If VMOS's
location reporting causes a real problem again, the fix is a geolocation
lookup layered *on top of* a successful VMOS reachability check (not
instead of it), not a full replacement.

**Update (2026-09): every "Test proxy" button was silently broken from the
start.** `checkIP`'s real request fields (confirmed against VMOS's published
spec) are `host`/`port`/`account`/`password`/`type` — the code had guessed
`ip`/`port`/`account`/`password`/`proxyName`, and `type` wants `"Socks5"` /
`"http"` / `"https"` (capitalized `Socks5`), not the internal `socks5`/
`http-relay` values the UI uses. Because `host` was missing and `type` was
never a value VMOS recognized, VMOS rejected every check outright — this
reproduced with a real proxy, real credentials, confirmed working seconds
earlier in VMOS's own console's own Network Detection feature, failing every
single time through our site with a generic `code: 400` "Network error
detected, please try again in 1 minute", consistently over 24+ minutes and
5 attempts (so not a transient rate limit — every attempt sent the same
wrong fields). The response was also being parsed wrong: VMOS actually
returns `proxyWorking` (boolean)/`proxyLocation`/`publicIp`, not `city`/
`country`, so even a *successful* check would have shown no location and,
worse, a `proxyWorking: false` response was being read as success since
nothing checked that field. All three call sites (`ProxyTestController`,
`DeviceControlController::testProxy`, `CustomerProxyController::test`) share
one fixed `VmosCloudPhoneService::checkProxyIp()`, so this one fix corrects
every "Test proxy" button on the site at once. Our UI's `http-relay` option
doesn't distinguish HTTP from HTTPS the way VMOS's `type` does — it's
mapped to `"http"`; if a customer's proxy is actually HTTPS-only, this could
still show a false failure, which would need a third UI option to fix
properly if it comes up.

Applying a proxy to a device still has to go through VMOS's `setCustomProxy`
either way, since VMOS is what actually hosts and controls the device's
network stack — no proxy checker, ours or anyone else's, changes that part.

## Wallet balance + BEP20 deposits (added, NOT yet tested against a live transaction)

Customers can hold a USD balance (`users.balance`, decimal — the *only* place
it's ever written is `App\Services\Wallet\WalletService::credit()`/`debit()`,
which locks the user row and writes a `wallet_transactions` ledger entry in
the same DB transaction; balance is deliberately absent from `User`'s
`$fillable` so it can never be mass-assigned from a request). Two ways
balance moves:

- **Deposits** — Admin → Settings → Payments now has a second network,
  USDT (BEP20 / BNB Smart Chain), alongside the original TRC20. A customer
  tops up from `/wallet` the same way they'd pay for an order: get a quote
  (`WalletDepositService`, mirrors `CryptoPaymentService`), send USDT, paste
  the tx hash, and `wallet:verify-deposits` (scheduled every minute,
  mirrors `crypto:verify-payments`) confirms it on-chain and credits the
  balance. BEP20 verification is a new `BscUsdtVerifier`. **Update (2026-10):**
  originally used BscScan's `tokentx` API like `TronUsdtVerifier` uses
  TronGrid — but unlike TronGrid, BscScan (now merged into "Etherscan V2")
  requires a registered API key for every request, even the free tier, with
  no keyless path at all. The owner didn't want to manage that key, so this
  was rewritten to read the transfer straight off a **public BSC RPC node**
  instead (`eth_getTransactionReceipt`, decoding the standard ERC20/BEP20
  `Transfer` event out of the logs) — genuinely keyless, since public chain
  RPC nodes don't require registration the way block-explorer APIs do.
  `BSC_RPC_URL` defaults to `https://bsc-dataseed.binance.org`; only change
  it if that default proves unreliable from the live host.
- **Checkout** — the "3. Order" step on `/plans` shows a "Pay from wallet
  balance" checkbox when the signed-in customer has any balance. Picking it
  skips the crypto quote entirely: `OrderController::store` debits the
  balance and marks the order paid inside the same DB transaction the order
  is created in (so a race that leaves the balance short rolls the whole
  order back, not just the debit), then provisions immediately — no
  cron round-trip needed since there's no on-chain confirmation to wait for.
  If provisioning itself then fails, the order stays `paid`/unprovisioned
  exactly like a manually-marked-paid order does — same recovery path,
  Admin → Orders → "Provision now" — nothing new invented for that case.
- Regular per-order crypto payment also gained a network choice (TRC20 vs
  BEP20) at checkout, once BEP20 is configured — reuses the same
  `CryptoPaymentService`/`VerifyCryptoPayments` path, just dispatching to
  `BscUsdtVerifier` instead of `TronUsdtVerifier` by `payment.network`.
- Admin → a user's page has a manual credit/debit form (refunds, goodwill,
  correcting a support mistake) — goes through the same `WalletService`, so
  it's ledgered identically to a real deposit or purchase.
- Both payment screens (`/wallet` and the order payment page) show a **QR
  code** next to the receiving address now, not just copyable text — the
  `qrcode` npm package (bundled via Vite like everything else; CLAUDE.md's
  "npm never needed on the server" rule still holds, the built JS/CSS is
  committed) renders onto a `<canvas>` via a small Alpine component
  (`resources/js/payment-qr.js`, `Alpine.data('paymentQr', …)`). It encodes
  the plain receiving address as text, not a payment URI — TRC20 and BEP20
  don't share a standardized URI scheme the way `bitcoin:` does for BTC, and
  every wallet checked treats a scanned plain address as "fill in the
  recipient," which is the one behavior that has to work everywhere.

Confidence: the BscScan API itself is well-documented and stable (unlike
several VMOS endpoints elsewhere in this file), and the whole flow —
deposit quote → tx hash → cron verification → balance credit → spend at
checkout — has full test coverage with faked HTTP responses. What's *not*
tested is a real on-chain transaction on either network, same caveat
`TronUsdtVerifier` has always carried. Try a real TRC20 and BEP20 deposit
with a small amount before relying on this for real customer funds.

## `auto_renew` already works — VMOS does it, not this app

The `auto_renew` checkbox at checkout is passed straight through to VMOS's
`createMoneyOrder` as `autoRenew`, so VMOS bills *their own account balance*
automatically when a device is due for renewal — this app doesn't run a
renewal job and doesn't need to. The only real risk is the VMOS account
balance itself running dry, which is already visible in Admin → Diagnostics
and Admin → Proxies' "Account balance" line. If a customer's device expires
unexpectedly, check that first, not this app's cron.

## Current state

Working in production: plan sync and pricing, crypto checkout, wallet
balance/BEP20 deposits, device provisioning, the per-device control panel
(SIM/GPS/locale/proxy/apps/ADB), **live screen streaming** via the VMOS H5
SDK, admin panel, and **live chat** (Claude or any OpenAI-compatible
provider, with human takeover).

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
directly) — trust that one. The `vmos` path is better than it was, but still
not fully deterministic. **Update (2026-09):** VMOS published real docs for
`createProxyOrder` — it's confirmed async now: it only *accepts* the purchase
and returns a `taskId`, and `proxyOrderStatus()` (new) must be polled until
`FINISHED` (or `NEEDS_REVIEW`, which VMOS itself says never to resubmit —
needs a human) before the proxy reliably shows up in `listStaticProxies()`.
`purchase()` now sends a stable per-order `clientRequestId` (`order-{id}-proxy`)
so a retried call can't double-buy, and `apply()` won't attempt to find/attach
until the task reports `FINISHED` — `SyncCloudInstances` now retries proxy
`apply()` on every sync pass (not just once, when padCode first appears),
since the async task can still be `PENDING`/`PROCESSING` at that moment.

Even once `FINISHED`, VMOS still doesn't hand back a direct proxy ID the way
`createMoneyOrder` returns an `equipmentId` — Admin → Proxies' own existing
purchase flow never uses one either, it just re-lists owned proxies
afterward. So `apply()` still has to *find* the just-bought proxy in
`listStaticProxies()` by matching country + unused. If a customer buys two
VMOS proxies in the same country close together, or another admin-side
purchase lands in between, that match can be ambiguous. Rather than guess
wrong on a paid purchase, it deliberately does NOT attach in that case — it
marks the order `proxy_status = 'failed'` with a clear message and points to
Admin → Proxies to attach by hand (visible on both the admin and customer
order pages). **Watch Admin → Orders for a few real vmos-mode purchases
before treating the auto-match as reliable** — this part is unchanged by the
above and still needs that verification.

**Plans page filters** — Android version and Device model are button/pill
rows now (`x-pill-filter` component), not dropdowns. There's also a Region
pill row, but it does NOT filter which devices show — checked VMOS's API
first and it doesn't support filtering the catalogue by region
(`getCloudGoodList` only takes `androidVersion`/`goodIds`; `createOrder`
takes `countryCode` as an independent parameter). Picking a region just
pre-selects it on every device's "Buy now" form below.

The checkout region list itself (`VmosRegionCatalog::purchaseOptions()` /
`PURCHASE_REGIONS`) is a **fixed list, not live-pulled** — HK, US, JP, KR,
DE, SG, BR, VN, ID, MY, TW, FR, IT, ES, TH, GB, PH, AU (18 regions, confirmed
2026-09 from the owner's own VMOS console screenshot — VMOS expanded this
from an original 10 by adding Vietnam, Malaysia, France, Italy, Spain,
Thailand, the UK and Australia; watch for this drifting stale again, it's
happened once already). The live `…/padApi/country` list (`options()`) is real but
broader than what VMOS's purchase flow actually accepts (it's meant for SIM
regeneration on an already-owned device, a separate feature that does
support more countries) — using it for checkout showed the customer regions
VMOS's own buy page doesn't offer, which is exactly what got reported. If
VMOS ever exposes a real "regions available to buy in" endpoint, swap
`PURCHASE_REGIONS` for that; until then, update the constant by hand if VMOS
adds a region to their console.

**Cloud Drive** — a tab on the device control panel (`My cloud phones →
Manage → Cloud Drive`). Storage capacity, file upload/list/delete, and
whole-disk backups are customer-facing; buying more storage is **admin-only**
(charges the VMOS account balance, same as buying a proxy — see Admin →
Proxies for the same pattern). **Update (2026-09):** VMOS finally published
real field names for these endpoints, and the original best-effort guesses
were wrong in several places — fixed now:

- It's **account-wide, not per device** — `getRenewStorageInfo` and
  `selectFiles` take no `padCode` at all; the old code sent one, which VMOS
  just silently ignored. Every device's Cloud Drive tab shows the same
  shared files/capacity.
- Capacity fields are `storageUsedAvail`/`storageCapacityLimit` (bytes), not
  the guessed `used`/`total` variants — those never matched, so the capacity
  bar showed "not available" the whole time this was live.
- `buyStorageGoods` real params are `storageId`/`autoRenewOrder`, not
  `goodId`/`padCode`/`num` — buying storage never actually worked before this.
- `uploadFile` only accepts an actual file body (`multipart/form-data`) —
  there is no "upload by URL" option at all, so the old "paste a link"
  feature could never have worked. `uploadCloudFile()` now takes raw file
  contents; `DeviceControlController::uploadDriveFile()` downloads the
  customer's URL server-side first and re-uploads the bytes.
- `deleteOssFiles` takes `files` (an array of numeric file IDs), not
  `fileIds`.
- `addBackup` takes `vcPadBackupList: [{padCode, name}]`, not
  `padCodes`/`description`.

If it breaks again, check Admin → Diagnostics' `storage_goods`/`storage_info`/
`drive_files` probes against the raw response before assuming the code is
wrong — but as of this fix, all of the above match VMOS's own published docs
exactly, not a guess.

**Standalone Cloud Drive page at `/cloud-drive`** — since Cloud Drive is
account-wide anyway, it didn't need to live only inside a specific device's
tab. `CloudDriveController` is the exact same storage/files/backups/
buy-storage logic as the device tab (`DeviceControlController`), reachable
on its own URL instead. The one thing that genuinely needs a device —
backups, since VMOS's `addBackup` takes a `padCode` — asks the customer to
pick one of their own owned devices from a dropdown, since the standalone
page has no "current device" the way a device tab does. Nothing is written
to our own disk anywhere in this flow (upload-by-URL downloads into memory
for one request and re-uploads straight to VMOS, nothing cached but small
display metadata) — came up because the owner specifically wanted that
confirmed before building this page, worth remembering if asked again.

Also investigated VMOS's "Automation + AI" console feature — the reseller
API only exposes `asyncCmd` (run a shell/ADB command) plus result polling
(`executeScriptInfo`, `padTaskDetail`), not a flow builder or any AI
decision-making. Not built — the owner explicitly said skip it for now given
what it actually is.

## Cloud Number Service (built, NOT yet tested against a live VMOS account)

VMOS's **"云号码服务" / Cloud Number Service** (`/vcpcloud/api/padApi/cloudNumber/*`)
is a different product from the existing hidden/guessed `phone_number` Sku
type — it's an ongoing *rented* number (30/90/365-day plans) with its own
auto-renew/release lifecycle, rather than a one-off verification-code
purchase. Unlike a normal Sku it's paid out of VMOS's own account balance
(not customer USDT) and delivered asynchronously, and it must be explicitly
**bound to a specific cloud phone** (`bind`, which forces a real device
restart) before it can receive SMS. Built reusing the existing
Sku/Order/OrderProvisioner pipeline (`Sku.type = 'cloud_number'`) the same
way `email_account`/`phone_number` do, rather than a parallel system:

- `Sku.android_version` is keyed as `cn-{countryCode}` (not the blank string
  `email_account`/`phone_number` use) because VMOS's `planId` is only
  confirmed unique *within* a country, not globally — two countries can
  reuse the same `planId` for different plans, which would otherwise collide
  on the `(vmos_good_id, android_version)` unique index.
- `vmos:sync-cloud-number-skus` (hourly) calls `cloudNumberSkus()` once (it
  returns every country in one response) and upserts a Sku per
  country+plan. New SKUs sync **active by default** — unlike the
  low-confidence `phone_number` type, Cloud Number's endpoints are fully
  documented by VMOS, so there's no reason to hide them pending manual
  confirmation the way `vmos:sync-sms-skus` does.
- `CloudNumberProvisioner` (dispatched from `OrderProvisioner`) purchases
  with a stable `clientToken` (`order-{id}-cloudnumber`) so a retried call
  can't double-buy, mirroring the async-proxy-purchase pattern above. VMOS's
  purchase call can return non-terminal (`PROCESSING`) immediately; when it
  does, the order stays `provisioning` and `vmos:sync-cloud-number-purchases`
  (every minute) polls `cloudNumberPurchaseStatus()` until terminal.
- Like the proxy purchase, VMOS's purchase response gives back the delivered
  number *strings* but not the internal record `id` needed for later
  bind/auto-renew calls — that id is recovered by cross-referencing
  `cloudNumberList()` and matching on the exact number string. Unlike the
  proxy case this match is exact and unambiguous (no "which one did we just
  buy" guesswork), since VMOS's own purchase response already tells us
  which number strings were delivered.
- Customer-facing at `/cloud-numbers`: browse/buy like any other Sku, then
  from "My cloud numbers" bind to an owned device (requires ticking an
  explicit "I understand this restarts the device" checkbox — `bind` is a
  real, disruptive action, not something to trigger silently), check bind
  progress, toggle auto-renew, check for SMS, or release the number. Admin →
  Plans & pricing gained a **Cloud numbers** tab with its own sync button.

Confidence: VMOS's docs for this service are fully specified (unlike the
guessed `phone_number`/SMS endpoints), and the whole flow has full test
coverage with faked HTTP responses. What's *not* tested is a real purchase
against a live VMOS account — same caveat as email accounts, phone numbers,
and the wallet BEP20 flow. Try a real purchase (small plan, cheap country)
and a real bind before pointing customers at `/cloud-numbers`.

## Standalone proxies at `/proxies` (built, NOT yet tested against a live VMOS account)

Added because the checkout proxy add-on (above) is permanently welded to the
device it was bought alongside — there was no way for a customer to buy a
VMOS residential proxy on its own and move it between their own devices
later, the way VMOS's own console's "Proxy IP" menu lets you. `Sku.type =
'proxy'` reuses the same Sku/Order/OrderProvisioner pipeline as every other
product here:

- VMOS's proxy products (`staticProxyGoods()`) aren't priced per-country, but
  `vmos:sync-proxy-skus` (hourly) still syncs one Sku per (product, country)
  combination — same `px-{countryCode}` android_version-placeholder trick as
  `Sku::TYPE_CLOUD_NUMBER` — so a customer just picks a ready-made card on
  `/proxies` instead of a separate country dropdown at checkout, and pricing
  stays admin-editable the normal way.
- `StandaloneProxyProvisioner` (dispatched from `OrderProvisioner`) purchases
  via the same async `createProxyOrder` → `taskId` → `proxyOrderStatus()`
  flow `ProxyProvisioner` uses for the checkout add-on (see that section
  above for the full async history) — `vmos:sync-customer-proxy-purchases`
  (every minute) polls any purchase VMOS left non-terminal.
- VMOS doesn't hand back a proxy id from the purchase call itself here
  either, so the delivered proxy/proxies are found by the same "newest
  unused, country-matching entry in `listStaticProxies()`" heuristic
  `ProxyProvisioner::findUnattachedProxy()` uses — and both code paths now
  exclude whatever the *other* one has already claimed (`CustomerProxy` rows
  and `Order.proxy_config.matched_proxy_id` respectively), since they draw
  from the same VMOS proxy inventory pool. If fewer proxies can be positively
  matched than were paid for, this does not guess on the remainder — it
  records whatever matched and fails the order with a message pointing to
  support, same "never guess on a paid purchase" rule as the checkout add-on.
- Customer-facing at `/proxies`: browse/buy, then from "Your proxies" attach
  to any owned device (VMOS's `attachProxies`), detach (`disableProxy`, the
  same call the device panel's own "Clear proxy" already uses), or test
  reachability (VMOS's `checkIP`, same as every other "Test proxy" button on
  the site). VMOS's `listStaticProxies()` doesn't return a password field, so
  only host/port/account are shown — same limitation Admin → Proxies already
  has. Admin → Plans & pricing gained a **Proxies** tab with its own sync
  button.
- Order confirmation pages previously only showed delivered email accounts
  and phone numbers, not Cloud Numbers or proxies — `Order::cloudNumbers()`/
  `Order::customerProxies()` relations and matching blocks on `/orders/{id}`
  were added alongside this feature to close that gap for both.
- **"Buy Proxy" popup, not a flat grid** — the browse UI went through two
  iterations: first a flat card grid, then a Country pill filter narrowing
  it, then (per the owner's own screenshots of VMOS's "Purchase Proxy IP"
  popup) replaced entirely by a single **Buy Proxy** button that opens a
  modal — Region select, then Plan radios filtered to that region by Alpine
  (`x-show`, client-side, all SKUs are already in the page), then Submit —
  mirroring VMOS's own purchase flow shape (their popup also has an IP Type
  and IP Provider step we don't replicate, since we only resell one product
  tier and don't expose VMOS's own upstream provider choice to customers).
  Region labels are full names (`VmosRegionCatalog::nameFor()`, a small
  static code→name lookup built from the confirmed 18 `PURCHASE_REGIONS`
  plus a larger best-effort list for display only — never used for
  validation), not raw 2-letter codes, since those read as meaningless to a
  non-technical customer. The old `?country=` query-string filtering is
  gone — `CustomerProxyController::index()` just loads every available Sku
  and the modal's own Region `<select>` does the narrowing.
- **Manually adding your own proxy** — `CustomerProxy.source` is `'vmos'`
  (bought through us, the async purchase/poll/match flow above) or
  `'custom'` (the customer's own proxy, added directly via a form on
  `/proxies` — same idea as the checkout add-on's "Use my own proxy" mode:
  free, no order, no VMOS charge, usable immediately). This needed
  `customer_proxies.order_id`/`sku_id` to go nullable (a custom entry has
  neither) plus new `password`/`proxy_name`/`proxy_type` columns — VMOS's
  `listStaticProxies()` never returns a password for a *bought* proxy, but a
  *custom* one needs one, since it's the customer's own real credentials.
  `attach()` branches on `source`: a `custom` proxy attaches via
  `setCustomProxy()` (the same call the device panel's own proxy form uses),
  a `vmos` one via `attachProxies()` as before — `detach()`/`test()` already
  worked for both without changes, since `disableProxy()`/`checkProxyIp()`
  don't care where the proxy came from. A customer can remove a `custom`
  entry outright (`DELETE /proxies/{id}`); a `vmos` one can't be deleted this
  way — it's real, paid-for inventory, not a free-text entry.
- **Label, Remarks, and Edit** — the owner compared the add-proxy form
  against VMOS's own "Add Proxy" dialog and it was missing a nickname field,
  a free-text notes field, and any way to fix a mistake short of delete+redo.
  Added `label`/`remarks` columns and a `PUT /proxies/{id}` (`update()`,
  `custom` source only, same ownership check as `destroy()`) — the edit
  modal reuses the add form's fields via Alpine (`x-data="{ editing: null
  }"` on the table, populated from `@js($proxy->...)` on each row's Edit
  button). Leaving the password field blank on an edit keeps the existing
  one rather than clearing it, since VMOS's own `listStaticProxies()` (and
  by extension anything reading a *bought* proxy's details) never hands a
  password back to redisplay — there'd be nothing to show in that field even
  if we tried.

Confidence: same tier as the checkout proxy add-on it reuses (the async
purchase/poll/match mechanics are identical, just not tied to a device order)
— full test coverage with faked HTTP responses, but no real purchase against
a live VMOS account yet. Try a real standalone purchase and a real
attach/detach before pointing customers at `/proxies`.

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
