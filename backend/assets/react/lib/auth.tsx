import React, { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { api, ApiError, type Impersonation, impersonationStore, setLanguage, setUnauthorizedHandler, tokenStore } from './api';
import type { Schema } from './types';
import { FullPageLoading } from '../components/ui';

/** GET /api/me: who the requests run as, and their account's settings. */
export type Me = Schema<'MeOutput'>;

/** What the JWT says, read for routing only. */
export interface Claims {
    exp: number;
    roles: string[];
    userId?: string;
    accountSlug?: string | null;
}

export const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
export const ROLE_OWNER = 'ROLE_OWNER';
export const ROLE_ASSISTANT = 'ROLE_ASSISTANT';
export const ROLE_CLIENT = 'ROLE_CLIENT';

/** Everyone who works in the admin: the consultant and their assistants. */
export const STAFF_ROLES = [ROLE_OWNER, ROLE_ASSISTANT];

/** The role an entry of GET /api/platform/impersonatable-users stands for. */
export const IMPERSONATION_ROLES: Record<string, string> = { owner: ROLE_OWNER, assistant: ROLE_ASSISTANT };

/** Reads the JWT payload for routing only; the API never trusts these claims. */
export function decodeJwt(token: string): Claims | null {
    try {
        const base64 = (token.split('.')[1] ?? '').replace(/-/g, '+').replace(/_/g, '/');
        const bytes = Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
        return JSON.parse(new TextDecoder().decode(bytes)) as Claims;
    } catch {
        return null;
    }
}

/** Where a client signs in and works: under their consultant's address. */
export function portalPath(slug: string | null | undefined): string {
    return slug ? `/${slug}/portal` : '/login';
}

export function homePathFor(roles: readonly string[] = [], accountSlug?: string | null): string {
    if (roles.includes(ROLE_SUPER_ADMIN)) return '/plataforma';
    if (roles.includes(ROLE_OWNER) || roles.includes(ROLE_ASSISTANT)) return '/admin';
    if (roles.includes(ROLE_CLIENT)) return portalPath(accountSlug);
    return '/login';
}

function initialToken(): string | null {
    const token = tokenStore.get();
    if (!token) return null;
    const claims = decodeJwt(token);
    if (!claims || claims.exp * 1000 <= Date.now()) {
        tokenStore.clear();
        return null;
    }
    return token;
}

/** Only a super admin's session may carry a stored impersonation (not, say, the next person on this browser). */
function initialImpersonation(token: string | null): Impersonation | null {
    const isSuperAdmin = Boolean(token && decodeJwt(token)?.roles?.includes(ROLE_SUPER_ADMIN));
    if (!isSuperAdmin) {
        impersonationStore.clear();
    }
    return impersonationStore.get();
}

export interface Auth {
    token: string | null;
    claims: Claims | null;
    /** The roles of whoever the requests run as (the impersonated user while impersonating). */
    roles: string[];
    /** The signed-in person's own roles, from the JWT. */
    realRoles: string[];
    impersonation: Impersonation | null;
    impersonate: (user: Schema<'ImpersonatableUserOutput'>) => void;
    stopImpersonating: () => void;
    me: Me | null;
    loading: boolean;
    /** Staff: email and password. */
    login: (email: string, password: string) => Promise<Claims | null>;
    /** A client, at their consultant's portal. */
    portalLogin: (account: string, email: string, password: string) => Promise<Claims | null>;
    logout: () => void;
}

const AuthContext = createContext<Auth | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [token, setToken] = useState<string | null>(initialToken);
    const [impersonation, setImpersonation] = useState(() => initialImpersonation(token));
    const [me, setMe] = useState<Me | null>(null);
    const [loading, setLoading] = useState(Boolean(token));
    const claims = useMemo(() => (token ? decodeJwt(token) : null), [token]);

    const stopImpersonating = useCallback(() => {
        if (!impersonationStore.get()) return;
        impersonationStore.clear();
        setImpersonation(null);
        setMe(null);
        setLoading(true);
    }, []);

    /** Acts as a consultant or an assistant from GET /api/platform/impersonatable-users. */
    const impersonate = useCallback((user: Schema<'ImpersonatableUserOutput'>) => {
        if (impersonationStore.get()?.id === user.id) return;
        const value: Impersonation = {
            id: user.id,
            email: user.email,
            fullName: user.fullName,
            accountName: user.account.name,
            role: IMPERSONATION_ROLES[user.role] ?? ROLE_ASSISTANT,
        };
        impersonationStore.set(value);
        setImpersonation(value);
        // Reload /api/me as that person before any page renders with the previous profile.
        setMe(null);
        setLoading(true);
    }, []);

    const logout = useCallback(() => {
        tokenStore.clear();
        impersonationStore.clear();
        setToken(null);
        setImpersonation(null);
        setMe(null);
    }, []);

    useEffect(() => setUnauthorizedHandler(logout), [logout]);

    // The session ends with the JWT.
    useEffect(() => {
        if (!claims) return undefined;
        const timer = setTimeout(logout, Math.max(claims.exp * 1000 - Date.now(), 0));
        return () => clearTimeout(timer);
    }, [claims, logout]);

    // /api/me follows who is signed in and who they act as. When that changes, loading is set while rendering — not
    // in the effect, which would paint the old profile first.
    const sessionKey = claims?.userId ?? null;
    const profileKey = sessionKey ? `${sessionKey}|${impersonation?.id ?? ''}` : null;
    const [loadingFor, setLoadingFor] = useState(profileKey);
    if (loadingFor !== profileKey) {
        setLoadingFor(profileKey);
        setLoading(profileKey !== null);
    }
    useEffect(() => {
        if (!sessionKey) return undefined;

        let cancelled = false;
        api.get<Me>('/api/me')
            .then((data) => {
                if (cancelled) return;
                setMe(data);
                setLanguage((data.account?.locale || 'es').split(/[_-]/)[0] ?? 'es');
            })
            .catch((error: unknown) => {
                if (cancelled) return;
                // The person can't be acted as anymore (disabled, suspended): back to the platform.
                if (impersonationStore.get() && error instanceof ApiError && error.status === 403) {
                    impersonationStore.clear();
                    setImpersonation(null);
                } else {
                    logout();
                }
            })
            .finally(() => !cancelled && setLoading(false));

        return () => {
            cancelled = true;
        };
    }, [sessionKey, impersonation?.id]); // eslint-disable-line react-hooks/exhaustive-deps

    const start = useCallback((newToken: string) => {
        impersonationStore.clear();
        setImpersonation(null);
        tokenStore.set(newToken);
        setToken(newToken);
        return decodeJwt(newToken);
    }, []);

    const login = useCallback(
        async (email: string, password: string) => start((await api.publicPost<Schema<'TokenOutput'>>('/api/login', { email, password })).token),
        [start],
    );

    const portalLogin = useCallback(
        async (account: string, email: string, password: string) =>
            start((await api.publicPost<Schema<'TokenOutput'>>('/api/portal-login', { account, email, password })).token),
        [start],
    );

    const value = useMemo((): Auth => {
        const realRoles = claims?.roles || [];
        return {
            token,
            claims,
            // Routes and menu follow the roles /api/me reports for whoever the requests run as. Until it answers: the
            // stored impersonation's role, or the JWT's. The API still decides what is allowed.
            roles: me?.roles ?? (impersonation ? [impersonation.role] : realRoles),
            realRoles,
            impersonation,
            impersonate,
            stopImpersonating,
            me,
            loading,
            login,
            portalLogin,
            logout,
        };
    }, [token, claims, impersonation, impersonate, stopImpersonating, me, loading, login, portalLogin, logout]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): Auth {
    const auth = useContext(AuthContext);
    if (!auth) {
        throw new Error('useAuth() is used outside <AuthProvider>.');
    }
    return auth;
}

/**
 * Formatting settings come from the account (not the browser), so a Colombian consultant sees COP and Bogotá dates
 * wherever they, their assistants or their clients are.
 */
export function useLocaleSettings() {
    const { me } = useAuth();
    const account = me?.account;
    return {
        locale: (account?.locale || 'es-CO').replace('_', '-'),
        currency: account?.currency || 'COP',
        country: account?.country || 'CO',
        timezone: account?.timezone || 'America/Bogota',
    };
}

/**
 * Renders its children for the given role(s) only: someone signed out goes to the sign-in page ($loginPath: the
 * staff one, or a consultant's portal), someone with another role to their own home.
 */
export function RequireRole({ role, loginPath = '/login', children }: { role: string | string[]; loginPath?: string; children: ReactNode }) {
    const allowed = Array.isArray(role) ? role : [role];
    const { token, roles, claims, loading } = useAuth();
    const location = useLocation();

    if (!token) {
        return <Navigate to={loginPath} replace state={{ from: location.pathname }} />;
    }
    if (loading) {
        return <FullPageLoading />;
    }
    if (!allowed.some((one) => roles.includes(one))) {
        return <Navigate to={homePathFor(roles, claims?.accountSlug)} replace />;
    }
    return <>{children}</>;
}
