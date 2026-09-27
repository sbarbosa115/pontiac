# Pontiac

Landing pages, leads, booking, payments and clients for independent consultants — sold to many consultants, each
one's data isolated from the others'. The product plan is [`docs/pdr/prd-pontiac.md`](docs/pdr/prd-pontiac.md).

Stack: Symfony 8 (PHP 8.4) JSON API · React 19 + TypeScript · MySQL 8.4 (MariaDB on cPanel) · Docker for
development · shared hosting (cPanel) in production.

## Running locally

```bash
docker compose up -d --build
docker compose exec php composer install
docker compose restart node                                   # npm needs vendor/
docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec php php bin/console doctrine:migrations:migrate -n --env=test
docker compose exec php php bin/console app:seed-demo
```

The app answers on `http://localhost:${HTTP_PORT}` and Mailpit (every email sent locally) on
`http://localhost:${MAILPIT_PORT}`. Ports come from the root `.env` (not committed; 8080/3306/8025 by default), so
several checkouts can run side by side — this one uses 8082/3308/8027.

### Demo accounts

`app:seed-demo` creates one consultant, **Finanzas Claras** (`/finanzas-claras`), and one login per role, all with
the password `demo-password-123`:

| Role | Signs in at | Email |
|---|---|---|
| Super admin | `/login` | admin@pontiac.test |
| Consultant (owner) | `/login` | asesor@pontiac.test |
| Assistant | `/login` | asistente@pontiac.test |
| Client | `/finanzas-claras/portal` | cliente@pontiac.test |

A real super admin: `SUPER_ADMIN_PASSWORD=… php bin/console app:create-super-admin you@example.com -n`.

## Architecture

- `backend/src/Api` — the API's conventions: `ApiController` (the signed-in user, their account, pages, 404s),
  `ApiException` (a stable snake_case `error` code), `ApiValidationException` (422 with violations, translated),
  `InputMapper` (request → validated Input DTO, strict types), `Presenter` (entity → Output DTO), `#[ApiResponse]`
  (the OpenAPI schema the UI's types come from).
- `backend/src/Controller/{Admin,Platform,…}` — one namespace per URL space; the namespace is the authorization
  boundary.
- `backend/src/Doctrine` — account isolation (below).
- `backend/assets/react` — the React app: `lib/` (API client, auth, hooks, i18n, formatting, theme), `components/ui.tsx`
  (the UI kit every page uses), `pages/` (one folder per role).
- `backend/templates/public` — server-rendered public pages.

### Roles and URL spaces

| Role | API | UI | Signs in |
|---|---|---|---|
| Super admin (`ROLE_SUPER_ADMIN`) | `/api/platform` | `/plataforma` | `/login` |
| Consultant (`ROLE_OWNER`) and assistant (`ROLE_ASSISTANT`) | `/api/admin` | `/admin` | `/login` |
| Client (`ROLE_CLIENT`) | `/api/portal` | `/<slug>/portal` | `/<slug>/portal/ingresar` |

No role inherits another. Owner-only endpoints in `/api/admin` add `#[IsGranted('ROLE_OWNER')]`. A super admin can
act as a consultant or an assistant (never a client) with the `X-Switch-User: <user id>` header; each such request is
logged.

Staff emails are unique across Pontiac. A client's email is unique within one consultant only (the same person can
be a client of two consultants), so clients sign in with the consultant's slug: `POST /api/portal-login`.

### Account isolation

Every customer-owned table carries `account_id`. `AccountScopeFilter` adds `account_id = …` to every Doctrine query
of those tables and **matches nothing when no account is set** (fail-closed). `AccountFilterSubscriber` enters the
signed-in user's account on each request, from the database user, never from the token's claims; only a super admin
on the platform firewall gets cross-account access. `AccountOwnershipListener` stamps new rows with the account and
throws on a write to another account's row or a link to another account's entity. Console commands and queue
handlers enter an account explicitly.

### Background work and email

The Messenger queue is a table (`messenger_messages`). Locally the `worker` service drains it; on cPanel a cron line
every minute runs `php bin/console messenger:consume async --time-limit=50`. Emails are queued, except those
someone waits for on screen (invitations), which are sent at once.

## API reference

Every error is `{"error": "<code>", "message": "…"}`, plus `violations: [{field, message}]` on a 422. Lists answer
`{items, total, page, perPage}` and take `?page=`, `?perPage=` (≤ 100) and `?q=`.

| Method | Path | Who | What |
|---|---|---|---|
| POST | `/api/login` | anyone | Staff sign-in: `{email, password}` → `{token}` |
| POST | `/api/portal-login` | anyone | Client sign-in: `{account, email, password}` → `{token}` |
| GET | `/api/me` | signed in | Who the requests run as, their account, theme, impersonator |
| PATCH | `/api/me/preferences` | signed in | `{uiTheme}`: light, dark or system |
| POST | `/api/invitations/lookup` | anyone | `{token}` → who the invitation is for |
| POST | `/api/invitations/accept` | anyone | `{token, password}` (≥ 12 characters) |
| GET | `/api/admin/team` | owner, assistant | The consultant and their assistants |
| POST | `/api/admin/team` | owner | Invite an assistant `{fullName, email}` |
| POST | `/api/admin/team/{id}/resend-invitation` | owner | A new invitation link |
| DELETE | `/api/admin/team/{id}` | owner | Disable an assistant (409 for the owner) |
| POST | `/api/admin/team/{id}/enable` | owner | Enable them again |
| GET | `/api/platform/impersonatable-users` | super admin | Who can be acted as |

## Data model decisions

- UUIDv7 ids, stored as `BINARY(16)`; ids travel as strings.
- Nothing is deleted: people and things are disabled (`active: false`).
- `app_user.login_scope` is `staff` or the account id, and `(email, login_scope)` is unique: staff emails are unique
  across Pontiac, client emails per consultant.
- A consultant's slug is the first segment of every public URL; the app's own first segments are reserved
  (`Account::RESERVED_SLUGS`).

## Deploying to cPanel

Not written yet: it comes with the release milestone (PRD, "Delivery plan").

## Known gaps

- The client portal's sign-in page does not show the consultant's name yet (only Pontiac's).
- Lists of users are small today and not paginated by the database for the super admin's picker.
- No self-service password reset yet.
