# Omni POS — Loop Progress

Maintained by the `pos-loop` agent. Update after every iteration (action + evidence).

## Stack facts (baseline, 2026-08-14)

- Laravel 13.8 + Filament 5.7 + Livewire 3, Postgres in Docker, Redis, Elasticsearch (Scout/Explorer), MinIO (S3 images), Reverb (websockets), Octane, Telescope (dev). Filament admin in SPA mode.
- Dev runs in Docker (`compose.yaml`); `start.bat` orchestrates. Windows host.
- PHP 8.3.29 available locally. **Docker Desktop was not running** at baseline; local `php artisan test` works for tests that don't need the DB (`.env` points at `DB_HOST=pgsql`, a Docker-internal name).
- `phpunit.xml`: sqlite `:memory:` + `SCOUT_DRIVER=null`/`SCOUT_QUEUE=false` (Scout NullEngine) + broadcast/cache/queue/session neutralized. Materialized-views migration is pgsql-guarded so it skips on sqlite. Tests are Docker/ES-free.
- Tests at baseline: **2 passing** (Feature ExampleTest `GET /` 200 + Unit ExampleTest). No real coverage of any business logic.
- ~198 PHP files in `app/`. Filament resources: Products, ProductVariants, Categories, Companies, Branches, Tables, FloorPlans, Shifts, Discounts, ModifierGroups/Modifiers/ModifierFactors, StockTransactions, Invoices, SerialNumbers, PaymentMethodResource, Users, Roles, Permissions, ActivityLogs.
- **NativePHP: not in `composer.json` yet.** eMenu exists at `/emenu/table/{uuid}`; LAN access via Caddy + HTTPS + QR (documented in README).

## Key architecture facts (iteration 1 deep-dive, 2026-08-14)

- **There is no `Order` model — `Invoice` is the order** (POS, eMenu, and kitchen tickets are all Invoices).
- POS terminal = `app/Filament/Pages/Pos.php` + `pos.blade.php` (Alpine cart → server re-prices in `priceCart()` → `checkout()` in one DB transaction: Invoice + OrderItems + OrderItemModifiers + StockTransactions(sale, negative) + Payment + `syncPaidStatus()` → fires OrderCreated/PaymentReceived/StockLow). Shift open/close lives on the same page.
- KDS = `app/Filament/Pages/Kds.php`; `setStatus()` flips invoice+items, broadcasts OrderStatusUpdated, frees table on completed. Realtime via `echo.private('branch.{id}.kds')`, no polling fallback.
- eMenu = `app/Livewire/EMenu.php` (public route, throttled, server-side cart, checkout with system user, optional pending Payment) + `app/Livewire/OrderTracking.php` (public channel `order.{tableUuid}.{invoiceId}` + `wire:poll.15s`).
- Payments: `PaymentMethod.type` ∈ cash|card|khqr; KHQR via `app/Services/KhqrService.php` (Bakong SDK). POS confirms KHQR manually; `KhqrService::checkTransaction()` exists but is **never called**.
- AuthZ: spatie permissions + record-agnostic policies; tenancy enforced at query layer (`getEloquentQuery()` overrides + action re-scoping). License middleware blocks admin (not eMenu).
- `echo.js` uses `window.location.hostname` for Reverb host (good). QR URLs use `route()` → `APP_URL` — no hardcoded localhost in code; `.env` currently `APP_URL=https://192.168.31.88`.

## Goals & status

| # | Goal | Status | Evidence |
|---|------|--------|----------|
| 1 | Learn app flow, propose improvements, fix issues | learned; **P0-1, P0-2, P1-1, P1-2, P1-4, P1-6 fixed**; P1-5 open (KHQR auto-check) | fixes below (Fixed log) |
| 2 | Admin POS as NativePHP desktop app | **built: `Omni POS-1.0.0-setup.exe` (151 MB, unsigned)**; signing + distribution pending | `nativephp/electron/dist/`; details in "NativePHP (goal 2)" section below |
| 3 | eMenu on network / forward host | **plan ready (Cloudflare named tunnel + Caddy WS routing)**; execution needs domain + `cloudflared` from owner | plan in "eMenu network (goal 3)" section below |
| 4 | All tests pass (and keep green) | **18/18 passing, 56 assertions** | `php artisan test` local 2026-08-14 |

## Findings — verified issues & suggestions

