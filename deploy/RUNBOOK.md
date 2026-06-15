# BuzzPay — Production Deployment Runbook

Operational, step-by-step guide for publishing **BuzzPay** on a **shared Ubuntu 24.04 LTS VPS** that already hosts other sites.

BuzzPay is a single Laravel 12 app: a REST API (Sanctum bearer tokens) under `/api` that also serves the compiled React/Vite SPA from `public/`. There are **no queue workers** — the only background work is Laravel's scheduler (the 48h payment-request expiry, driven by `Schedule::command('payment-requests:expire')->hourly()`).

---

## Safety contract — this deploy is strictly additive

This VPS is **shared**. Every step below only *adds* BuzzPay-specific resources and never touches the existing sites:

- A **new** Nginx vhost file (`/etc/nginx/sites-available/buzzpay.conf`) — existing vhosts are never edited or overwritten.
- A **dedicated** PHP-FPM pool (`buzzpay`) on its **own socket** `/run/php/buzzpay.sock`, running as `buzzpay:www-data` — fully isolated from other pools/sockets.
- A **new** PostgreSQL role and database, both named `buzzpay` — no other database is touched.
- A **new** system user `buzzpay` owning `/var/www/buzzpay`.
- A **new** systemd timer `buzzpay-scheduler.timer` — no shared cron is modified.
- Nginx is **reloaded** (`nginx -s reload` / `systemctl reload nginx`), never restarted, so the other sites keep serving without a drop.
- Globally shared services already present (Nginx, PHP 8.3-FPM) are **not reinstalled**; `provision.sh` checks before installing anything.

> If any single step would overwrite an existing config, **stop** and resolve the name collision first. All BuzzPay artifacts are namespaced with the `buzzpay` prefix specifically to avoid this.

---

## Conventions (used verbatim everywhere)

| Item | Value |
|------|-------|
| App path | `/var/www/buzzpay` |
| Document root | `/var/www/buzzpay/public` |
| System user | `buzzpay` (pool runs as `buzzpay:www-data`) |
| PHP-FPM service | `php8.3-fpm` |
| PHP-FPM pool | `buzzpay` → socket `/run/php/buzzpay.sock` |
| PostgreSQL | database `buzzpay`, role `buzzpay` |
| DB password | `BUZZPAY_DB_PASSWORD` env var (never hardcoded) |
| Domain | `${DOMAIN}` (e.g. `pay.exemplo.com`) |
| Git remote | `https://github.com/luqalefe/buzzvel-teste-tecnico` (branch `main`) |
| Required secret | `EXCHANGE_API_KEY` (exchangerate-api.com) |

Throughout this runbook, replace `${DOMAIN}` with the real hostname (e.g. `pay.exemplo.com`).

---

## 0. Prerequisites

Before touching the server:

1. **DNS** — create an **A record** for `${DOMAIN}` and for `www.${DOMAIN}` pointing at the VPS public IP. (If you serve IPv6, add matching `AAAA` records.) Certbot in step 5 validates both names over HTTP-01, so DNS must resolve first.

   Verify from your laptop:
   ```bash
   dig +short ${DOMAIN}
   dig +short www.${DOMAIN}
   # both must return the VPS public IP
   ```
2. **An exchangerate-api.com API key** ready (free tier is fine). It is set by hand on the server — never committed.
3. **SSH access** to the VPS as a sudo-capable user.
4. Confirm the box is the intended shared host and that Nginx is already serving the other sites:
   ```bash
   systemctl is-active nginx
   ls /etc/nginx/sites-enabled/      # note the existing vhosts — we will NOT touch them
   ```

---

## 1. First SSH + accept host key

```bash
ssh <youruser>@${DOMAIN}
# or by IP:  ssh <youruser>@<VPS_IP>
```

On the very first connection you'll see:

```
The authenticity of host '...' can't be established.
ED25519 key fingerprint is SHA256:...
Are you sure you want to continue connecting (yes/no/[fingerprint])?
```

Type `yes` to add the host key to `~/.ssh/known_hosts`, then authenticate.

---

## 2. Clone / update the repo and run provisioning

The provisioning script is **idempotent and guarded**: it checks whether each resource already exists before creating it, installs only missing packages, and creates **only** BuzzPay-scoped resources.

Clone (first time) — the repo carries the `deploy/` scripts:

