#!/usr/bin/env bash
# Updates this deployment to the latest commit on GitHub. Made for a cPanel account over SSH:
#
#   cd ~/pontiac && ./deploy/cpanel-update.sh
#
# It pulls, installs the PHP dependencies, rebuilds (or checks) the UI, backs up and migrates the database, clears
# the production cache, and checks what production needs: its own secrets, HTTPS, writable folders and the two cron
# lines. Every step is safe to run again.
#
# First time on a new account, before this script is of any use:
#   1. git clone <repo> ~/pontiac        (cPanel › Git Version Control, or HTTPS + a personal access token)
#   2. Point the domain's document root at ~/pontiac/backend/public, and turn on HTTPS (cPanel › SSL/TLS Status)
#   3. Create the database and its user in cPanel (MySQL 8), with all privileges on it
#   4. cp deploy/env.local.example backend/.env.local, fill it in, chmod 600 backend/.env.local
#   5. Run this script: it creates the tables
#   6. Create the first super admin:  cd backend && php bin/console app:create-super-admin you@pontiac.co --env=prod
#   7. Add the two cron lines this script prints (cPanel › Cron Jobs)
#
# Settings it takes from the environment, all optional:
#   PHP         path to the PHP 8.4 CLI  (detected)
#   COMPOSER    path to composer         (detected)
#   BRANCH      branch to pull           (the checked out one)
#   SKIP_PULL   =1 to rebuild without pulling
#   ALLOW_DIRTY =1 to run with local changes to tracked files (the pull fails if it touches one)
#   BACKUP_ALWAYS =1 to dump the database even when no migration is pending
#   BACKUP_DIR  where the dump goes (default: the home directory)
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$APP_DIR/backend"
cd "$APP_DIR"

step() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
note() { printf '    %s\n' "$*"; }
warn() { printf '\033[1;33m    %s\033[0m\n' "$*" >&2; }
fail() { printf '\033[1;31m%s\033[0m\n' "$*" >&2; exit 1; }

# The last value a dotenv file gives a variable, without its quotes ("" when it gives none).
env_value() {
    local file=$1 name=$2
    [[ -f "$file" ]] || return 0
    { grep -E "^$name=" "$file" || true; } | tail -1 | cut -d= -f2- | sed -E "s/^[\"'](.*)[\"']$/\\1/"
}
local_value() { env_value "$BACKEND/.env.local" "$1"; }

# The first PHP 8.4+ CLI that exists. cPanel installs each version under /opt/cpanel, and "php" is often an old one.
find_php() {
    local candidate
    for candidate in "${PHP:-}" ea-php84 /opt/cpanel/ea-php84/root/usr/bin/php ea-php85 /opt/cpanel/ea-php85/root/usr/bin/php php84 php85 php; do
        [[ -n "$candidate" ]] || continue
        command -v "$candidate" >/dev/null 2>&1 || continue
        if "$candidate" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' 2>/dev/null; then
            command -v "$candidate"
            return 0
        fi
    done
    return 1
}

find_composer() {
    local candidate
    for candidate in "${COMPOSER:-}" composer "$HOME/composer.phar" "$APP_DIR/composer.phar"; do
        [[ -n "$candidate" ]] || continue
        if command -v "$candidate" >/dev/null 2>&1; then command -v "$candidate"; return 0; fi
        if [[ -f "$candidate" ]]; then echo "$candidate"; return 0; fi
    done
    return 1
}

step "Checking this account"
[[ -d "$APP_DIR/.git" ]] || fail "$APP_DIR is not a git clone: see the first-time steps at the top of this script."
[[ -f "$BACKEND/.env.local" ]] || fail "backend/.env.local is missing: copy deploy/env.local.example, fill it in, then chmod 600 it."
grep -q '^APP_ENV=prod' "$BACKEND/.env.local" || fail "backend/.env.local must set APP_ENV=prod."

