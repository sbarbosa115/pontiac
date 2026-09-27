// Thin fetch wrapper for the Symfony API: JWT header, Accept-Language, JSON errors.

const TOKEN_KEY = 'pontiac.token';
const IMPERSONATION_KEY = 'pontiac.impersonation';

/** A violation as the API reports it (422): the field it is about, and a message in English. */
export interface Violation {
    field: string;
    message: string;
}

/** An API error body: `{ error: 'code', message?, violations? }`. */
interface ErrorBody {
    error?: string;
    message?: string;
    violations?: Violation[];
}

export class ApiError extends Error {
    status: number;
    code: string;
    violations: Violation[];

    constructor(status: number, body: ErrorBody | null) {
        super(body?.message || `HTTP ${status}`);
        this.status = status;
        this.code = body?.error || 'http_error';
        this.violations = body?.violations || [];
    }

    /** First message per field, e.g. { email: 'This value is not a valid email address.' }. */
    fieldErrors(): Record<string, string> {
        const errors: Record<string, string> = {};
        for (const { field, message } of this.violations) {
            if (!(field in errors)) {
                errors[field] = message;
            }
        }
        return errors;
    }
}

/** Client-side validation failures reuse the API's error shape so forms render them the same way. */
export function validationError(fieldErrors: Record<string, string>): ApiError {
    return new ApiError(422, {
        error: 'validation_failed',
        violations: Object.entries(fieldErrors).map(([field, message]) => ({ field, message })),
    });
}

/** The user a super admin is acting as. */
export interface Impersonation {
    id: string;
    email: string;
    fullName: string;
    accountName: string;
    role: string;
}

function readToken(): string | null {
    try {
        return window.localStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

function readImpersonation(): Impersonation | null {
    try {
        return JSON.parse(window.localStorage.getItem(IMPERSONATION_KEY) ?? 'null') || null;
    } catch {
        return null;
    }
}

let token = readToken();
let impersonation = readImpersonation();
let language = 'es';
let onUnauthorized: () => void = () => {};

/**
 * The JWT lives in localStorage for as long as it is valid (an hour), so a reload keeps the session.
 */
export const tokenStore = {
    get: () => token,
    set(value: string) {
        token = value;
        try {
            window.localStorage.setItem(TOKEN_KEY, value);
        } catch {
            // Private mode: the session simply won't survive a reload.
        }
    },
    clear() {
        token = null;
        try {
            window.localStorage.removeItem(TOKEN_KEY);
        } catch {
            // ignore
        }
    },
};

/**
 * The consultant or assistant a super admin is acting as. X-Switch-User carries their id.
 * Not a privilege by itself: the API only honors X-Switch-User for ROLE_SUPER_ADMIN.
 */
export const impersonationStore = {
    get: () => impersonation,
    set(value: Impersonation) {
        impersonation = value;
        try {
            window.localStorage.setItem(IMPERSONATION_KEY, JSON.stringify(value));
        } catch {
            // Private mode: impersonation simply won't survive a reload.
        }
    },
    clear() {
        impersonation = null;
        try {
            window.localStorage.removeItem(IMPERSONATION_KEY);
        } catch {
            // ignore
        }
    },
};

function authHeaders(path: string): Record<string, string> {
    if (!token) {
        return {};
    }
    const headers: Record<string, string> = { Authorization: `Bearer ${token}` };
    // The platform API has no switch_user: the super admin always uses it as themselves.
    if (impersonation && !new URL(path, window.location.origin).pathname.startsWith('/api/platform')) {
        headers['X-Switch-User'] = impersonation.id;
    }
    return headers;
}

export function setUnauthorizedHandler(handler: () => void): void {
    onUnauthorized = handler;
}

export function setLanguage(value: string): void {
    language = value;
}

/** Query parameters: empty values (undefined, null, '') are left out of the URL. */
export type Query = Record<string, string | number | boolean | null | undefined>;

interface RequestOptions {
    json?: unknown;
    form?: FormData;
    query?: Query;
    anonymous?: boolean;
}

/**
 * Resolves to the response body. `T` is what the caller knows the endpoint returns — a schema from
 * `assets/types/api.d.ts` (`Schema<'TeamMemberOutput'>`, see lib/types.ts). It is not checked at runtime.
 */
export async function request<T = unknown>(method: string, path: string, { json, form, query, anonymous = false }: RequestOptions = {}): Promise<T> {
    const url = new URL(path, window.location.origin);
    for (const [key, value] of Object.entries(query || {})) {
        if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(key, String(value));
        }
    }

    const headers: Record<string, string> = { Accept: 'application/json', 'Accept-Language': language, ...(anonymous ? {} : authHeaders(path)) };

    let body: BodyInit | undefined;
    if (json !== undefined) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(json);
    } else if (form) {
        body = form; // the browser sets the multipart boundary
    }

    let response: Response;
    try {
        response = await fetch(url, { method, headers, body });
    } catch {
        throw new ApiError(0, { error: 'network_error' });
    }

    if (response.status === 401 && headers.Authorization) {
        onUnauthorized();
    }
    if (response.status === 204) {
        return null as T;
    }

    const isJson = (response.headers.get('Content-Type') || '').includes('json');
    const data: unknown = isJson ? await response.json() : null;
    if (!response.ok) {
        throw new ApiError(response.status, data as ErrorBody | null);
    }

    return data as T;
}

