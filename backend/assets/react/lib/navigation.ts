import { type Me, portalPath, ROLE_ASSISTANT, ROLE_CLIENT, ROLE_OWNER, ROLE_SUPER_ADMIN } from './auth';
import type { IconName } from '../components/Icon';

export interface MenuItem {
    /** A path; one starting with ":portal" is under the client's portal (/<slug>/portal). */
    to: string;
    /** An i18n key. */
    label: string;
    icon: IconName;
    end?: boolean;
    roles?: string[];
    /** Shown only while the user's account has this feature on (/api/me `account.features`). */
    feature?: string;
}

export interface MenuSection {
    title: string;
    roles?: string[];
    items: MenuItem[];
}

/**
 * Every menu option in the app, in the order of the work. Sections (and single items) declare which roles see
 * them. This only hides options: routes are guarded by RequireRole and the API enforces access on its own.
 */
export const MENU: MenuSection[] = [
    {
        title: 'nav.section.platform',
        roles: [ROLE_SUPER_ADMIN],
        items: [
            { to: '/plataforma', label: 'nav.home', icon: 'dashboard', end: true },
            { to: '/plataforma/asesores', label: 'nav.consultants', icon: 'users' },
            { to: '/plataforma/configuracion', label: 'nav.platformSettings', icon: 'settings' },
            { to: '/plataforma/correos', label: 'nav.emails', icon: 'inbox' },
        ],
    },
    {
        title: 'nav.section.practice',
        roles: [ROLE_OWNER, ROLE_ASSISTANT],
        items: [
            { to: '/admin', label: 'nav.home', icon: 'dashboard', end: true },
            { to: '/admin/agenda', label: 'nav.agenda', icon: 'calendar', feature: 'booking' },
            { to: '/admin/prospectos', label: 'nav.contacts', icon: 'inbox' },
            { to: '/admin/planes', label: 'nav.plans', icon: 'receipt' },
            { to: '/admin/paginas', label: 'nav.pages', icon: 'file' },
            { to: '/admin/ajustes', label: 'nav.settings', icon: 'settings' },
        ],
    },
    {
        title: 'nav.section.portal',
        roles: [ROLE_CLIENT],
        items: [{ to: ':portal', label: 'nav.home', icon: 'home', end: true }],
    },
];

const visibleTo = (roles: readonly string[]) => (entry: { roles?: string[] }) => !entry.roles || entry.roles.some((role) => roles.includes(role));

/** An item with a `feature` shows only while the account has it on; the super admin has no account and no such items. */
const enabledFor = (me: Me | null) => (item: MenuItem) => !item.feature || (me?.account?.features ?? []).includes(item.feature);

/** The sections and items the given roles may see, with portal paths resolved; empty sections removed. */
export function menuFor(roles: readonly string[] = [], me: Me | null = null): MenuSection[] {
    const portal = portalPath(me?.account?.slug);
    return MENU.filter(visibleTo(roles))
        .map((section) => ({
            ...section,
            items: section.items.filter(visibleTo(roles)).filter(enabledFor(me)).map((item) => ({ ...item, to: item.to.replace(/^:portal/, portal) })),
        }))
        .filter((section) => section.items.length > 0);
}

export function roleLabelKey(roles: readonly string[] = []): string {
    if (roles.includes(ROLE_SUPER_ADMIN)) return 'roles.superAdmin';
    if (roles.includes(ROLE_OWNER)) return 'roles.owner';
    if (roles.includes(ROLE_ASSISTANT)) return 'roles.assistant';
    return 'roles.client';
}
