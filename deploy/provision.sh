#!/usr/bin/env bash
#
# deploy/provision.sh — BuzzPay initial provisioning for Ubuntu 24.04 LTS
# =============================================================================
# Laravel 12 (API REST + Sanctum) that also serves a React/Vite SPA from public/.
#
# CONTEXT: this VPS ALREADY HOSTS OTHER SITES. Everything here is STRICTLY
# ADDITIVE and NON-DESTRUCTIVE:
#   * We NEVER touch, edit or overwrite existing nginx vhosts/configs.
#     We only ADD a brand-new vhost dedicated to BuzzPay.
#   * BuzzPay gets its OWN PHP-FPM pool + its OWN socket
#     (/run/php/buzzpay.sock) — total isolation from the other sites' pools.
#   * We 'reload' nginx AND php-fpm (never 'restart') so the OTHER sites keep
#     their running workers/pools and never drop an in-flight request.
#   * Globally-shared services (nginx, postgresql, php-fpm) are NEVER
#     reinstalled if already present — we only install what is missing.
#   * Every step is idempotent and guarded: safe to re-run any number of times.
#
# RUN AS: root (or via sudo). This script provisions the host only.
#   It does NOT build/release the app — that is deploy.sh's job.
#
# REQUIRED ENV:
#   BUZZPAY_DB_PASSWORD   PostgreSQL password for the 'buzzpay' role (no default).
# OPTIONAL ENV:
#   DOMAIN                Public domain (e.g. pay.exemplo.com). Used only to echo
#                         the certbot next-step; the vhost ships templatized.
# =============================================================================

set -euo pipefail

# --- Fixed conventions (must match every other BuzzPay artifact) -------------
APP_USER="buzzpay"
APP_GROUP="www-data"
APP_PATH="/var/www/buzzpay"
APP_DOCROOT="${APP_PATH}/public"
PHP_VER="8.3"                                   # native on Ubuntu 24.04
PHP_FPM_SERVICE="php${PHP_VER}-fpm"
FPM_POOL_DST="/etc/php/${PHP_VER}/fpm/pool.d/buzzpay.conf"
FPM_SOCKET="/run/php/buzzpay.sock"              # DEDICATED socket
NGINX_AVAILABLE="/etc/nginx/sites-available/buzzpay"
NGINX_ENABLED="/etc/nginx/sites-enabled/buzzpay"
SYSTEMD_DIR="/etc/systemd/system"
GIT_REMOTE="https://github.com/luqalefe/buzzvel-teste-tecnico"
GIT_BRANCH="main"
COMPOSER_BIN="/usr/local/bin/composer"
DB_NAME="buzzpay"
DB_ROLE="buzzpay"
DOMAIN="${DOMAIN:-pay.exemplo.com}"             # placeholder; real value at deploy/certbot time

