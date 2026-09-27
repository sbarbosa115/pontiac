# Milestone 4 — Client portal

Part of [prd-pontiac.md](prd-pontiac.md) ("Delivery plan" 4). A client signs in at `/<consultant>/portal` and runs
their side of the consultancy: sessions, plans and payments, shared notes, files and their account.

Decided with the user (2026-09-27): access **on first payment and by hand** (the consultant invites from the
contact's page); the portal pays **plans assigned to the client**; **"¿Olvidaste tu contraseña?"** for clients and
staff; files are **the consultant's shared ones plus the client's own uploads**.

## What people can do

**Client** (`/<consultant>/portal`, behind the *portal* feature)
- *Inicio*: the next session (day, time, join link), each plan's progress ("1 de 2 sesiones"), plans waiting for
  payment.
- *Sesiones*: book a session of a plan with sessions left, at one of the consultant's free slots; move or cancel it
  until the cancellation limit; past sessions.
- *Mis planes*: their plans and payments; **Pagar** a plan waiting for payment (Wompi's checkout).
- *Notas*: the shared notes, by session.
- *Archivos*: download what the consultant shared, upload their own (PDF, images, XLSX, CSV, DOCX, up to the
  account's max file size).
- *Mi cuenta*: theme, change password.

**Consultant (owner and assistant)**
- Contact's *Resumen*: portal access — none, invited, active or turned off; **Invitar al portal**, **Reenviar
  invitación**, **Quitar acceso** / **Devolver acceso**.
- Contact's new *Archivos* tab: upload (shared with the client or internal), download, share or unshare, turn off.
- A client's first paid plan invites them automatically ("Accede a tu portal").

**Everyone who signs in**: "¿Olvidaste tu contraseña?" on both sign-in pages; the emailed link lasts one hour.

## Rules

- A client login belongs to one contact; every portal request reads the contact from the signed-in user, never from
  the request. Another person's ids answer 404.
- Client bookings follow the visitor's rules (free slots, notice, cancellation limit); paid plans only once paid.
- Files are stored outside the web root, type detected from content, counted in the storage limit with the images,
  served only through the API after a scoped lookup. Nothing is deleted: files are turned off.
- Anonymising a contact turns off their portal access.
- A reset request always answers the same (no account discovery), and is rate limited.

## Model

- `User` gains its contact (clients), and a password-reset token hash and expiry.
- `ClientFile` (owned): contact, name, content type, size, uploaded by (and whether by the client), shared, active.
- `BookingSession.bookedBy` gains `client`.

## Out of scope

Messages between client and consultant, notifications inside the portal, a client choosing a new plan by themselves.
