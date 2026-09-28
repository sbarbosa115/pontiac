# Stack conventions

What the foundation provides and every feature leans on. Read the part you need when a feature touches it; when the
code and this file disagree, the code wins — and update this file in the same change.

## API

The plumbing lives in `backend/src/Api` (namespace `App\Api`); Input DTOs in `Api/Input`, Output DTOs in
`Api/Output`, entity → DTO in `Api/Presenter`. Controllers live in `src/Controller/<Space>`.

- JSON, camelCase, ids as strings, dates `Y-m-d`, timestamps ISO 8601 UTC, money
  `{"amount": "2500000.00", "currency": "COP"}`. Money is a decimal string end to end, never a float.
- `ApiException` with a stable snake_case `error` code (the UI translates by code) and named constructors
  (`notFound`, `badRequest`, `forbidden`, `conflict`, `unprocessable`, `tooManyRequests`).
- `ApiValidationException` → 422 `{error: "validation_failed", violations: [{field, message}]}`; messages written in
  English and translated on the way out in the `validators` domain (`translations/validators.<lang>.yaml`), values
  as parameters (`'Upload at most %max% photos.'`) — a test fails when a message has no translation.
- An exception subscriber turns every `/api/` error into that JSON (Symfony's page stays in dev for 500s).
- `InputMapper`: request → Input DTO, strict types (a money amount sent as a JSON number is a 422), validated.
- `Pagination` (`?page=&perPage=`, 25 default, 100 max) and `Page::fromQuery()`; lists answer
  `{items, total, page, perPage}` through `ApiController::page()`.
- `ApiController` with `appUser()`, `account()`, `found()` (→ 404), `pagination()`, `page()`, `enumQuery()` (unknown
  filter → 400).
- Repositories of owned entities extend `AccountOwnedRepository` (`findOneById()`, `findByIds()`, `whereId()`);
  every list query uses the `ListQueries` trait: `whereTerm($qb, $q, [fields])` and `paginate($qb, $pagination)`.
- Every response is an **Output DTO** (`final readonly`, promoted properties) declared with an `#[ApiResponse]`
  attribute; a dev/test-only Nelmio describer turns it into OpenAPI, and `openapi-typescript` turns that into
  `assets/types/api.d.ts`, so a changed response is a TypeScript error in the UI.
- Nothing is deleted: `DELETE` disables (`active: false`), 409 while something depends on it.

## Security and roles

- Stateless firewalls: `login` (`json_login` on `/api/login`, Lexik JWT, login throttling), `platform`
  (`^/api/platform`, super admin only), `api` (`^/api`, JWT; `switch_user` via the `X-Switch-User` header if
  impersonation exists), public ones and `main` (the SPA shell) with `security: false`.
- A `UserChecker` runs on login **and every JWT request**, so disabling a user or suspending an account works at once.
- Staff (super admin, owner, assistant) sign in at `/api/login` with email and password; clients at
  `/api/portal-login` with the consultant's slug too (a client's email is unique per account only:
  `User::$loginScope`).
- Each role is its own URL space (`/api/admin`, `/api/portal`, `/api/platform`) and React area; `access_control`
  maps each space to its role; no `role_hierarchy`. The controller's namespace is the authorization boundary.
- `User` is identified by **id** (JWT and impersonation name users by id); `/api/me` returns the user, roles,
  account settings (locale, currency, timezone), `uiTheme` and — while switched — the impersonator.

## Multi-customer isolation (if the app has it)

- Every customer-owned entity implements `AccountOwnedInterface` + `AccountOwnedTrait` (`account_id` join column),
  denormalized even where it could be derived, so isolation is one indexed equality check.
- `AccountScopeFilter` (Doctrine SQL filter, `account_id = UNHEX(:hex)`) **matches nothing without a parameter**.
  `AccountContext` (`enterAccount`, `enterPlatformScope`, `reset`) fails closed. A request subscriber enters the
  signed-in user's account from the database user, never from JWT claims.
- An ownership listener stamps new rows and throws on cross-account writes or links.
- Console commands and queue handlers enter an account explicitly. `User` is not filtered.
- `find()` bypasses the filter through the identity map — load by id with a DQL `findOneById()`. No native SQL on
  owned tables.

## Background work, email, files

- Every email is tagged with `EmailTag::apply($email, $kind, $account)`; `App\Mail\EmailLog` records each attempt
  (Plataforma › Correos) and strips the tags before the email leaves.
- Features (`AccountFeature`, on `Account`) gate controllers with `#[RequiresFeature]` (403 `feature_disabled`) and menu
  items with `feature`; limits are `Account::getMax…()` checked at creation (409 `…_limit_reached`).

- Messenger `async` on the Doctrine transport (a table), retries 3× (1 min, ×5), a `failed` transport;
  `in-memory://` in tests. Messages carry ids, never entities.
