# Pontiac — PRD

Status: draft, awaiting confirmation · Date: 2026-09-27

## Glossary

| In the UI (es) | In the code | Meaning |
|---|---|---|
| Asesor | `Account` owner (`ROLE_OWNER`) | The finance consultant whose practice this is. One account per practice. |
| Asistente | `ROLE_ASSISTANT` | Someone the asesor invites to help run the practice. |
| Prospecto | `Contact` with status `lead` | A person who left their details on a landing page or booked a free session. |
| Cliente | `Contact` with status `client` | A person who has paid for at least one plan. Can sign in to the portal (`ROLE_CLIENT`). |
| Página | `LandingPage` | A public page built from one of the 5 templates. |
| Plan | `Plan` | Something the asesor sells: a price × a number of sessions ("Plan A: $250.000 × 2 sesiones", "Diagnóstico: $0 × 1"). What the brief calls a *pack*. |
| Inscripción | `Enrollment` | One contact taking one plan once: its sessions, its payment, its progress. |
| Sesión | `Session` | One meeting between the asesor and a contact, in a slot. |
| Flujo / Etapa | `Flow` / `FlowStage` | The path a contact follows (e.g. page → session booked → follow-ups → done), drawn by the asesor. |

## Problem

An independent finance consultant sells advice in small packs. Today they juggle a website builder, a form tool, a
calendar link, WhatsApp, bank transfers and a notebook. Leads get lost between tools, nobody reminds the client of
the session, and the consultant cannot see who is where in the process or what was said in the last session.

Pontiac puts the whole path in one place: publish a landing page for each offer, capture and categorise the people who
answer, let them book a slot and pay, turn them into clients, keep notes per session, remind everyone before each
meeting, and see every contact on a flow the consultant designed. It is sold to many independent consultants.

## Goals (observable)

1. A consultant publishes an SEO-ready landing page from a template in under 15 minutes, without help.
2. Every form submission appears as a prospecto in the admin within a second, with its category and page.
3. A visitor can book a free session from a page, and a client can pay for a plan with Wompi (cards, PSE, Nequi)
   and then book their sessions — no messages back and forth.
4. Both the consultant and the client get a reminder email 24 h and 1 h before every session.
5. On a board, the consultant sees each contact's stage in each flow, and contacts move on their own when they book,
   pay or finish a session.
6. Public pages score ≥ 90 in Lighthouse for Performance and SEO on mobile.
7. No consultant can ever see or change another consultant's data.

## Non-goals (v1)

- Charging consultants for Pontiac (subscriptions, invoices). Accounts are created by the super admin.
- Custom domains per consultant (pages live at `pontiac.co/<consultant>/<page>`).
- Drag-and-drop page layout; new templates made by consultants.
- Calendar sync (Google/Outlook), video-call creation, SMS/WhatsApp messages.
- Conditional automation (waits, branches with conditions). Flows move on events and by hand.
- Electronic invoicing (DIAN), refunds through the app (done in the Wompi dashboard), installments.
- Pages in languages other than Spanish.
- Native mobile apps (the admin and portal are responsive).

## Users and roles

| Role | Who | Sign-in | Sees | Can change | URL space |
|---|---|---|---|---|---|
| Super admin | Pontiac's operator | Email + password (the first from a console command; more invited by a super admin) | All consultants (accounts), their users, usage, platform email log | Create consultants and invite their owner, suspend/reactivate, per-account limits and features, platform settings (sender, templates, defaults, limits, legal texts), invite other super admins, act as an owner or assistant | `/api/platform`, `/plataforma` |
| Asesor (owner) | The consultant | Email + password from invitation | Everything in their account | Everything in their account, incl. Wompi keys, team, plans' prices | `/api/admin`, `/admin` |
| Asistente | Consultant's helper | Email + password from invitation | Pages, contacts, calendar, sessions, payments; **not** private session notes, Wompi keys, team | Leads, bookings, availability, pages' content; **not** prices, Wompi keys, team, disabling pages | `/api/admin`, `/admin` |
| Cliente | Person who paid a plan | Email + password from invitation sent on first payment; signs in at `/<consultant>/portal` | Own plans, sessions, payments, shared notes, shared files | Book/reschedule/cancel own sessions (within rules), pay for an offered plan, upload files | `/api/portal`, `/<consultant>/portal` |
| Visitor | Anyone on the internet | None | Published pages | Send a form, book a free slot, start a payment | public, no `/api` auth |

