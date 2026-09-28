# Milestone 3 — Payments and clients

Part of [prd-pontiac.md](prd-pontiac.md) ("Delivery plan" 3). A visitor pays for a plan with Wompi, the consultant
assigns plans and records payments, a person who pays becomes a *cliente*, sessions get notes, and a finished plan
is renewed or the consultancy closed.

Decided with the user (2026-09-27): **Wompi Web Checkout** (a redirect, no JavaScript on our pages); the portal
invitation on first payment comes **with milestone 4**; Wompi is **simulated locally** (a signed-event command and a
fake API in tests), real sandbox keys are tried by the user.

## What people can do

**Owner** (behind the *payments* feature where it is about money)
- *Ajustes › Pagos Wompi*: the four keys (public key shown, secrets write-only: "termina en …"), test or production
  (from the keys), the events URL to paste in Wompi, **Probar conexión**.
- Record a **manual payment** (efectivo, transferencia, otro, with a note) for a plan waiting for payment.

**Owner and assistant**
- Contact page in tabs: *Resumen* (as today), *Planes y pagos* (their plans with sessions used, payments; **Asignar
  plan**, **Copiar enlace de pago**, **Reenviar enlace**, **Cancelar** a plan waiting for payment, and for a finished
  plan **Renovar** or **Finalizar asesoría**), *Sesiones* (their sessions, with notes).
- **Agendar sesión** books a free plan or one of the person's paid plans with sessions left.
- *Pagos*: every payment, searched by person or reference, by status and dates; rows tinted by status.
- Session **notes**: shared (the client will read them in the portal, milestone 4) or private (owner only; an
  assistant neither sees nor writes them).

**Visitor**
- A page's *Precios y pago* section (plan and event templates) shows one to three paid plans; they pick one, give
  name, email, phone and consent, and go to Wompi's checkout. They come back to `/<consultant>/pago/<reference>`,
  which asks Wompi for the result (never trusting the URL) and refreshes itself while it is pending.
- A payment link (`/<consultant>/pagar/<token>`, emailed when a plan is assigned) shows the plan and a **Pagar con
  Wompi** button.

**Emails**: "Tu plan con …" with the payment link (person), "Recibimos tu pago" (person), "Nuevo pago" (owner).

## Rules

- A payment is approved by Wompi's signed event (`POST /webhooks/wompi/<account id>`, checksum with the events
  secret) or by asking Wompi for the transaction (result page), after checking reference, amount and currency; a
  mismatch marks it `error`. Applying a result is idempotent.
- Approval: payment `approved` → its plan `active` → the person becomes `client` (only a paid plan does that).
- A plan is `completed` when its sessions are used (done or no-show); reopening a session reopens it.
- Staff book a paid plan's session only while it is active and has sessions left (409 `no_sessions_left`).
- Wompi secrets are encrypted with libsodium (`APP_ENCRYPTION_KEY`), never returned by the API.

## Model

- `WompiSettings` (owned, one per account): public key, encrypted private key, events secret, integrity secret.
- `Payment` (owned): enrollment, contact, reference (ours, unique), amount, currency, status `pending` →
  `approved` | `declined` | `voided` | `error`, method, Wompi transaction id, last event, manual (+ note, recorded
  by), created and paid at.
- `Enrollment` gains a payment token, an outcome (`renewed` | `finished`) and a completion time.
- `SessionNote` (owned): session, author, body, visibility `private` | `shared`.
- Page content: a `payment` section type (heading, body, 1–3 paid plans, button label, note).

## Out of scope

The portal invitation and client self-booking (milestone 4), refunds (the Wompi dashboard), invoices, installments.
