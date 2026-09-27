import React, { Suspense, useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../lib/api';
import { homePathFor, IMPERSONATION_ROLES, ROLE_ASSISTANT, ROLE_OWNER, ROLE_SUPER_ADMIN, useAuth } from '../lib/auth';
import { useApi } from '../lib/hooks';
import { errorMessage, t } from '../lib/i18n';
import { isThemeChoice, THEME_CHOICES, useTheme } from '../lib/theme';
import { menuFor, roleLabelKey } from '../lib/navigation';
import type { Get, Schema } from '../lib/types';
import Icon from './Icon';
import { Badge, Button, Loading } from './ui';

// "Estás actuando como {name} ({role} de {account})".
function impersonationRoleKey(roles: readonly string[]): string {
    return roles.includes(ROLE_OWNER) ? 'impersonation.roleOwner' : 'impersonation.roleAssistant';
}

/**
 * App shell for every role: a sidebar with the options the user's role may see.
 * On narrow screens the sidebar becomes a drawer opened from the top bar.
 */
export default function Layout() {
    const { me, roles, realRoles, impersonation, stopImpersonating, logout } = useAuth();
    const location = useLocation();
    const navigate = useNavigate();
    const [open, setOpen] = useState(false);
    const sections = menuFor(roles, me);

    // Close the drawer after navigating (adjusted while rendering, not in an effect), and on Escape.
    const [openedOn, setOpenedOn] = useState(location.pathname);
    if (openedOn !== location.pathname) {
        setOpenedOn(location.pathname);
        setOpen(false);
    }
    useEffect(() => {
        if (!open) return undefined;
        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open]);

    const exitImpersonation = () => {
        stopImpersonating();
        navigate('/plataforma');
    };

    return (
        <div className={`shell ${open ? 'sidebar-open' : ''}`}>
            <header className="mobile-bar">
                <button
                    type="button"
                    className="icon-btn menu-toggle"
                    aria-label={t('common.menu')}
                    aria-expanded={open}
                    aria-controls="sidebar"
                    onClick={() => setOpen(true)}
                >
                    <Icon name="menu" size={22} />
                </button>
                <Brand accountName={me?.account?.name} />
            </header>

            <aside id="sidebar" className="sidebar">
                <div className="sidebar-header">
                    <Brand accountName={me?.account?.name} />
                    <button type="button" className="icon-btn sidebar-close" aria-label={t('common.closeMenu')} onClick={() => setOpen(false)}>
                        <Icon name="close" />
                    </button>
                </div>

                {realRoles.includes(ROLE_SUPER_ADMIN) && <ImpersonationPicker onExit={exitImpersonation} />}

                <nav className="sidebar-nav" aria-label={t('nav.label')}>
                    {sections.map((section) => (
                        <div key={section.title} className="nav-section">
                            <div className="nav-section-title">{t(section.title)}</div>
                            {section.items.map((item) => (
                                <NavLink key={item.to} to={item.to} end={item.end} className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                                    <Icon name={item.icon} />
                                    <span>{t(item.label)}</span>
                                </NavLink>
                            ))}
                        </div>
                    ))}
                </nav>

                <div className="sidebar-footer">
                    {/* Always the person really signed in, also while impersonating. */}
                    <div className="sidebar-user">
                        <span className="sidebar-user-name">{me?.impersonator?.fullName ?? me?.fullName}</span>
                        <span className="sidebar-user-role">{t(roleLabelKey(realRoles))}</span>
                    </div>
                    <ThemePicker />
                    <button type="button" className="btn btn-ghost btn-sm btn-block" onClick={logout}>
                        <Icon name="logout" size={16} />
                        {t('common.logout')}
                    </button>
                </div>
            </aside>

            <div className="sidebar-backdrop" aria-hidden="true" onClick={() => setOpen(false)} />

            <main className="content">
                {impersonation && (
                    <div className="impersonation-banner" role="status">
                        <span>
                            {t('impersonation.banner', {
                                name: impersonation.fullName,
                                role: t(impersonationRoleKey(roles)),
                                account: impersonation.accountName,
                            })}
                        </span>
                        <Button variant="ghost" size="sm" onClick={exitImpersonation}>
                            {t('impersonation.exit')}
                        </Button>
                    </div>
                )}
                {/* Pages are lazy chunks (App.tsx): the menu stays while one loads, only the page area waits. */}
                <Suspense fallback={<Loading />}>
                    <Outlet />
                </Suspense>
            </main>
        </div>
    );
}

/**
 * Super admin only: pick a consultant or an assistant to act as, grouped by consultant, the consultant first.
 */
type ImpersonatableUser = Schema<'ImpersonatableUserOutput'>;

function ImpersonationPicker({ onExit }: { onExit: () => void }) {
    const { impersonation, impersonate, roles } = useAuth();
    const navigate = useNavigate();
    const users = useApi(() => api.get<Get<'/api/platform/impersonatable-users'>>('/api/platform/impersonatable-users'), []);
    const items = users.data?.items || [];

    const groups: { account: ImpersonatableUser['account']; users: ImpersonatableUser[] }[] = [];
    for (const user of items) {
        const last = groups[groups.length - 1];
        if (last && last.account.id === user.account.id) {
            last.users.push(user);
        } else {
            groups.push({ account: user.account, users: [user] });
        }
    }

    const onChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        const id = event.target.value;
        if (id === '') {
            onExit();
            return;
        }
        const user = items.find((item) => item.id === id);
        if (user) {
            impersonate(user);
            navigate(homePathFor([IMPERSONATION_ROLES[user.role] ?? ROLE_ASSISTANT]));
        }
    };

    return (
        <div className="impersonation-picker">
            <label className="nav-section-title" htmlFor="impersonation-select">
                {t('impersonation.label')}
            </label>
            <select id="impersonation-select" value={impersonation?.id || ''} onChange={onChange}>
                <option value="">{t('impersonation.none')}</option>
                {/* Keep the current choice visible while the list loads or if it failed. */}
                {impersonation?.id && !items.some((user) => user.id === impersonation.id) && (
                    <option value={impersonation.id}>
                        {impersonation.accountName} · {impersonation.fullName}
                    </option>
                )}
                {groups.map(({ account, users: people }) => (
                    <optgroup key={account.id} label={account.name}>
                        {people.map((user) => (
                            <option key={user.id} value={user.id}>
                                {`${user.fullName} · ${t(`impersonation.role.${user.role}`)} (${user.email})`}
                            </option>
                        ))}
                    </optgroup>
                ))}
            </select>
            {users.error ? <span className="field-error">{errorMessage(users.error)}</span> : null}
            {impersonation && (
                <span className="impersonation-current">
                    <Badge value="active">{t(roleLabelKey(roles))}</Badge>
                    <span className="small muted">{impersonation.accountName}</span>
                </span>
            )}
        </div>
    );
}

function Brand({ accountName }: { accountName?: string | null }) {
    return (
        <div className="brand">
            <span className="brand-name">Pontiac</span>
            {accountName && <span className="brand-account">{accountName}</span>}
        </div>
    );
}

/**
 * "Tema": light, dark or the device's. It applies at once and is saved on the person's login, so it follows them to
 * other devices; if saving fails it still holds here, and says so.
 */
function ThemePicker() {
    const { choice, choose } = useTheme();
    const [error, setError] = useState<string | null>(null);

    const onChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        const next = event.target.value;
        if (!isThemeChoice(next)) return;
        setError(null);
        choose(next).catch(() => setError(t('theme.saveFailed')));
    };

    return (
        <div className="theme-picker">
            <label className="nav-section-title" htmlFor="theme-select">
                {t('theme.label')}
            </label>
            <select id="theme-select" value={choice} onChange={onChange}>
                {THEME_CHOICES.map((option) => (
                    <option key={option} value={option}>
                        {t(`theme.${option}`)}
                    </option>
                ))}
            </select>
            {error && <span className="field-error">{error}</span>}
        </div>
    );
}