# This script's own directory == the cloned repo's deploy/ folder. We copy the
# committed config templates from here into their system locations.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# -----------------------------------------------------------------------------
log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m  ok\033[0m %s\n' "$*"; }
skip() { printf '\033[1;33m  ~~\033[0m %s (already present, skipping)\n' "$*"; }
die()  { printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "Run as root (or via sudo)."

# -----------------------------------------------------------------------------
# 0. Sanity: required secrets must be supplied via env, never hardcoded.
#    BUZZPAY_DB_PASSWORD is only *needed* if the role does not yet exist, but we
#    fail fast here so a fresh run never gets half-way before noticing.
# -----------------------------------------------------------------------------
: "${BUZZPAY_DB_PASSWORD:?Set BUZZPAY_DB_PASSWORD before running (PostgreSQL role password).}"

# -----------------------------------------------------------------------------
# 1. apt: refresh index, then install ONLY the packages that are missing.
#    We never reinstall nginx / postgresql if they are already on the box,
#    so other sites are untouched.
# -----------------------------------------------------------------------------
log "Refreshing apt package index"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y

# pkg_installed <pkg> -> 0 if dpkg reports it installed
pkg_installed() { dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -q "install ok installed"; }

# Required PHP extensions are derived from composer.json / composer.lock
# (laravel/framework, sanctum, l5-swagger, tinker) + the PostgreSQL driver +
# production opcache. composer.json requires php ^8.2; Ubuntu 24.04 ships 8.3
# natively, which satisfies it.
#   php8.3-{cli,fpm} ........ runtime + FPM SAPI
#   php8.3-pgsql ............ pdo_pgsql / pgsql (config/database.php uses pgsql)
#   php8.3-bcmath .......... money / rate math (ext-bcmath)
#   php8.3-mbstring ........ ext-mbstring
#   php8.3-xml ............. ext-xml / dom / xmlwriter / libxml (l5-swagger)
#   php8.3-curl ........... ext-curl (exchangerate-api.com HTTP client)
#   php8.3-zip ............ ext-zip (composer dist installs)
#   php8.3-gd ............. ext-gd
#   php8.3-intl .......... ext-intl
#   php8.3-opcache ....... production bytecode cache (opcache enabled in prod)
# Host tooling (curl / ca-certificates / gnupg) is required EARLY by the Node
# (NodeSource) and Composer-installer steps below, so it is installed here in
# the same guarded pass rather than assumed present on a minimal image.
WANT_PKGS=(
  "php${PHP_VER}-cli"
  "php${PHP_VER}-fpm"
  "php${PHP_VER}-pgsql"
  "php${PHP_VER}-bcmath"
  "php${PHP_VER}-mbstring"
  "php${PHP_VER}-xml"
  "php${PHP_VER}-curl"
  "php${PHP_VER}-zip"
  "php${PHP_VER}-gd"
  "php${PHP_VER}-intl"
  "php${PHP_VER}-opcache"
  "postgresql"                # shared service — installed only if absent
  "nginx"                     # shared service — NEVER reinstalled if present
  "certbot"
  "python3-certbot-nginx"
  "git"
  "unzip"
  "curl"                      # NodeSource bootstrap + general HTTP fetches
  "ca-certificates"           # TLS trust store for https downloads
  "gnupg"                     # NodeSource apt key handling
  "acl"                       # setfacl for storage/cache perms in deploy.sh
)

MISSING=()
for p in "${WANT_PKGS[@]}"; do
  if pkg_installed "$p"; then
    skip "package ${p}"
  else
    MISSING+=("$p")
  fi
done

if [ "${#MISSING[@]}" -gt 0 ]; then
  log "Installing missing packages: ${MISSING[*]}"
  apt-get install -y --no-install-recommends "${MISSING[@]}"
  ok "installed ${#MISSING[@]} package(s)"
else
  ok "all required packages already present"
fi

# -----------------------------------------------------------------------------
# 2. Composer (official installer, with signature verification) -> /usr/local/bin
#    We only check the canonical path: the BuzzPay convention is a Composer at
#    ${COMPOSER_BIN}. A composer elsewhere on PATH must NOT make us skip the
#    install of the one we depend on.
# -----------------------------------------------------------------------------
if [ -x "$COMPOSER_BIN" ]; then
  skip "Composer ($("$COMPOSER_BIN" --version 2>/dev/null | head -n1 || echo present))"
else
  log "Installing Composer (verifying installer hash)"
  EXPECTED_SIG="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
  php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
  ACTUAL_SIG="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
  if [ "$EXPECTED_SIG" != "$ACTUAL_SIG" ]; then
    rm -f /tmp/composer-setup.php
    die "Composer installer signature mismatch — aborting."
  fi
  php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
  ok "Composer installed at ${COMPOSER_BIN}"
fi

# -----------------------------------------------------------------------------
# 3. Node.js LTS via NodeSource — only if node is missing or older than v18.
#    (Vite 7 build of the SPA into public/build happens later in deploy.sh.)
# -----------------------------------------------------------------------------
NODE_OK=0
if command -v node >/dev/null 2>&1; then
  NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
  if [ "${NODE_MAJOR:-0}" -ge 18 ]; then
    NODE_OK=1
    skip "Node.js ($(node -v))"
  fi
fi
if [ "$NODE_OK" -ne 1 ]; then
  log "Installing Node.js LTS via NodeSource"
  curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
  apt-get install -y nodejs
  ok "Node.js installed ($(node -v))"
fi

# -----------------------------------------------------------------------------
# 4. System user + application directory (idempotent).
#    Pool runs as buzzpay:www-data; setgid keeps new files group-owned by
#    www-data so nginx (www-data) can read static assets. Owner is the
#    dedicated buzzpay user.
# -----------------------------------------------------------------------------
if id "$APP_USER" >/dev/null 2>&1; then
  skip "system user ${APP_USER}"
else
  log "Creating system user ${APP_USER}"
  useradd --system --create-home --home-dir "/home/${APP_USER}" \
          --shell /usr/sbin/nologin --user-group "$APP_USER"
  ok "user ${APP_USER} created"
fi
# Ensure buzzpay is a supplementary member of www-data (so the FPM pool's
# group is valid and so the user can collaborate on www-data-grouped files).
usermod -aG "$APP_GROUP" "$APP_USER"

if [ -d "$APP_PATH" ]; then
  skip "directory ${APP_PATH}"
else
  log "Creating ${APP_PATH}"
  mkdir -p "$APP_PATH"
fi
chown "${APP_USER}:${APP_GROUP}" "$APP_PATH"
chmod 2750 "$APP_PATH"        # setgid (2) + owner rwx + group rx; no world access

# -----------------------------------------------------------------------------
# 5. PostgreSQL: create the dedicated role + database ONLY if absent.
#    Password is read from the env var — never written into this script.
#    We touch nothing else in the cluster (other sites' DBs untouched).
#    On PG 16 (Ubuntu 24.04) the database OWNER is implicitly a member of
#    pg_database_owner, which owns the 'public' schema, so the buzzpay role can
#    create tables there at migrate time without extra GRANTs.
# -----------------------------------------------------------------------------
log "Ensuring PostgreSQL role + database for BuzzPay"
systemctl enable --now postgresql >/dev/null 2>&1 || true

# Role
if [ "$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_ROLE}'")" = "1" ]; then
  skip "PostgreSQL role ${DB_ROLE}"
else
  # Pass the secret via a psql variable interpolated as a quoted literal (:'pw'),
  # fed on STDIN. psql does NOT interpolate :'var' in -c mode — only when reading
  # a script/STDIN — so we pipe the statement in. This keeps the password out of
  # process args and shell history.
  printf "CREATE ROLE %s LOGIN PASSWORD :'pw';\n" "${DB_ROLE}" \
    | sudo -u postgres psql -v ON_ERROR_STOP=1 -v pw="${BUZZPAY_DB_PASSWORD}"
  ok "role ${DB_ROLE} created"
fi

# Database (owned by the role)
if [ "$(sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'")" = "1" ]; then
  skip "PostgreSQL database ${DB_NAME}"
else
  sudo -u postgres createdb -O "$DB_ROLE" "$DB_NAME"
  ok "database ${DB_NAME} created (owner ${DB_ROLE})"
fi

# -----------------------------------------------------------------------------
# 6. Install BuzzPay's OWN config files from the repo. These are additive:
#    a dedicated FPM pool, a dedicated nginx vhost, and systemd units.
#    Existing pools/vhosts/units belonging to other sites are NEVER touched.
# -----------------------------------------------------------------------------
log "Installing BuzzPay configuration files"

# 6a. Dedicated PHP-FPM pool -> isolated socket /run/php/buzzpay.sock
[ -f "${SCRIPT_DIR}/php/buzzpay-pool.conf" ] \
  || die "Missing ${SCRIPT_DIR}/php/buzzpay-pool.conf (clone the repo first)."
install -m 0644 "${SCRIPT_DIR}/php/buzzpay-pool.conf" "$FPM_POOL_DST"
ok "FPM pool -> ${FPM_POOL_DST}"

# 6b. Dedicated nginx vhost. We add it to sites-available and symlink it into
#     sites-enabled WITHOUT removing or rewriting any other enabled site.
[ -f "${SCRIPT_DIR}/nginx/buzzpay.conf" ] \
  || die "Missing ${SCRIPT_DIR}/nginx/buzzpay.conf (clone the repo first)."
# Render the real domain into the vhost. The conf templatizes ONLY the literal
# ${DOMAIN} token (nginx does NOT expand env vars), so we substitute just that
# token and leave every nginx $variable ($uri, $https, $realpath_root, ...)
# untouched. envsubst is deliberately avoided (it would clobber those).
sed "s|\${DOMAIN}|${DOMAIN}|g" "${SCRIPT_DIR}/nginx/buzzpay.conf" > "$NGINX_AVAILABLE"
chmod 0644 "$NGINX_AVAILABLE"
ok "nginx vhost rendered for ${DOMAIN} -> ${NGINX_AVAILABLE}"
if [ -L "$NGINX_ENABLED" ] || [ -e "$NGINX_ENABLED" ]; then
  skip "nginx symlink ${NGINX_ENABLED}"
else
  ln -s "$NGINX_AVAILABLE" "$NGINX_ENABLED"
  ok "nginx vhost enabled (symlink only — other sites untouched)"
fi

# 6c. systemd units: scheduler service + timer. The timer fires
#     'php artisan schedule:run' every minute; Laravel's scheduler then decides
#     when each task runs — here payment-requests:expire is scheduled hourly
#     (see routes/console.php) and sweeps pending requests past their 48h TTL.
#     There is NO queue worker — the app runs no queues in production.
for unit in buzzpay-scheduler.service buzzpay-scheduler.timer; do
  if [ -f "${SCRIPT_DIR}/systemd/${unit}" ]; then
    install -m 0644 "${SCRIPT_DIR}/systemd/${unit}" "${SYSTEMD_DIR}/${unit}"
    ok "systemd unit -> ${SYSTEMD_DIR}/${unit}"
  else
    die "Missing ${SCRIPT_DIR}/systemd/${unit} (clone the repo first)."
  fi
done

# 6d. Allow the buzzpay user to gracefully reload ONLY its own php-fpm service
#     without a password, so deploy.sh (run as buzzpay) can reload after each
#     release. Scoped to one exact command — no broad sudo is granted.
SUDOERS_FILE="/etc/sudoers.d/buzzpay-deploy"
printf '%s ALL=(root) NOPASSWD: /usr/bin/systemctl reload %s\n' "$APP_USER" "$PHP_FPM_SERVICE" > "$SUDOERS_FILE"
chmod 0440 "$SUDOERS_FILE"
if visudo -cf "$SUDOERS_FILE" >/dev/null 2>&1; then
  ok "sudoers: ${APP_USER} may reload ${PHP_FPM_SERVICE} (scoped, NOPASSWD)"
else
  rm -f "$SUDOERS_FILE"
  die "Generated sudoers file was invalid — removed it."
fi

# -----------------------------------------------------------------------------
# 7. Reload services NON-DESTRUCTIVELY (this is a shared host).
#    * php8.3-fpm: RELOAD first (SIGUSR2 — the FPM master re-reads pool.d/ and
#      gracefully spawns the new 'buzzpay' pool/socket WITHOUT killing the
#      other sites' pools or their in-flight requests). NEVER 'restart' here.
#    * nginx: validate config, then RELOAD (graceful — other sites keep serving).
#    * systemd: reload units, enable+start the scheduler timer.
#    Order matters: bring up the FPM socket before reloading nginx, so the
#    upstream the vhost points at already exists.
# -----------------------------------------------------------------------------
log "Reloading ${PHP_FPM_SERVICE} to load the buzzpay pool (graceful)"
# 'reload' re-reads pool definitions without dropping other pools. If the unit
# is not yet running (fresh install), fall back to a one-time start.
systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || systemctl start "$PHP_FPM_SERVICE"
systemctl enable "$PHP_FPM_SERVICE" >/dev/null 2>&1 || true
ok "${PHP_FPM_SERVICE} reloaded (buzzpay pool live; other pools untouched)"

log "Validating and reloading nginx"
nginx -t
systemctl reload nginx
ok "nginx reloaded (graceful)"

log "Enabling the BuzzPay scheduler timer"
systemctl daemon-reload
systemctl enable --now buzzpay-scheduler.timer
ok "buzzpay-scheduler.timer enabled and started"

# -----------------------------------------------------------------------------
# 8. First clone of the repo into /var/www/buzzpay, as the buzzpay user.
#    If the working tree already exists we leave it alone (deploy.sh pulls).
# -----------------------------------------------------------------------------
if [ -d "${APP_PATH}/.git" ]; then
  skip "git checkout at ${APP_PATH}"
else
  log "Cloning ${GIT_REMOTE} (branch ${GIT_BRANCH}) into ${APP_PATH}"
  # Clone as the app user so ownership is correct from the start. git refuses
  # to clone into a non-empty dir, which is fine: a fresh APP_PATH is empty.
  sudo -u "$APP_USER" git clone --branch "$GIT_BRANCH" "$GIT_REMOTE" "$APP_PATH"
  ok "repository cloned"
fi

# -----------------------------------------------------------------------------
# 9. Done. We deliberately do NOT build or release the app here.
#    Next steps are manual / handled by deploy.sh.
# -----------------------------------------------------------------------------
cat <<EOF

=============================================================================
 BuzzPay provisioning complete (host is ready; app NOT yet built).
=============================================================================

 Next steps:

 1) Create the production .env (NEVER world-readable):
      sudo -u ${APP_USER} cp ${APP_PATH}/.env.example ${APP_PATH}/.env
      sudo -u ${APP_USER} chmod 600 ${APP_PATH}/.env
    Then edit ${APP_PATH}/.env and set at minimum:
      APP_ENV=production
      APP_DEBUG=false
      APP_URL=https://${DOMAIN}
      DB_CONNECTION=pgsql
      DB_HOST=127.0.0.1
      DB_PORT=5432
      DB_DATABASE=${DB_NAME}
      DB_USERNAME=${DB_ROLE}
      DB_PASSWORD=<the BUZZPAY_DB_PASSWORD you used here>
      EXCHANGE_API_KEY=<your exchangerate-api.com key>   # required, set by hand
      SANCTUM_STATEFUL_DOMAINS=${DOMAIN}                 # SPA is served same-origin
    (session/cache/queue all default to the 'database' driver, so deploy.sh's
     migrate step creates the sessions/cache/jobs tables in PostgreSQL.)

 2) Build + release the application (composer, npm/Vite, migrate, caches):
      sudo -u ${APP_USER} bash ${APP_PATH}/deploy/deploy.sh

 3) Issue TLS + HSTS for the real domain (edits the BuzzPay vhost only):
      sudo certbot --nginx -d ${DOMAIN}

 Reminder: this host also serves other sites. The BuzzPay vhost, PHP-FPM pool
 (${FPM_SOCKET}) and database are fully isolated — nothing else was modified.
=============================================================================
EOF
