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

The consultant has two plans (a free *Diagnóstico gratuito* and *Plan A*, 250.000 × 2 sessions) and its home page
books the free one in its *Reserva* section, Monday to Friday 9–12 and 14–18 (Bogotá). Its page `plan-2-sesiones`
sells Plan A in its *Precios y pago* section, with Wompi test keys that are not real: the checkout signature is
right, and Wompi's answer is simulated with `php bin/console app:wompi:simulate-event <PON-reference>` (it sends our
webhook the signed event Wompi would send). To see Wompi's real checkout, save your own sandbox keys in Ajustes ›
Pagos Wompi.

The home page feeds the ready flow *Diagnóstico gratuito* (Nuevo → Sesión agendada → Seguimiento → Cliente →
Finalizado), whose *Sesión agendada* stage sends the email of the same name; Carlos is in Cliente and Laura Gómez has
waited in Seguimiento past its alert, so she shows on Inicio.

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

Session reminders come from `php bin/console app:send-due-reminders`: locally the `scheduler` service runs it every
minute; on cPanel it is a second cron line every minute. It sends each due reminder once (a row in
`session_reminder` claims it), so running it twice, or late, is safe.

## API reference

Every error is `{"error": "<code>", "message": "…"}`, plus `violations: [{field, message}]` on a 422. Lists answer
`{items, total, page, perPage}` and take `?page=`, `?perPage=` (≤ 100) and `?q=`.

