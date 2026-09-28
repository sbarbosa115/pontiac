import { type ChangeEvent, type DependencyList, useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api, ApiError } from './api';
import { errorMessage, t } from './i18n';

export interface ApiState<T> {
    data: T | null;
    error: unknown;
    loading: boolean;
}

/**
 * Runs an async loader when deps change. Keeps the previous data while reloading.
 *
 * Typed by what the loader resolves to: `useApi(() => api.get<Schema<'MeOutput'>>('/api/me'), [])`,
 * with the schema from `assets/types/api.d.ts` (see lib/types.ts).
 */
export function useApi<T>(loader: () => Promise<T>, deps: DependencyList): ApiState<T> & { reload: () => void } {
    const [state, setState] = useState<ApiState<T>>({ data: null, error: null, loading: true });
    const [version, setVersion] = useState(0);

    // A new request (deps or reload changed) is loading from the render that asks for it, keeping the previous
    // data on screen; marked while rendering rather than in the effect, which would paint "not loading" first.
    // Compared element by element, as React compares effect dependencies.
    const [requested, setRequested] = useState({ deps, version });
    const changed =
        requested.version !== version || requested.deps.length !== deps.length || requested.deps.some((dep, i) => !Object.is(dep, deps[i]));
    if (changed) {
        setRequested({ deps, version });
        setState((previous) => ({ ...previous, loading: true, error: null }));
    }

    useEffect(() => {
        let active = true;
        loader()
            .then((data) => active && setState({ data, error: null, loading: false }))
            .catch((error: unknown) => active && setState({ data: null, error, loading: false }));
        return () => {
            active = false;
        };
    }, [...deps, version]); // eslint-disable-line react-hooks/exhaustive-deps

    const reload = useCallback(() => setVersion((v) => v + 1), []);

    return { ...state, reload };
}

/** One page of a list endpoint (ApiController::page()). */
export interface ListPage<T> {
    items: T[];
    total: number;
    page: number;
    perPage: number;
}

export type Filters = Record<string, string | number | undefined>;

/** A paginated API list with filters; changing a filter goes back to page 1. */
export function useList<T = unknown, F extends Filters = Filters>(path: string, initialFilters: F = {} as F) {
    const [filters, setFilters] = useState<F>(initialFilters);
    const [page, setPage] = useState(1);
    const key = JSON.stringify(filters);

    const result = useApi(() => api.get<ListPage<T>>(path, { ...filters, page }), [path, key, page]);

    const update = useCallback((patch: Partial<F>) => {
        setFilters((current) => ({ ...current, ...patch }));
        setPage(1);
    }, []);

    return { ...result, filters, update, page, setPage };
}

/**
 * The tab a grouped page is showing, kept in the URL so a link, a reload and the back button all land on the
 * same view (and so a help article can point at one: "/admin/ajustes?tab=equipo").
 *
 * The first tab is the default and leaves the URL clean. Other parameters are left alone: a filter the other
 * tab put there is still waiting when the user switches back.
 */
export function useTabParam<Tab extends string>(tabs: readonly Tab[], key = 'tab'): [Tab, (next: Tab) => void] {
    const [params, setParams] = useSearchParams();
    const current = params.get(key);
    const value = (tabs as readonly string[]).includes(current ?? '') ? (current as Tab) : (tabs[0] as Tab);

    const setTab = useCallback(
        (next: Tab) => {
            const updated = new URLSearchParams(params);
            if (next === tabs[0]) {
                updated.delete(key);
            } else {
                updated.set(key, next);
            }
            setParams(updated, { replace: true });
        },
        [params, setParams, key, tabs],
    );

    return [value, setTab];
}

type FieldEvent = ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement> | { target: { value: string; type?: string; checked?: boolean } };

export function useForm<V extends Record<string, unknown>>(initial: V) {
    const [values, setValues] = useState<V>(initial);

    const set = useCallback(<K extends keyof V>(name: K, value: V[K]) => setValues((current) => ({ ...current, [name]: value })), []);

    // Spread onto an input, a select or a DateInput: `<input {...form.bind('title')} />`.
    const bind = <K extends keyof V & string>(name: K) => ({
        name,
        value: (values[name] ?? '') as string,
        onChange: (event: FieldEvent) => {
            const target = event.target as { value: string; type?: string; checked?: boolean };
            set(name, (target.type === 'checkbox' ? target.checked : target.value) as V[K]);
        },
    });

    return { values, setValues, set, bind };
}

export interface SubmitState {
    busy: boolean;
    errors: Record<string, string>;
    formError: string | null;
}

export type SubmitResult<T> = { ok: true; value: T } | { ok: false; error: unknown };

/**
 * Wraps a submit action: tracks busy state and maps API errors to
 * per-field messages (422) or a general form message.
 */
export function useSubmit() {
    const [state, setState] = useState<SubmitState>({ busy: false, errors: {}, formError: null });

    const run = useCallback(async <T,>(action: () => Promise<T>): Promise<SubmitResult<T>> => {
        setState({ busy: true, errors: {}, formError: null });
        try {
            const value = await action();
            setState({ busy: false, errors: {}, formError: null });
            return { ok: true, value };
        } catch (error) {
            const errors = error instanceof ApiError ? error.fieldErrors() : {};
            const hasFieldErrors = Object.keys(errors).length > 0;
            setState({ busy: false, errors, formError: hasFieldErrors ? t('errors.validation_failed') : errorMessage(error) });
            return { ok: false, error };
        }
    }, []);

    return { ...state, run };
}

/** Converts empty strings to null so optional fields are cleared on the API. */
export function emptyToNull<V extends Record<string, unknown>>(values: V): { [K in keyof V]: V[K] | null } {
    return Object.fromEntries(Object.entries(values).map(([key, value]) => [key, value === '' ? null : value])) as {
        [K in keyof V]: V[K] | null;
    };
}
