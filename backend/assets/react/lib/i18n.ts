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
    'common.view': 'Ver',
    'common.saved': 'Guardado.',

    'money.hint': 'Ejemplo: {example}',

    // Navigation
    'nav.label': 'Menú principal',
    'nav.home': 'Inicio',
    'nav.settings': 'Ajustes',
    'nav.section.platform': 'Plataforma',
    'nav.section.practice': 'Mi práctica',
    'nav.section.portal': 'Mi asesoría',
    'nav.consultants': 'Asesores',
    'nav.platformSettings': 'Configuración',
    'nav.emails': 'Correos',

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
    'platformHome.activeAccounts': { one: 'Asesor activo', other: 'Asesores activos' },
    'platformHome.suspendedAccounts': { one: 'Asesor suspendido', other: 'Asesores suspendidos' },
    'platformHome.failedEmails': { one: 'Correo fallido en 7 días', other: 'Correos fallidos en 7 días' },
    'platformHome.failedJobs': { one: 'Tarea fallida en la cola', other: 'Tareas fallidas en la cola' },
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
    'team.emptyAllPlatform': 'Este asesor aún no tiene equipo.',

    // Plataforma › Asesores
    'consultants.subtitle': 'Cada asesor es una cuenta con sus páginas, clientes y equipo, aislada de las demás.',
    'consultants.searchPlaceholder': 'Buscar por nombre, dirección o correo del asesor',
    'consultants.status': 'Estado',
    'consultants.statusAll': 'Todos',
    'consultants.statusName.active': 'Activo',
    'consultants.statusName.suspended': 'Suspendido',
    'consultants.new': 'Nuevo asesor',
    'consultants.createAndInvite': 'Crear e invitar',
    'consultants.name': 'Nombre de la práctica',
    'consultants.slug': 'Dirección',
    'consultants.slugHint': 'Sus páginas quedarán en {host}/{slug}',
    'consultants.ownerName': 'Nombre del asesor',
    'consultants.ownerEmail': 'Correo del asesor',
    'consultants.ownerEmailHint': 'Le llegará la invitación para crear su contraseña.',
    'consultants.owner': 'Asesor',
    'consultants.noOwner': 'Sin asesor',
    'consultants.people': 'Personas',
    'consultants.assistantsCount': { one: '# asistente', other: '# asistentes' },
    'consultants.clientsCount': { one: '# cliente', other: '# clientes' },
    'consultants.createdAt': 'Creado',
    'consultants.actAs': 'Actuar como',
    'consultants.suspend': 'Suspender',
    'consultants.reactivate': 'Reactivar',
    'consultants.confirmSuspend': '¿Suspender a {name}? Nadie de su cuenta podrá ingresar y sus páginas dejarán de verse hasta que lo reactives.',
    'consultants.created': 'Creamos a {name} y enviamos la invitación a {email}.',
    'consultants.empty': 'Ningún asesor coincide con la búsqueda.',
    'consultants.emptyAll': 'Aún no hay asesores. Crea el primero: recibirá un correo para empezar.',

    'consultant.tab.datos': 'Datos',
    'consultant.tab.limites': 'Límites y funciones',
    'consultant.tab.usuarios': 'Usuarios',
    'consultant.tab.correos': 'Correos',
    'consultant.dataIntro': 'Cómo se llama, dónde están sus páginas y cómo se muestran el dinero y las fechas.',
    'consultant.slugChangeHint': 'Si la cambias, sus enlaces anteriores dejan de funcionar.',
    'consultant.country': 'País',
    'consultant.countryHint': 'Código de 2 letras, por ejemplo CO.',
    'consultant.currency': 'Moneda',
    'consultant.currencyHint': 'Código de 3 letras, por ejemplo COP.',
    'consultant.locale': 'Idioma y formato',
    'consultant.locales.es_CO': 'Español (Colombia)',
    'consultant.locales.es_MX': 'Español (México)',
    'consultant.locales.es_PE': 'Español (Perú)',
    'consultant.locales.es_CL': 'Español (Chile)',
    'consultant.locales.es_ES': 'Español (España)',
    'consultant.locales.en_US': 'Inglés (Estados Unidos)',
    'consultant.timezone': 'Zona horaria',
    'consultant.timezoneHint': 'Por ejemplo America/Bogota.',
    'consultant.limitsIntro': 'Lo que puede usar este asesor. Cambiarlo no afecta a los demás.',
    'consultant.features': 'Funciones',
    'consultant.usersIntro': 'El asesor y sus asistentes. Para dejar sin acceso al asesor, suspende su cuenta.',
    'consultant.emailsIntro': 'Los correos que Pontiac envió por este asesor.',

    'limits.maxPublishedPages': 'Páginas publicadas',
    'limits.maxAssistants': 'Asistentes activos',
    'limits.storageMb': 'Almacenamiento (MB)',
    'limits.maxFileMb': 'Tamaño máximo de archivo (MB)',

    'features.booking': 'Agenda',
    'features.bookingHint': 'Reservas desde sus páginas, horarios y recordatorios.',
    'features.payments': 'Pagos',
    'features.paymentsHint': 'Cobros con Wompi y planes pagos.',
    'features.portal': 'Portal de clientes',
    'features.portalHint': 'Sus clientes ingresan a ver sesiones, planes y notas. Apagado, no pueden ingresar.',
    'features.flows': 'Flujos',
    'features.flowsHint': 'Etapas de prospectos y clientes, con correos automáticos.',

    // Plataforma › Configuración
    'platformSettings.subtitle': 'Los ajustes de Pontiac y los valores con los que empieza cada asesor nuevo.',
    'platformSettings.tab.general': 'General',
    'platformSettings.tab.plantillas': 'Plantillas',
    'platformSettings.tab.valores': 'Valores por defecto',
    'platformSettings.tab.limites': 'Límites',
    'platformSettings.tab.funciones': 'Funciones',
    'platformSettings.tab.legal': 'Legal',
    'platformSettings.tab.administradores': 'Administradores',
    'platformSettings.tab.historial': 'Historial',
    'platformSettings.generalIntro': 'Cómo se presenta Pontiac en sus correos y páginas.',
    'platformSettings.platformName': 'Nombre de la plataforma',
    'platformSettings.senderName': 'Remitente de los correos',
    'platformSettings.senderNameHint': 'El nombre que ven al recibir un correo de la plataforma.',
    'platformSettings.supportEmail': 'Correo de soporte',
    'platformSettings.supportEmailHint': 'Las respuestas a los correos de Pontiac llegan aquí.',
    'platformSettings.templatesIntro': 'Las plantillas con las que un asesor puede crear páginas nuevas. Apagar una no cambia las páginas ya creadas.',
    'platformSettings.bookingIntro': 'Con estos valores empieza la agenda de cada asesor nuevo; cada uno puede cambiarlos después.',
    'platformSettings.reminderHours': 'Recordatorios (horas antes de la sesión)',
    'platformSettings.reminderHoursHint': 'Hasta tres, separados por comas. Por ejemplo: 24, 1.',
    'platformSettings.minNoticeHours': 'Anticipación mínima para reservar (horas)',
    'platformSettings.bookingWindowDays': 'Reservas hasta (días adelante)',
    'platformSettings.clientCancelHours': 'El cliente cancela hasta (horas antes)',
    'platformSettings.sessionBufferMinutes': 'Descanso entre sesiones (minutos)',
    'platformSettings.limitsIntro': 'Los límites con los que empieza cada asesor nuevo. Los asesores existentes conservan los suyos.',
    'platformSettings.featuresIntro': 'Las funciones con las que empieza cada asesor nuevo. Los asesores existentes conservan las suyas.',
    'platformSettings.legalIntro': 'Los textos legales de Pontiac y las direcciones que ningún asesor puede usar.',
    'platformSettings.termsText': 'Términos y condiciones',
    'platformSettings.defaultPrivacyText': 'Política de privacidad por defecto',
    'platformSettings.defaultPrivacyTextHint': 'Cada asesor nuevo empieza con este texto (Ley 1581) y lo ajusta a su práctica.',
    'platformSettings.reservedSlugs': 'Direcciones reservadas',
    'platformSettings.reservedSlugsHint': 'Una por línea. Además de estas, las rutas propias de Pontiac siempre están reservadas.',
    'platformSettings.historyIntro': 'Cada vez que alguien guardó la configuración: qué cambió y quién lo hizo.',
    'platformSettings.historySearch': 'Buscar por persona o ajuste',
    'platformSettings.historyEmpty': 'Ningún cambio coincide con la búsqueda.',
    'platformSettings.historyEmptyAll': 'Nadie ha cambiado la configuración todavía.',
    'platformSettings.changedAt': 'Fecha',
    'platformSettings.changedBy': 'Quién',
    'platformSettings.changes': 'Cambios',
    'platformSettings.field.platformName': 'Nombre de la plataforma',
    'platformSettings.field.supportEmail': 'Correo de soporte',
    'platformSettings.field.senderName': 'Remitente',
    'platformSettings.field.enabledTemplates': 'Plantillas activas',
    'platformSettings.field.reminderHours': 'Recordatorios',
    'platformSettings.field.minNoticeHours': 'Anticipación mínima',
    'platformSettings.field.bookingWindowDays': 'Reservas hasta',
    'platformSettings.field.clientCancelHours': 'Cancelación del cliente',
    'platformSettings.field.sessionBufferMinutes': 'Descanso entre sesiones',
    'platformSettings.field.defaultMaxPublishedPages': 'Páginas publicadas',
    'platformSettings.field.defaultMaxAssistants': 'Asistentes activos',
    'platformSettings.field.defaultStorageMb': 'Almacenamiento',
    'platformSettings.field.defaultMaxFileMb': 'Tamaño máximo de archivo',
    'platformSettings.field.defaultFeatures': 'Funciones por defecto',
    'platformSettings.field.termsText': 'Términos y condiciones',
    'platformSettings.field.defaultPrivacyText': 'Política de privacidad',
    'platformSettings.field.reservedSlugs': 'Direcciones reservadas',

    'templates.free_diagnostic': 'Diagnóstico gratuito',
    'templates.free_diagnosticHint': 'Para agendar una primera sesión sin costo.',
    'templates.plan_offer': 'Plan o paquete',
    'templates.plan_offerHint': 'Para vender un plan de sesiones con pago en línea.',
    'templates.consultant_profile': 'Perfil del asesor',
    'templates.consultant_profileHint': 'Quién es el asesor, su experiencia y sus servicios.',
    'templates.event': 'Evento o taller',
    'templates.eventHint': 'Una fecha, una agenda y cupos.',
    'templates.lead_magnet': 'Recurso descargable',
    'templates.lead_magnetHint': 'Un archivo a cambio de los datos del visitante.',

    'admins.intro': 'Quienes administran Pontiac. Solo pueden invitar a otros administradores; nadie se puede desactivar a sí mismo.',
    'admins.invite': 'Invitar administrador',
    'admins.you': 'tú',
    'admins.confirmDisable': '¿Desactivar a {name}? No podrá ingresar a la plataforma hasta que lo actives de nuevo.',
    'admins.empty': 'Ningún administrador coincide con la búsqueda.',

    // Plataforma › Correos
    'emails.subtitle': 'Cada correo que Pontiac intentó enviar, y si lo logró.',
    'emails.searchPlaceholder': 'Buscar por destinatario, asunto o asesor',
    'emails.searchPlaceholderAccount': 'Buscar por destinatario o asunto',
    'emails.status': 'Estado',
    'emails.statusAll': 'Todos',
    'emails.statusName.sent': 'Enviado',
    'emails.statusName.failed': 'Fallido',
    'emails.sentAt': 'Fecha',
    'emails.recipient': 'Para',
    'emails.subject': 'Asunto',
    'emails.account': 'Asesor',
    'emails.kind': 'Tipo',
    'emails.platform': 'Pontiac',
    'emails.kinds.invitation': 'Invitación',
    'emails.kinds.test': 'Prueba',
    'emails.kinds.other': 'Otro',
    'emails.sendTest': 'Enviar correo de prueba',
    'emails.send': 'Enviar',
    'emails.to': 'Para',
    'emails.testIntro': 'Envía un correo corto para comprobar que el servidor de correo funciona. Aparecerá en esta lista.',
    'emails.testSent': 'Enviamos el correo de prueba a {email}.',
    'emails.empty': 'Ningún correo coincide con la búsqueda.',
    'emails.emptyAll': 'Aún no se ha enviado ningún correo.',

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
    'errors.owner_cannot_be_disabled': 'El asesor no se puede desactivar: suspende su cuenta.',
    'errors.assistant_limit_reached': 'Llegaste al máximo de asistentes activos de tu plan. Desactiva a alguien o pide más cupo a Pontiac.',
    'errors.feature_disabled': 'Esta función no está activa para este asesor.',
    'errors.cannot_disable_yourself': 'No puedes quitarte el acceso a ti mismo.',
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