Roles do not inherit each other. Owner and assistant share the admin space; owner-only endpoints add
`#[IsGranted('ROLE_OWNER')]`. The super admin can act as any owner or assistant (impersonation) for support; acting
as a client is not allowed.

## Domain model

All customer-owned rows carry `account_id`. Nothing is hard-deleted: things are disabled or archived. A contact's
data-deletion request (Ley 1581) anonymises the contact instead.

- **PlatformSettings** — one row, not owned by an account: platform name, support email, sender, enabled
  templates, defaults for new accounts (reminders, booking rules, limits, features), terms and default privacy
  text, reserved slugs. Changed only by the super admin; each change is logged (who, what, when).
- **Account** — a consultant's practice. Limits (pages, assistants, storage) and features (booking, payments,
  portal, flows) copied from the platform defaults at creation and overridable by the super admin. Name, `slug`
  (unique, used in URLs; reserved words refused), country (CO),
  currency (COP), locale (es-CO), timezone (America/Bogota), sender name, reply-to email, default meeting link,
  Wompi keys (public key, private key, events secret, integrity secret — encrypted at rest), privacy-policy text,
  active.
- **User** — email, full name, roles, password, account, active, invitation token hash + expiry, `uiTheme`. Staff
  emails are unique across Pontiac; client users are unique per account (a person can be a client of two consultants).
  A client user links to one Contact.
- **LandingPage** — account, title, `slug` (unique per account; one page per account is the **home** at
  `/<consultant>`), template key (one of 5), content (JSON validated against the template's section schema: texts,
  images, section order and on/off), SEO fields (meta title, meta description, social image, index/noindex),
  form definition, components enabled (booking → a plan with price 0; payment → one or more paid plans), default
  lead category, flow it feeds. Status: `draft` → `published` ⇄ `disabled`. Editing a published page edits a draft
  copy; **Publicar** replaces the live content.
- **PageTemplate** (code, not a table) — 5 templates, each a Twig layout plus a section schema:
  1. *Diagnóstico gratuito* — hero, problem, how it works, booking, FAQ, form.
  2. *Plan / paquete* — hero, benefits, what's included, price cards with payment, testimonials, FAQ.
  3. *Perfil del asesor* — photo hero, bio, credentials, services, testimonials, contact form.
  4. *Evento / taller* — date and agenda, speaker, seats, payment or form.
  5. *Recurso descargable* — lead magnet: hero, what you get, form; the file is emailed after sending the form.
- **MediaAsset** — account, stored key, original name, content type (detected), dimensions, sizes generated
  (WebP 480/960/1600). Used by pages and by the portal.
- **LeadCategory** — account, name, colour token, active. Pages assign a default; a form select field can map each
  option to a category.
- **Contact** — account, full name, email, phone, status `lead` → `client` → `finished` (can go back to `client`
  when they re-join), category, source page, consent (timestamp + policy version), anonymised at. Unique per account
  on email.