### P0 (money/logic corruption)
- **P0-1 Shift reconciliation splits by display name, not type. → FIXED 2026-08-14.** `Shift::revenueForPaymentType(string $type)` sums successful payments by `PaymentMethod.type` (via `payment_method_id`), with legacy fallback: methodless rows are cash by construction. `ShiftInfolist` delegates to it (`'khqr'` replaces the old `'qr'` literal). Display name still stored on `Payment.method` for receipts.
- **P0-2 No stock-on-hand check at checkout. → FIXED 2026-08-14.** Both `Pos::checkout()` and `EMenu::checkout()` now lock the product rows (`lockForUpdate`), assert `onHand >= qty` inside the transaction, and throw `App\Exceptions\InsufficientStockException` on violation. POS shows a Filament danger notification; eMenu `addError('cart', …)` rendered in the blade above the checkout button. Postgres honours the lock (serializes concurrent checkouts); sqlite ignores it but the guard still applies.

### P1 (functional/correctness)
- **P1-1 Shift open/close races** → **FIXED 2026-08-14**: `Pos::openShift()` now serializes per branch (branch row lock + re-check inside a transaction) and a partial unique index `shifts_single_open_per_branch ON shifts(branch_id) WHERE status='open'` (pgsql + sqlite; MySQL-guarded but app targets pgsql) is the DB backstop against a second open shift forking the drawer history. `closeShift()` was already gated by `currentShift()`.
- **P1-2 eMenu order tracker is public & enumerable** → **FIXED 2026-08-14**: route is now `/emenu/order/{tableUuid}/{invoice}`; `OrderTracking::mount` requires the invoice to belong to that table (else 404). Sequential ids alone can't be walked; the table UUID4 is the capability token. eMenu redirect updated.
- **P1-3 Zero tests on money paths** → **FIXED 2026-08-14**: 18 tests (money math, reconciliation, stock guard, shift singleton, KDS table lifecycle, order-tracking gating) — see tests/Feature.
- **P1-4 eMenu "pay at counter" never surfaces** → **FIXED 2026-08-14**: eMenu now always creates a pending Payment row (selected method, else `method='cash'`, `payment_method_id` set when the id resolves) so the POS pending-payments modal sees it.
- **P1-5 KHQR reconciliation is manual; `checkTransaction()` dead code.** — still open; needs a scheduled job decision (Bakong API creds + polling interval), P2 for now.
- **P1-6 Table lifecycle races** → **FIXED 2026-08-14**: `Kds::setStatus('completed')` frees the table only when no other invoice is pending/preparing/ready on it; `EMenu::checkout` marks occupied only when the table is available (re-orders on an occupied table are allowed and don't flip state).

### P2 (hygiene/hardening)
- Hardcoded: `city: 'Phnom Penh'`, `storeLabel: 'POS Terminal'`, `terminalLabel: 'POS-01'`, `'Counter'` table name, `LOW_STOCK_THRESHOLD = 10` — open (cosmetic; flag if productized).
- eMenu invoice attribution → **FIXED 2026-08-14**: deterministic system user (`orderBy('id')`, branch first, then company).
- Validation gaps → **cart qty cap FIXED 2026-08-14** (`min(99, max(1, qty))` in both checkouts); **floor-plan `is_active` gate FIXED 2026-08-14** (inactive floor plan → table QR 404); payment-method branch scoping already handled in queries (verified).
- Duplicated pricing logic client/server (Alpine getters vs `priceCart()`); `ProductVariant.barcode` unique is global, not per-company — open (schema change, needs dedupe; parked).
- Dead/vestigial: `CashNote` (no UI), `invoices.status='cancelled'` never set — open (cleanup). **Public legacy `kds` channel REMOVED 2026-08-14** (nothing subscribed; leaked order events). **KDS polling fallback ADDED 2026-08-14** (`wire:poll.15s` alongside Echo).
- Policies don't scope records (safe today only because query scoping is consistent) — open (hardening).
- Scout browse cache TTL 10 min → price edits can appear stale — open (config).

## NativePHP blockers (goal 2 research — needs decision)

- App is server-first by architecture (Postgres/Redis/ES/MinIO/Reverb/Octane in compose, multi-tenant, machine-bound licensing). NativePHP options: (a) ship all infra beside desktop runtime — heavy; (b) thin webview to remote server — then NativePHP adds little. Licensing fingerprint may break in native runtime.
- Camera QR scanning (@zxing `getUserMedia`) needs secure context + webview camera permissions (WebView2/CefSharp plumbing).
- Reverb: URL built from `window.location.hostname` + baked scheme/port; LAN/desktop deployment must expose Reverb port + TLS consistency; mixed content risk.
- Printing: hidden-iframe `window.print()` — webviews inconsistent; thermal printing likely needs native host API.
- Kiosk mode uses `requestFullscreen()` — webviews may restrict; prefer native window flags.
- Uploads → MinIO public URL must match the host users browse from; cert trust in webview.

## NativePHP (goal 2 — research done 2026-08-14, decision recorded)

Research (2026): NativePHP Desktop v2 is officially production-ready. Requires PHP 8.3+, **Laravel 11+** (13 is fine), Node 22+, Windows 10+ / macOS 12+ / Linux — Windows is now first-class (Sept 2025 release fixed Windows port conflicts; i18n + stability work). Install: `composer require nativephp/desktop` → `php artisan native:install` → `php artisan native:run` (dev) / `native:build win`. Gotchas: unsigned Windows builds can sit as a zombie process for a long startup before showing a window (GH issue #105); distribution requires signing (Azure Trusted Signing or certificate; SmartScreen otherwise). Web-wrapper mode is a real, working pattern (`Window::open()->url('https://…')` — the CitiPOS case).

**Decision (recommended): thin wrapper, NOT bundled infra.** The app is server-first (Postgres/Redis/ES/MinIO/Reverb/Octane in compose, multi-tenant, machine-bound licensing). Bundling that stack into each terminal (option a) is high ops complexity for near-zero benefit. NativePHP's real wins for this POS: kiosk/fullscreen window flags, app lifecycle (auto-restart/crash recovery), signed installer + auto-update distribution — all available with a thin Electron shell pointing at the deployed server. Before any wrapper work: verify the license middleware + camera QR (getUserMedia) behave in the Electron webview (UA differs). Cost: signing (~$10/mo Azure Trusted Signing or a cert) + per-platform builds. **Sequence: deploy server → wrapper shell → iterate. Not a prerequisite for current pain; parked as polish.**

**DONE 2026-08-14:** `composer require nativephp/desktop` (v2.2) + `php artisan native:install`; `NativeAppServiceProvider` opens `Window::open('pos')->url(env('NATIVE_APP_URL', 'https://192.168.31.88'))->kiosk(NATIVE_KIOSK)->preventLeaveDomain()`. `php artisan native:build win` **succeeded** → `nativephp/electron/dist/Omni POS-1.0.0-setup.exe` (151 MB, unsigned, oneClick NSIS). `nativephp/` build output gitignored.

**Build blocker solved (Windows symlink):** electron-builder's winCodeSign cache 7z contains macOS dylib symlinks; extracting it fails on Windows without Developer Mode/admin ("Cannot create symbolic link"), and the cache hash changes per run so manual extraction is futile. Fix: in the electron scaffold `vendor/nativephp/desktop/resources/electron/electron-builder.mjs`, set `win.signAndEditExecutable: false` — skips rcedit/signing entirely so winCodeSign is never downloaded. Signing can be re-enabled via Azure Trusted Signing (config already wired in `electron-builder.mjs` when azure env vars are set).

**Runtime notes:** the wrapper boots the embedded PHP server; the machine running it must reach the app's DB (this host qualifies via forwarded compose ports). `NATIVE_APP_URL` is read at runtime from the bundled .env (not in cleanup list) — set it before building for a different server URL. Unsigned installer → SmartScreen warning; sign for distribution (Azure Trusted Signing or cert).

## eMenu network (goal 3 — plan researched 2026-08-14)

- QR URLs: `route('emenu.table', ['uuid' => ...])` → `APP_URL`; no hardcoded host in code. Footguns: `APP_URL`/`MINIO_URL` per install (`.env.production.example` documents this).
- LAN path exists (Caddy TLS + CA cert + phones trust it). P1-2 (tracker enumeration) is fixed; tracking URLs now need the table UUID.
- **Recommended: Cloudflare named tunnel (free).** `cloudflared` (Windows exe or the official Docker image as a compose service) dials outbound — no port forwarding, no public IP. Free tier proxies HTTP + WebSockets (100s idle WS timeout — Laravel Echo's ping keeps it alive). Named tunnel = stable subdomain (`emenu.yourdomain.com`); quick tunnels (`trycloudflare.com`) are ephemeral and unsuitable for printed QR codes.
- **DONE 2026-08-14 (quick tunnel):** added a `cloudflared` service to `compose.yaml` (`cloudflare/cloudflared:latest`, Apache-2.0 → free + open source as requested) running `tunnel --no-autoupdate --no-tls-verify --url https://caddy:443`. Caddyfile now path-routes `/app*` (WebSocket) → reverb:8080 on :443, so one tunnel hostname serves the app AND realtime. Get the URL: `docker compose logs cloudflared` (grep trycloudflare). NOTE: ephemeral per restart + port 8443 is baked in `.env` (`VITE_REVERB_PORT`) by the cert script — for tunnel realtime, set `VITE_REVERB_PORT=443` in .env and rebuild vite (`docker compose up -d --force-recreate vite`); LAN on 8443 keeps working either way.
- **Topology (matches how `echo.js` builds its WS URL from `window.location.hostname`):** ONE tunnel hostname → local Caddy → path routing: `/app/*` (WebSocket) → Reverb `:8080`, everything else → Laravel/Octane. Then `APP_URL=https://emenu.yourdomain.com`, `REVERB_HOST=emenu.yourdomain.com`, `REVERB_PORT=443`, `REVERB_SCHEME=https`; MINIO URLs must be reachable from customer phones (public URL or proxied path).
- Dev-time alternative: `localhoist` (`composer require --dev localhoist/laravel` + `php artisan share`) rewires Vite HMR + Reverb through one free quick tunnel — good for demos, not for printed QRs.
- Needs from owner: a domain on Cloudflare (free account) + installing `cloudflared`. Nothing in the app code needs changing beyond `.env`.

## Fixed log

_Each entry: date · what · tests · files._

- 2026-08-14 · **P0-1** shift reconciliation by `PaymentMethod.type` (legacy cash fallback) · red→green: `test_shift_reconciliation_counts_sales_by_payment_method_type`, `test_legacy_methodless_cash_rows_still_reconcile_as_cash` · `app/Models/Shift.php` (`revenueForPaymentType`), `app/Filament/Resources/Shifts/Schemas/ShiftInfolist.php`.
- 2026-08-14 · **P0-2** stock-on-hand guard in POS + eMenu checkout (row lock + throw, surfaced per-context) · red→green: `test_checkout_is_blocked_when_stock_is_insufficient` (POS + eMenu), exact-stock test · `app/Exceptions/InsufficientStockException.php` (new), `app/Filament/Pages/Pos.php`, `app/Livewire/EMenu.php`.
- 2026-08-14 · **P1-4** eMenu always records a pending payment (selected method or cash) · red→green: `test_emenu_checkout_without_method_creates_pending_cash_payment` · `app/Livewire/EMenu.php`, `resources/views/livewire/e-menu.blade.php` (error display).
- 2026-08-14 · **P1-1** one open shift per branch: atomic open (`Branch` lock + re-check in transaction) + partial unique index backstop · green: `test_opening_a_shift_twice_keeps_a_single_open_shift`, `test_a_second_open_shift_on_the_same_branch_violates_the_unique_index` · `app/Filament/Pages/Pos.php`, `database/migrations/2026_08_14_000000_add_single_open_shift_per_branch_index.php` (new).
- 2026-08-14 · **P1-6** table lifecycle: KDS frees a table only when no other invoice is open; eMenu occupies only when available · green: `KdsTableLifecycleTest` (2), `test_ordering_on_an_already_occupied_table_still_works` · `app/Filament/Pages/Kds.php`, `app/Livewire/EMenu.php`.
- 2026-08-14 · **P1-2** order tracking gated by table UUID (`/emenu/order/{tableUuid}/{invoice}`, mismatch = 404) · green: `OrderTrackingTest::test_tracking_requires_the_table_uuid` · `routes/web.php`, `app/Livewire/OrderTracking.php`, `app/Livewire/EMenu.php` (redirect).
- 2026-08-14 · **P2** hardening: eMenu floor-plan `is_active` gate (404), cart qty cap 99 (POS + eMenu), deterministic eMenu system user, KDS `wire:poll.15s` fallback, public legacy `kds` broadcast removed (nothing subscribed; leak), low-stock threshold config-driven (`config/pos.php` + `StockTransaction::lowStockThreshold()`, env `LOW_STOCK_THRESHOLD`) · green: `test_emenu_table_is_not_reachable_on_an_inactive_floor_plan`, `test_emenu_cart_quantity_is_capped_at_99`, `test_cart_quantity_is_capped_at_99` · `app/Livewire/EMenu.php`, `app/Filament/Pages/Pos.php`, `app/Events/OrderCreated.php`, `app/Models/StockTransaction.php`, `app/Services/AiAssistantService.php`, `app/Filament/Resources/Products/Tables/ProductsTable.php`, `resources/views/filament/pages/kds.blade.php`, `config/pos.php` (new), `.env.example`.

## Iteration log

- 2026-08-14 — Baseline: infra recon + stack facts recorded. `php artisan test` local: **2/2 passed** (ExampleTests only).
- 2026-08-14 — Iteration 1 (deep-dive): full app-flow map produced; P0/P1/P2 issues compiled and **P0-1 verified directly in source** (`Pos.php:422` stores `name`, `ShiftInfolist.php:117-123` matches `cash|card|qr`; no seeder creates methods). NativePHP + eMenu facts recorded. Next slice: **write money-path test suite (goal 4) → proves P0 bugs → fix them (goal 1)**.
- 2026-08-14 — Iteration 2 (test-first fixes): added sqlite `:memory:` test DB (`phpunit.xml`), Scout null engine, pgsql-guarded materialized-views migration; added `tests/Feature/Concerns/CreatesPosFixtures.php`, `PosCheckoutTest.php` (7), `EMenuCheckoutTest.php` (4); roles seeded in fixture (NotifyLowStock listener); fixed cart payloads (`key`/`name`). Red phase proved P0-1 (undefined `revenueForPaymentType`), P0-2 (both checkouts oversold), P1-4 (no payment row). Implemented the three fixes; two tests were updated to seed stock (the math tests previously relied on negative stock). **Final: 12/12 passed, 46 assertions.** Pint run on changed files. Next slice: **P1-1 shift race guard + P1-6 table lifecycle, then NativePHP decision (goal 2), then tunnel + P1-2 (goal 3)**.
- 2026-08-14 — Iteration 3 (races + enumeration): P1-1 fixed (atomic open + partial unique index, DB-enforced on sqlite too), P1-6 fixed (KDS keeps table occupied while other invoices are open; eMenu occupy-if-available), P1-2 fixed (tracking URL requires table UUID). Added `KdsTableLifecycleTest`, `OrderTrackingTest`, +2 POS shift tests, +1 eMenu occupy test. **Final: 18/18 passed, 56 assertions.** Pint clean. Next slice: **goal 2 NativePHP research/decision + goal 3 tunnel plan (P1-5 KHQR auto-check is the only open P1)**.
- 2026-08-14 — Iteration 4 (decisions): research done — NativePHP v2 production-ready, Windows first-class; **decision: thin Electron wrapper, not bundled infra (parked as polish)**. Goal 3 plan: Cloudflare named tunnel → Caddy path routing (WS `/app` → Reverb, rest → Laravel); `.env`-only changes; needs owner's domain + `cloudflared`. Remaining open work (needs owner input): P1-5 KHQR auto-check (Bakong API keys + polling job), tunnel execution, wrapper build.
- 2026-08-14 — Iteration 5 (execution): **P1-5** implemented keyless-now — `khqr:check-pending` command (every minute, `withoutOverlapping`) polls Bakong only for pending khqr payments that carry an md5 AND a method token; eMenu tracking page now persists `khqr_md5` when the QR renders; no-op until Bakong credentials are added. Tests: `KhqrCheckTest` (4) — verified→successful+invoice paid, unconfirmed→stays pending, no token/md5→no API call. **Goal 3**: added `cloudflared` quick-tunnel service to compose.yaml + Caddy `/app*` WS path route; docs for URL retrieval + `VITE_REVERB_PORT=443` note. **Goal 2**: installed nativephp/desktop v2.2, configured thin kiosk wrapper (`NativeAppServiceProvider`), solved the Windows symlink build blocker (`win.signAndEditExecutable: false` in the electron scaffold), built **`Omni POS-1.0.0-setup.exe` (151 MB)**. `.env.example` gains NATIVE_APP_URL/NATIVE_KIOSK; `/nativephp` gitignored. **Suite: 22/22 passed, 70 assertions.** Open: goal 3 URL stability (named tunnel) + goal 2 signing/distribution + P1-5 Bakong credentials.
- 2026-08-14 — Iteration 6 (P2 hardening): floor-plan `is_active` gate on eMenu table QR (404), cart qty capped at 99 in both checkouts, deterministic eMenu system-user attribution, KDS polling fallback (`wire:poll.15s`), public legacy `kds` broadcast channel removed (verified zero subscribers; leak). **Suite: 25/25 passed, 73 assertions.** Remaining open: P2 cosmetics (hardcoded labels, barcode uniqueness, CashNote cleanup, policy scoping, Scout TTL). **Owner-dependent items (Bakong creds, stable tunnel domain, installer signing) SKIPPED by owner decision 2026-08-14 — loop closed.**
