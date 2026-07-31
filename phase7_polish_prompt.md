# Phase 7: Performance, UX/UI Polish, Search, Realtime, Docker & Realistic Data

Hi! I am building a comprehensive Omnichannel POS System (Coffee shops, Marts, Pubs, Restaurants, Electronics) using **Laravel v13, Filament v5, PostgreSQL, Redis, Elasticsearch (via Laravel Scout + jeroen-g/explorer), Laravel Reverb, and Docker (Sail)**. Phases 1–6 are complete: multi-tenancy (Company/Branch/User + Spatie Permissions), full catalog (Categories, Products, Variants, Serial Numbers, Modifiers/Groups/Factors), inventory & floor plans, POS Terminal + KDS UIs (Tailwind + Alpine), Reverb WebSockets wired between POS checkout and KDS, and the customer-facing eMenu with QR table ordering.

This phase is about making the app **production-grade**. Work through the 6 workstreams below **in order**, verifying each before moving on. Do not ask me to confirm each step — execute autonomously and report at the end of each workstream.

---

## Workstream 1 — Docker: EVERYTHING runs in containers

Goal: `docker compose up -d` brings up the entire stack with zero host dependencies.

1. Audit `compose.yaml`. It already has `laravel.test` (Sail PHP 8.5), `pgsql`, `redis`, `elasticsearch`. Add dedicated services for:
   - **reverb** — `php artisan reverb:start` (port 8080, healthcheck, restart unless-stopped)
   - **queue** — `php artisan queue:work redis --tries=3 --backoff=5` (Scout indexing jobs, broadcasts)
   - **scheduler** — `php artisan schedule:work`
   - **vite** — dev server for hot reload (or a build stage for prod assets)
2. Add healthchecks + `depends_on` with `condition: service_healthy` so startup order is deterministic (app waits for pgsql/redis/elasticsearch).
3. Update `.env` / `.env.example` so ALL hosts point at service names (`DB_HOST=pgsql`, `REDIS_HOST=redis`, Explorer/Elasticsearch host `elasticsearch:9200`, `REVERB_HOST` correct for both server-side and browser-side — browser must use `localhost`, containers use the service name).
4. Verify: `docker compose up -d`, then confirm every container is healthy, migrations run, and the app loads at `http://localhost`.

## Workstream 2 — Realistic seed data for ALL entities, then run it

Currently `database/seeders/` only has an empty `DatabaseSeeder`. Create **factories + seeders for every model** in `app/Models`: Company, Branch, User, Category, Product, ProductVariant, SerialNumber, ModifierGroup, Modifier, ModifierFactor, FloorPlan, Table, StockTransaction, Shift, CashNote, Invoice, OrderItem, Payment, Discount, Tax — plus roles/permissions.

Requirements — the data must feel like a REAL business, not lorem ipsum:
1. **2 companies**, each themed: e.g. "Brew Haven Coffee" (coffee shop) and "TechHub Electronics" (electronics store), each with 2 branches with real-sounding addresses and phone numbers.
2. **Real product catalogs**: for the coffee company — actual menu items (Americano, Cappuccino, Matcha Latte, Croissant…) with realistic prices ($2.50–$7.00), size variants (S/M/L), and modifiers (extra shot +$0.75, oat milk +$0.60, sugar level factors). For electronics — real product names (iPhone 16 Pro, Galaxy S25, AirPods Pro 2, Anker chargers…) with realistic prices, storage/color variants, and serial numbers on serialized items.
3. **Categories** that match each vertical (Hot Coffee, Iced Drinks, Pastries / Smartphones, Audio, Accessories) with a sensible hierarchy.
4. **Floor plans & tables** for the coffee branches (Main Hall, Terrace; tables T1–T12 with seats), none for electronics.
5. **Operational history**: 30 days of realistic invoices (more on weekends, lunch/evening peaks), each with 1–6 order items, correct tax and discount math, mixed payment methods (cash/card/QR), linked to shifts with opening/closing cash notes, and matching stock transactions so inventory levels are consistent.
6. **Users**: an owner, branch managers, cashiers, and kitchen staff per branch with the right Spatie roles; seed a known super-admin (`admin@pos.test` / `password`) and print credentials at the end.
7. Make seeding **idempotent-safe** (use `migrate:fresh --seed` as the canonical path) and fast (chunked inserts where volume is high).
8. **RUN IT**: execute `sail artisan migrate:fresh --seed` inside Docker and confirm counts per table. Fix any factory/constraint errors until it completes cleanly.

## Workstream 3 — Elasticsearch: seamless, everywhere it matters