```bash
sudo mkdir -p /var/www
sudo git clone https://github.com/luqalefe/buzzvel-teste-tecnico /var/www/buzzpay
cd /var/www/buzzpay
sudo git checkout main
```

> **Ownership matters.** The clone above runs as root, so the tree (and its `.git`) is root-owned. Every later step runs git and the deploy scripts **as the `buzzpay` user**, and modern git (Ubuntu 24.04 ships git 2.43) refuses to operate on a repo owned by someone else ("detected dubious ownership"). `provision.sh` creates the `buzzpay` user and chowns the tree, but if you run any `sudo -u buzzpay git ...` before provisioning, hand the tree over first:
> ```bash
> sudo useradd --system --create-home --shell /usr/sbin/nologin buzzpay 2>/dev/null || true
> sudo chown -R buzzpay:buzzpay /var/www/buzzpay
> ```

On a later run (repo already cloned and owned by `buzzpay`):

```bash
cd /var/www/buzzpay
sudo -u buzzpay git fetch origin
sudo -u buzzpay git checkout main
sudo -u buzzpay git pull --ff-only origin main
```

Export the DB password (kept out of shell history by the leading space; never hardcoded into any file):

```bash
 export BUZZPAY_DB_PASSWORD='<choose-a-strong-password>'
```

Run provisioning. It will (each step guarded):

- install **only missing** packages: `php8.3-fpm php8.3-cli php8.3-pgsql php8.3-bcmath php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip`, PostgreSQL, Certbot's Nginx plugin (`python3-certbot-nginx`), and Node.js LTS via NodeSource; install Composer to `/usr/local/bin/composer` if absent;
- create the `buzzpay` system user if missing, and ensure `/var/www/buzzpay` is owned `buzzpay:buzzpay`;
- create the **dedicated** PHP-FPM pool `buzzpay` on `/run/php/buzzpay.sock` (running as `buzzpay:www-data`) — leaving `www.conf` and other pools untouched;
- create the PostgreSQL role/database `buzzpay` using `BUZZPAY_DB_PASSWORD` (existence-guarded so a re-run is a no-op);
- drop a **new** vhost `/etc/nginx/sites-available/buzzpay.conf`, symlink it into `sites-enabled/`, `nginx -t`, then **reload** (never restart);
- install and enable the `buzzpay-scheduler.timer` systemd unit.

> `php8.3-bcmath` is **required** (not optional): the EUR conversion math in `app/Services/ExchangeRate.php` uses `bcdiv`/`bcadd`/`bcsub` for arbitrary-precision money handling. `ext-intl` is **not** needed.

```bash
sudo -E deploy/provision.sh
```

> `-E` preserves the exported `BUZZPAY_DB_PASSWORD` through sudo. After provisioning, `unset BUZZPAY_DB_PASSWORD` if you won't re-run it in this session.

Sanity check that nothing else was disturbed:

```bash
nginx -t                              # config valid
systemctl is-active php8.3-fpm        # shared service still up
ls /etc/nginx/sites-enabled/          # existing vhosts still present + buzzpay.conf added
ls -l /run/php/buzzpay.sock           # dedicated socket exists
```

---

## 3. Create and fill the production `.env`

The repo ships a single template, `.env.example`, with **local/Sail defaults** (`APP_ENV=local`, `L5_SWAGGER_GENERATE_ALWAYS=true`, `DB_HOST=pgsql`, `DB_DATABASE=laravel`, `DB_USERNAME=sail`). Copy it, then **override every production-critical key** below — **never** edit the example in git:

```bash
cd /var/www/buzzpay
sudo -u buzzpay cp .env.example .env
```

Lock it down **before** putting secrets in (not world-readable):

```bash
sudo chown buzzpay:buzzpay .env
sudo chmod 600 .env
```

Edit `.env` (`sudo -u buzzpay nano .env`) and set/override at least the following (the defaults shipped in `.env.example` are NOT production-safe):

