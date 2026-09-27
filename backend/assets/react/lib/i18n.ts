// UI strings. Spanish first; add another language by adding a dictionary with the same keys.
// Placeholders: {name}. Plurals: { one, other } with "#" replaced by the first numeric parameter.
// The API's error codes are translated here too ("errors.<code>"): every new code the API can answer gets a line.

/** A string, or its plural forms: `{ one, other }`, "#" standing for the count. */
type Message = string | (Partial<Record<Intl.LDMLPluralRule, string>> & { other: string });

const es: Record<string, Message> = {
    // Common
    'common.working': 'Procesando…',
    'common.writeEmail': 'Escribir',
    'common.optional': 'opcional',
    'common.close': 'Cerrar',
    'common.retry': 'Reintentar',
    'common.loading': 'Cargando…',
    'common.search': 'Buscar…',
    'common.searchLabel': 'Buscar',
    'common.rowLegend': 'Color de la fila:',
    'common.showAll': 'Ver todos',
    'common.pickDate': 'Elegir en el calendario',
    'common.datePart.day': 'dd',
    'common.datePart.month': 'mm',
    'common.datePart.year': 'aaaa',
    'common.previous': 'Anterior',
    'common.next': 'Siguiente',
    'common.pageOf': 'Página {page} de {pages}',
    'common.cancel': 'Cancelar',
    'common.save': 'Guardar',
    'common.menu': 'Menú',
    'common.closeMenu': 'Cerrar menú',
    'common.logout': 'Cerrar sesión',
    'common.disable': 'Desactivar',
    'common.enable': 'Activar',
    'common.actions': 'Acciones',

    'money.hint': 'Ejemplo: {example}',

    // Navigation
    'nav.label': 'Menú principal',
    'nav.home': 'Inicio',
    'nav.settings': 'Ajustes',
    'nav.section.platform': 'Plataforma',
    'nav.section.practice': 'Mi práctica',
    'nav.section.portal': 'Mi asesoría',

    'roles.superAdmin': 'Administrador de Pontiac',
    'roles.owner': 'Asesor',
    'roles.assistant': 'Asistente',
    'roles.client': 'Cliente',

    'theme.label': 'Tema',
    'theme.light': 'Claro',
    'theme.dark': 'Oscuro',
    'theme.system': 'Según el dispositivo',
    'theme.saveFailed': 'No pudimos guardar tu tema; lo verás solo en este dispositivo.',

    'impersonation.label': 'Actuar como',
    'impersonation.none': 'Nadie (mi cuenta de plataforma)',
    'impersonation.role.owner': 'asesor',
    'impersonation.role.assistant': 'asistente',
    'impersonation.roleOwner': 'asesor',
    'impersonation.roleAssistant': 'asistente',
    'impersonation.banner': 'Estás actuando como {name} ({role} de {account}). Todo lo que hagas quedará registrado en su cuenta.',
    'impersonation.exit': 'Volver a la plataforma',

    // Signing in
    'login.title': 'Ingresar',
    'login.staffSubtitle': 'Para asesores y su equipo',
    'login.email': 'Correo electrónico',
    'login.password': 'Contraseña',
    'login.submit': 'Ingresar',
    'login.clientHint': '¿Eres cliente de un asesor? Ingresa desde el enlace de tu portal que te llegó por correo.',

    'portalLogin.title': 'Tu portal de cliente',
    'portalLogin.subtitle': 'Tus sesiones, tus planes y tus pagos',
    'portalLogin.noAccess': '¿Aún no tienes contraseña? Tu asesor te envía el acceso por correo cuando empiezas un plan.',

    'invitation.title': 'Hola, {name}',
    'invitation.subtitle': 'Crea tu contraseña para ingresar.',
    'invitation.password': 'Nueva contraseña',
    'invitation.passwordHint': 'Mínimo {min} caracteres.',
    'invitation.confirmPassword': 'Repite la contraseña',
    'invitation.submit': 'Crear contraseña',
    'invitation.passwordTooShort': 'La contraseña debe tener al menos {min} caracteres.',
    'invitation.passwordMismatch': 'Las contraseñas no coinciden.',
    'invitation.done': 'Tu contraseña quedó lista. Ya puedes ingresar.',
    'invitation.invalidTitle': 'Enlace no válido',
    'invitation.invalidBody': 'Este enlace ya fue usado o venció. Pide una nueva invitación.',
    'invitation.goToLogin': 'Ir a ingresar',

    'notFound.title': 'No encontramos esta página',
    'notFound.body': 'Revisa la dirección o vuelve al inicio.',
    'notFound.home': 'Ir al inicio',

    // Homes
    'adminHome.title': 'Hola, {name}',
    'adminHome.publicAddress': 'Tu dirección en Pontiac',
    'adminHome.publicAddressHint': 'Aquí estarán tus páginas. Mientras no publiques ninguna, muestra solo tu nombre.',
    'platformHome.title': 'Hola, {name}',
    'platformHome.subtitle': 'Administración de Pontiac',
    'platformHome.actAsHint': 'Para dar soporte, elige a un asesor o asistente en «Actuar como»: verás Pontiac como lo ve esa persona.',
    'portalHome.title': 'Hola, {name}',
    'portalHome.soon': 'Muy pronto verás aquí tus sesiones, tus planes y las notas que tu asesor comparta contigo.',

    // Ajustes
    'settings.subtitle': 'Lo que define cómo funciona tu práctica.',
    'settings.tab.equipo': 'Equipo',

    'team.intro': 'Quienes trabajan contigo en Pontiac. Tus asistentes atienden prospectos, agenda y pagos; los precios, las llaves de pago y el equipo solo los cambias tú.',
    'team.searchPlaceholder': 'Buscar por nombre o correo',
    'team.invite': 'Invitar asistente',
    'team.sendInvitation': 'Enviar invitación',
    'team.fullName': 'Nombre',
    'team.email': 'Correo',
    'team.emailHint': 'Le llegará un enlace para crear su contraseña.',
    'team.role': 'Rol',
    'team.lastSignIn': 'Último ingreso',
    'team.neverSignedIn': 'Nunca',
    'team.roles.owner': 'Asesor',
    'team.roles.assistant': 'Asistente',
    'team.status.active': 'Con acceso',
    'team.status.invited': 'Invitación enviada',
    'team.status.inactive': 'Desactivado',
    'team.resendInvitation': 'Reenviar invitación',
    'team.invitationSent': 'Enviamos la invitación a {email}.',
    'team.confirmDisable': '¿Desactivar a {name}? No podrá ingresar hasta que lo actives de nuevo.',
    'team.empty': 'Nadie de tu equipo coincide con la búsqueda.',
    'team.emptyAll': 'Aún no tienes asistentes. Invita a quien te ayuda con prospectos, agenda y pagos.',

    // API error codes
    'errors.generic': 'No pudimos completar la acción. Intenta de nuevo.',
    'errors.network_error': 'No hay conexión con el servidor. Revisa tu internet.',
    'errors.internal_error': 'Ocurrió un error inesperado. Intenta de nuevo.',
    'errors.validation_failed': 'Revisa los campos marcados.',
    'errors.bad_request': 'La solicitud no es válida.',
    'errors.invalid_json': 'La solicitud no es válida.',
    'errors.invalid_filter': 'El filtro no es válido.',
    'errors.unauthorized': 'Ingresa para continuar.',
    'errors.session_expired': 'Tu sesión expiró. Vuelve a ingresar.',
    'errors.invalid_credentials': 'Correo o contraseña incorrectos.',
    'errors.forbidden': 'No tienes permiso para esta acción.',
    'errors.not_found': 'No encontramos lo que buscabas.',
    'errors.payload_too_large': 'El archivo es demasiado grande.',
    'errors.too_many_requests': 'Demasiados intentos. Espera unos minutos.',
    'errors.too_many_attempts': 'Demasiados intentos. Espera unos minutos.',
    'errors.email_failed': 'No pudimos enviar el correo. Intenta más tarde.',
    'errors.email_in_use': 'Ese correo ya tiene una cuenta en Pontiac.',
    'errors.user_disabled': 'Activa a esta persona antes de invitarla de nuevo.',
    'errors.already_has_access': 'Esta persona ya creó su contraseña.',
    'errors.owner_cannot_be_disabled': 'El asesor no se puede desactivar de su propio equipo.',
};