PHP_BIN="$(find_php)" || fail "No PHP 8.4 or newer found. Set PHP=/opt/cpanel/ea-php84/root/usr/bin/php and run again."
note "PHP:      $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# cPanel picks the web PHP with an AddHandler line in the document root's .htaccess, and it is easily a different
# version from the one on the command line — then Composer, the migrations and the compiled cache are built with
# one PHP while Apache runs the app with another, whose extensions may not match.
check_extensions() {
    local binary=$1 label=$2 extension
    # intl: dates and money in the consultant's locale; sodium: Wompi secrets; gd: page images; fileinfo: uploads.
    for extension in pdo_mysql intl mbstring sodium gd fileinfo ctype iconv; do
        "$binary" -m | grep -qix "$extension" || fail "$label has no \"$extension\". Enable it in cPanel → Select PHP Version."
    done
    # Page images are stored as WebP: a gd built without it fails every upload.
    "$binary" -r 'exit(empty(gd_info()["WebP Support"]) ? 1 : 0);' \
        || fail "$label has gd without WebP support: page images cannot be stored. Ask the host for gd with WebP."
}
check_extensions "$PHP_BIN" "The command-line PHP"

# "|| true": with pipefail, a missing .htaccess (a fresh account) or no handler line would abort the script.
WEB_PHP=""
if [[ -f "$BACKEND/public/.htaccess" ]]; then
    WEB_PHP="$( { grep -hoE 'x-httpd-ea-php[0-9]+' "$BACKEND/public/.htaccess" || true; } | head -1 | sed 's/x-httpd-//')"
fi
if [[ -n "$WEB_PHP" ]]; then
    # Symfony 8 needs PHP 8.4: an older web PHP would serve a fatal error on every page once the new vendor/ is in.
    (( 10#${WEB_PHP#ea-php} >= 84 )) || fail "Apache runs the app with $WEB_PHP; it needs PHP 8.4. Set it in cPanel → Select PHP Version (MultiPHP Manager), then run this again."
    CLI_TAG="ea-php$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION, PHP_MINOR_VERSION;')"
    if [[ "$WEB_PHP" != "$CLI_TAG" ]]; then
        warn "Apache runs the app with $WEB_PHP, but this script is using $CLI_TAG."
        warn "Set the same version in cPanel → Select PHP Version, or run this with PHP=/opt/cpanel/$WEB_PHP/root/usr/bin/php."
    fi
    WEB_PHP_BIN="/opt/cpanel/$WEB_PHP/root/usr/bin/php"
    if [[ -x "$WEB_PHP_BIN" ]]; then
        check_extensions "$WEB_PHP_BIN" "$WEB_PHP (the one Apache uses)"
        note "Web PHP: $WEB_PHP ($("$WEB_PHP_BIN" -r 'echo PHP_VERSION;'))"
    fi
fi

step "Production settings"
# backend/.env is committed with the development values: production must replace every secret, or anyone with the
# repository could sign in as anyone (APP_SECRET, JWT) and read every consultant's Wompi secrets (APP_ENCRYPTION_KEY).
for name in APP_SECRET JWT_PASSPHRASE APP_ENCRYPTION_KEY; do
    value="$(local_value "$name")"
    [[ -n "$value" ]] || fail "backend/.env.local does not set $name: see deploy/env.local.example."
    [[ "$value" != "$(env_value "$BACKEND/.env" "$name")" ]] || fail "$name in backend/.env.local is the development value from backend/.env: generate a new one."
done
"$PHP_BIN" -r '$k = base64_decode($argv[1], true); exit($k !== false && strlen($k) === 32 ? 0 : 1);' "$(local_value APP_ENCRYPTION_KEY)" \
    || fail "APP_ENCRYPTION_KEY must be 32 bytes in base64: php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'"
note "Secrets:  APP_SECRET, JWT_PASSPHRASE and APP_ENCRYPTION_KEY are this server's own."
note "          Keep APP_ENCRYPTION_KEY with your backups: the stored Wompi secrets need it."

APP_URL="$(local_value APP_URL)"
case "$APP_URL" in
    https://*) note "APP_URL:  $APP_URL" ;;
    "") fail "backend/.env.local does not set APP_URL: every link in the emails is built from it." ;;
    *) fail "APP_URL must be https:// ($APP_URL): Wompi sends its events only to HTTPS, and the links in the emails use it." ;;
esac
MAILER_DSN="$(local_value MAILER_DSN)"
case "$MAILER_DSN" in
    ""|*mailpit*|null://*) fail "backend/.env.local has no real MAILER_DSN: no email would leave (see deploy/env.local.example)." ;;