- Rate limiters live in their own config file with generous `when@test` values.
- One notification mailer and template; mail is rendered in the request and queued, sent after the flush.
- Files are stored by key under the account, type detected from content, served only through the API.
- A third-party API is called through one small client service whose HTTP client the test environment replaces
  with a fake (`when@test` in `config/services.yaml`); its webhooks verify the provider's signature before reading
  anything, answer 200 to what they ignore (so the provider stops retrying) and apply each event once.
- Secrets a customer gives the app (API keys) are encrypted at rest (libsodium, key in the environment), write-only
  in the API (the screen shows their last characters).
- Scheduled work is a console command with a lock, run every minute (a `scheduler` compose service; a cron line in
  production). It enters each account in turn and claims each piece of work with an insert the database refuses
  twice (unique key), so overlapping or repeated runs do nothing twice.

## Frontend

Encore + Symfony UX React (React 19), TypeScript strict (`noUncheckedIndexedAccess`), ESLint 9, Vitest + Testing
Library, Playwright. One Twig page (`spa.html.twig`) served by a catch-all controller; React Router does the rest.

- `lib/api.ts` — fetch wrapper (JWT, `Accept-Language`, `X-Switch-User`), errors as `ApiError` with `code`,
  `status`, `violations`, `fieldErrors()`; files fetched with the JWT and opened from a blob URL.
- `lib/types.ts` — `Schema<'XOutput'>` from `api.d.ts`.
- `lib/auth.tsx` — session, `me`, roles, `RequireRole` (with `loginPath` for the portal), `homePathFor()`,
  `portalPath(slug)`, `login`, `portalLogin`, logout, impersonation.
- `lib/hooks.ts` — `useList(path, filters)`, `useApi`, `useForm`, `useSubmit`.
- `lib/i18n.ts` — `t(key, params)` with `{name}` placeholders and `{one, other}` plurals; `errorMessage(error)`
  translates by API error code. `lib/format.ts` — money and dates in the account's locale and timezone.
- `lib/theme.tsx` — light / dark / follow the device, `data-theme` on `<html>`, saved on the user.
- `components/ui.tsx` — the UI kit: `PageHeader`, `FilterBar`, `ListView`, `DataTable`, `Row`, `RowLegend`,
  `Actions`, `ActionButton`, `IconButton`, `Modal`, `FormModal`, `Field`, `Alert`, `EmptyState`, `Tabs`/`TabPanel`,
  `Pager`, `Badge`. `Layout.tsx` — sidebar per role. `controllers/App.tsx` — every logged-in page is lazy.
- `styles/app.css` — **every colour is a token**, defined for `:root` and `:root[data-theme='dark']`; a Vitest file
  guards it (no colour literals elsewhere, every token in both themes, text contrast ≥ 4.5:1).

- `@xyflow/react` draws the flow editor's canvas. Its nodes are controlled: pass `colorMode`, hand the `dimensions`
  changes back as each node's `measured` (or it never settles), and theme it with its `--xy-*` variables set to our
  tokens in `app.css`.
- Drag and drop (the flow board) is native HTML5, with a select on each card doing the same for the keyboard.

### House table style

One "Acciones" column, last; buttons coloured by kind of action; no status column — the row colour is the status,
with a `RowLegend`; a search box and labelled dropdowns above every table; every string through `t()`; money inputs
group thousands.

## Tests

- `ApiTestCase` (WebTestCase, DAMA rolled-back transactions on `app_test`) with helpers: `createAccount`,
  `createOwner`, `createAssistant`, `createClientLogin`, `createSuperAdmin`, `actAs`, `signOut`, `api`, `upload`,
  `responseStatus`, `runWorker`, `createPage`, `createCategory`, `formData` (a person's form, with a valid time token),
  `imageFile`. `actAs()` clears the entity manager: create fixtures before it, or re-fetch them. A request leaves its
  account entered; `asPlatform()` (called by `save()`) lets the test read and write every account's data.
- `assets/react/lib/i18n.test.ts` fails on a `t('key')` whose key is missing.
- Validation messages follow the request's `Accept-Language` (the UI sends `es`): a test asserting a Spanish message
  sets `$this->client->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'es')`. `ValidatorsTranslationTest` fails on a
  message without its Spanish line.
- An email sent at once (invitations, password resets) after queued ones in the same request confuses
  `getMailerMessages()`, which takes it for one of them and drops that one: read `getMailerEvents()` instead.
- Tab labels built at runtime (`t(\`prefix.${value}\`)` over a `{ value, icon }` list) are checked by
  `lib/i18n.test.ts`; other runtime keys need a component test.
- Booking helpers: `createPlan`, `createContact`, `bookSession` (optionally "booked at" a past time), `freeSlots`.
  Services that depend on the clock take `$now`; test them with a fixed one.
- Shared tests every list joins: **search** (`?q=` filters) and **query count** (one row vs five must not change it).
- PHPStan level 6 with an empty baseline.
- `phpunit.dist.xml` pins `APP_URL` so tests pass on any port.
- Playwright e2e in the `e2e` compose profile.
- `docs/tests/ui-regression.md` — the manual browser pass, one case per use case.
