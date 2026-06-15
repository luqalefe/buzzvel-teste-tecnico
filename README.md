# BuzzPay — Multi-Currency Payment Requests

A small but production-shaped service where employees around the world submit payment requests in **their local currency**. At creation the system fetches the live **EUR → local** exchange rate, **freezes it immutably** on the request, and stores the EUR-equivalent. Finance approves or rejects pending requests, and anything left pending for **more than 48 hours expires automatically**.

Built test-first (TDD / XP) as a **Laravel 12 REST API** plus a polished **React 19 + TypeScript SPA** that consumes it.

---

## Highlights

- **Immutable exchange-rate snapshot** — `exchange_rate`, `rate_source` and `rate_fetched_at` can never change after creation (enforced at the model layer).
- **EUR short-circuit** — requests already in EUR use rate `1.0` and make **no** external call.
- **Resilient** — if the rate provider is down, creation returns `503` and **nothing** is persisted.
- **Role-based** — employees see/manage only their own requests; finance reviews everyone's.
- **Automatic 48h expiration** via a scheduled command.
- **OpenAPI / Swagger UI** for every endpoint + a ready-to-import **Postman collection**.
- **React SPA** — "fintech-premium" design (ink/paper + emerald, Inter Tight + JetBrains Mono tabular numbers); dashboard with charts, live EUR-conversion preview, 48h countdown badges, one-click approvals, optimistic updates, **dark/light** themes.
- **Internationalised** — UI in all **24 official EU languages**, and a registration country picker (every European country) that auto-fills the currency and switches the UI to that country's language.
- Every domain behaviour driven by a failing test first.

---

## Tech stack

