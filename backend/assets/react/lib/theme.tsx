import React, { createContext, type ReactNode, useContext, useEffect, useState } from 'react';
import { api, tokenStore } from './api';
import { useAuth } from './auth';
import type { Schema } from './types';

/** What the person chose: light, dark, or whatever their device is set to. */
export type ThemeChoice = 'light' | 'dark' | 'system';
/** What the page shows: `system` resolved against the device. */
export type ResolvedTheme = 'light' | 'dark';

export const THEME_CHOICES: readonly ThemeChoice[] = ['light', 'dark', 'system'];

/**
 * The last choice seen in this browser. A reload applies it before the page is drawn (the inline script in
 * spa.html.twig reads the same key), and the sign-in screens keep it after signing out. It is only a convenience:
 * the choice itself is the person's, on their login (/api/me), and wins as soon as it arrives.
 */
const CACHE_KEY = 'pontiac.theme';
const DARK_QUERY = '(prefers-color-scheme: dark)';

export function isThemeChoice(value: unknown): value is ThemeChoice {
    return THEME_CHOICES.includes(value as ThemeChoice);
}

export function resolveTheme(choice: ThemeChoice, deviceIsDark: boolean): ResolvedTheme {
    if (choice === 'system') return deviceIsDark ? 'dark' : 'light';
    return choice;
}

function deviceIsDark(): boolean {
    return typeof window.matchMedia === 'function' && window.matchMedia(DARK_QUERY).matches;
}

export function cachedChoice(): ThemeChoice {
    try {
        const value = window.localStorage.getItem(CACHE_KEY);
        return isThemeChoice(value) ? value : 'light';
    } catch {
        return 'light';
    }
}

function cache(choice: ThemeChoice): void {
    try {
        window.localStorage.setItem(CACHE_KEY, choice);
    } catch {
        // Private mode: a reload starts light until /api/me answers.
    }
}

interface ThemeState {
    choice: ThemeChoice;
    resolved: ResolvedTheme;
    /** Applies at once and saves it on the person's login; the promise fails only if saving did. */
    choose: (choice: ThemeChoice) => Promise<void>;
    /** Takes the choice the server has, without saving it again. */
    adopt: (choice: ThemeChoice) => void;
}

const ThemeContext = createContext<ThemeState | null>(null);

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [choice, setChoice] = useState<ThemeChoice>(cachedChoice);
    const [dark, setDark] = useState(deviceIsDark);

    // "Según el dispositivo" follows the device live, not only when the page loads.
    useEffect(() => {
        if (typeof window.matchMedia !== 'function') return undefined;
        const query = window.matchMedia(DARK_QUERY);
        const onChange = (event: MediaQueryListEvent) => setDark(event.matches);
        query.addEventListener('change', onChange);
        return () => query.removeEventListener('change', onChange);
    }, []);

    const resolved = resolveTheme(choice, dark);
    useEffect(() => {
        document.documentElement.dataset.theme = resolved;
    }, [resolved]);

    const adopt = (next: ThemeChoice) => {
        setChoice(next);
        cache(next);
    };

    const choose = async (next: ThemeChoice) => {
        adopt(next);
        if (tokenStore.get()) {
            await api.patch<Schema<'MePreferencesOutput'>>('/api/me/preferences', { uiTheme: next });
        }
    };

    return <ThemeContext.Provider value={{ choice, resolved, choose, adopt }}>{children}</ThemeContext.Provider>;
}

export function useTheme(): ThemeState {
    const state = useContext(ThemeContext);
    if (!state) {
        throw new Error('The theme is read outside <ThemeProvider>.');
    }
    return state;
}

/**
 * Takes the signed-in person's theme from /api/me: set on the laptop, seen on the phone. While a super admin acts as
 * someone, /api/me carries the super admin's own. Rendered inside AuthProvider.
 */
export function ThemeFollowsSession() {
    const { me } = useAuth();
    const { adopt } = useTheme();
    const fromServer = me?.uiTheme;

    useEffect(() => {
        if (isThemeChoice(fromServer)) adopt(fromServer);
    }, [me?.id, fromServer]); // eslint-disable-line react-hooks/exhaustive-deps

    return null;
}
