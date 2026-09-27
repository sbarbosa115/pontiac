# Starting the app (before the first feature)

Only for a repo with no running app yet. A feature needs the foundation below to exist; if it does not, plan the app
and build the foundation first.

## A. Plan the app

A few decisions are expensive to change later. Get them answered and written down before any code.

### Interview

Ask only what the user's description does not answer, two to four questions at a time, each with a
**recommended answer and why** (use a multiple-choice question tool). Talk about what people do, not about tables.

1. **The use case.** What problem, for whom, what they use today. The one thing v1 must do. The main things the app
   keeps track of (they become entities).
2. **One customer or many (hard to change).** An internal tool for one organisation, or a product sold to many
   independent customers? Many → every customer-owned row carries an `account_id`, a Doctrine filter narrows every
   query to the signed-in user's account and fails closed, and a super admin sits above all accounts. Recommend it
   whenever the user mentions selling the app or "each company sees only its own". Name the customer in the user's
   words for the UI; the code says `Account`.
3. **Roles and sign-in (hard to change).** Each kind of person, what they see, what they may change. Each role is
   its own URL space and React area; roles do not inherit each other. Staff sign in with email + password set from
   an emailed invitation. Should support staff be able to act as a user (impersonation)?
4. **Locale, money, time.** UI language (default: Spanish UI, English code and API messages), country, currency,
   timezone — per account if there are many.
5. **Outside the screen.** Which events email whom, file uploads, public pages, SMS/WhatsApp, payments, PDFs,
   scheduled jobs. Each is a phase item, not foundation.
6. **Hosting (shapes what is allowed).** Shared hosting (cPanel) means no Redis, no daemons, no root: the queue is a
   database table drained by a cron every minute and every dependency must be pure PHP. A VPS allows a real worker.
   Plan for the most constrained target the user might use.
7. **Look.** Name, logo, main colour. Light and dark themes come with the foundation.

### The PRD

Write `docs/pdr/prd-<app>.md`: **Problem · Goals** (observable) **· Non-goals · Users and roles** (table: role, who,
sign-in, sees, can change, URL space) **· Domain model** (each entity in the user's words, main fields, owner,
statuses and transitions, nothing hard-deleted) **· Screens and navigation** (menu per role in the order of the
work; which screens are tabs of one page; each table's columns, filters, row actions, modals) **· Flows** (3–5
journeys with the emails) **· Security and isolation · Delivery plan** (phase 0 foundation, phase 1 the smallest
useful set, then the rest) **· Risks · Decisions** (table: question, decided, why).

Show the user the Decisions table and phase 1, and **wait for confirmation** before the foundation. Commit the PRD
on its own branch and push it. When a phase ships, add a "What shipped" section to the PRD.

## B. Build the foundation

Everything the app needs that has nothing to do with its use case, written once, carefully. What it must provide is
in `conventions.md`; this is how it is set up.

**Repository layout.** `docker-compose.yml`, `docker/` (php, nginx, mysql init), `backend/` (the Symfony app,
including `assets/` for React), `docs/pdr/`, `docs/tests/`, `CLAUDE.md`, `README.md`, `.gitignore` (with `/.env`).

**Docker services.** `php` (8.4-fpm-alpine with `intl` + `icu-data-full`, `pdo_mysql`, `zip`, `opcache`; runs as the
host UID; composer inside), `worker` (`messenger:consume async --time-limit=3600`), `nginx` (→ php, `try_files $uri
/index.php`), `database` (MySQL 8.4, utf8mb4_unicode_ci, init script granting `app_test%` to the app user),
`node` (`npm install && npm run watch`), `mailpit`, and `e2e` (Playwright image, `profiles: [e2e]`). **Host ports
come from variables** — `"${HTTP_PORT:-8080}:80"`, `"${DB_PORT:-3306}:3306"`, `"${MAILPIT_PORT:-8025}:8025"` — and
php/worker get `APP_URL: http://localhost:${HTTP_PORT:-8080}`, so every feature worktree can run its own stack.

**Symfony packages.** framework, console, dotenv, runtime, flex, yaml, doctrine-bundle + orm + migrations, security,
lexik/jwt-authentication, serializer + property-access/-info + phpdoc parser, validator, uid, messenger +
doctrine-messenger, mailer, translation, intl, twig, webpack-encore-bundle, ux-react, rate-limiter, http-client,
lock, nelmio/cors. Dev: phpunit, dama/doctrine-test-bundle, browser-kit, css-selector, maker, debug,
web-profiler, nelmio/api-doc (dev/test only), phpstan + doctrine/symfony/phpunit extensions.

**Configuration that matters.**
- Doctrine: utf8mb4, `underscore_number_aware` naming, UUIDv7 ids (`BINARY(16)`), test DB via
  `dbname_suffix: '_test'`; no automatic `{id}` → entity resolution.
- Framework: `enabled_locales`, `set_locale_from_accept_language: true` (validation messages follow the UI).
- Security, Messenger, rate limiters: as in `conventions.md`.
- `phpunit.dist.xml` pins `APP_URL` to `http://localhost:8080` (`force="true"`).

**Users and accounts.** `User` (email unique, fullName, roles, nullable password, account, active, invitation token
**hash** + expiry, `uiTheme`). Named constructors per role enforce who has an account. `UserRepository` implements
`loadUserByIdentifier` (id or email). Commands: `app:create-super-admin` (password from an env var) and
`app:seed-demo` (idempotent demo data with one login per role — the browser pass of every feature depends on it).
Invitation endpoints: lookup and accept (password ≥ 12 chars, single use, 7 days).

**Theme without a flash.** `lib/theme.tsx` caches the choice in `localStorage` and an inline script in the page head
applies it before the CSS paints.

**Project docs.** `CLAUDE.md` with the house rules (see "House table style" in `conventions.md`) and `README.md`
(running locally, demo accounts, architecture, isolation design, roles and URL spaces, API reference, data model
decisions, known gaps). `docs/tests/ui-regression.md`.

**Done when** on a fresh stack: a user signs in, sees the shell and one example list (the account's team), switches
theme, and PHPUnit, PHPStan, ESLint, typecheck, Vitest and e2e are green. Commit it and push it before any feature.