export const messages = es;
const pluralRules = new Intl.PluralRules('es');

export function t(key: string, params: Record<string, string | number | null | undefined> = {}): string {
    const message = messages[key];
    if (message === undefined) {
        return key;
    }
    let template: string;
    if (typeof message === 'object') {
        const count = Object.values(params).find((value): value is number => typeof value === 'number') ?? 0;
        template = (message[pluralRules.select(count)] ?? message.other).replace('#', String(count));
    } else {
        template = message;
    }
    return template.replace(/\{(\w+)\}/g, (match, name: string) => (name in params ? String(params[name]) : match));
}

/**
 * For an action in a table row, where no field can show a validation error: the API's first reason (it answers
 * in the request's language), else the message for its error code.
 */
export function rowErrorMessage(error: unknown): string {
    const { violations } = (error ?? {}) as { violations?: { message: string }[] };
    return violations?.[0]?.message || errorMessage(error);
}

/** What to tell the user about a failed request: by the API's error code, else by the kind of failure. */
export function errorMessage(error: unknown): string {
    const { code, status } = (error ?? {}) as { code?: string; status?: number };
    if (code && typeof messages[`errors.${code}`] === 'string') return t(`errors.${code}`);
    if (status === 0) return t('errors.network_error');
    if (status !== undefined && status >= 500) return t('errors.internal_error');
    return t('errors.generic');
}