- **LeadSubmission** — account, contact, page, answers (JSON of the form's fields at that time), UTM parameters,
  referrer, submitted at. Every submission is kept; the contact is found or created by email.
- **FormField** (inside the page's form definition) — label, key, type (text, email, phone, textarea, select,
  checkbox, number), required, options, maps to (name / email / phone / category / none). Name, email, phone and the
  consent checkbox are always present.
- **Plan** — account, name, description, price (decimal string, COP), sessions included, session duration
  (minutes), active. Price 0 = free (e.g. diagnóstico).
- **Enrollment** — account, contact, plan (name, price and sessions copied at creation), status
  `pending_payment` → `active` → `completed` | `cancelled`, sessions used, source (page, or assigned by the asesor),
  outcome after completion (`renewed` → a new enrollment, `finished`).
- **Payment** — account, enrollment, Wompi reference (ours, unique) and transaction id, amount, method, status
  `pending` → `approved` | `declined` | `voided` | `error`, raw last event, paid at. An owner can record a
  **manual payment** (cash/transfer) with a note.
- **Availability** — account: weekly rules (weekday, from, to), date exceptions (day off / extra hours), slot
  length follows the plan's session duration, buffer between sessions, minimum notice (h), booking window (days).
- **Session** — account, enrollment, contact, starts at / ends at (UTC), status `scheduled` → `done` | `no_show` |
  `cancelled`, meeting link or address, cancel reason, reminders sent (24 h, 1 h). No two active sessions overlap in
  an account (checked under a lock).
- **SessionNote** — account, session, author, body, visibility `private` (owner only) | `shared` (client sees it).
- **ClientFile** — account, contact, media asset, uploaded by (staff or client), shared with client.
- **Flow** — account, name, active, the pages that feed it. **FlowStage** — flow, name, kind (`start`, `step`,
  `end`), position on the canvas, email template sent on entry (optional). **FlowTransition** — from stage, to stage,
  trigger: `manual` or an event (`lead_submitted`, `session_booked`, `session_done`, `session_no_show`,
  `payment_approved`, `enrollment_completed`, `consultancy_finished`). **ContactFlowState** — contact, flow, current
  stage, entered at; **FlowEvent** — history of every move (from, to, trigger, by whom, when).
- **EmailTemplate** — account, name, subject, body with variables (`{nombre}`, `{asesor}`, `{fecha_sesion}`,
  `{enlace_reserva}`, `{enlace_pago}`, `{enlace_portal}`), active. Used by flow stages.
- **OutgoingEmail** — account, to, template, status, sent at, error: a log the asesor can see per contact.

### Lifecycle, with the brief's two packs

- **Pack 2 (free diagnostic):** visitor opens *page 2* → books a slot in the booking component → a contact
  (`lead`), a free enrollment (Diagnóstico $0 × 1) and a session are created; flow moves to *Sesión agendada* →
  reminders → asesor marks the session done → flow moves to *Diagnóstico hecho*. The asesor **assigns a plan**
  (Plan A) → an enrollment `pending_payment` and a payment link are emailed → on payment the contact becomes a
  `client` and is invited to the portal.
- **Pack 1 (paid plan):** visitor opens *page 1* → pays $250.000 for Plan A with Wompi → contact `client`,
  enrollment `active` with 2 sessions, portal invitation → client books both sessions from the portal (or from the
  link in the email) → notes per session → after the last session the enrollment is `completed` and the asesor
  chooses **Renovar** (a new enrollment of any plan, a new payment link) or **Finalizar asesoría** (contact
  `finished`; they can re-join later by paying again, which makes them `client`).
- A paid plan is paid **before** booking: no slot is held for an unpaid visitor.

## Screens and navigation

Admin UI in React (`/admin`); public pages rendered on the server (Twig) for SEO; portal in React
(`/<consultant>/portal`).

### Asesor / asistente — menu in the order of the work

1. **Inicio** — today's and tomorrow's sessions, new prospectos (7 days), payments (7 days), contacts waiting in
   each flow's stages longer than N days.
2. **Prospectos y clientes** — tabs:
   - *Tablero* — pick a flow; one column per stage; cards with name, category, days in stage; drag to move
     (manual move), click to open the contact.
   - *Lista* — table: name, email/phone, category, source page, stage, last activity; filters: search (name,
     email, phone), status (prospecto/cliente/finalizado), category, page, flow stage; row tint = status; actions:
     ver, asignar plan, mover de etapa, deshabilitar.
   - Contact detail page — tabs: *Resumen* (data, consent, submissions), *Planes y pagos* (enrollments, payments,
     assign plan, copy payment link, record manual payment), *Sesiones* (list, book on their behalf, notes),
     *Archivos*, *Historial* (flow moves and emails sent).
3. **Agenda** — tabs: *Calendario* (week/day view of sessions; open one → mark done / no-show / cancel /
   reschedule, notes), *Sesiones* (table with filters: date range, status, plan), *Disponibilidad* (weekly rules,
   exceptions, buffer, notice, window).
4. **Páginas** — table: title, URL, template, status, prospectos (30 days); filters: search (title, slug), status,
   template; actions: editar, ver, duplicar, publicar/deshabilitar (owner). **Nueva página**: choose template →
   editor.
   - Editor — left: sections (on/off, reorder, fields: texts, images from the media library, buttons); tabs
     *Contenido*, *Formulario* (fields, categories), *Componentes* (booking plan, payment plans), *SEO* (URL slug,
     meta title/description with length hints, social image, indexar); right: live preview (desktop/mobile);
     **Guardar borrador**, **Vista previa**, **Publicar**.
5. **Flujos** — table of flows; editor: a canvas (React Flow) with stages as nodes and transitions as edges; each
   edge has a trigger (manual or an event); each stage an optional email template; pages that feed the flow.
6. **Planes** — table: name, price, sessions, duration, active enrollments; owner edits prices.
7. **Pagos** — table: date, contact, plan, amount, method, status; filters: search, status, date range.
8. **Ajustes** — tabs: *Perfil del asesor* (name, slug, sender, reply-to, meeting link, privacy policy),
   *Equipo* (owner: invite/disable assistants), *Pagos Wompi* (owner: keys, test/production, webhook URL to paste in
   Wompi, "probar conexión"), *Correos* (email templates), *Categorías*, *Medios* (media library).

### Cliente (portal)

**Inicio** (next session with join link, plan progress "1 de 2 sesiones"), **Sesiones** (book / reschedule /
cancel up to 24 h before), **Mis planes y pagos** (history; pay an offered plan), **Notas** (shared notes by
session), **Archivos** (download shared, upload own), **Mi cuenta** (theme, password).

### Super admin (`/plataforma`)

1. **Inicio** — accounts active/suspended, pages published, leads and payments (30 days) across the platform,
   failed emails and failed queue jobs.
2. **Asesores** — table: consultant, slug, owner email, status, pages, contacts, last sign-in; filters: search
   (name, slug, email), status; actions: ver, editar, invitar de nuevo, suspender/reactivar, actuar como.
   **Nuevo asesor**: practice name, slug (checked against reserved words and existing ones), owner's name and
   email, limits → creates the account and emails the owner an invitation. Detail page tabs: *Datos* (name, slug,
   locale, currency, timezone), *Límites y funciones*, *Usuarios* (owner and assistants: resend invitation,
   disable), *Actividad* (pages, contacts, payments, emails sent).
3. **Configuración** — platform-wide settings, tabs:
   - *General* — platform name, support email, sender name and address for all emails, public marketing page
     texts.
   - *Plantillas* — the 5 templates: enable/disable each for new pages, preview.
   - *Valores por defecto* — applied to new accounts: reminder times (24 h and 1 h), minimum booking notice,
     booking window, cancellation limit for clients (24 h), session buffer.
   - *Límites* — defaults per account: max published pages, max assistants, storage (MB), max file size;
     overridable per account in *Límites y funciones*.
   - *Funciones* — defaults per account: booking, payments, client portal, flows on/off (a suspended feature hides
     its menu and answers 403 `feature_disabled`).
   - *Legal* — the platform's terms and default privacy-policy text (Ley 1581) that new accounts start from;
     reserved slugs.
4. **Correos** — outgoing email log across accounts (to, template, status, error) and a "send test email" button.

### Public

- `/<consultant>` home page, `/<consultant>/<page>` other pages, `/<consultant>/portal` sign-in.
- `/<consultant>/reservar/<token>` manage a booking from an email (reschedule/cancel).
- `/<consultant>/pago/<reference>` payment result page (polls the payment status).
- `/sitemap.xml` (index), `/<consultant>/sitemap.xml`, `/robots.txt`.
- `/` Pontiac's own marketing page (static in v1).

## Flows

1. **Publish a page.** Asesor → Páginas → Nueva → template *Diagnóstico gratuito* → edits hero, adds a "¿Cuál es
   tu mayor preocupación financiera?" select mapped to categories, enables booking with plan *Diagnóstico*, picks
   the flow *Diagnóstico* → Publicar → the page is live and in the sitemap. *No email.*
2. **Free diagnostic.** Visitor fills the form and picks Tuesday 10:00 → sees a confirmation → gets
   **"Tu sesión está agendada"** (with .ics, meeting link, reschedule/cancel link); asesor gets **"Nueva sesión
   agendada"**. 24 h and 1 h before: **reminders** to both. Asesor marks done, writes a private note, assigns Plan A
   → client gets **"Tu plan: Plan A"** with the payment link.
3. **Pay and become a client.** Visitor on the Plan A page (or from the payment link) → Wompi checkout → approved →
   result page shows "Pago aprobado" → client gets **"Pago recibido"** and **"Accede a tu portal"** (invitation);
   asesor gets **"Nuevo pago"**. Client sets a password, books session 1 and 2 → confirmations + reminders.
4. **Sessions to the end.** After each session the asesor writes notes (shared ones appear in the portal) → after
   the last one the enrollment completes → asesor chooses Renovar (new plan → payment link email) or Finalizar →
   client gets **"Gracias"** (a flow stage template). Months later they pay again from a page → back to `client`.
5. **A flow moves itself.** Flow *Diagnóstico*: Página → (lead_submitted) Nuevo → (session_booked) Agendado →
   (session_done) Seguimiento 1 → manual → Seguimiento 2 … → (payment_approved) Cliente → (consultancy_finished)
   Fin. Entering *Seguimiento 1* sends the asesor's follow-up template.

## Security and isolation

- Multi-account isolation as in the foundation: `account_id` on every owned row, Doctrine filter that fails closed,
  ownership listener, other accounts' ids answer 404. Public routes resolve the account from the URL slug and enter
  it explicitly; only published pages of an active account are served.
- Owner-only: Wompi keys, team, plan prices, disabling pages, private notes, manual payments.
- Clients see only their own contact's data; client ids in the portal come from the signed-in user, never from the
  request.
- Wompi: integrity signature on every checkout; webhook checksum verified with the account's events secret; the
  amount and reference are checked against our payment before approving; webhook processing is idempotent; the
  result page never trusts query parameters — it asks Wompi.
- Wompi keys and other secrets encrypted with libsodium (key in the environment), never returned by the API (only
  "configured: yes, ends in …").
- Public form: honeypot, time-to-submit check, rate limit per IP and per page; consent checkbox required and stored
  with the policy version (Ley 1581 de 2012). Anonymise-on-request for contacts.
- Files: type detected from content, 10 MB max, allowed types (PDF, images, XLSX, CSV, DOCX), stored outside the
  web root, served through the API after a scoped lookup.
- Rate limits on login, invitation acceptance, booking and payment start.

## SEO (public pages)

- Server-rendered HTML (Twig), no React; a few KB of vanilla JS for the form, booking picker and payment button.
- Per page: `<title>`, meta description, canonical URL, Open Graph and Twitter cards, `lang="es-CO"`, JSON-LD
  (`ProfessionalService` with the asesor, `Offer` per plan with price in COP, `FAQPage` when the FAQ section is on).
- Semantic headings (one `h1`), alt text required on images, responsive WebP images with width/height and lazy
  loading, critical CSS inline per template, fonts self-hosted with `font-display: swap`.
- `sitemap.xml` per account and an index; `robots.txt`; draft/disabled pages `noindex` and 404/410; slug changes
  leave a 301 redirect from the old URL.
- HTTP caching of published pages (ETag / `Cache-Control`), purged on publish.

## Hosting and operations (shared hosting, cPanel)

- PHP 8.4 with the extensions the app needs; MySQL 8 (or MariaDB ≥ 10.6 — to be confirmed with the host).
- No daemons: one cPanel cron every minute runs `messenger:consume async --time-limit=50` and
  `app:send-due-reminders`; both are idempotent and safe to overlap (a lock).
- Assets are built before upload (no Node on the server); deploy by git + `composer install --no-dev` + migrations.
- Local development and tests run in Docker, as for every app on this stack.
- Email through the host's SMTP (or a transactional provider if the host's hourly limit is too low).

