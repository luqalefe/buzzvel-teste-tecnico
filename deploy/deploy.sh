#!/usr/bin/env bash
#
# deploy/deploy.sh — BuzzPay release script (idempotent, re-runnable).
#
# Publishes a NEW version into an EXISTING checkout at /var/www/buzzpay on the
# production VPS. This is a RELEASE script, not a provisioning script: it assumes
# the server has already been bootstrapped (system user, PHP-FPM pool, socket,
# PostgreSQL db/user, nginx vhost, and — critically — the .env file) by the
# one-time setup. It only pulls code, builds assets, runs migrations, rebuilds
# Laravel caches, fixes runtime permissions, and reloads the dedicated FPM pool.
#
# Run AS the application owner:   buzzpay
#     sudo -u buzzpay /var/www/buzzpay/deploy/deploy.sh
# or, when already logged in as buzzpay:
#     /var/www/buzzpay/deploy/deploy.sh
#
# Shared-VPS safety: this script touches ONLY /var/www/buzzpay and the BuzzPay
# PHP-FPM pool. It does not install OS packages, does not write to any other
# site, and 'reload's (never 'restart's) php8.3-fpm so co-hosted apps using the
# same php8.3-fpm service are not interrupted.

# Strict mode: abort on error, on unset variables, and on any failure in a pipe.
set -euo pipefail


# --- Configuration (matches the project's deploy conventions) ---------------
# Keep these in sync with the nginx vhost / PHP-FPM pool / systemd units.
APP_DIR="/var/www/buzzpay"      # Laravel checkout (document root is ${APP_DIR}/public)
APP_USER="buzzpay"              # owning system user
APP_GROUP="www-data"            # group shared with the FPM/nginx workers for read/write
GIT_BRANCH="main"               # branch to deploy
FPM_SERVICE="php8.3-fpm"        # native PHP-FPM service on Ubuntu 24.04


# --- Helpers ----------------------------------------------------------------
# Coloured, easy-to-follow release output.
log()  { printf '\n\033[1;34m==>\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

# Resolve the PHP CLI binary. The dedicated FPM pool runs php8.3, so the CLI
# must match that minor version to avoid cache/opcode mismatches. Prefer the
# pinned binary, then a generic `php`, and fail fast (with a clear message)
# rather than letting `set -e` abort on an opaque empty assignment.
PHP_BIN="$(command -v php8.3 2>/dev/null || command -v php 2>/dev/null || true)"
[[ -n "${PHP_BIN}" ]] || fail "No PHP CLI found (looked for php8.3 then php). Install php8.3-cli."

# Resolve Composer. Convention installs it at /usr/local/bin/composer; fall
# back to PATH so the script still works if it was placed elsewhere.
if [[ -x /usr/local/bin/composer ]]; then
  COMPOSER_BIN="/usr/local/bin/composer"
else
  COMPOSER_BIN="$(command -v composer 2>/dev/null || true)"
fi
[[ -n "${COMPOSER_BIN}" ]] || fail "Composer not found (looked in /usr/local/bin then PATH)."

# Resolve Node's package manager up front, so the front-end build fails fast
# with a clear message instead of mid-way through.
command -v npm >/dev/null 2>&1 || fail "npm not found. Install Node.js LTS (NodeSource) before deploying."


# --- Optional flags ----------------------------------------------------------
# --seed : also run the database seeders after migrating. DatabaseSeeder is
#          idempotent (users are upserted by e-mail; sample payment requests are
#          only created on an empty DB), so it is safe to pass on every release.
#          Used to provision the documented demo accounts (finance@buzzvel.test,
#          employee@buzzvel.test, …) for this technical-test deployment.
RUN_SEED=0
for arg in "$@"; do
  case "$arg" in
    --seed) RUN_SEED=1 ;;
    *) fail "Unknown argument: '${arg}' (supported: --seed)" ;;
  esac
done


# --- 0. Sanity checks (guarded; fail fast with a clear message) -------------
log "Validating environment"

# Must run from/own the expected directory.
[[ -d "${APP_DIR}/.git" ]] || fail "${APP_DIR} is not a git checkout. Run the provisioning step first."

# Move into the app root for the remainder of the script.
cd "${APP_DIR}"

# .env is a PREREQUISITE — never created or overwritten by this release script.
# (It carries APP_KEY, BUZZPAY_DB_PASSWORD, EXCHANGE_API_KEY, etc.)
[[ -f "${APP_DIR}/.env" ]] || fail "${APP_DIR}/.env is missing. Create it (perm 600) before deploying."

# Defence in depth: refuse to deploy if .env is world-readable. The release
# only tightens its own file; it never touches other sites.
if [[ "$(stat -c '%a' "${APP_DIR}/.env" 2>/dev/null || echo '')" =~ [0-9][0-9][1-7]$ ]]; then
  log "Tightening ${APP_DIR}/.env permissions to 600"
  chmod 600 "${APP_DIR}/.env" || true
fi

# Warn (don't abort) if we're not running as the intended owner: running as
# another user can leave files root/www-data-owned and break later releases.
if [[ "$(id -un)" != "${APP_USER}" ]]; then
  echo "WARNING: running as '$(id -un)', expected '${APP_USER}'. Continuing, but" >&2
  echo "         file ownership may need fixing afterwards." >&2
fi


