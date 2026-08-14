# Omni POS — Session Summary (2026-08-14)

Everything done in this session, in one place. Full working log: `.loop/progress.md`.

## Status
- **Tests: 25/25 passing, 73 assertions** (was 2/2 scaffold-only at baseline) — runs locally, no Docker needed
- **11 bugs found & fixed**, each proven by a red → green regression test
- **NativePHP Windows installer built** (151 MB)
- **Free Cloudflare tunnel wired** into docker compose
- Nothing committed yet (see `git status`)

## Bugs fixed
| # | Issue | Fix |
|---|-------|-----|
| P0-1 | Shift cash counts keyed on display names ("Bakong QR" read as $0) | Key on `PaymentMethod.type` (`Shift::revenueForPaymentType`) |
| P0-2 | Checkout oversold into negative stock | Stock guard (row lock + on-hand check) in POS + eMenu |
| P1-1 | Two open shifts per branch possible | Atomic open + partial unique DB index |
| P1-2 | Order tracker enumerable by sequential ID | Gated behind table UUID (`/emenu/order/{tableUuid}/{invoice}`) |
| P1-4 | eMenu "pay at counter" invisible to cashier | Always creates a pending payment row |
| P1-5 | No KHQR auto-confirm | `khqr:check-pending` job (every min; no-op until Bakong creds added) |
| P1-6 | KDS freed tables with other orders open | Frees only when no other invoice is open |
| P2 | Inactive floor plans still served orders | Table QR → 404 when floor plan deactivated |
| P2 | Unbounded cart quantities | Capped at 99 server-side |
| P2 | Public `kds` broadcast leaked order events | Removed (zero subscribers) |
| P2 | Low-stock threshold hardcoded | Config-driven (`LOW_STOCK_THRESHOLD` env) |

Also: KDS polling fallback (`wire:poll.15s`), deterministic eMenu system-user attribution.

## Deliverables
- **Installer:** `nativephp/electron/dist/Omni POS-1.0.0-setup.exe` (unsigned kiosk wrapper → `NATIVE_APP_URL`, default `https://192.168.31.88`)
- **Tunnel:** `cloudflared` service in `compose.yaml` (free + open source); URL from `docker compose logs cloudflared`
- **Test suite:** `tests/Feature/` — PosCheckoutTest, EMenuCheckoutTest, KdsTableLifecycleTest, KhqrCheckTest, OrderTrackingTest + fixtures trait; sqlite `:memory:` via `phpunit.xml`

## Notes
- ~~Bakong credentials, Cloudflare domain, code signing~~ — **skipped by owner decision (2026-08-14)**, not required.
  - KHQR auto-confirm stays a manual no-op job until/unless credentials are ever added (`bakong_token` on a payment method switches it on — no code change needed).
  - The tunnel remains the free quick tunnel (URL changes per restart); stable domain optional later.
  - The installer remains unsigned (SmartScreen warning) unless signing is added later.
- No follow-up work is pending.

## New env vars (documented in .env.example)
- `NATIVE_APP_URL`, `NATIVE_KIOSK` — desktop wrapper target
- `LOW_STOCK_THRESHOLD` — reorder alert level (default 10)
