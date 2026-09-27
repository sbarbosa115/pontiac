# Milestone 5 — Flows

Part of [prd-pontiac.md](prd-pontiac.md) ("Delivery plan" 5). The consultant draws the path people follow (a flow of
stages), people move on their own when something happens (they book, pay, finish), the consultant moves them by hand
on a board, and entering a stage can send an email.

Decided with the user (2026-09-27): hand moves go **to any stage**; people enter **from the flow's pages or added by
hand** (several flows at once); stage emails carry a **"no quiero recibir más correos"** link that stops flow emails.

## What people can do

**Consultant (owner and assistant)**, behind the *flows* feature
- *Flujos*: list (name, stages, people, status); **Nuevo flujo** starts from a ready example (Nuevo → Agendado →
  Seguimiento → Cliente → Fin).
- Flow editor: a canvas (React Flow) of stages and arrows. A stage has a name, a kind (inicio, paso, fin), an optional
  email template sent on entry and an optional "alert after N days". An arrow has a trigger: an event (form sent,
  session booked, session done, no-show, payment approved, plan completed, consultancy finished) or *manual*
  (documentation only). The pages that feed the flow are listed (chosen in each page's Ajustes).
- *Prospectos › Tablero*: pick a flow; a column per stage; cards with name, category and days in the stage; drag a
  card to any stage; open the person.
- Contact page: their flows and stages (move, add to a flow, take out) in *Resumen*; a new *Historial* tab with every
  move and every email sent.
- *Ajustes › Correos*: email templates with variables `{nombre}`, `{asesor}`, `{fecha_sesion}`, `{enlace_reserva}`,
  `{enlace_pago}`, `{enlace_portal}`.
- *Inicio*: people waiting in a stage longer than its alert.

**Person**: stage emails from the consultant, with a link to stop them.

## Rules

- An event moves a person along the arrow leaving their current stage with that trigger (one per stage and trigger).
  A form sent or a session booked on a page that feeds a flow first puts them in its start stage.
- Entering a stage sends its email once per entry, unless the person stopped flow emails or was anonymised.
- A stage with people in it cannot be removed; a flow has exactly one start stage.

## Model

`Flow`, `FlowStage`, `FlowTransition`, `ContactFlowState` (unique per person and flow), `FlowEvent` (history),
`EmailTemplate`; `Contact.flowEmailsStoppedAt`; page content `settings.flowId`.

## Out of scope

Waits and conditions, branches by answer, emails to the consultant from flows, analytics per flow.