Scout + Explorer are installed and Products/Categories index. Take it to "seamless":
1. Ensure `SCOUT_QUEUE=true` so indexing never blocks requests (queue container from Workstream 1 handles it).
2. Make the **POS Terminal product search** hit Elasticsearch: instant search-as-you-type (debounced), matching name, SKU, barcode, and category, with typo tolerance (fuzziness) and prefix matching. Filter results by the active branch and only sellable products — put branch/tenant scoping into `toSearchableArray` / the Explorer index mapping, not client-side.
3. Add searchable support to **ProductVariant** (search by variant SKU/barcode should resolve straight to the sellable item on the POS grid).
4. Make the **eMenu** search use the same index with the customer-safe subset of fields.
5. Add graceful degradation: if Elasticsearch is down, fall back to a database `LIKE` search instead of erroring (wrap the search call, log the failure).
6. After seeding, run `scout:import` for all searchable models inside Docker and verify with real queries (e.g. "capucino" typo should still find Cappuccino; a barcode should return exactly one hit).

## Workstream 4 — WebSockets: complete the realtime picture

Reverb + `OrderCreated` → KDS already works. Add the remaining events where realtime genuinely matters (don't broadcast things nobody watches):
1. **`OrderStatusUpdated`** — KDS bump (preparing → ready → served) pushes to: the POS terminal, and the customer's eMenu order-tracking page (private channel scoped to the table/order token).
2. **`TableStatusChanged`** — floor plan view updates live when a table is seated/ordered/paid.
3. **`StockLow` / stock updates** — when a sale drives on-hand below reorder level, notify manager screens (Filament database notification broadcast is fine).
4. **`PaymentReceived`** — eMenu shows "Paid ✓" in real time; POS cart clears/locks accordingly.
5. Use **private/presence channels with proper authorization** (`routes/channels.php`) — branch-scoped channels so one branch never receives another branch's events; the customer channel authorized by the QR/table session token, not by login.
6. Handle reconnection on the KDS and eMenu (Echo reconnect + a "connection lost" indicator + state refetch on reconnect so no orders are missed).
7. Verify end-to-end in Docker: two browser windows (POS + KDS), place an order, bump statuses, watch the eMenu tracker update without refresh.

## Workstream 5 — Performance

Target: every page under ~200ms server time with the seeded 30-day dataset; no N+1 anywhere.
1. Enable strict mode in dev: `Model::shouldBeStrict()` (prevents lazy loading) and fix every violation it surfaces — Filament tables are the usual offenders; add `modifyQueryUsing` eager loads (`->with([...])`) on every Resource table and relation manager.
2. Review the POS Terminal and KDS queries: eager-load variants/modifiers/category on the product grid, select only needed columns, and cache the product grid per branch in Redis with tag-based invalidation on product save.
3. Add the missing **database indexes**: every FK, plus composite indexes for hot paths (`invoices(branch_id, created_at)`, `order_items(invoice_id)`, `products(company_id, category_id)`, barcode/SKU unique indexes). Create one migration for all of them.
4. Ensure `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` in the Docker env.
5. Move anything slow out of the request cycle onto the queue (receipt generation, Scout indexing, low-stock checks).
6. Use Debugbar/Telescope (already installed) to profile the POS page, KDS, eMenu, and the two heaviest Filament list pages before and after — report query counts and timings in a small before/after table.
7. Frontend: lazy-load product images with proper sizes, debounce search input, and make sure Vite builds are code-split so the eMenu doesn't ship admin JS.

## Workstream 6 — UX/UI polish

Focus on the three surfaces real users touch all day:
1. **POS Terminal**: keyboard-first flow (barcode scanner input always focused, hotkeys for pay/discount/void), skeleton loaders instead of spinners, optimistic cart updates, clear error toasts, large touch targets (min 44px), a visible offline/WS-disconnected banner, and a clean tender screen (cash quick-amount buttons: exact, $5, $10, $20, $50).
2. **KDS**: color-coded order age (green <5min, yellow <10, red >10) with live timers, sound/flash on new order, one-tap bump, and readable-from-2-meters typography.
3. **eMenu**: mobile-first, thumb-reachable add-to-cart, sticky cart summary bar, product images with graceful placeholders, order-status stepper (Received → Preparing → Ready → Served), and dark-mode support.
4. Consistency pass on the Filament admin: sensible navigation groups & icons, empty states with helpful CTAs, badge counts where useful (pending orders, low stock), and confirmation modals on destructive actions.
5. Loading, empty, and error states for EVERY async surface — no blank screens ever.

---

## Definition of done
- `docker compose up -d && sail artisan migrate:fresh --seed && sail artisan scout:import` produces a fully working, fully populated system with zero manual steps.
- Demo flow works end-to-end: log in as cashier at Brew Haven → search "capucino" (typo) → add Large Cappuccino + oat milk → checkout → order appears on KDS instantly → bump to Ready → customer eMenu tracker updates live → payment recorded → stock decremented → invoice visible in admin with correct totals.
- No N+1 warnings under strict mode; report the before/after performance numbers.
- Give me the final summary: what changed per workstream, all seeded credentials, and the exact commands to start everything.

Please acknowledge this state and begin with Workstream 1.