# --- 1. Fetch and hard-reset to the deployed branch -------------------------
# 'reset --hard' makes the working tree EXACTLY match origin/main, so the script
# is fully idempotent regardless of any local drift on the server. Tracked files
# only: vendor/, node_modules/, public/build, and .env are git-ignored and are
# therefore never disturbed by the reset.
log "Syncing code to origin/${GIT_BRANCH}"
git fetch origin --prune
git reset --hard "origin/${GIT_BRANCH}"


# --- 2. Install PHP dependencies (production profile) ------------------------
# --no-dev:               skip dev tooling (pint, phpunit, sail, ...)
# --optimize-autoloader:  generate a classmap for faster autoloading
# --no-interaction:       never prompt (this runs unattended)
# --prefer-dist:          download zipped dists (faster, no git needed per pkg)
# Scripts are intentionally NOT disabled: post-autoload-dump runs
# `artisan package:discover`, which the app needs.
log "Installing Composer dependencies (production)"
"${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-interaction --prefer-dist


# --- 3. Build the React/Vite SPA into public/build --------------------------
# 'npm ci' installs from package-lock.json deterministically (and wipes any
# stale node_modules), then the project's "build" script runs `vite build`,
# emitting the hashed assets + manifest that Laravel's @vite directive serves.
log "Installing Node dependencies and building front-end assets"
npm ci
npm run build


# --- 4. Database migrations -------------------------------------------------
# --force: required to run migrations in a production (non-interactive) env.
#
# Seeding only runs when --seed is passed. The seeder is idempotent (users are
# upserted by e-mail; sample requests only on an empty DB), so re-running it is
# safe. NOTE: DatabaseSeeder plants demo accounts with the well-known password
# "password" (finance@buzzvel.test, employee@buzzvel.test, …) — desired for this
# technical-test demo so reviewers can log in, but you would NOT pass --seed on
# a real production system that holds genuine user data.
if [[ "${RUN_SEED}" -eq 1 ]]; then
  log "Running database migrations (with --seed)"
  "${PHP_BIN}" artisan migrate --force --seed
else
  log "Running database migrations"
  "${PHP_BIN}" artisan migrate --force
fi


# --- 5. Ensure the public storage symlink exists ----------------------------
# The 'public' filesystem disk maps public_path('storage') -> storage/app/public.
# public/build and the symlink are git-ignored, so the link must be (re)created
# on the server. --force makes this idempotent across releases.
log "Linking public storage"
"${PHP_BIN}" artisan storage:link --force


# --- 6. Rebuild Laravel caches for production -------------------------------
# Clear stale config first (it can hold paths/values from a previous release),
# then rebuild all caches in one shot. `optimize` runs config:cache,
# route:cache, view:cache and event:cache together (Laravel 12).
log "Rebuilding application caches"
"${PHP_BIN}" artisan config:clear
"${PHP_BIN}" artisan optimize     # = config:cache + route:cache + view:cache + event:cache


# --- 7. Ensure runtime directories are writable -----------------------------
# storage/ and bootstrap/cache must be writable by the FPM workers (group
# www-data). Re-applying ownership + group-write each release fixes anything
# that composer/npm/git may have created with the wrong owner. Scoped strictly
# to the BuzzPay tree — no other site is touched.
log "Fixing runtime permissions on storage/ and bootstrap/cache"
chown -R "${APP_USER}:${APP_GROUP}" storage bootstrap/cache
# Directories: group rwx + setgid (new files inherit www-data); files: group rw.
find storage bootstrap/cache -type d -exec chmod 2775 {} +
find storage bootstrap/cache -type f -exec chmod 0664 {} +


# --- 8. Reload the dedicated PHP-FPM pool ------------------------------------
# 'reload' (graceful) — never 'restart' — so other sites sharing php8.3-fpm are
# not dropped. This clears OPcache for the new code. May require root: the
# script auto-uses sudo when not already root. To run unattended, grant buzzpay
# a sudoers entry, e.g. in /etc/sudoers.d/buzzpay-deploy:
#   buzzpay ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
log "Reloading ${FPM_SERVICE} (graceful, shared service — no other site dropped)"
if [[ "$(id -u)" -eq 0 ]]; then
  systemctl reload "${FPM_SERVICE}"
elif command -v sudo >/dev/null 2>&1; then
  sudo -n systemctl reload "${FPM_SERVICE}" || {
    echo "WARNING: 'sudo -n systemctl reload ${FPM_SERVICE}' failed (no NOPASSWD entry?)." >&2
    echo "         New code will load once OPcache notices the change; or run manually:" >&2
    echo "         sudo systemctl reload ${FPM_SERVICE}" >&2
  }
else
  echo "WARNING: cannot reload ${FPM_SERVICE} (no root, no sudo)." >&2
  echo "         Run manually: sudo systemctl reload ${FPM_SERVICE}" >&2
fi


# --- 9. Release summary ------------------------------------------------------
DEPLOYED_SHA="$(git rev-parse HEAD)"
DEPLOYED_SHORT="$(git rev-parse --short HEAD)"
DEPLOYED_SUBJECT="$(git log -1 --pretty=%s)"
log "BuzzPay release complete"
printf '    Branch : %s\n' "${GIT_BRANCH}"
printf '    Commit : %s (%s)\n' "${DEPLOYED_SHORT}" "${DEPLOYED_SHA}"
printf '    Message: %s\n' "${DEPLOYED_SUBJECT}"
printf '    Path   : %s\n\n' "${APP_DIR}"
