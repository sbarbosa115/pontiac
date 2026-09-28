# Milestone 2 — Booking and reminders

Part of [prd-pontiac.md](prd-pontiac.md) ("Delivery plan" 2). A visitor books a session from a page; the consultant
runs their agenda; everyone is reminded before each session.

## What people can do

**Consultant** (`/admin`, behind the *booking* feature)
- *Planes*: what they sell — name, description, price (COP, a decimal string), sessions, minutes per session. Owner
  creates, edits, disables; assistants read.
- *Agenda › Semana*: the week's sessions by day, previous/next week.
- *Agenda › Sesiones*: search (contact name, email), filter by status and dates; mark done, no-show; cancel with a
  reason; reschedule to a free slot; book a session for an existing contact.
- *Agenda › Disponibilidad*: weekly hours, exceptions (a day off, or other hours that day), buffer, minimum notice,
  booking window, client cancellation limit, reminder hours, default meeting link. Starts from the platform's
  defaults (Configuración › Valores por defecto).

**Visitor**
- A page's *Reserva* section (tied to a free plan) lists the free slots of the coming days, grouped by day, with
  name, email, phone and consent. Booking makes them a prospecto with a free enrollment and a session.
- `/<consultant>/reservar/<token>`: their session; reschedule or cancel until the cancellation limit.

**Emails** (queued, tagged, logged): confirmation with an `.ics` and the manage link (visitor); "Nueva sesión agendada"
(owner); rescheduled and cancelled (both, with an updated or cancelling `.ics`); reminders at each reminder hour
(both). A reminder is not sent when the session was booked after its time had passed.

## Model

- `Plan` (owned): name, description, price, currency, sessions, duration, active.
- `Enrollment` (owned): contact, plan (name, price, sessions, duration copied), status (`active` now; payments in 3),
  sessions used, source page. Created for free plans on booking.
- `BookingSession` (owned, table `session`): enrollment, contact, starts/ends (UTC), status `scheduled` → `done` |
  `no_show` | `cancelled`, meeting link, cancel reason, manage-token hash, booked by (visitor/staff).
- `SessionReminder` (owned): session, hours; unique (session, hours) — the claim that makes sending idempotent.
- `Availability` (owned, one per account): weekly rules, exceptions, buffer, notice, window, cancel hours, reminder
  hours, meeting link.
- Page content: a `booking` section type (heading, body, plan). Drafts missing a section the template gained get it
  appended, switched off.

## Rules

- Slots: rules in the account's timezone, every `duration + buffer` minutes, from now + notice to today + window,
  minus exceptions, minus active sessions (with the buffer around them). A booking re-checks under a lock
  (`booking:<account>`) and refuses an overlap (409 `slot_taken`).
- Paid plans are booked after payment (milestone 3): a page's booking section takes free plans only.
- `app:send-due-reminders` (cron every minute; `scheduler` service locally) walks active accounts, enters each, and
  claims every reminder due; the queue sends the emails.