/** `api.get<Schema<'TeamMemberOutput'>>(…)`: the type argument is the response body; without one it is `unknown`. */
// Methods rather than arrow functions: Babel reads every file with JSX on, and `<T = unknown>(` would parse as a tag.
export const api = {
    get<T = unknown>(path: string, query?: Query) {
        return request<T>('GET', path, { query });
    },
    post<T = unknown>(path: string, json: unknown = {}) {
        return request<T>('POST', path, { json });
    },
    patch<T = unknown>(path: string, json: unknown) {
        return request<T>('PATCH', path, { json });
    },
    put<T = unknown>(path: string, json: unknown) {
        return request<T>('PUT', path, { json });
    },
    del<T = unknown>(path: string) {
        return request<T>('DELETE', path);
    },
    upload<T = unknown>(path: string, form: FormData) {
        return request<T>('POST', path, { form });
    },
    publicGet<T = unknown>(path: string, query?: Query) {
        return request<T>('GET', path, { query, anonymous: true });
    },
    publicPost<T = unknown>(path: string, json: unknown) {
        return request<T>('POST', path, { json, anonymous: true });
    },
};

/**
 * POSTs JSON to an endpoint that answers HTML (the page editor's preview), with the session's headers. API errors are
 * thrown as ApiError, like request().
 */
export async function postForHtml(path: string, json: unknown): Promise<string> {
    let response: Response;
    try {
        response = await fetch(path, {
            method: 'POST',
            headers: { Accept: 'text/html', 'Content-Type': 'application/json', 'Accept-Language': language, ...authHeaders(path) },
            body: JSON.stringify(json),
        });
    } catch {
        throw new ApiError(0, { error: 'network_error' });
    }
    if (!response.ok) {
        const body = (response.headers.get('Content-Type') || '').includes('json') ? ((await response.json().catch(() => null)) as ErrorBody | null) : null;
        if (response.status === 401) onUnauthorized();
        throw new ApiError(response.status, body);
    }

    return response.text();
}

/** What the file helpers need of an attachment (AttachmentOutput). */
export interface AttachmentLink {
    downloadUrl: string;
    originalFilename: string;
}

/**
 * A temporary blob URL for the attachment (e.g. to show a PDF in a modal). Call URL.revokeObjectURL() when done.
 */
export async function fetchAttachmentUrl(attachment: AttachmentLink): Promise<string> {
    let response: Response;
    try {
        response = await fetch(attachment.downloadUrl, { headers: authHeaders(attachment.downloadUrl) });
    } catch {
        throw new ApiError(0, { error: 'network_error' });
    }
    if (!response.ok) {
        throw new ApiError(response.status, null);
    }

    return URL.createObjectURL(await response.blob());
}

/**
 * Saves a file the API generates (e.g. a PDF). It needs the JWT, so it is fetched and saved from a blob;
 * the name comes from the response's Content-Disposition. API errors are thrown as ApiError, like request().
 */
export async function downloadFile(path: string, fallbackName = 'download'): Promise<void> {
    const response = await fetch(path, { headers: authHeaders(path) });
    if (!response.ok) {
        const body = (response.headers.get('Content-Type') || '').includes('json') ? ((await response.json().catch(() => null)) as ErrorBody | null) : null;
        if (response.status === 401) onUnauthorized();
        throw new ApiError(response.status, body);
    }

    const disposition = response.headers.get('Content-Disposition') || '';
    // The UTF-8 name (filename*) when there is one: the plain one replaces accents.
    const name = /filename\*=UTF-8''([^;]+)/i.exec(disposition)?.[1] ?? /filename="?([^";]+)"?/i.exec(disposition)?.[1];
    const url = URL.createObjectURL(await response.blob());
    const link = document.createElement('a');
    link.href = url;
    link.download = name ? decodeURIComponent(name) : fallbackName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

/**
 * Attachments need the Authorization header, so they can't be plain links:
 * fetch the file and open it from a temporary blob URL.
 */
export async function openAttachment(attachment: AttachmentLink): Promise<void> {
    // Open the tab synchronously so popup blockers allow it.
    const tab = window.open('about:blank', '_blank');

    const response = await fetch(attachment.downloadUrl, { headers: authHeaders(attachment.downloadUrl) });
    if (!response.ok) {
        tab?.close();
        throw new ApiError(response.status, null);
    }

    const url = URL.createObjectURL(await response.blob());
    if (tab) {
        tab.location.href = url;
    } else {
        const link = document.createElement('a');
        link.href = url;
        link.download = attachment.originalFilename;
        link.click();
    }
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
}
