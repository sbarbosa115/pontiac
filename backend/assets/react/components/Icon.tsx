import React, { type ReactNode } from 'react';

// Small stroke icons (24×24 grid) used by the sidebar; they inherit the text color.
const PATHS = {
    book: (
        <>
            <path d="M12 6.5C10.3 5 7.9 4.5 3.5 4.5v14c4.4 0 6.8.5 8.5 2 1.7-1.5 4.1-2 8.5-2v-14c-4.4 0-6.8.5-8.5 2Z" />
            <path d="M12 6.5v14" />
        </>
    ),
    chart: (
        <>
            <path d="M4 20V4M4 20h16" />
            <path d="M8.5 20v-6M13 20V8.5M17.5 20v-9.5" />
        </>
    ),
    dashboard: (
        <>
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
        </>
    ),
    building: (
        <>
            <rect x="4.5" y="3" width="15" height="18" rx="1.5" />
            <path d="M9 7h1.5M13.5 7H15M9 11h1.5M13.5 11H15M9 15h1.5M13.5 15H15M10.5 21v-3h3v3" />
        </>
    ),
    door: (
        <>
            <path d="M6 21V4.5A1.5 1.5 0 0 1 7.5 3h9A1.5 1.5 0 0 1 18 4.5V21M3.5 21h17" />
            <circle cx="14.5" cy="12" r="1" />
        </>
    ),
    users: (
        <>
            <circle cx="9" cy="8" r="3.5" />
            <path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.7a3.5 3.5 0 0 1 0 6.6M18 14.3a6.5 6.5 0 0 1 3.5 5.7" />
        </>
    ),
    file: <path d="M14 3H7a1.5 1.5 0 0 0-1.5 1.5v15A1.5 1.5 0 0 0 7 21h10a1.5 1.5 0 0 0 1.5-1.5V7.5zM14 3v4.5h4.5M9 13h6M9 17h6" />,
    wrench: <path d="M14.7 6.3a4 4 0 0 0-5.3 5.2L3.5 17.4V20.5h3.1l5.9-5.9a4 4 0 0 0 5.2-5.3l-2.5 2.5-2.3-.5-.5-2.3z" />,
    card: (
        <>
            <rect x="2.5" y="5" width="19" height="14" rx="2" />
            <path d="M2.5 10h19M6.5 15h4" />
        </>
    ),
    calendar: (
        <>
            <rect x="3.5" y="5" width="17" height="15.5" rx="2" />
            <path d="M3.5 10h17M8 3v4M16 3v4" />
            <circle cx="8.5" cy="14" r="1" />
            <circle cx="12" cy="14" r="1" />
            <circle cx="15.5" cy="14" r="1" />
        </>
    ),
    megaphone: <path d="M4 10v4a1 1 0 0 0 1 1h2l5 4V5L7 9H5a1 1 0 0 0-1 1zM16 8.5a4.5 4.5 0 0 1 0 7M18.5 6a8 8 0 0 1 0 12" />,
    inbox: <path d="M3.5 13.5h5l1.5 2.5h4l1.5-2.5h5M6 4.5h12l2.5 9V19a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 19v-5.5z" />,
    clipboard: (
        <>
            <rect x="5" y="4.5" width="14" height="16.5" rx="1.5" />
            <path d="M9 4.5V3.5h6v1M9 11h6M9 15h4" />
        </>
    ),
    tag: (
        <>
            <path d="M3.5 12.2V4.5a1 1 0 0 1 1-1h7.7a1 1 0 0 1 .7.3l7.6 7.6a1 1 0 0 1 0 1.4l-7.7 7.7a1 1 0 0 1-1.4 0l-7.6-7.6a1 1 0 0 1-.3-.7Z" />
            <circle cx="8" cy="8" r="1.5" />
        </>
    ),
    shield: <path d="M12 3.5 5 6v5.5c0 4.3 2.9 7.6 7 9 4.1-1.4 7-4.7 7-9V6l-7-2.5Z" />,
    chat: <path d="M4.5 5.5h15a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H10l-4.5 3.5v-3.5h-1a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1ZM8 10h8M8 13h5" />,
    palette: (
        <>
            <path d="M12 3.5a8.5 8.5 0 0 0 0 17c1.1 0 1.8-.8 1.8-1.7 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-.9.8-1.7 1.7-1.7h2a4 4 0 0 0 4-4c0-4-3.8-7.2-8.5-7.2Z" />
            <circle cx="7.5" cy="11" r="1" />
            <circle cx="10" cy="7.5" r="1" />
            <circle cx="14.5" cy="7.5" r="1" />
        </>
    ),
    settings: (
        <>
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" />
        </>
    ),
    receipt: <path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3" />,
    bolt: <path d="M13 3 5 13.5h6L10 21l8-10.5h-6z" />,
    home: <path d="M3.5 10.5 12 4l8.5 6.5V20a1 1 0 0 1-1 1H15v-6H9v6H4.5a1 1 0 0 1-1-1z" />,
    globe: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
        </>
    ),
    logout: <path d="M9.5 21H5.5a1.5 1.5 0 0 1-1.5-1.5v-15A1.5 1.5 0 0 1 5.5 3h4M16 17l5-5-5-5M21 12H9.5" />,
    menu: <path d="M4 6.5h16M4 12h16M4 17.5h16" />,
    close: <path d="M6 6l12 12M18 6 6 18" />,
    chevronLeft: <path d="M15 5l-7 7 7 7" />,
    chevronRight: <path d="M9 5l7 7-7 7" />,
    eye: (
        <>
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" />
            <circle cx="12" cy="12" r="3" />
        </>
    ),
    paperclip: <path d="m20.5 11.5-8.2 8.2a5 5 0 0 1-7.1-7.1l8.5-8.5a3.3 3.3 0 0 1 4.7 4.7l-8.5 8.5a1.7 1.7 0 0 1-2.4-2.4l7.8-7.8" />,
    check: <path d="m4.5 12.5 5 5 10-11" />,
    // Reopen / put back: an arrow curving back on itself.
    undo: <path d="M4 9h10a5.5 5.5 0 0 1 0 11h-6M4 9l4-4M4 9l4 4" />,
    clock: (
        <>
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7v5.2l3.3 2" />
        </>
    ),
    download: <path d="M12 3.5v12M7.5 11l4.5 4.5 4.5-4.5M4.5 20.5h15" />,
    plus: <path d="M12 5v14M5 12h14" />,
    pencil: <path d="M4 20h4L19.5 8.5a2.8 2.8 0 0 0-4-4L4 16zM13.5 6.5l4 4" />,
    chevronUp: <path d="m6 15 6-6 6 6" />,
    chevronDown: <path d="m6 9 6 6 6-6" />,
    ban: (
        <>
            <circle cx="12" cy="12" r="9" />
            <path d="m5.6 5.6 12.8 12.8" />
        </>
    ),
} satisfies Record<string, ReactNode>;

/** Every icon there is: a name outside this list is a type error, not an empty square. */
export type IconName = keyof typeof PATHS;

export default function Icon({ name, size = 18 }: { name: IconName; size?: number }) {
    return (
        <svg
            className="icon"
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
        >
            {PATHS[name]}
        </svg>
    );
}