esac
[[ -n "$(local_value MAILER_FROM)" ]] || fail "backend/.env.local does not set MAILER_FROM: it must be an address of the SMTP account."

COMPOSER_BIN="$(find_composer || true)"
[[ -n "$COMPOSER_BIN" ]] && note "Composer: $COMPOSER_BIN" || warn "Composer not found: backend/vendor/ must already be uploaded and up to date."

console() { "$PHP_BIN" "$BACKEND/bin/console" "$@"; }

# Only tracked changes matter: they are what a fast-forward pull would fight over. Untracked files are left
# alone — a cPanel account puts its own .htaccess, .user.ini and php.ini in the document root, and they must stay.
if [[ "${ALLOW_DIRTY:-0}" == "1" ]]; then
    warn "ALLOW_DIRTY=1: keeping the local changes to tracked files. The pull will fail if it touches one of them."
elif ! git diff --quiet || ! git diff --cached --quiet; then
    git status --short --untracked-files=no
    # The usual reason on a new account: the settings were put in .env, which is the committed file of defaults.
    if ! git diff --quiet -- "$BACKEND/.env" || ! git diff --cached --quiet -- "$BACKEND/.env"; then
        warn "backend/.env is committed, so every pull will fight over it, and a secret there is one push from being public."
        warn "This account's settings belong in backend/.env.local, which overrides .env and is never committed:"
        warn "    git diff backend/.env     # the lines you changed there"
        warn "    # make sure each of them is in backend/.env.local, then:"
        warn "    git restore backend/.env"
    fi
    fail "Tracked files have local changes. Commit them, or discard them with \"git restore .\", then run again."
fi
UNTRACKED="$(git ls-files --others --exclude-standard | head -5 | tr '\n' ' ')"
if [[ -n "$UNTRACKED" ]]; then
    note "Leaving untracked files alone: $UNTRACKED"
fi


BEFORE="$(git rev-parse HEAD)"

if [[ "${SKIP_PULL:-0}" != "1" ]]; then
    BRANCH="${BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"
    step "Pulling $BRANCH from GitHub"
    case "$(git remote get-url origin)" in
        git@*|ssh://*) note "The remote is SSH: this account needs a deploy key, or switch it to an HTTPS URL with a token." ;;
    esac
    git pull --ff-only origin "$BRANCH"
    if [[ "$BEFORE" == "$(git rev-parse HEAD)" ]]; then
        note "Already up to date; the steps below run anyway."
    else
        git --no-pager log --oneline "$BEFORE..HEAD" | sed 's/^/    /'
    fi
fi

