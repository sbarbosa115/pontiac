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