```dotenv
# --- App: flip out of local mode ---
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${DOMAIN}

# --- PostgreSQL (created by provision.sh) — override the Sail defaults ---
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=buzzpay
DB_USERNAME=buzzpay
DB_PASSWORD=<the same value you exported as BUZZPAY_DB_PASSWORD>

# --- Sanctum SPA + CORS are served same-origin; scope them to the real host ---
SANCTUM_STATEFUL_DOMAINS=${DOMAIN},www.${DOMAIN}
CORS_ALLOWED_ORIGINS=https://${DOMAIN}

# --- Exchange-rate provider (config/exchange.php) ---
# Only EXCHANGE_API_KEY is secret/mandatory — set the real key by hand, never commit it.
# The other three already have working defaults in .env.example; keep them.
EXCHANGE_RATE_SOURCE=exchangerate-api.com
EXCHANGE_PROVIDER_URL=https://v6.exchangerate-api.com/v6
EXCHANGE_API_KEY=<your-exchangerate-api.com-key>
EXCHANGE_PROVIDER_TIMEOUT=5

# --- Production: do NOT regenerate Swagger on every request (example ships =true) ---
L5_SWAGGER_GENERATE_ALWAYS=false
```

> **Database-backed drivers.** `.env.example` sets `SESSION_DRIVER=database`, `CACHE_STORE=database`, and `QUEUE_CONNECTION=database`. Leave them as-is: `php artisan migrate --force` (step 4) creates the `sessions`, `cache`, and `jobs` tables, so **no Redis and no queue worker are required**. The `jobs` table exists but is unused — the only background job is the scheduler, not a queue.

Generate the app key:

```bash
sudo -u buzzpay php artisan key:generate
```

Re-verify permissions (the file must stay `600` and owned by `buzzpay`):

```bash
ls -l /var/www/buzzpay/.env     # expect: -rw------- buzzpay buzzpay
```

> `EXCHANGE_API_KEY` is mandatory: creating a non-EUR payment request triggers a live `EUR → local` lookup and persists nothing if the provider is unreachable (returns `503`). EUR requests short-circuit (`Currency::isBase()`) and never call the provider.

---

## 4. First release

`deploy/deploy.sh` performs a release against the current checkout: install deps, build the SPA, migrate, seed (first run), and warm caches. It is safe to re-run.

```bash
cd /var/www/buzzpay
sudo -u buzzpay deploy/deploy.sh --seed
```

