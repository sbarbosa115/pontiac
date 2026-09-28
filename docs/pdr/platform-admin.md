# Milestone 0b — Platform admin

Part of [prd-pontiac.md](prd-pontiac.md) ("Super admin", "Delivery plan" 0b). What Pontiac's operator needs to run the
platform: create consultants, set what each one may use, and keep the platform's own settings.

## What the super admin can do

- **Inicio** — active and suspended consultants, emails that failed in the last 7 days, queue jobs that failed.
- **Asesores** — search consultants by name, address or owner's email, filter by status. Create one (name, address,
  owner's name and email): the account takes the platform's default limits and features, and the owner gets the
  invitation email. Row actions: open, act as the consultant, send the owner's invitation again, suspend/reactivate.
- **A consultant's page** (tabs, kept in the URL):
  - *Datos* — name, address (slug), country, currency, locale, timezone.
  - *Límites y funciones* — max published pages, max assistants, storage (MB), max file size (MB); features
    (booking, payments, client portal, flows).
  - *Usuarios* — the owner and assistants: send the invitation again, disable/enable an assistant.
  - *Correos* — the emails sent for this consultant.
- **Configuración** (tabs):
  - *General* — platform name, support email (Reply-To of every email), sender name.
  - *Plantillas* — the 5 landing-page templates, each on or off for new pages.
  - *Valores por defecto* — reminder times, minimum booking notice, booking window, client cancellation limit,
    buffer between sessions.
  - *Límites* and *Funciones* — what a new consultant starts with.
  - *Legal* — terms, default privacy policy text, extra reserved addresses.
  - *Administradores* — invite other super admins, disable one (never yourself).
  - *Historial* — who changed which setting, from what to what, and when.
- **Correos** — every email Pontiac sent or failed to send (recipient, subject, consultant, status, error), searchable
  and filtered by status; "Enviar correo de prueba".

## Rules

- Defaults are copied into a consultant when it is created; changing a default never changes an existing consultant.
- A consultant's address must match the slug pattern, be unused, and be neither an app path nor an extra reserved
  address from *Legal*.
- Enforced now: the assistant limit (inviting or enabling one more answers 409 `assistant_limit_reached`) and the
  client portal (off: clients cannot sign in, 403 `feature_disabled`, and their open sessions stop at once). The
  other limits and features are enforced by the milestones that build what they limit; their menus hide with them.
- The booking defaults are stored now; milestone 2 copies them into each consultant's booking settings.
- Emails keep the server's `MAILER_FROM` address (it must match the SMTP account); the sender *name* and Reply-To
  come from the settings.
- The email log records each attempt: a queued email that fails and is retried shows one failed row per attempt.

## API

| Method | Path | What |
|---|---|---|
| GET | `/api/platform/dashboard` | The Inicio figures |
| GET, POST | `/api/platform/accounts` | List (`?q=&status=`), create |
| GET, PATCH | `/api/platform/accounts/{id}` | Detail, change data / limits / features |
| POST | `/api/platform/accounts/{id}/suspend`, `/reactivate` | |
| GET | `/api/platform/accounts/{id}/users` | Owner and assistants |
| POST | `/api/platform/accounts/{id}/users/{userId}/resend-invitation`, `/enable` | |
| DELETE | `/api/platform/accounts/{id}/users/{userId}` | Disable an assistant |
| GET, PATCH | `/api/platform/settings` | The settings; a change is logged |
| GET | `/api/platform/settings/history` | The log (`?q=`) |
| GET, POST | `/api/platform/admins` | Super admins; invite |
| POST, DELETE | `/api/platform/admins/{id}/resend-invitation`, `/enable`, `/{id}` | |
| GET | `/api/platform/emails` | The log (`?q=&status=&account=`) |
| POST | `/api/platform/emails/test` | Send a test email `{to}` |

## Tests

Functional per endpoint: the super admin's happy path, 422s (slug taken, reserved, malformed; bad limits), 409s
(email in use, limit reached, disabling yourself or an owner), 403 for every other role, 404 for unknown ids. The
assistant limit and the portal feature from the consultant's and the client's side. Every new list joins
`ListSearchTest` and `ListQueryCountTest`. The email log from a real send (direct and queued) and a failure, and our
tag headers never reaching the recipient. Vitest for the create form and the features form; Playwright: a super
admin creates a consultant.

## Out of scope

Billing consultants; self sign-up; template previews (milestone 1); redirects when a consultant's address changes
(milestone 1, with page slugs); enforcing page, storage and file limits (milestones 1 and 4).