| Method | Path | Who | What |
|---|---|---|---|
| POST | `/api/login` | anyone | Staff sign-in: `{email, password}` → `{token}` |
| POST | `/api/portal-login` | anyone | Client sign-in: `{account, email, password}` → `{token}` |
| POST | `/api/password-reset/request` | anyone | "¿Olvidaste tu contraseña?" `{email, account?}` (a client adds the consultant's slug): always 204 |
| POST | `/api/password-reset/confirm` | anyone | `{token, password}` → `{loginPath}` (the link lasts an hour, works once) |
| POST | `/api/me/password` | signed in | `{currentPassword, newPassword}` |
| GET | `/api/portal/overview` | client | Their next session, their current plans, the cancellation limit |
| GET | `/api/portal/sessions`, `/slots` | client (booking) | Their sessions; free slots for `?enrollmentId=` or `?sessionId=` |
| POST | `/api/portal/sessions`, `…/{id}/reschedule`, `…/{id}/cancel` | client (booking) | Book `{enrollmentId, startsAt}` a session of one of their plans; move; cancel — the visitor's rules |
| GET | `/api/portal/plans` | client | Their plans with progress and payments |
| POST | `/api/portal/plans/{id}/pay` | client | `{checkoutUrl}`: Wompi's checkout for a plan assigned to them |
| GET | `/api/portal/notes` | client | The shared notes of their sessions |
| GET, POST | `/api/portal/files`, `…/{id}/download` | client | Shared files and their own; upload (multipart `file`) |
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
| GET | `/api/platform/dashboard` | super admin | Consultants by status, failed emails (7 days), failed queue jobs |
| GET, POST | `/api/platform/accounts` | super admin | Consultants (`?q=&status=active\|suspended`); create one and invite its owner |
| GET, PATCH | `/api/platform/accounts/{id}` | super admin | A consultant: data, limits, features |
| POST | `/api/platform/accounts/{id}/suspend`, `/reactivate` | super admin | |
| GET | `/api/platform/accounts/{id}/users` | super admin | Its owner and assistants |
| POST, DELETE | `/api/platform/accounts/{id}/users/{userId}[/resend-invitation\|/enable]` | super admin | Resend, enable, disable an assistant |
| GET, PATCH | `/api/platform/settings` | super admin | The platform's settings; each change is logged |
| GET | `/api/platform/settings/history` | super admin | Who changed which setting (`?q=`) |
| GET, POST | `/api/platform/admins` | super admin | Super admins; invite one |
| POST, DELETE | `/api/platform/admins/{id}[/resend-invitation\|/enable]` | super admin | Resend, enable, disable (never yourself) |
| GET | `/api/platform/emails` | super admin | Every attempt to send an email (`?q=&status=&account=`) |
| POST | `/api/platform/emails/test` | super admin | Send a test email `{to}` |
| GET | `/api/admin/dashboard` | owner, assistant | New leads (7 days), published pages and the limit |
| GET, POST | `/api/admin/pages` | owner, assistant | Pages (`?q=&status=&template=`, with leads in 30 days); create from a template |
| GET | `/api/admin/pages/catalog` | owner, assistant | Templates, section types and their fields, accents, form field types |
| GET, PATCH | `/api/admin/pages/{id}` | owner, assistant | A page; save title, address and draft (checked against the template) |
| POST | `/api/admin/pages/{id}/preview` | owner, assistant | `{draft}` → the page as HTML, never indexed (the editor's live preview) |
| POST | `/api/admin/pages/{id}/publish`, `/duplicate`, `/home` | owner, assistant | Publish (409 `page_limit_reached`), copy, make the home page |
| POST | `/api/admin/pages/{id}/disable`, `/reactivate` | owner | Take down (visitors get 410), bring back |
| GET, POST | `/api/admin/media` | owner, assistant | Images (`?q=&includeInactive=1`); upload (multipart `file`, 409 `storage_limit_reached`) |
| GET, PATCH, DELETE | `/api/admin/media/{id}` | owner, assistant | An image; its alt text; disable (`POST …/enable` to undo) |
| GET, POST | `/api/admin/categories`, `/all` | owner, assistant | Lead categories (`?q=`); every one for pickers; create |
| PUT, DELETE | `/api/admin/categories/{id}` | owner, assistant | Rename and recolour; disable (`POST …/enable` to undo) |
| GET | `/api/admin/contacts` | owner, assistant | Prospectos (`?q=&status=&category=<id or none>&sourcePage=<id>`) |
| GET, PATCH | `/api/admin/contacts/{id}` | owner, assistant | A contact with every form they sent, their sessions, and their plans with payments; `{categoryId}` |
| POST | `/api/admin/contacts/{id}/anonymize` | owner | Erase the person's data (Ley 1581) |
| GET, PUT | `/api/admin/privacy` | owner (PUT), assistant (GET) | The privacy policy the forms link to |
| GET, POST | `/api/admin/plans`, `/all` | owner (POST), assistant (GET) | Plans (`?q=&includeInactive=1`); every one for pickers; create `{name, description, price, sessions, durationMinutes}` |
| PUT, DELETE | `/api/admin/plans/{id}` | owner | Change; disable (`POST …/enable` to undo). Enrollments keep what they were sold |
| GET, PUT | `/api/admin/availability` | owner, assistant (booking) | Weekly hours, exceptions, buffer, notice, window, cancellation limit, reminder hours, meeting link |
| GET | `/api/admin/availability/slots` | owner, assistant (booking) | Free slots by day, for `?planId=` (to book) or `?sessionId=` (to move it) |
| GET, POST | `/api/admin/sessions` | owner, assistant (booking) | Sessions (`?q=&status=&from=&to=`, local dates); book one for a contact on a free plan (409 `slot_taken`, 422 `plan_not_bookable`) |
| GET | `/api/admin/sessions/week` | owner, assistant (booking) | The scheduled sessions of the week starting `?start=` (Y-m-d) |
| POST | `/api/admin/sessions/{id}/reschedule`, `/cancel` | owner, assistant (booking) | Move `{startsAt}`; cancel `{reason}`; the person is emailed |
| POST | `/api/admin/sessions/{id}/done`, `/no-show`, `/reopen` | owner, assistant (booking) | Close once it has started (409 `session_not_closable`); undo. Closing the last session completes its plan |
| GET, POST | `/api/admin/sessions/{id}/notes` | owner, assistant (booking) | A session's notes (an assistant gets the shared ones); write one `{body, visibility: private\|shared}` (private: owner only) |
| PUT | `/api/admin/session-notes/{id}` | author or owner | Change a note |
| POST | `/api/admin/contacts/{id}/enrollments` | owner, assistant | Assign a plan `{planId}`: a free one starts; a paid one waits for payment and emails its link (payments feature) |
| POST | `/api/admin/enrollments/{id}/send-link`, `/cancel` | owner, assistant | Email the payment link again (payments); cancel a plan waiting for payment |
| POST | `/api/admin/enrollments/{id}/payments` | owner (payments) | Record money received `{method: cash\|transfer\|other, note, paidOn}`: the plan starts, the person becomes a client |
| POST | `/api/admin/enrollments/{id}/renew`, `/finish` | owner, assistant | A used-up plan: another one `{planId}`, or the end of the consultancy (409 `enrollment_not_completed`) |
| GET | `/api/admin/payments` | owner, assistant (payments) | Payments (`?q=&status=&from=&to=`) |
| POST | `/api/admin/contacts/{id}/portal/invitation`, `/disable`, `/enable` | owner, assistant (portal) | Invite to the portal (or again); take the access away; give it back |
| GET, POST | `/api/admin/contacts/{id}/files` | owner, assistant | A contact's files; upload (multipart `file`, `shared`) |
| PATCH, DELETE | `/api/admin/client-files/{id}` | owner, assistant | Share or unshare `{shared}`; turn off (`POST …/enable` to undo); `GET …/download` |
| GET, POST | `/api/admin/flows`, `/all` | owner, assistant (flows) | Flows (`?q=&includeInactive=1`); a new one `{name}` starts as the ready example |
| GET, PUT, DELETE | `/api/admin/flows/{id}` | owner, assistant (flows) | A flow with its stages, arrows and the pages that feed it; save the whole canvas `{name, stages, transitions}` (violations point at `stages[i].…`, `transitions[i].…`); disable (`POST …/enable` to undo) |
| GET | `/api/admin/flows/{id}/board` | owner, assistant (flows) | A column per stage, the people in it with their days there |
| POST, DELETE | `/api/admin/flows/{id}/people`, `…/people/{contactId}`, `…/people/{contactId}/move` | owner, assistant (flows) | Add `{contactId}` to the start stage (409 `already_in_flow`, `flow_not_ready`); take out; move to any stage `{stageId}`. Each answers the person's flows |
| GET, POST, PUT, DELETE | `/api/admin/email-templates`, `/all`, `/{id}`, `/{id}/enable` | owner, assistant (flows) | The emails stages send (`?q=` name and subject) |
| GET | `/api/admin/contacts/{id}/history` | owner, assistant | Every move through a flow and every email the person was sent |
| GET, PUT | `/api/admin/wompi` | owner (payments) | Wompi keys: the public key, secrets by their last 4 characters, the events URL; save (empty secret: keep) |
| POST | `/api/admin/wompi/test` | owner (payments) | Ask Wompi for the public key's merchant (409 `wompi_rejected`) |

Public (server-rendered): `/<consultant>` (home page), `/<consultant>/<page>`, `POST …/enviar` (the form),
`/<consultant>/privacidad`, `/<consultant>/media/<id>-<width>.webp`, `/<consultant>/sitemap.xml`, `/sitemap.xml`,
`/robots.txt`, `POST …/reservar` (a page's Reserva section), `/<consultant>/reservar/<token>` with
`POST …/cambiar` and `…/cancelar` (the person's session, from the emailed link), `POST …/pagar` (a page's *Precios
y pago* section → Wompi's checkout), `/<consultant>/pagar/<token>` (a plan's payment link) and
`/<consultant>/pago/<reference>` (where Wompi sends the person back), `POST /webhooks/wompi/<account id>` (Wompi's
events), `/<consultant>/correos/baja/<contact>/<signature>` with `POST` (stop a flow's emails, from their link). A former address (a consultant's or
a page's) answers 301; a disabled page 410; a draft 404.

## Data model decisions

- UUIDv7 ids, stored as `BINARY(16)`; ids travel as strings.
- Nothing is deleted: people and things are disabled (`active: false`).
- `app_user.login_scope` is `staff` or the account id, and `(email, login_scope)` is unique: staff emails are unique
  across Pontiac, client emails per consultant.
- A consultant's slug is the first segment of every public URL; the app's own first segments are reserved
  (`Account::RESERVED_SLUGS`), and the super admin can reserve more (Configuración › Legal).
- `platform_settings` is one row; until a super admin first saves, the entity's defaults are the settings. The
  defaults for new consultants (limits, features) are **copied** into the account when it is created, so changing a
  default never changes an existing consultant. Each save that changes something writes a `platform_settings_change`.
- `account.features` (JSON list) and four limit columns. Enforced today: the assistant limit (409
  `assistant_limit_reached`) and the client portal (clients cannot sign in, and their sessions end). Controllers of
  later features declare `#[RequiresFeature(AccountFeature::…)]` (403 `feature_disabled`); menu items declare `feature`.
- `outgoing_email` logs every attempt to send an email, written by `App\Mail\EmailLog` from the mailer's events with
  DBAL (never flushing someone else's changes). It is not account-owned: only the super admin reads it; `account_id`
  says which consultant an email was for. A queued email that fails and is retried logs one row per attempt.
- A page's content is JSON (`draft`, `published`) shaped by `App\Page\TemplateCatalog`: a template is a fixed list of
  sections; the consultant orders them, turns them on and off and edits their fields, but cannot add or remove
  sections. `ContentValidator` checks every save against the template (images and categories must be the
  consultant's own). MySQL returns JSON with its keys sorted, so drafts are compared as content, not as text.
- Public pages are Twig, CSS inline, no framework JavaScript: Lighthouse on mobile, production mode: performance 100,
  accessibility 100, SEO 100 (best practices 79 locally only because the stack is plain HTTP) — measured before the
  redesign below, not re-measured since.
- Landing pages are built to convert: every button on a page (hero, header, cta band, the sticky bar on phones) leads
  to its first visible form, booking or payment section, wherever the consultant put it (`PageRenderer::cta()`); with
  none of them on, the page shows no buttons. The look lives in `templates/public/page/_styles.css.twig` (inline) with
  one self-hosted font (Plus Jakarta Sans, OFL, 27 KB, preloaded) and one small inline script that is optional:
  without it every section shows and the buttons are plain links. `PageRenderer::DESIGN` is part of each page's
  ETag, so a change of look reaches browsers that hold a page from before.
- The page form is a plain HTML form (post, redirect, get). Bots are stopped by a trap field and a signed time token
  (at least 3 s to fill), and answered as if it worked. One contact per email and consultant; every form is a
  `lead_submission` with the answers labelled as they were asked. Consent is stored with a hash of the policy text.
- Page images are public by nature: served to anyone at their consultant's address, one-year immutable cache.
  Uploads are checked by content (JPEG, PNG, WebP), resized to WebP 480/960/1600 with GD (never enlarged), and count
  towards the storage limit with their copies.
- Times are stored in UTC; everything about days (weekly hours, exceptions, the week view, "from/to" filters, email
  dates) is worked out in the consultant's timezone (`SlotFinder`, `SessionTime`).
- A booking is an `enrollment` (the contact on a plan, with the plan's name, price, sessions and minutes **copied**,
  so changing a plan never changes what someone bought) and its `session`s. A page books only an active free plan;
  paid plans are booked after payment (milestone 3). The staff may book any free time; visitors only the offered
  slots. Both re-check under a lock per consultant, so two people cannot take the same slot (409 `slot_taken`).
- A session's manage link is a random token; only its hash is stored, and moving the session issues a new one (the
  old link stops working). The person can move or cancel it until the consultant's cancellation limit.
- A reminder is not sent for a time that had already passed when the session was booked (booked 3 hours before, no
  24-hour reminder). The meeting link is copied into the session when it is booked.
- Payments go through Wompi's Web Checkout (a redirect, no JavaScript on our pages), signed with the consultant's
  integrity secret. A payment is settled only by Wompi: its event (checksum with the events secret) or its API
  (asked by the result page, which never trusts the URL). Reference, amount and currency must be ours, or the
  payment is marked `error`; applying a result is idempotent (a lock per payment, only `pending` changes).
- An approved payment activates its plan and makes the person a client (a free plan never does); a plan completes
  when its sessions are used (done or no-show); "Renovar" or "Finalizar asesoría" closes it (`outcome`).
- Wompi's secrets are encrypted with libsodium (`APP_ENCRYPTION_KEY`) and never returned; the screen shows their
  last four characters.
- Session notes are private (owner only) or shared (the assistant, and the client in the portal from milestone 4).
- A client login belongs to one contact (`app_user.contact_id`); the portal reads everything from it, never from the
  request. A first paid plan invites the person; the team can invite anyone, take the access away or give it back;
  erasing the person's data turns the login off. Client bookings follow the visitor's rules.
- Contacts' files live at `backend/var/uploads/<account>/files/<id>`, type detected from content (PDF, JPG, PNG, WebP, XLSX,
  CSV, DOCX), counted in the storage limit with the images, downloaded only through the API. What the client uploads
  is always shared with them.
- Password resets email a one-hour, single-use link; asking answers the same whether the email exists or not.
- A flow is stages (one start, steps, ends) and arrows, each arrow a trigger: an event (form sent, session booked,
  done or no-show, payment approved, plan completed, consultancy finished) or *manual* (documentation only). The
  services where something happens to a person dispatch a `ContactMoment`; `FlowEngine` listens, first puts them
  in the start stage of the flow their page feeds (page `settings.flowId`), then moves them along the arrow of their
  stage with that trigger in every active flow they are in. A person is at most once per flow
  (`contact_flow_state`), in several flows at once; every move is a `flow_event` (kept when they leave).
- Entering a stage sends its email once per entry, after the flush, unless the person stopped flow emails
  (`contact.flow_emails_stopped_at`, from a link signed with the app secret and a `List-Unsubscribe` header) or their
  data was erased. Variables with nothing to say read "día por definir" (no session) or the booking link (nothing to
  pay).
- The flow editor saves the whole canvas at once; stage ids the editor invents (`new-1`) become real ids. A stage
  with people cannot be removed. The column is `trigger_event`: `trigger` is a reserved word in MySQL.
- Emails keep the server's `MAILER_FROM` address (it must match the SMTP account); the sender name and Reply-To come
  from the platform settings.

## Deploying to cPanel

`deploy/cpanel-update.sh` installs and updates Pontiac on a cPanel account over SSH (`cd ~/pontiac &&
./deploy/cpanel-update.sh`). Its header lists the first-time steps: clone, document root at `backend/public`,
HTTPS, a MySQL 8 database, `backend/.env.local` from `deploy/env.local.example`, then the first super admin.

Each run pulls, installs the PHP dependencies without dev packages, and builds the UI (or uses the
`public/build/` already there when the account has no npm). It dumps the database before any pending migration,
then migrates and warms the production cache. It refuses to go on while a secret is still the development value
from `backend/.env`, `APP_URL` is not HTTPS, or the mailer is not real. It
also prints the two cron lines the account needs, every minute: `messenger:consume async` (every email is sent
from the queue) and `app:send-due-reminders`.

Keep `APP_ENCRYPTION_KEY` with the backups (database dumps and `backend/var/uploads`): the stored Wompi secrets cannot
be read without it. Each consultant pastes their events URL (Ajustes › Pagos Wompi) in Wompi's dashboard.

## Known gaps

- Page, storage and file-size limits are stored but only enforced by the milestones that build what they limit.
- Payments: refunds and voids are done in Wompi's dashboard (their events do not reach an approved payment); no invoices.
- Booking: no calendar sync (Google/Outlook) — each email carries an `.ics` file instead; paid plans cannot be booked
  until payments (milestone 3); changing the meeting link does not change sessions already booked; the person may
  move a session into a slot inside the cancellation limit.
- Template previews in Configuración › Plantillas (the page editor's preview covers the consultant's side).
- Landing pages: the consultant picks an accent colour but not fonts or a layout variant; no A/B tests, no
  stats or logo sections; sections are reordered with up/down buttons, not dragged.
- EXIF orientation of uploaded photos is not applied (a phone photo taken sideways stays sideways).
- The page limit counts published pages; a consultant can keep any number of drafts.
- No export of prospectos yet; no custom domains.
- The client portal's sign-in page does not show the consultant's name yet (only Pontiac's).
- Flows: no waits or conditions ("3 days after…"), no branches by answer, no emails to the consultant from a flow, no
  numbers per flow. Arrows move people only on events: a stage alert shows who waits, it moves no one.
- Lists of users are small today and not paginated by the database for the super admin's picker.
- The client can book only the plans the consultant assigned; there are no messages between client and consultant.
