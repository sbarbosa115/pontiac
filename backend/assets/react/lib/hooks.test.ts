import { act, renderHook, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useApi } from './hooks';

/** A promise the test resolves by hand, so each state of the hook can be looked at. */
function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((r) => (resolve = r));
    return { promise, resolve };
}

describe('useApi', () => {
    it('is loading until the first answer arrives', async () => {
        const answer = deferred<string>();
        const { result } = renderHook(() => useApi(() => answer.promise, []));
        expect(result.current).toMatchObject({ data: null, loading: true });

        await act(async () => answer.resolve('first'));
        expect(result.current).toMatchObject({ data: 'first', loading: false });
    });

    it('marks a new request as loading in the same render, and keeps the previous data on screen meanwhile', async () => {
        const answers: Record<string, ReturnType<typeof deferred<string>>> = { a: deferred(), b: deferred() };
        const { result, rerender } = renderHook(({ key }) => useApi(() => answers[key]!.promise, [key]), { initialProps: { key: 'a' } });
        await act(async () => answers.a!.resolve('A'));
        expect(result.current).toMatchObject({ data: 'A', loading: false });

        rerender({ key: 'b' });
        // No render in between shows "not loading" with the old data.
        expect(result.current).toMatchObject({ data: 'A', loading: true });

        await act(async () => answers.b!.resolve('B'));
        expect(result.current).toMatchObject({ data: 'B', loading: false });
    });

    it('does not reload when it renders again with the same dependencies', async () => {
        let calls = 0;
        const { result, rerender } = renderHook(({ key }) => useApi(() => (calls++, Promise.resolve(key)), [key]), { initialProps: { key: 'a' } });
        await waitFor(() => expect(result.current.loading).toBe(false));
        rerender({ key: 'a' });
        expect(result.current.loading).toBe(false);
        expect(calls).toBe(1);
    });

    it('reloads on demand', async () => {
        let calls = 0;
        const { result } = renderHook(() => useApi(() => Promise.resolve(++calls), []));
        await waitFor(() => expect(result.current.data).toBe(1));
        act(() => result.current.reload());
        expect(result.current.loading).toBe(true);
        await waitFor(() => expect(result.current).toMatchObject({ data: 2, loading: false }));
    });
});