What it does (matching this app's real toolchain):

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build                        # vite build -> public/build
php artisan migrate --force          # also creates sessions/cache/jobs tables
php artisan db:seed --force          # only on the first release (--seed flag)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan l5-swagger:generate       # static OpenAPI for prod
```

The seeder (`DatabaseSeeder`) creates the demo accounts (finance + 6 employees across BRL/JPY/GBP/EUR/PLN/SEK) and several sample requests, and is **idempotent** (`firstOrCreate`) — re-running won't duplicate the users. After this step reload the pool so cached config is picked up (deploy.sh does this for you):

```bash
sudo systemctl reload php8.3-fpm
```

> Subsequent deploys (step 7) run **without** `--seed`.

---

## 5. TLS / HSTS via Certbot

DNS for both names must already resolve to this box (step 0). Certbot edits **only the BuzzPay vhost** (scoped by `-d`) and reloads Nginx:

```bash
sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}
```

Choose redirect-to-HTTPS when prompted. Certbot installs the cert, enables HSTS on the BuzzPay vhost, sets up the renewal timer, and reloads (not restarts) Nginx — the other sites are unaffected.

Confirm auto-renewal:

```bash
systemctl list-timers | grep certbot
sudo certbot renew --dry-run
```

---

## 6. Verification

**a. HTTPS + health check** (the app registers Laravel's `/up` via `bootstrap/app.php` `health: '/up'`):

```bash
curl -I https://${DOMAIN}/up                 # expect 200
curl -s https://${DOMAIN}/up | head          # health JSON / 200 page
```

**b. SPA loads** (catch-all web route; the regex `^(?!api|docs|build|storage|up).*$` means it never shadows `/api`, `/docs`, `/build`, `/storage`, `/up`):

```bash
curl -sI https://${DOMAIN}/ | head -1        # 200, serves the React shell
```

**c. Log in as finance** (`finance@buzzvel.test` / `password`). The login response field is `token`:

```bash
TOKEN=$(curl -s https://${DOMAIN}/api/login \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"email":"finance@buzzvel.test","password":"password"}' \
  | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')
echo "$TOKEN"     # non-empty bearer token
```

> The `/login` and `/register` endpoints are throttled to **5 requests/min keyed by email+IP** (`throttle:login`, see `AppServiceProvider`). If you re-run this smoke test rapidly you'll get a `429` — that's the brute-force guard, not a deploy failure. Wait a minute and retry.

**d. Create a BRL request to validate `EXCHANGE_API_KEY` end-to-end.** A BRL amount forces a live `EUR → BRL` lookup; success proves the key works. (`201` = key OK and rate frozen; `503` = provider/key problem and **nothing persisted**.) Idempotency is opt-in (no `Idempotency-Key` header = normal create), so this single call is safe:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://${DOMAIN}/api/payment-requests \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"amount":"100.00","currency":"BRL","description":"deploy smoke test"}'
# expect 201
```

A lighter check that also exercises the provider without persisting anything:

```bash
curl -s "https://${DOMAIN}/api/payment-requests/preview?amount=100&currency=BRL" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
# expect a JSON conversion preview (converted EUR amount + rate)
```

**e. Scheduler timer is active** (drives the 48h auto-expiry; `schedule:run` runs every minute and the `payment-requests:expire` command itself fires hourly):

```bash
systemctl status buzzpay-scheduler.timer       # active (waiting)
systemctl list-timers | grep buzzpay
# optional: prove the sweep runs by hand
sudo -u buzzpay php /var/www/buzzpay/artisan payment-requests:expire
```

**f. Swagger UI** (optional): open `https://${DOMAIN}/api/documentation`.

---

## 7. Future updates

For routine deploys, pull and re-run `deploy.sh` (no provisioning, **no** `--seed`). Run git as the `buzzpay` owner so the working tree stays owned by `buzzpay`:

```bash
cd /var/www/buzzpay
sudo -u buzzpay git pull --ff-only origin main
sudo -u buzzpay deploy/deploy.sh
```

`deploy.sh` rebuilds the SPA, runs `migrate --force`, rebuilds all caches (`config/route/view/event:cache`), regenerates Swagger, and reloads `php8.3-fpm`. Nginx and the other sites are untouched.

> If a deploy changed `composer.json`/lockfiles or migrations, the script handles it; you do **not** need to re-run `provision.sh` unless system packages, the pool, the vhost, or the DB role need to change.

---

## 8. Rollback

Roll back to a previous known-good commit and redeploy. First find the SHA:

```bash
cd /var/www/buzzpay
sudo -u buzzpay git log --oneline -n 15
```

Then reset and redeploy (all as the `buzzpay` owner):

```bash
sudo -u buzzpay git fetch origin
sudo -u buzzpay git reset --hard <previous-good-sha>
sudo -u buzzpay deploy/deploy.sh
```

`deploy.sh` reinstalls deps for that commit, rebuilds the SPA and caches, and reloads PHP-FPM.

> **Migrations:** `git reset` reverts *code*, not the database. If the bad release added a destructive migration, roll the schema back deliberately (`php artisan migrate:rollback --step=N --force`) **before** the code redeploy, and only if those migrations are safely reversible. When in doubt, restore from a `pg_dump` taken before the upgrade rather than rolling forward blindly. Take a backup before risky releases. The DB dump must be written with privileges (`/var/backups` is root-owned, and the `>` redirect would otherwise run as your unprivileged shell), so pipe through `sudo tee`:
> ```bash
> sudo install -d -m 0750 /var/backups          # ensure dir exists (root-owned)
> sudo -u postgres pg_dump buzzpay | sudo tee /var/backups/buzzpay-$(date +%F-%H%M).sql > /dev/null
> ```

---

## 9. Log locations

| What | Where |
|------|-------|
| Laravel app log | `/var/www/buzzpay/storage/logs/laravel.log` |
| Nginx access (BuzzPay vhost) | `/var/log/nginx/buzzpay.access.log` |
| Nginx error (BuzzPay vhost) | `/var/log/nginx/buzzpay.error.log` |
| PHP-FPM pool (buzzpay) | `journalctl -u php8.3-fpm` (+ pool-level `php8.3-fpm.log` / slowlog if configured) |
| Scheduler runs | `journalctl -u buzzpay-scheduler.service` |
| Certbot / renewals | `/var/log/letsencrypt/` |
| PostgreSQL | `/var/log/postgresql/` |

Quick tails:

```bash
sudo -u buzzpay tail -f /var/www/buzzpay/storage/logs/laravel.log
sudo tail -f /var/log/nginx/buzzpay.error.log
journalctl -u buzzpay-scheduler.service -f
```

> Note: the exchange-rate-unavailable and invalid-transition exceptions are intentionally **not** logged (they are listed in `dontReport`; the provider exception carries the API key in its request URL), so a `503` won't appear in `laravel.log` — that's by design.

---

## 10. Security checklist

- [ ] **`.env` is `600` and owned by `buzzpay`** — not world-readable:
      `ls -l /var/www/buzzpay/.env` → `-rw------- buzzpay buzzpay`.
- [ ] **`APP_ENV=production`** and **`APP_DEBUG=false`** in `.env` (the example ships `APP_ENV=local` — confirm you overrode it). Verify config sees production:
      `sudo -u buzzpay php artisan tinker --execute='echo app()->environment().PHP_EOL; echo config("app.debug")?"DEBUG-ON":"debug-off";'`
- [ ] **`APP_KEY` is set** (`php artisan key:generate` was run).
- [ ] **Nginx denies dotfiles, `.env`, and `.git`** (except `/.well-known/` for ACME) — confirm:
      ```bash
      curl -s -o /dev/null -w '%{http_code}\n' https://${DOMAIN}/.env      # 403/404
      curl -s -o /dev/null -w '%{http_code}\n' https://${DOMAIN}/.git/HEAD # 403/404
      curl -s -o /dev/null -w '%{http_code}\n' https://${DOMAIN}/.well-known/acme-challenge/x  # NOT 403 (allowed)
      ```
- [ ] **`L5_SWAGGER_GENERATE_ALWAYS=false`** in `.env` (the example ships `=true`, which would regenerate OpenAPI on every request in prod).
- [ ] **`EXCHANGE_API_KEY` set by hand on the server**, never committed; rotate it via `.env` + `php artisan config:cache` + `systemctl reload php8.3-fpm`.
- [ ] **TLS + HSTS active** for `${DOMAIN}` and `www.${DOMAIN}`; HTTP redirects to HTTPS.
- [ ] **`SANCTUM_STATEFUL_DOMAINS` / `CORS_ALLOWED_ORIGINS`** scoped to the real host (no `*`). (`config/cors.php` sets `supports_credentials=false`; auth is bearer-token.)
- [ ] **PostgreSQL** listens on localhost only (`127.0.0.1`); the `buzzpay` role has a strong password and access to the `buzzpay` DB only.
- [ ] **Production caches built** (`config/route/view/event:cache`) and OPcache enabled in `php8.3-fpm`.

### Firewall (ufw) — CAUTION on a shared box

This VPS already serves other sites. **Do not blindly enable `ufw`** — a default-deny policy will instantly cut off the other sites (and possibly your SSH session) if their ports aren't allowed.

- If `ufw` is **already active**, just confirm 80/443 are open (they almost certainly are, since the other sites are reachable):
  ```bash
  sudo ufw status verbose
  ```
- If `ufw` is **inactive** and you want to enable it, first allow **everything the other sites and you need** before turning it on — at minimum SSH, HTTP, HTTPS:
  ```bash
  sudo ufw allow OpenSSH
  sudo ufw allow 'Nginx Full'      # 80 + 443 (covers BuzzPay AND the existing sites)
  # add any other ports the existing services require BEFORE enabling
  sudo ufw enable
  ```
  Keep your current SSH session open and verify access from a second terminal before closing it. Nginx serves all vhosts on the same 80/443, so `Nginx Full` covers BuzzPay and the neighbours together — no per-site rule is needed.

### fail2ban (optional)

Optional brute-force protection for SSH (and optionally Nginx). Additive and harmless to the other sites if you only enable the `sshd` jail:

```bash
sudo apt-get install -y fail2ban
sudo systemctl enable --now fail2ban
sudo fail2ban-client status
```

---

## Appendix — quick reference

```bash
# App home
cd /var/www/buzzpay

# Run any artisan command as the app user
sudo -u buzzpay php artisan <cmd>

# Reload PHP-FPM (after .env / cache changes) — never restart Nginx blindly
sudo systemctl reload php8.3-fpm

# Reload Nginx after a vhost change (validate first)
sudo nginx -t && sudo systemctl reload nginx

# Rebuild production caches
sudo -u buzzpay php artisan config:cache route:cache view:cache event:cache

# Clear caches (e.g. before debugging a config issue)
sudo -u buzzpay php artisan optimize:clear
```

Demo credentials (seeded): every account uses password `password`.
Finance: `finance@buzzvel.test` (EUR). Employee (BRL): `employee@buzzvel.test`.