| Layer | Choice |
|------|--------|
| API | Laravel 12 · **PHP 8.2+** (Composer lock resolved for the 8.2 floor, so it installs on 8.2–8.5; developed on 8.5) |
| Auth | Laravel Sanctum (bearer tokens) |
| Runtime DB | PostgreSQL (via Laravel Sail) |
| Test DB | SQLite in-memory |
| Exchange rates | [exchangerate-api.com](https://www.exchangerate-api.com) (EUR base; free API key) |
| Docs | l5-swagger (OpenAPI 3) + Postman |
| Frontend | React 19, TypeScript, Vite, Tailwind v4, shadcn-style UI (Radix), TanStack Query, React Router, react-i18next, Recharts, Sonner |

---

## Getting started

### Option A — Docker (Laravel Sail) · recommended

```bash
cp .env.example .env
composer install                 # bootstraps ./vendor/bin/sail
./vendor/bin/sail up -d          # PHP + PostgreSQL
./vendor/bin/sail artisan key:generate
# add your exchangerate-api.com key to .env: EXCHANGE_API_KEY=...
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build  # or `npm run dev` for hot reload
```

App: <http://localhost> · Swagger UI: <http://localhost/api/documentation>

### Option B — No Docker (SQLite) · fastest to grade

```bash
cp .env.example .env
# set DB_CONNECTION=sqlite in .env and remove the pgsql DB_* lines
touch database/database.sqlite
composer install
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve            # http://127.0.0.1:8000
```

### Demo accounts (seeded)

| Role | Email | Country / Currency |
|------|-------|--------------------|
| Finance | `finance@buzzvel.test` | Portugal / EUR |
| Employee | `employee@buzzvel.test` | Brazil / BRL |
| Employee | `kenji@buzzvel.test` | Japan / JPY |
| Employee | `hannah@buzzvel.test` | United Kingdom / GBP |
| Employee | `lukas@buzzvel.test` | Germany / EUR |
| Employee | `zofia@buzzvel.test` | Poland / PLN |
| Employee | `astrid@buzzvel.test` | Sweden / SEK |

Password for every account is `password`. The seeder creates **6 employees across 6 countries/currencies** plus the finance member, with ~40 sample requests across all statuses.

> Registration only ever creates **employees** — finance accounts are provisioned out of band (seeded). This prevents anyone self-assigning the finance role.

### Automatic expiration

The `payment-requests:expire` command is scheduled **hourly**. In production run Laravel's scheduler via cron:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

For local dev: `php artisan schedule:work` (or run it manually: `php artisan payment-requests:expire`).

---

## Testing

```bash
php artisan test          # full feature + unit suite
./vendor/bin/pint --test  # PSR-12 / Laravel style check
```

Tests run on **SQLite in-memory** with `RefreshDatabase`, and the exchange-rate provider is faked with `Http::fake()` — the suite is fully deterministic and never touches the network.

---

## API

Base path: `/api`. All domain routes require `Authorization: Bearer {token}` (Sanctum).

| Method | Endpoint | Who | Purpose |
|-------|----------|-----|---------|
| POST | `/api/register` | guest | Register (employee) → token + user |
| POST | `/api/login` | guest | Authenticate → token |
| POST | `/api/logout` | auth | Revoke current token |
| GET | `/api/user` | auth | Current user |
| GET | `/api/payment-requests` | auth | List (own / all for finance), `?status=` filter, paginated |
| POST | `/api/payment-requests` | auth | Create with automatic EUR conversion |
| GET | `/api/payment-requests/preview` | auth | Preview a conversion (no persistence) |
| GET | `/api/payment-requests/stats` | auth | Dashboard aggregates (role-scoped) |
| GET | `/api/payment-requests/{id}` | owner / finance | Show one |
| PATCH | `/api/payment-requests/{id}` | finance | Approve / reject a pending request |

**Status codes**: `201` create · `200` ok · `401` unauthenticated / bad credentials · `403` forbidden · `404` not found · `422` validation / invalid transition · `503` exchange-rate provider unavailable.

Errors follow Laravel's shape: `{ "message": "...", "errors": { ... } }` (the `errors` object is present for validation failures).

**Idempotency**: both unsafe writes — `POST /api/payment-requests` (create) and `PATCH /api/payment-requests/{id}` (approve/reject) — accept an optional `Idempotency-Key` header. A retry with the same key replays the original response (no duplicate create, no second FX call, no double-decision); the same key reused for a *different* request returns `409` (the fingerprint is method + path + body). Transient `503`s (provider down) aren't cached, so they stay retryable. Reads are naturally idempotent and the 48h expiry job is safe to re-run.

- **Interactive docs**: `/api/documentation` (Swagger UI). Regenerate with `php artisan l5-swagger:generate`.
- **Postman**: import [`docs/PaymentPT.postman_collection.json`](docs/PaymentPT.postman_collection.json). Run *Login* first; the token is stored and reused automatically.

---

## How the conversion works

The provider (exchangerate-api.com) is EUR-based: `GET /v6/{KEY}/pair/EUR/BRL` → `{"result":"success","conversion_rate":5.5}` means **1 EUR = 5.5 BRL**. So a local amount converts to EUR by **dividing** by the rate (computed with `bcmath` to avoid float drift):

```
converted_amount_eur = amount / exchange_rate     (rounded to 2 dp)
```

For `EUR`, the rate is `1.0`, no external call is made, and the converted amount equals the original. The provider is configured in `config/exchange.php` (`EXCHANGE_*` env) and fully mocked in tests, so it can be swapped without touching the suite.

---

## Architecture

**Backend** (`app/`)
- `Enums/` — `Role`, `PaymentStatus`, `Currency` (the supported ECB / Frankfurter set).
- `Services/ExchangeRateService` + `ExchangeRate` DTO — isolated rate logic; throws `ExchangeRateUnavailableException` (→ 503).
- `Models/PaymentRequest` — enum casts, the immutability guard (`updating` event), guarded `transitionTo()` state machine, query scopes.
- `Actions/ExpireStalePaymentRequests` + `Console/Commands/ExpirePaymentRequests` — the 48h sweep.
- `Http/Controllers/Api`, `Http/Requests`, `Http/Resources`, `Policies/PaymentRequestPolicy` — thin controllers, Form Request validation, API Resources, policy-based authorization.
- Exception → HTTP mapping in `bootstrap/app.php`.

**Frontend** (`resources/js/`)
- `lib/` (axios client + token interceptor, formatters), `types/`, `data/` (European countries), `i18n/` (24 EU languages), `providers/` (auth, theme), `hooks/` (TanStack Query), `components/ui/` (shadcn-style primitives), `components/` (layout, guards, shared), `features/` (auth, dashboard, payment-requests, finance).
- Token-based auth (stored client-side); the SPA is served by a catch-all web route that never shadows `/api` or `/docs`.

---

## Security notes

- **Authorization** is enforced by a Sanctum guard on every domain route plus a `PaymentRequestPolicy` (employees only touch their own requests; only finance decides).
- **Tokens expire** after 24h (`SANCTUM_TOKEN_EXPIRATION`), and a global `401` interceptor in the SPA clears the session and redirects to login.
- **Login** returns a generic message and runs a hash even for unknown emails, so response timing doesn't reveal whether an account exists.
- **Registration** can never create a finance account.
- **Trade-off**: the SPA stores the bearer token in `localStorage` because the brief specifies a **token-based** API (register/login return a token). This is the conventional SPA pattern but is exposed to XSS; it's mitigated by the finite token expiry above. For a stricter posture, Sanctum's SPA **cookie/session** mode (HttpOnly cookies) would remove the token from JS entirely.

## XP / TDD

Every domain behaviour was written **test-first** (Red → Green → Refactor). Acceptance criteria from the brief map directly to feature/unit tests (e.g. `test_when_the_provider_is_unavailable_it_returns_503_and_persists_nothing`). See `tests/`.

---

## Notes

- All 24 UI languages format **money, dates and numbers in their native locale** (`Intl`, see `resources/js/lib/format.ts`) — not just translated strings.
- The required tests target **the API**; the suite is backend (74 tests / PHPUnit). The React SPA is guarded by **TypeScript type-checking + the production build**, not a separate frontend test harness.
- The submission **video / public URL** lives outside this repo; the README, Swagger UI (`/api/documentation`) and Postman collection cover the walkthrough.
- The exchange-rate provider is swappable via `config/exchange.php` / `EXCHANGE_*` env vars and is fully mocked in tests.
