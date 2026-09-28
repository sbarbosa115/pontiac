import { act, renderHook } from '@testing-library/react';
import React, { type ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cachedChoice, resolveTheme, ThemeProvider, useTheme } from './theme';

/** A device whose dark setting the test flips, the way the OS does. */
function fakeDevice(dark: boolean) {
    const listeners = new Set<(event: MediaQueryListEvent) => void>();
    const query = {
        get matches() {
            return dark;
        },
        addEventListener: (_: string, listener: (event: MediaQueryListEvent) => void) => listeners.add(listener),
        removeEventListener: (_: string, listener: (event: MediaQueryListEvent) => void) => listeners.delete(listener),
    };
    vi.stubGlobal('matchMedia', () => query);
    return {
        setDark(next: boolean) {
            dark = next;
            listeners.forEach((listener) => listener({ matches: next } as MediaQueryListEvent));
        },
    };
}

const wrapper = ({ children }: { children: ReactNode }) => <ThemeProvider>{children}</ThemeProvider>;

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.localStorage.clear();
    delete document.documentElement.dataset.theme;
});

describe('resolveTheme', () => {
    it('keeps light and dark, and follows the device for "system"', () => {
        expect(resolveTheme('light', true)).toBe('light');
        expect(resolveTheme('dark', false)).toBe('dark');
        expect(resolveTheme('system', true)).toBe('dark');
        expect(resolveTheme('system', false)).toBe('light');
    });
});

describe('cachedChoice', () => {
    it('is light when nothing is stored, or something unknown is', () => {
        expect(cachedChoice()).toBe('light');
        window.localStorage.setItem('pontiac.theme', 'purple');
        expect(cachedChoice()).toBe('light');
    });

    it('is light when the browser refuses storage (private mode)', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('denied');
        });
        expect(cachedChoice()).toBe('light');
    });
});

describe('ThemeProvider', () => {
    it('starts from the last choice this browser saw and puts it on the page', () => {
        fakeDevice(false);
        window.localStorage.setItem('pontiac.theme', 'dark');

        const { result } = renderHook(() => useTheme(), { wrapper });

        expect(result.current.choice).toBe('dark');
        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('follows the device live while the choice is "system"', () => {
        const device = fakeDevice(false);
        const { result } = renderHook(() => useTheme(), { wrapper });

        act(() => result.current.adopt('system'));
        expect(document.documentElement.dataset.theme).toBe('light');

        act(() => device.setDark(true));
        expect(result.current.resolved).toBe('dark');
        expect(document.documentElement.dataset.theme).toBe('dark');
    });

    it('applies a choice at once and remembers it for the next load, even signed out', async () => {
        fakeDevice(true);
        const { result } = renderHook(() => useTheme(), { wrapper });

        await act(() => result.current.choose('light'));

        expect(document.documentElement.dataset.theme).toBe('light');
        expect(window.localStorage.getItem('pontiac.theme')).toBe('light');
    });
});
