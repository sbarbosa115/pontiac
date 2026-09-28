# UI regression pass

The manual browser pass, one case per use case. Run it on a fresh stack with the demo data (`app:seed-demo`, README
"Demo accounts"), in **Claro** and in **Oscuro**, and check the browser console for errors on every screen.

| Id | Who | Steps | Expected |
|---|---|---|---|
| AUTH-01 | anyone | `/login`, a demo email with a wrong password | "Correo o contraseña incorrectos." above the form |
| AUTH-02 | consultant | `/login` as asesor@pontiac.test | Lands on `/admin`, "Hola, Andrés Asesor", menu Inicio · Ajustes |
| AUTH-03 | client | Signed out, open `/finanzas-claras/portal` | Sent to `/finanzas-claras/portal/ingresar` |
| AUTH-04 | client | Sign in as cliente@pontiac.test there | Lands on the portal, "Hola, Carlos Cliente"; typing `/admin` goes back to the portal |
| AUTH-05 | anyone | `/nadie/portal` | 404 page from the server |
| TEAM-01 | consultant | Ajustes › Equipo | The consultant first, then assistants; row colours match the legend; the consultant's row has no switch |
| TEAM-02 | consultant | Search "zzz" | "Nadie de tu equipo coincide con la búsqueda." with "Ver todos" |
| TEAM-03 | consultant | Invitar asistente, empty name and "no-es-un-correo", send | Both fields show their error in Spanish; the modal stays open |
| TEAM-04 | consultant | Invite with a real name and email | Notice "Enviamos la invitación a …", the row shows "Invitación enviada" and "Reenviar invitación"; the email is in Mailpit |
| TEAM-05 | invited assistant | Open the Mailpit link, a short password, then a good one | The short one is refused; the good one leads to `/login` with "Tu contraseña quedó lista." and the email filled in |
| TEAM-06 | consultant | Disable an assistant (confirm), then enable | Row greys out, then comes back |
| TEAM-07 | assistant | Ajustes › Equipo as asistente@pontiac.test | The same list with no "Acciones" column and no invite button |
| THEME-01 | anyone | Sidebar › Tema › Oscuro, reload | Stays dark with no white flash; follows the person to another browser |
| PLAT-01 | super admin | `/login` as admin@pontiac.test | Lands on `/plataforma` in their own theme |
| PLAT-02 | super admin | "Actuar como" › Andrés Asesor | Goes to `/admin` with the banner "Estás actuando como Andrés Asesor (asesor de Finanzas Claras)…"; "Volver a la plataforma" ends it |
| PUB-01 | anyone | `/` and `/finanzas-claras` | Server-rendered pages; the consultant's says "Muy pronto…" and is `noindex` |
| PLAT-03 | super admin | Inicio | Four figures (active, suspended, failed emails, failed jobs); each linked one opens its list filtered |
| PLAT-04 | super admin | Asesores › Nuevo asesor, type a name | The address follows the name until edited; a taken or reserved address is refused under its field |
| PLAT-05 | super admin | Create with a free address | Notice "Creamos a … y enviamos la invitación a …"; the row shows "Invitación enviada" and "Reenviar invitación"; the email is in Mailpit with the subject "Tu cuenta de asesor en Pontiac está lista" |
| PLAT-06 | super admin | Open a consultant › Datos, change the name, save | "Guardado."; the header shows the new name |
| PLAT-07 | super admin | Límites y funciones: assistants −1, save; then 0 and the portal off | −1 refused under its field; then "Guardado." |
| PLAT-08 | super admin | The consultant's Usuarios and Correos tabs | Owner and assistants (no clients); the invitation email listed |
| PLAT-09 | super admin | Suspend a consultant (confirm), then reactivate | Row turns red and greys; its people cannot sign in and `/<slug>` answers 404 until reactivated |
| PLAT-10 | super admin | Configuración › General, change the sender, save; then Historial | "Guardado."; Historial shows "Remitente: Pontiac → …" with who and when |
| PLAT-11 | super admin | Configuración › every tab | Each renders its form or list; the tab bar scrolls on a narrow screen |
| PLAT-12 | super admin | Configuración › Administradores | Own row says "tú" and has no switch; inviting sends an email |
| PLAT-13 | super admin | Correos › Enviar correo de prueba | Notice, and the email appears first in the list as "Prueba" |
| PLAT-14 | consultant | With the assistant limit reached, Invitar asistente | "Llegaste al máximo de asistentes activos de tu plan…" in the dialog |
| PLAT-15 | client | The consultant's portal turned off, sign in at the portal | "Esta función no está activa para este asesor." |
| PAGE-01 | visitor | `/finanzas-claras` | The published home page: one h1, the form, a footer link to the privacy policy; no framework JavaScript (one small inline script) |
| PAGE-02 | visitor | Send the form without consent | "Para enviar tus datos debes aceptar…" under the checkbox; what was typed stays; reloading goes back to the page |
| PAGE-03 | visitor | Send it with consent (after a few seconds) | The page says thanks; the owner gets "Nuevo prospecto" in Mailpit with the answers |
| PAGE-04 | consultant | Prospectos | The new contact, in the category their answer mapped to; filters by category and page; the contact's page shows consent and answers |
| PAGE-05 | consultant | Páginas › Nueva página | The address follows the title; creating opens the editor with the template's sections |
| PAGE-06 | consultant | Editor › Contenido, change the hero title | The preview updates; "Guardar borrador" turns on; the live page does not change until "Publicar" |
| PAGE-07 | consultant | Empty a required field, save | Error under the field, the section card red, a dot on its tab, the preview waits |
| PAGE-08 | consultant | Hero › Elegir imagen › Subir imágenes | The image is chosen, shown in the preview at its WebP widths, and live after publishing |
| PAGE-09 | consultant | Editor › Formulario, a list question with options mapped to categories | Saved; a visitor's answer sorts them into that category |
| PAGE-10 | consultant | Editor › SEO; › Ajustes (address, home page, colour) | Search preview updates; a published page's new address leaves a 301 from the old one |
| PAGE-11 | consultant | Editor in Oscuro, preview on Celular | Editor dark, the page light, 390 px wide |
| PAGE-12 | owner | Páginas › Retirar | Visitors get "Esta página ya no está disponible" (410); "Volver a publicar" brings it back |
| PAGE-13 | owner | A contact › Eliminar sus datos | Name, email, phone and answers erased; assistants do not see the button |
| PAGE-14 | consultant | Ajustes › Categorías, Medios, Privacidad | Each lists and edits; privacy says when the default text is used |
| LAND-01 | consultant | Páginas › Nueva › each of the 5 templates, publish, open it (desktop) | Hero in the accent colour with its tag, title, button and trust points; cards, numbered steps, FAQ cards with +/−, the cta band, the form in a card beside its points; sections come in as you scroll |
| LAND-02 | visitor | Same pages at phone width (390 px) | Nothing wider than the screen; the header button hides; past the hero a bottom bar shows the page's button, and hides while the hero, the cta band or the form is on screen |
| LAND-03 | visitor | Every button of a page (header, hero, band, bottom bar) | All lead to the same section: the booking when it is on and above the form, else the form (or the payment) |
| LAND-04 | visitor | Send the form with mistakes | The page opens at the form, errors in red under their fields, the cursor in the first one |
| LAND-05 | consultant | Editor › Contenido: fill Etiqueta and Puntos de confianza (a 5th is refused), turn on Llamado a la acción and move it first, save, Vista previa | The hints show under the fields; the preview follows the new order with one h1; the band's button leads to the form |
| LAND-06 | consultant | Editor › Contenido: turn off Formulario and Reserva | The preview shows no buttons at all; the band keeps its words |
| LAND-07 | visitor | `/finanzas-claras/plan-2-sesiones#precios` straight from an email link | Opens at the plans, just below the header |
| BOOK-01 | visitor | `/finanzas-claras`, Reserva: the calendar opens on the first free day; "›" to the next month, pick a day, then a time, name, email, consent, "Reservar mi diagnóstico" | "¡Listo! Tu sesión quedó reservada."; Mailpit: the confirmation with `sesion.ics` and a manage link, and "Nueva sesión agendada" to the owner |
| BOOK-02 | visitor | The same slot again from another browser | "Esa hora ya no está disponible. Elige otra." and what was typed stays |
| BOOK-03 | visitor | The manage link, a session more than 24 h ahead: pick another time, "Cambiar a esta hora" | "Listo: tu sesión quedó en la nueva hora"; the old link answers 404; both get "cambió de hora" |
| BOOK-04 | visitor | The manage link: cancel with a reason | "Tu sesión quedó cancelada"; no forms left; the owner gets "Sesión cancelada por el cliente" |
| BOOK-05 | visitor | The manage link of a session less than 24 h ahead | Its summary and "Ya no es posible cambiarla o cancelarla en línea…", no forms |
| BOOK-06 | consultant | Agenda › Semana, Siguiente | Worded actions (Reprogramar, Reunión, Cancelar) on each session; next week's sessions by day in Bogotá time; today's column outlined; "Esta semana" comes back |
| BOOK-07 | consultant | Agenda › Sesiones: "Pasadas" with none; "Ver todos" | Empty state with "Ver todos"; then every session, rows tinted by status as the legend says |
| BOOK-08 | consultant | Reprogramar with no time, then with one | "Elige un día y una hora." under Hora; then the row shows the new time and the person gets an email |
| BOOK-09 | consultant | Cancelar, with a reason | Row greys with the reason; only "Ver persona" is left |
| BOOK-10 | consultant | A past scheduled session: Realizada, then Volver a agendada | Row turns green, then blue again |
| BOOK-11 | consultant | Agendar sesión with nothing chosen; then a contact, a day and a time | Errors under Persona and Hora; then "Tu equipo" in the list |
| BOOK-12 | consultant | Agenda › Disponibilidad: a range ending before it starts, save; fix it and add a meeting link | "El final debe ser después del inicio." under it; then "Disponibilidad guardada."; new bookings show the meeting icon |
| BOOK-13 | owner | Planes › Nuevo plan empty; then 0 as price | Errors under Nombre and Precio; a price of 0 shows "Gratuito" |
| BOOK-14 | assistant | Planes | The list without Acciones or "Nuevo plan"; Agenda works as for the owner |
| BOOK-15 | consultant | A contact's page | Their sessions with actions and "Agendar sesión"; the form history shows "Sesión reservada" |
| BOOK-16 | consultant | Páginas › the home page › Reserva | "Plan que se reserva" offers free plans only |
| BOOK-17 | super admin | Turn off a consultant's booking | Their Agenda leaves the menu; their pages hide the Reserva section |
| PAY-01 | visitor | `/finanzas-claras/plan-2-sesiones`, Precios y pago: send it empty | Errors under name, email and consent, in Spanish |
| PAY-02 | visitor | Fill it in and "Pagar con Wompi" | Sent to `checkout.wompi.co/p/` with the reference, amount in cents and signature (with the demo keys Wompi shows an error: that is expected) |
| PAY-03 | anyone | `app:wompi:simulate-event <reference>`, then `/finanzas-claras/pago/<reference>` | "¡Pago aprobado!"; Mailpit: "Recibimos tu pago" to the person and "Nuevo pago" to the owner; the person is a Cliente |
| PAY-04 | anyone | `…/pago/<reference>` of a payment still pending | "Estamos confirmando tu pago", the page refreshes itself |
| PAY-05 | owner | A contact › Planes y pagos › Asignar plan, a paid one | Amber row "Esperando pago" with Registrar pago · Copiar enlace · Reenviar enlace · Cancelar; the person gets "Tu plan con …" with the link |
| PAY-06 | owner | Registrar pago (transferencia, a note) | Green row, the payment in the history by day with "Registrado por", the person becomes Cliente |
| PAY-07 | assistant | The same contact | No "Registrar pago"; the rest as the owner |
| PAY-08 | consultant | A used-up plan: Renovar with another plan; another: Finalizar asesoría | "Renovado" under the old one and a new plan; "Asesoría finalizada" and the person Finalizado |
| PAY-09 | consultant | Sesiones › Agendar sesión | The person's paid plans with sessions left come first, oldest first, then free plans |
| PAY-10 | owner | Notas on a session: empty, then a private and a shared note | "Escribe la nota."; both listed with who reads them |
| PAY-11 | assistant | Notas on the same session | Only the shared note, without "Editar"; their own notes are shared |
| PAY-12 | consultant | Pagos: "Pendiente" with none, "Ver todos" | Empty state, then every payment tinted by status |
| PAY-13 | owner | Ajustes › Pagos Wompi: a bad public key; Probar conexión with the demo key | The error under the field; "Wompi no reconoce esta llave pública…" |
| PAY-14 | assistant | Ajustes | No "Pagos Wompi" tab; `?tab=pagos` shows Equipo |
| PAY-15 | owner | Páginas › plan-2-sesiones › Precios y pago | "Planes que se pagan aquí" lists paid plans, at most three ticked |
| PORT-01 | client | `/finanzas-claras/portal/ingresar` as cliente@pontiac.test | Inicio: the next session with "Entrar a la sesión" and "Cambiar o cancelar", Plan A "1 de 2 sesiones realizadas" with its bar |
| PORT-02 | client | Mis sesiones › Reprogramar a session more than 24 h ahead | "Tu sesión quedó en la nueva hora…"; the consultant and the client get the emails |
| PORT-03 | client | Mis sesiones with every session of their plans taken | "Para agendar necesitas un plan activo…", no "Agendar sesión"; past sessions without an Acciones column |
| PORT-04 | client | Notas | The shared note only, under its session |
| PORT-05 | client | Archivos › Subir archivos (a PDF); then a `.txt` renamed `.pdf` | "Archivos subidos." and the file as "Tú"; the fake one refused in Spanish |
| PORT-06 | client | Mis planes with a plan assigned and waiting for payment | "Pagar" goes to Wompi's checkout (with demo keys Wompi shows an error: expected) |
| PORT-07 | client | Mi cuenta, a wrong current password, in Oscuro | "Esa no es tu contraseña actual." under the field |
| PORT-08 | client | ¿Olvidaste tu contraseña? at the portal, the emailed link, a new password | Mailpit: "Cambia tu contraseña" from the consultant; the link lands on the portal's sign-in with "Tu contraseña cambió" |
| PORT-09 | consultant | A contact › Resumen › Portal de cliente: Invitar al portal | "Invitación enviada"; the person gets "… te dio acceso a tu portal de cliente" |
| PORT-10 | consultant | Quitar acceso, then Devolver acceso | "Acceso quitado" and they cannot sign in; then "Con acceso" |
| PORT-11 | consultant | A contact › Archivos: upload with "Compartir con la persona" off | Row "Interno" with "Compartir"; the client does not see it |
| PORT-12 | consultant | The client's own upload in Archivos | "(desde su portal)", always shared (no "Dejar de compartir") |
| PORT-13 | staff | `/login` › ¿Olvidaste tu contraseña? | Same flow; the link lands on `/login` |
| FLOW-01 | consultant | Flujos › Nuevo flujo, empty; then a name | "Ponle nombre al flujo." under Nombre; then the editor with the ready example (Nuevo → … → Finalizado) |
| FLOW-02 | consultant | Editor: click a stage, empty its name, Guardar cambios | "Revisa los campos marcados."; the stage red on the canvas with the reason in its panel |
| FLOW-03 | consultant | Editor: drag from a stage's bottom point to another; pick "Pago aprobado"; Guardar | The arrow labelled with its event; "Guardado."; after a reload it is still there |
| FLOW-04 | consultant | Editor: a stage with people | "Quitar etapa" disabled, "Mueve a las personas…" under it |
| FLOW-05 | consultant | Prospectos › Tablero | A column per stage; a card past its stage's alert amber with "lleva más de lo esperado" |
| FLOW-06 | consultant | Drag a card to "Sesión agendada" (or "Mover a…" under it) | The card moves; Mailpit: the stage's email with "¿No quieres recibir más correos…?" and its link |
| FLOW-07 | person | The email's link, "No quiero recibir más correos" | "Listo: no te enviaremos más correos de seguimiento…"; moving them again sends nothing |
| FLOW-08 | consultant | A contact › Resumen › Flujos: change the stage; Sacar del flujo; Agregar a un flujo | Each change shows at once; Historial lists every move with who made it and the emails sent |
| FLOW-09 | consultant | Inicio with someone past a stage's alert | "Personas esperando en una etapa" with Tablero and Ver |
| FLOW-10 | consultant | Páginas › a page › Ajustes › Flujo que alimenta; send its form | The person appears in the flow's start stage |
| FLOW-11 | consultant | Ajustes › Correos › Nuevo correo empty; then filled | Errors under Nombre, Asunto and Mensaje; the variables listed; the new email in the stage's "Correo al entrar" |
| FLOW-12 | super admin | Turn off a consultant's flows | Flujos, Tablero, Historial, the contact's Flujos and Ajustes › Correos disappear |