if [[ -n "$COMPOSER_BIN" ]]; then
    step "PHP dependencies"
    # No scripts: they boot the kernel, which the cache warmup below does properly once the code is in place.
    (cd "$BACKEND" && "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --no-scripts)
else
    [[ -d "$BACKEND/vendor" ]] || fail "backend/vendor/ is missing and Composer is not available: upload vendor/ built with PHP 8.4, or install Composer."
fi

step "Production cache"
# Before the migrations, not after: right after a pull, var/cache/prod still holds the previous version's
# container, and in prod Symfony trusts it without checking. A migration check booting that stale container can
# fail for reasons that have nothing to do with the database. The directory is removed rather than cleared with
# cache:clear, because cache:clear has to boot that same stale container to run at all.
rm -rf "$BACKEND/var/cache/prod"
console cache:warmup --env=prod --no-debug

step "Frontend build"
# public/build/ is not committed (backend/.gitignore): the admin, portal and flow editor are built on the account.
if command -v npm >/dev/null 2>&1; then
    note "npm $(npm -v), node $(node -v)"
    # npm ci wipes and reinstalls node_modules, which takes minutes and a few hundred MB of the account's quota.
    # It writes node_modules/.package-lock.json, so that file's age says whether the lock has moved since.
    if [[ ! -d "$BACKEND/node_modules" || "$BACKEND/package-lock.json" -nt "$BACKEND/node_modules/.package-lock.json" ]]; then
        # NODE_ENV=production here would skip devDependencies, and Webpack Encore is one of them.
        (cd "$BACKEND" && NODE_ENV=development npm ci --no-audit --no-fund)
    else
        note "Dependencies unchanged since the last deploy; skipping npm ci."
    fi
    (cd "$BACKEND" && npm run build)
    [[ -f "$BACKEND/public/build/entrypoints.json" ]] || fail "The build wrote no entrypoints.json. Run \"npm run build\" in backend/ and read the error above it."
elif [[ -f "$BACKEND/public/build/entrypoints.json" ]]; then
    note "npm is not on this account; using the public/build/ already here."
    if [[ -n "$(find "$BACKEND/assets" -newer "$BACKEND/public/build/entrypoints.json" -print -quit 2>/dev/null)" ]]; then
        warn "assets/ is newer than public/build/: this build is stale. Build it where npm exists and upload public/build/."
    fi
else
    fail "No UI build and no npm on this account. Install Node (cPanel → Setup Node.js App), or build backend/public/build/ elsewhere and copy it here."
fi

step "Database server"
# Doctrine picks its SQL dialect from serverVersion in DATABASE_URL, not from the server. A version string that
# does not match the server makes Doctrine report its migration table out of sync on every run, so every deploy
# would take a pointless backup and never trust the migration check.
REAL_VERSION="$(console dbal:run-sql 'SELECT VERSION()' --env=prod 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+[A-Za-z0-9.+~-]*' | head -1 || true)"
CONFIGURED_VERSION="$( { grep -E '^DATABASE_URL=' "$BACKEND/.env.local" || true; } | tail -1 | grep -oE 'serverVersion=[^&"'"'"']+' | cut -d= -f2 || true)"
if [[ -z "$REAL_VERSION" ]]; then
    fail "Could not reach the database with DATABASE_URL from backend/.env.local."
fi
note "Server:     $REAL_VERSION"
note "Configured: serverVersion=${CONFIGURED_VERSION:-(missing)}"
real_is_mariadb=0; [[ "$REAL_VERSION" == *MariaDB* ]] && real_is_mariadb=1
conf_is_mariadb=0; [[ "$CONFIGURED_VERSION" == *[Mm]ariaDB* ]] && conf_is_mariadb=1
if [[ -z "$CONFIGURED_VERSION" || "$real_is_mariadb" != "$conf_is_mariadb" ]]; then
    SUGGESTED="${REAL_VERSION%%-*}"
    [[ "$real_is_mariadb" == "1" ]] && SUGGESTED="$SUGGESTED-MariaDB"
    fail "serverVersion does not match the database server. In backend/.env.local set serverVersion=$SUGGESTED in DATABASE_URL, then run again."
fi
if [[ "$real_is_mariadb" == "1" ]]; then
    # Pontiac is built and tested on MySQL 8 (JSON columns, the booking lock); MariaDB is not.
    warn "This is MariaDB: Pontiac is built and tested on MySQL 8. Ask the host for MySQL 8 if anything misbehaves."
else
    (( 10#${REAL_VERSION%%.*} >= 8 )) || fail "Pontiac needs MySQL 8 or newer; this server is $REAL_VERSION."
fi

step "Database migrations"
# A migration is the only step here that can destroy data, so it never runs without a dump first. The dump is
# taken with the credentials from .env.local, through a 0600 defaults file so the password is never an argument
# (arguments are visible to every user on the box via ps).
# Exit 0 from up-to-date means nothing to run, but a console that cannot boot exits non-zero too, and the two
# must not look alike: the message decides, and whatever it said is printed before a backup is taken.
MIGRATION_CHECK="$(console doctrine:migrations:up-to-date --env=prod 2>&1)" && CHECK_OK=1 || CHECK_OK=0
if [[ "$CHECK_OK" == "1" ]] && grep -q 'Up-to-date' <<<"$MIGRATION_CHECK" && [[ "${BACKUP_ALWAYS:-0}" != "1" ]]; then
    note "No migration is pending; the database is untouched and no backup was taken."
else
    if [[ "${BACKUP_ALWAYS:-0}" == "1" ]]; then
        note "BACKUP_ALWAYS=1."
    else
        note "Backing up because the migration check said:"
        grep -vE '^[[:space:]]*$' <<<"$MIGRATION_CHECK" | sed 's/^/      /'
    fi
    command -v mysqldump >/dev/null 2>&1 || fail "A migration is pending but mysqldump is not here: it must not run without a backup."

    DB_CONF="$(mktemp)"
    trap 'rm -f "$DB_CONF" "$DB_CONF.err"' EXIT
    chmod 600 "$DB_CONF"
    # PHP parses the DSN: it already understands the URL encoding a password needs (%40 and friends).
    DB_NAME="$("$PHP_BIN" -r '
        $line = preg_grep("/^DATABASE_URL=/", file($argv[1], FILE_IGNORE_NEW_LINES));
        $url = trim(substr(end($line), strlen("DATABASE_URL=")), "\"'"'"'");
        $p = parse_url($url) ?: exit(1);
        $conf = sprintf("[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n",
            $p["host"] ?? "localhost", $p["port"] ?? 3306,
            rawurldecode($p["user"] ?? ""), rawurldecode($p["pass"] ?? ""));
        file_put_contents($argv[2], $conf);
        echo ltrim($p["path"] ?? "", "/");
    ' "$BACKEND/.env.local" "$DB_CONF")" || fail "Could not read DATABASE_URL from backend/.env.local."
    [[ -n "$DB_NAME" ]] || fail "DATABASE_URL has no database name."

    BACKUP="${BACKUP_DIR:-$HOME}/$DB_NAME-$(date +%Y%m%d-%H%M%S).sql"
    note "Backing up $DB_NAME before migrating…"
    dump() { mysqldump --defaults-extra-file="$DB_CONF" --single-transaction --no-tablespaces \
        --default-character-set=utf8mb4 --add-drop-table "$@" "$DB_NAME" > "$BACKUP"; }

    if ! dump 2>"$DB_CONF.err"; then
        # A MariaDB client against a MySQL server (or the reverse) refuses the server's self-signed certificate.
        # On a loopback connection there is nothing to protect, so retry without the check rather than block the
        # deploy; the flag differs between the two clients.
        DB_HOST="$(grep '^host=' "$DB_CONF" | cut -d= -f2-)"
        TLS_OFF=""
        case "$DB_HOST" in
            localhost|127.0.0.1|::1)
                if mysqldump --help 2>/dev/null | grep -q -- '--ssl-mode'; then
                    TLS_OFF="--ssl-mode=DISABLED"
                else
                    TLS_OFF="--skip-ssl-verify-server-cert"
                fi
                ;;
        esac
        if [[ -z "$TLS_OFF" ]] || ! dump "$TLS_OFF"; then
            sed 's/^/    /' "$DB_CONF.err" >&2
            # Leave no half-written file behind: an empty .sql in the home directory reads like a real backup.
            rm -f "$DB_CONF.err" "$BACKUP"
            fail "The backup failed, so nothing was migrated. The database is untouched."
        fi
        warn "The database refused a verified TLS connection; the backup was taken over plain loopback."
    fi
    rm -f "$DB_CONF.err"

    # A truncated dump is worse than none: mysqldump writes this line last, only on success.
    if ! tail -5 "$BACKUP" | grep -q '^-- Dump completed'; then
        rm -f "$BACKUP"
        fail "The backup came out incomplete, so nothing was migrated. The database is untouched."
    fi
    if command -v gzip >/dev/null 2>&1; then
        gzip -f "$BACKUP"
        BACKUP="$BACKUP.gz"
    fi
    chmod 600 "$BACKUP"
    note "Backup: $BACKUP ($(du -h "$BACKUP" | cut -f1))"
    rm -f "$DB_CONF"
    trap - EXIT

    console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod
fi

step "JWT keys"
console lexik:jwt:generate-keypair --skip-if-exists --env=prod
chmod 600 "$BACKEND"/config/jwt/*.pem 2>/dev/null || true

step "Bundle assets"
console assets:install "$BACKEND/public" --env=prod --no-debug

step "Apache rewrite rules"
# cPanel writes its own .htaccess (the PHP handler) into the document root and rewrites it whenever the PHP
# version changes, so the front-controller rules are appended once, between markers, instead of owning the file.
HTACCESS="$BACKEND/public/.htaccess"
if [[ -f "$HTACCESS" ]] && grep -q '>>> pontiac front controller >>>' "$HTACCESS"; then
    note "Already in $HTACCESS."
else
    [[ -f "$HTACCESS" ]] && cp -p "$HTACCESS" "$HTACCESS.before-pontiac" && note "Kept a copy as .htaccess.before-pontiac"
    cat "$APP_DIR/deploy/htaccess-symfony.conf" >> "$HTACCESS"
    note "Added the front-controller rules to $HTACCESS"
fi

step "Writable directories"
mkdir -p "$BACKEND/var/log"
chmod -R u+rwX "$BACKEND/var"
# Page images and clients' files live in var/uploads (the app.uploads_dir parameter), outside the document root.
# This clone is updated in place and var/ is never pulled over, so they survive every deploy.
mkdir -p "$BACKEND/var/uploads"
note "Uploads: $BACKEND/var/uploads (back it up with the database)"

step "Queue and reminders"
# A worker started by the cron before this deploy would keep running the old code until its time limit; this asks it
# to stop after its current message, and the next cron run starts one on the new code.
console messenger:stop-workers --env=prod --no-debug >/dev/null 2>&1 || true
# Two cron lines, every minute. The worker sends every email (all of them are queued: bookings, payments, invitations,
# flows) and runs nothing else; the reminders line queues the reminders that are due. flock keeps one of each at a time.
WORKER_LINE="* * * * * flock -n $HOME/.pontiac-worker.lock $PHP_BIN $BACKEND/bin/console messenger:consume async --time-limit=55 --memory-limit=128M --env=prod --no-debug >> $BACKEND/var/log/worker.log 2>&1"
REMINDERS_LINE="* * * * * flock -n $HOME/.pontiac-reminders.lock $PHP_BIN $BACKEND/bin/console app:send-due-reminders --env=prod --no-debug >> $BACKEND/var/log/reminders.log 2>&1"
CRONTAB="$(crontab -l 2>/dev/null || true)"
if ! grep -q 'messenger:consume' <<<"$CRONTAB"; then
    warn "No cron line drains the queue, so no email ever leaves. Add it in cPanel › Cron Jobs (every minute):"
    note "  $WORKER_LINE"
elif ! grep 'messenger:consume' <<<"$CRONTAB" | grep -qE 'consume([^|>]* )?async( |$)'; then
    warn "The cron line runs messenger:consume but not the \"async\" queue: emails are queued and never sent. Replace it with:"
    note "  $WORKER_LINE"
fi
if ! grep -q 'app:send-due-reminders' <<<"$CRONTAB"; then
    warn "No cron line sends the session reminders. Add it in cPanel › Cron Jobs (every minute):"
    note "  $REMINDERS_LINE"
fi
FAILED="$(console messenger:stats --env=prod --no-debug 2>/dev/null | grep -E '^\s*failed\s' | grep -oE '[0-9]+' | head -1 || true)"
if [[ -n "$FAILED" && "$FAILED" != "0" ]]; then
    warn "$FAILED message(s) failed every retry (usually emails): php bin/console messenger:failed:show --env=prod"
fi

step "Done"
note "Now on $(git rev-parse --short HEAD) ($(git --no-pager log -1 --format=%s))"
if ! console dbal:run-sql "SELECT COUNT(*) AS admins FROM app_user WHERE roles LIKE '%ROLE_SUPER_ADMIN%'" --env=prod 2>/dev/null | grep -qE '^\s*[1-9][0-9]*\s*$'; then
    warn "There is no super admin yet: cd backend && php bin/console app:create-super-admin you@pontiac.co --env=prod"
fi
# Only worth offering when this run actually moved the code.
if [[ "$BEFORE" != "$(git rev-parse HEAD)" ]]; then
    note "Roll back the code with:  git reset --hard ${BEFORE:0:7} && SKIP_PULL=1 $0"
    note "(a migration it ran is not undone: restore the backup above if the old code needs the old tables)"
fi
note "Each consultant pastes $APP_URL/webhooks/wompi/<their account id> in Wompi (shown in Ajustes › Pagos Wompi)."
note "If a change does not show up, restart PHP from cPanel (OPcache may still hold the old files)."
