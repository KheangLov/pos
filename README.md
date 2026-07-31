# Omni POS

A Docker-based Laravel + Filament point-of-sale system: admin panel, POS terminal, Kitchen Display System (KDS), a customer-facing self-order menu (eMenu), realtime order broadcasting via Reverb, and Elasticsearch-backed search. White-labelable per company (logo + name) for reselling to multiple businesses.

Local development uses `compose.yaml` (below). Installing for a paying customer uses a separate sealed build that keeps the source off their filesystem and enforces a signed licence — see [Shipping to a customer](#shipping-to-a-customer).

## Quick start (one click)

1. Make sure [Docker Desktop](https://www.docker.com/products/docker-desktop/) is installed.
2. Double-click [`start.bat`](start.bat) in the project root.

That script will: create `.env` from `.env.example` and generate a fresh app key on first run, start Docker Desktop if it isn't running, bring up every container (`docker compose up -d`), wait for the app to come online, run migrations, seed demo data + the admin account on first run only, create the MinIO image bucket, cache config/routes/views for speed, and open the admin panel in your browser.

To stop everything, double-click [`stop.bat`](stop.bat) (data is preserved — `start.bat` will pick up where you left off).

To have Omni POS start automatically every time you log into Windows, double-click [`enable-autostart.bat`](enable-autostart.bat) once (also turn on "Start Docker Desktop when you log in" in Docker Desktop's own settings). Undo with [`disable-autostart.bat`](disable-autostart.bat).

### Admin login

| Field | Value |
|---|---|
| URL | http://localhost:8000/admin |
| Email | `admin@pos.test` |
| Password | `password` |

Other seeded accounts (all use password `password`): `vanna@brewhaven.coffee` (cashier), `sophia@brewhaven.coffee` (Brew Haven admin), `marcus@techhub.store` (TechHub admin).

### Feature tour

- **POS terminal** (`/admin/pos`) — product grid with modifier picker (milk options, add-ons, etc.), discounts, shift open/close with cash reconciliation, and a fullscreen/kiosk mode (top-right icon) that hides the admin chrome for a dedicated terminal screen.
- **KDS** (`/admin/kds`) — realtime kitchen order board, shows selected modifiers per item.
- **eMenu** (`/emenu/table/{uuid}`, linked from each Table's QR code) — customer self-order menu with the same modifier picker, for scan-and-order at the table.
- **Shifts & EOD report** (`/admin/shifts`) — every shift close redirects to a summary: cash reconciliation (expected vs. counted, with variance), sales breakdown by payment method, and top products.
- **Discounts** (`/admin/discounts`) — percentage or fixed-amount discounts, selectable at POS checkout.
- **Reports** (`/admin/reports`) — product sales report with CSV export.
- **Company branding** (`/admin/companies`) — upload a logo per company; it replaces the admin panel's brand logo/name for that company's users and shows on their eMenu.
- **Product/category/company images** — uploaded through the admin, automatically resized, compressed to WebP, and given a thumbnail, stored in the self-hosted MinIO bucket (see below).

### Manual Docker commands

If you'd rather drive it yourself instead of `start.bat`:

```bash
# Start every service (app, reverb, queue, scheduler, postgres, redis, elasticsearch, minio)
docker compose up -d

# Stop everything (data is preserved in named volumes)
docker compose down

# Tail app logs
docker compose logs -f laravel.test

# Run migrations
docker compose exec laravel.test php artisan migrate --force

# Seed demo data + admin account only if the DB is empty (safe to re-run)
docker compose exec laravel.test php artisan app:ensure-seeded

# Create the MinIO image bucket if it doesn't exist yet (safe to re-run)
docker compose exec laravel.test php artisan app:ensure-minio-bucket

# Full reset: wipe and reseed everything from scratch
docker compose exec laravel.test php artisan migrate:fresh --seed
docker compose exec laravel.test php artisan scout:import "App\Models\Product"
docker compose exec laravel.test php artisan scout:import "App\Models\Category"

# Rebuild frontend assets after changing anything in resources/css or resources/js
docker compose up vite
```

### Frontend assets

The `vite` service builds the frontend once (`npm run build`) and exits — there's no live dev server or HMR. `start.bat` waits for that build before opening the browser. If you edit anything under `resources/css` or `resources/js`, rerun `docker compose up vite` (or `start.bat`) to rebuild; otherwise the browser keeps serving the previously built bundle in `public/build`.

### Performance: config/route/view caching

`start.bat` ends by running `php artisan optimize` and `php artisan filament:optimize`, which cache config, routes, views, events and Filament/icon components — this is a big speedup for a local-only deployment since PHP no longer re-parses config files or re-discovers Filament components on every request. `stop.bat` clears that cache on the way down, so the next `start.bat` always rebuilds it fresh against whatever code is currently on disk — you never need to think about stale cache, just use start/stop as normal. If you're actively editing code and want the change to show up *without* restarting, run `docker compose exec laravel.test php artisan optimize:clear` manually.

### Debugging with Telescope

Telescope is off by default (`TELESCOPE_ENABLED=false` — it costs roughly 0.5–1.5s per request). To use it:

```bash
docker compose exec laravel.test php artisan optimize:clear
```

Set `TELESCOPE_ENABLED=true` in `.env` first, then run the command above and `docker compose restart laravel.test` — both are needed, since config is cached and Octane keeps a booted app in memory. It's then at http://localhost:8000/telescope.

**Access requires the `view_telescope` permission**, in every environment including local. Laravel's stock provider lets anyone through when `APP_ENV=local`, which matters here because the app publishes `0.0.0.0:8000` so phones on the LAN can reach the eMenu — that would leave Telescope readable by anyone on the network. `app/Providers/TelescopeServiceProvider.php` overrides `authorization()` to remove that bypass.

The permission is deliberately **not** attached to the Admin role: this is a multi-tenant product where every customer company has its own Admin, and Telescope shows all tenants' queries, payloads and responses together. `RolePermissionSeeder` syncs the Admin role to the generated per-entity permissions only, so `view_telescope` is never granted by a reseed. Hand it to a specific operator instead:

```bash
docker compose exec laravel.test php artisan tinker --execute="App\Models\User::where('email','you@example.com')->first()->givePermissionTo('view_telescope');"
```

Entries are pruned daily to 48 hours (`routes/console.php`). Note the log watcher is set to `'level' => 'error'` in `config/telescope.php`, so `Log::warning()` and below never reach the Logs tab — lower it to `'debug'` if you need them. Telescope is a dev dependency and is absent entirely from production builds.

### Image storage (MinIO)

Product, category and company images are stored in a self-hosted S3-compatible bucket (MinIO), not the local disk — this is what lets image uploads work the same way whether you're on one machine or several. `app:ensure-minio-bucket` (run automatically by `start.bat`) creates the bucket and makes it publicly readable on first run. The MinIO web console (`http://localhost:9001`, login `minioadmin` / `minioadmin` by default — see `MINIO_ROOT_USER`/`MINIO_ROOT_PASSWORD` in `.env`) lets you browse uploaded files directly.

Uploads are automatically resized (max 1600px), compressed to WebP, and given a 400×400 thumbnail (`app/Support/ImageOptimizer.php`) — POS/eMenu product grids use the thumbnail for faster loading.

### Building the Windows installer (development / demo only)

> **This path ships readable source code.** It copies the entire working tree — `app/`, `resources/`, `database/seeders/` — onto the target machine, where `compose.yaml` bind-mounts it into the container. Use it for demos and internal machines. For a paying customer, use [Shipping to a customer](#shipping-to-a-customer) below.

[`installer/omnipos.iss`](installer/omnipos.iss) is an [Inno Setup](https://jrsoftware.org/isinfo.php) script that packages the whole app (including `vendor/`, `node_modules/` and the pre-built `public/build`, so nothing needs the internet at install time except Docker Desktop itself) into a proper Windows installer/uninstaller with Start Menu shortcuts. It checks for Docker Desktop and points the user at the download page if it's missing; uninstalling stops the containers but never deletes your Docker volumes (data survives reinstalls).

To build it:
1. Install [Inno Setup 6](https://jrsoftware.org/isdl.php) (free) if you haven't already.
2. Prepare a release build: `composer install --no-dev --optimize-autoloader` and `npm install && npm run build`.
3. Compile: `"C:\Program Files (x86)\Inno Setup 6\ISCC.exe" installer\omnipos.iss`
4. The installer lands in `installer\dist\OmniPOS-Setup-<version>.exe`.

### URLs

| Service | URL |
|---|---|
| App | http://localhost:8000 |
| Admin panel | http://localhost:8000/admin |
| eMenu (per table) | http://localhost:8000/emenu/table/{uuid} |
| Reverb (websockets) | ws://localhost:8080 |
| Elasticsearch | http://localhost:9200 |
| MinIO API | http://localhost:9000 |
| MinIO console | http://localhost:9001 |
| Postgres | localhost:5433 |
| Redis | localhost:6380 |

### Troubleshooting

- **`laravel.test` shows "unhealthy" but the site still loads**: on Windows, PHP has to re-warm its opcache against the Windows bind-mounted filesystem after every container start/restart, which can make the first stretch of requests noticeably slow. The app is still usable while this settles — check `docker compose logs laravel.test` if it doesn't improve after a few minutes.
- **Port already in use**: another process (or a stale container from a different project) is bound to 8000/5433/6380/8080/9200/9000/9001. Check with `docker ps -a` and stop the conflicting container, or change the port in `.env`.
- **Elasticsearch won't start**: it needs a v9 image (see `compose.yaml`); if you previously ran an older ES version, its data volume is incompatible — `docker compose down -v` then `docker compose up -d` followed by `scout:import` rebuilds it.
- **CSS/JS changes aren't showing up**: the `vite` container only builds once per `docker compose up`. Run `docker compose up vite` again to rebuild, then hard-refresh the browser.
- **Code changes aren't showing up**: config/routes/views may be cached (see Performance above) — run `docker compose exec laravel.test php artisan optimize:clear`.
- **Uploaded images don't show / upload fails**: the MinIO bucket may not exist yet — run `docker compose exec laravel.test php artisan app:ensure-minio-bucket`.
- **A new `<x-heroicon-o-*>` you added to a Blade view throws "Unable to locate a class or view for component"**: Filament's icon manifest doesn't always pick up every icon on this stack — try a different icon name, or use Filament's own `->icon('heroicon-o-name')` API (e.g. on Actions) instead of a raw Blade component tag, which resolves icons a different way and isn't affected.

---

## Shipping to a customer

Everything in this section is about installing Omni POS on a machine you don't own, without handing over the source.

### What this actually protects — read this first

PHP is interpreted. On the customer's own hardware, the runtime must be able to read your code, so **anyone with root on that box can eventually recover it.** No configuration in this repository changes that, and any tool that claims otherwise is overselling.

What the production setup below *does* achieve is making a copy expensive and obvious instead of trivial:

| Threat | Covered? |
|---|---|
| Staff browsing `C:\...\Omni POS\app` out of curiosity | **Yes** — the source is inside an image layer, not on the filesystem |
| A competitor handed a copy of the install folder | **Yes** — the folder holds a compose file and an env template, nothing else |
| Your git history, demo seeders, tests and dev tooling leaking | **Yes** — stripped at build time, and the build fails if they reappear |
| Running the app on a second site without paying | **Yes, in practice** — licences are signed and machine-bound |
| A determined admin running `docker save` and unpacking layers | **No** — this is recoverable source; the deterrent here is the contract |
| Someone with physical access and time | **No** |

The last two rows are why a signed agreement matters as much as anything technical. Treat the licensing below as the thing that makes casual re-deployment fail loudly, and the contract as the thing that makes deliberate theft actionable.

### Two deployment modes

| | `compose.yaml` (dev) | `compose.prod.yaml` (customer) |
|---|---|---|
| Source | bind-mounted from disk, readable | baked into the image |
| Assets | `vite` container rebuilds on demand | built at image build time |
| Seeders | demo companies + sales history | stripped; `app:provision` instead |
| Dev tooling | Telescope, Debugbar, Pail, PHPUnit | not installed (`--no-dev`) |
| Exposed ports | app, reverb, Postgres, Redis, ES, MinIO + console | app, reverb, MinIO API only |
| Opcache | revalidates on every request | `validate_timestamps=0` (code is immutable) |
| Licence | not enforced | enforced |

### One-time setup: your signing key

Licence keys are Ed25519-signed. You generate the key pair **once**, keep the secret half forever, and ship only the public half.

```bash
docker compose exec laravel.test php artisan license:keygen
```

- The **public key** goes into `LICENSE_PUBLIC_KEY` in every customer's `.env.production`. It can verify licences but never create them, so it is safe to ship.
- The **secret key** signs licences. Put it in your password manager. It is never written to disk, is not recoverable, and must never appear on a machine you deliver. If it leaks, anyone can mint licences that every existing install accepts.

`license:keygen` and `license:issue` live in `app/Console/Commands/Vendor/` and are **deleted from the production image** — issuing licences is not something a customer install can do.

### Building a release

```bash
powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1 -Version 1.0.0
```

This builds [`Dockerfile.prod`](Dockerfile.prod) (three stages: composer `--no-dev` → Vite assets → runtime), then **verifies the result before packaging it**. The build fails if `.git`, `.env`, `tests/`, `installer/`, the demo seeders, the factories, the vendor licence commands, Telescope, Debugbar, PHPUnit or Faker are found in the image, or if anything references a licence signing secret. That check is the point — a `.dockerignore` entry is easy to break by accident, so the guarantee is tested rather than assumed.

Output lands in `dist/<version>/`:

| File | Purpose |
|---|---|
| `omnipos-<version>.tar` | the application image |
| `compose.prod.yaml` | pinned to this exact version |
| `.env.production.example` | template; every `CHANGE ME` is a real secret |
| `start-prod.bat` / `stop-prod.bat` | customer-facing launchers |
| `SHA256SUMS.txt` | checksum of the image tarball |

### Installing on the customer's machine

1. Install Docker Desktop.
2. Copy `dist/<version>/` to the machine and `docker load --input omnipos-<version>.tar`.
3. Copy `.env.production.example` to `.env.production` and fill in every `CHANGE ME`. Generate a distinct `APP_KEY`, database password, MinIO credentials and Reverb secret **per site** — one shared set means one leaked deployment compromises them all. Set `APP_URL` and `MINIO_URL` to the host's LAN IP, not `localhost`, or table QR codes won't work from a phone.
4. Run `start-prod.bat`. It resolves the machine fingerprint, starts the stack, migrates, and prints the licence status.
5. Create the first company, branch and administrator:

```bash
docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan app:provision
```

6. Send yourself the fingerprint printed by `license:show`, issue a licence (below), and install it.

### Licensing

**You (vendor)** — issue a licence bound to the fingerprint the customer sent you:

```bash
docker compose exec laravel.test php artisan license:issue --customer="Brew Haven Co" --machine=<fingerprint> --months=12
```

`--perpetual` issues one that never expires, `--expires=2027-06-30` sets an exact date, and omitting `--machine` issues a portable licence that runs anywhere (useful for your own demo machines, not for customers). The command reads the signing secret from `LICENSE_SECRET_KEY` or prompts for it.

**Customer** — install the key they were sent:

```bash
docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan license:install "OMNIPOS1...."
docker compose -f compose.prod.yaml --env-file .env.production restart app
```

**How it behaves.** The licence resolves to one of seven states, checked on every admin-panel request *and* on every Livewire update — the check is registered as persistent middleware, so a POS page already open in a browser can't keep taking orders after the licence lapses:

| State | Effect |
|---|---|
| Valid | normal operation |
| Expiring (within `LICENSE_WARN_DAYS`, default 14) | amber renewal banner, full access |
| Grace (expired, within `LICENSE_GRACE_DAYS`, default 7) | red banner counting down, **still full access** |
| Expired past grace | admin panel blocked |
| Machine mismatch | admin panel blocked |
| Invalid / tampered | admin panel blocked |
| Missing | admin panel blocked |

The grace window is deliberate: a lapsed renewal must never take a shop offline mid-service, so the system nags for a week before it locks. Blocking covers the admin panel — which includes POS and KDS — but **not** the customer-facing eMenu, since locking that punishes the shop's guests rather than pressuring the operator. Nothing is ever deleted; installing a valid key restores everything immediately.

Set `LICENSE_ENFORCE=false` to disable blocking entirely (this is the default in development, which is why you never see a banner locally).

**Machine binding.** `start-prod.bat` hashes the Windows `MachineGuid` and the SMBIOS system UUID and passes the result in as `LICENSE_MACHINE_ID`, so a licence is tied to real host hardware. If those can't be read, the app falls back to an identifier persisted on the storage volume — that still detects the install being copied elsewhere, but not someone who clones the whole volume set with it. `license:show` tells you which of the two is in force.

### What a release build strips

Removed by [`.dockerignore`](.dockerignore) (never enters the build context) or deleted in the runtime stage:

- `.git` — the single biggest accidental leak; a repo copy hands over the full history regardless of how clean the working tree looks
- `database/seeders/` demo data (BrewHaven, TechHub, sales history) and `database/factories/` — fictional coffee shops have no business in a customer's database. `RolePermissionSeeder` is kept because `app:provision` needs it
- `app/Console/Commands/Vendor/` — the licence issuing tooling
- `tests/`, `phpunit.xml`, `installer/`, `README.md`, `.env.example`, the root `*.php` dev scripts (`dump_schema`, `fix_*`, `populate_*`, `generate_policies`) and `*_prompt.md` notes
- dev dependencies — Telescope, Debugbar, Pail, PHPUnit, Faker, Sail (`composer install --no-dev`)

`APP_DEBUG=false` and `display_errors = Off` matter as much as any of the above: a stack trace rendered in a browser prints file paths and source excerpts to whoever triggered it.

> Telescope's provider is registered conditionally in `AppServiceProvider` rather than listed in `bootstrap/providers.php` — it is a dev dependency, so listing it there makes any `--no-dev` install fatal on boot. Keep it that way.

### Optional: a bytecode encoder

An encoder (SourceGuardian, ionCube) compiles `app/` to bytecode so a recovered image yields no readable business logic. Worth considering, with three caveats specific to this stack:

- Verify your encoder supports **PHP 8.5** before buying. Support for new PHP majors lags, and adopting one pins your production PHP version to whatever it handles.
- The loader is a PHP extension and this image runs **Octane/Swoole** with long-lived workers. Test that combination explicitly; it is not a common configuration.
- Encoders don't cover `.blade.php`, so `pos`, `kds`, `e-menu` and `order-tracking` views ship readable either way.

Encode `app/` only — never `vendor/`, which is MIT-licensed third-party code. If you adopt one, the encode step belongs in `Dockerfile.prod` between the `vendor` stage and the runtime stage. Note that a decompiler exists for every encoder on the market: this raises the cost of theft, it does not prevent it.

### The contract

The technical measures above stop casual copying. Deliberate copying is a legal problem, so the agreement is the part that actually has teeth. At minimum it should cover: a licence grant limited to named sites and machines; explicit prohibitions on decompiling, redistributing and sublicensing; ownership of the software staying with you while the customer's *data* stays theirs; term, renewal and what happens on non-payment (the grace period above should match whatever you write); and source escrow if the customer wants continuity guarantees — which is usually what "we want the source code" really means, and is worth offering instead.

Have a lawyer draft it. The above is a checklist of what to raise with them, not legal advice.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