## Delivery plan

The user chose to release everything at once. It is still built in milestones, each merged into `main` with its
tests, and released together at the end.

| Milestone | Content |
|---|---|
| **0. Foundation** | Docker, API conventions, JWT, roles (super admin, owner, assistant, client), accounts and isolation, invitations, React shell and UI kit, themes, tests and tooling, public page rendering with the account slug, cron-driven queue. |
| **0b. Platform admin** | Super admin area: create consultants and invite owners, suspend/reactivate, act as, per-account limits and features, platform settings (general, templates, defaults, limits, features, legal), email log, invite super admins. Features and limits enforced as later milestones land. |
| **1. Pages and leads** | Media library, the 5 templates with section schemas, page editor with preview, publish/disable, SEO (meta, JSON-LD, sitemap, redirects), form builder, lead categories, contacts and submissions, lead list. |
| **2. Booking and reminders** | Plans, availability, booking component, sessions, calendar, manage-booking links, confirmation and reminder emails with .ics. |
| **3. Payments and clients** | Wompi settings, payment component and checkout, webhook, payment result page, enrollments, assign plan and payment link, manual payment, lead → client, session notes, renew/finish. |
| **4. Client portal** | Portal sign-in per consultant, sessions (book/reschedule/cancel), plans and payments, shared notes, files. |
| **5. Flows** | Flow editor (canvas), stages and transitions with event triggers, board, stage emails, history, dashboard. |
| **Release** | cPanel deployment guide, cron, production Wompi keys, Lighthouse and security pass, full UI regression. |

## Risks

- **Scope.** Everything in one release is large; the risk is months without real users. Mitigation: milestones are
  usable on their own, and the release can be cut after milestone 3 if needed.
- **Shared hosting.** Cron granularity (1 min) delays emails by up to a minute; hourly email limits may block
  reminders when many consultants share one host; MariaDB vs MySQL differences; memory for image resizing.
- **Wompi.** Webhooks need a public HTTPS URL (tested through a tunnel locally); each consultant must have their own
  Wompi merchant account.
- **Double booking** under concurrent requests — a lock plus a check in one transaction, covered by a test.
- **Personal data (Ley 1581).** Consent, privacy policy per consultant, anonymisation on request; financial notes are
  sensitive — private by default, owner only.
- **Email deliverability** from a shared host (SPF/DKIM for pontiac.co; Reply-To the consultant).

## Decisions

| Question | Decided | Why |
|---|---|---|
| One consultant or many? | Many (accounts, isolated) | Sold as a product; costly to add later. |
| Client access | Client portal with login | Clients see sessions, plans, payments, shared notes and files. |
| Payment provider | Wompi, keys per consultant | Cards, PSE, Nequi; clean API, signed webhooks; money goes straight to each consultant. |
| Hosting | Shared hosting (cPanel) | User's choice. Queue in a DB table drained by a cron; pure-PHP dependencies; no daemons. |
| Page URLs | Path on one domain: `pontiac.co/<consultant>/<page>` | No per-consultant DNS or certificates on cPanel. |
| Meetings | Meeting link / address in emails + .ics, no calendar sync | Works everywhere; sync is a later phase. |
| Flows | Stages + transitions moved by events or by hand; email on stage entry | Automates the common path without a full automation engine. |
| Page editor | Template sections: edit, toggle, reorder; live preview | Fast, consistent, SEO-clean pages; far less to build than drag-and-drop. |
| Team | Owner + assistants | Assistants run the day-to-day without prices, keys or private notes. |
| Locale | Spanish UI and pages, COP, America/Bogota; English code | The market is Colombia. |
| Release | Everything at once, built in milestones | User's choice; milestones keep each piece tested and merged. |
| Public pages rendering | Server-side Twig, not React | SEO and speed on mobile. |
| Paid plans | Pay first, then book | No slot held for an unpaid visitor; simpler and fairer to the calendar. |
| Lead vs client | Client = has paid at least one plan (price > 0) | Matches the brief: a free diagnostic keeps them a lead. |
| Client identity | Client users unique per account; sign-in at `/<consultant>/portal` | One person can be a client of two consultants. |
| Who creates consultants? | The super admin, from the platform area; no self sign-up in v1 | Pontiac is sold, not self-served, until billing exists. |
| Platform settings | One settings record edited by the super admin; defaults copied to each new account and overridable per account | Changing a default never silently changes an existing consultant. |
| Deleting personal data | Anonymise, never hard-delete | Keeps history and payments consistent while honouring Ley 1581. |
