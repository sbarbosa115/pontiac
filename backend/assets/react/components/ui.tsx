import React, {
    type ButtonHTMLAttributes,
    type HTMLAttributes,
    type ReactNode,
    useEffect,
    useRef,
    useState,
} from 'react';
import { errorMessage, t } from '../lib/i18n';
import { type AttachmentLink, openAttachment } from '../lib/api';
import type { Filters, ListPage } from '../lib/hooks';
import Icon, { type IconName } from './Icon';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    /** `link` only for text that is a link (a file name); never for an action (see ActionButton). */
    variant?: 'primary' | 'secondary' | 'ghost' | 'link';
    size?: 'sm';
    busy?: boolean;
};

export function Button({ variant = 'primary', size, busy = false, className = '', children, ...props }: ButtonProps) {
    const classes = ['btn', `btn-${variant}`, size && `btn-${size}`, className].filter(Boolean).join(' ');
    return (
        <button type="button" className={classes} {...props} disabled={busy || props.disabled}>
            {busy ? t('common.working') : children}
        </button>
    );
}

/**
 * What each kind of action looks like, the same in every table and modal: the colour says what the button does,
 * so two different actions side by side never share one.
 *
 * - confirm (green): complete, confirm, enable, mark as paid
 * - danger (red): cancel, reject, disable
 * - edit (blue): edit
 * - open (violet): open, view, review, go to a related list
 * - file (teal): look at a document, or at the documents asked for
 * - setup (indigo): set something up or add to it: schedules, invitations, uploads, registering
 * - revert (amber): undo, reopen, an outcome that needs attention ("No asistió")
 * - contact (rose): write to the person outside the app (WhatsApp, email)
 */
export const ACTIONS = ['confirm', 'danger', 'edit', 'open', 'file', 'setup', 'revert', 'contact'] as const;

export type Action = (typeof ACTIONS)[number];

// The action an icon stands for, so an icon button is coloured without each page having to say so.
const ICON_ACTIONS: Partial<Record<IconName, Action>> = {
    check: 'confirm',
    ban: 'danger',
    close: 'danger',
    pencil: 'edit',
    eye: 'open',
    file: 'file',
    paperclip: 'file',
    download: 'file',
    receipt: 'setup',
    plus: 'setup',
    calendar: 'setup',
    undo: 'revert',
};

/**
 * The classes of an action button, for elements that are not a <button> (a router Link to another page).
 * Small by default, as in tables; size "md" for a modal's footer.
 */
export function actionClass(action: Action, className = '', size: 'sm' | 'md' = 'sm'): string {
    return ['btn', size === 'sm' && 'btn-sm', 'btn-action', `btn-action-${action}`, className].filter(Boolean).join(' ');
}

/**
 * A worded button in an "Acciones" cell (or wherever an action sits next to others): outlined in the colour of
 * its `action`, so it reads as a button and not as a link.
 */
type ActionButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & { action: Action; size?: 'sm' | 'md'; busy?: boolean };

export function ActionButton({ action, size = 'sm', busy = false, className = '', children, ...props }: ActionButtonProps) {
    return (
        <button type="button" className={actionClass(action, className, size)} {...props} disabled={busy || props.disabled}>
            {busy ? t('common.working') : children}
        </button>
    );
}

/**
 * How to reach a person, as text in their cell: the email and, if there is one, the phone. Not links — nothing
 * in a data column is clickable (CLAUDE.md, "Tables"); writing to them is `WriteEmail`, in the actions cell.
 */
export function ContactDetails({ email, phone }: { email?: string | null; phone?: string | null }) {
    return (
        <>
            {email && <div className="small">{email}</div>}
            {phone && <div className="small">{phone}</div>}
        </>
    );
}

/** "Escribir": opens the person's mail app to write to someone, outside the app — so it is a contact action. */
export function WriteEmail({ email }: { email?: string | null }) {
    if (!email) return null;
    return (
        <a className={actionClass('contact')} href={`mailto:${email}`}>
            {t('common.writeEmail')}
        </a>
    );
}

/**
 * A compact button that shows only an icon; `label` says what it does (tooltip on hover and focus, and the
 * accessible name). Its colour follows the action the icon stands for; pass `action` only for an icon that is not
 * in ICON_ACTIONS.
 */
type IconButtonProps = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'> & {
    icon: IconName;
    label: string;
    action?: Action;
    busy?: boolean;
};

export function IconButton({ icon, label, action, busy = false, className = '', ...props }: IconButtonProps) {
    const tone = action || ICON_ACTIONS[icon] || 'open';
    return (
        <button
            type="button"
            className={`btn btn-action btn-action-${tone} btn-icon ${busy ? 'is-busy' : ''} ${className}`}
            aria-label={label}
            data-tooltip={label}
            aria-busy={busy || undefined}
            {...props}
            disabled={busy || props.disabled}
        >
            <Icon name={icon} size={16} />
        </button>
    );
}

interface FieldProps {
    label: ReactNode;
    error?: string | null;
    hint?: ReactNode;
    optional?: boolean;
    className?: string;
    children?: ReactNode;
}

export function Field({ label, error, hint, optional = false, className = '', children }: FieldProps) {
    return (
        <label className={`field ${error ? 'field-invalid' : ''} ${className}`}>
            <span className="field-label">
                {label}
                {optional && <span className="field-optional"> ({t('common.optional')})</span>}
            </span>
            {children}
            {hint && !error && <span className="field-hint">{hint}</span>}
            {error && <span className="field-error">{error}</span>}
        </label>
    );
}

export function Checkbox({ label, checked, onChange }: { label: ReactNode; checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <label className="checkbox">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
            <span>{label}</span>
        </label>
    );
}

export function Alert({ kind = 'info', children, onDismiss }: { kind?: 'info' | 'success' | 'warning' | 'error'; children?: ReactNode; onDismiss?: () => void }) {
    if (!children) return null;
    return (
        <div className={`alert alert-${kind}`} role={kind === 'error' ? 'alert' : 'status'}>
            <span>{children}</span>
            {onDismiss && (
                <button type="button" className="icon-btn" onClick={onDismiss} aria-label={t('common.close')}>
                    ×
                </button>
            )}
        </div>
    );
}

/**
 * status value → colour, shared by badges and row tints so the two can never disagree. Add every new status
 * here: one left out shows as neutral, which reads as "no status" rather than as a colour of its own.
 */
const TONES: Record<string, string> = {
    active: 'success',
    inactive: 'muted',
    // A login not set up yet: the link was sent (invited) or never was (none).
    invited: 'info',
    none: 'warning',
};

export function Badge({ value, children }: { value: string; children?: ReactNode }) {
    return <span className={`badge badge-${TONES[value] || 'neutral'}`}>{children}</span>;
}

/**
 * The colour a status is shown in. Rows and badges share it, so the tint of a row and the badge inside it
 * always agree. Unknown statuses stay neutral rather than picking a colour at random.
 */
export function toneFor(status: string): string {
    return TONES[status] || 'neutral';
}

export function Loading() {
    return (
        <div className="loading" role="status">
            <span className="spinner" aria-hidden="true" />
            {t('common.loading')}
        </div>
    );
}

export function FullPageLoading() {
    return (
        <div className="full-page-center">
            <Loading />
        </div>
    );
}

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
    return (
        <div className="state state-error">
            <p>{errorMessage(error)}</p>
            {onRetry && (
                <Button variant="ghost" onClick={onRetry}>
                    {t('common.retry')}
                </Button>
            )}
        </div>
    );
}

export function EmptyState({ children, action }: { children?: ReactNode; action?: ReactNode }) {
    return (
        <div className="state">
            <p>{children}</p>
            {action}
        </div>
    );
}

/**
 * One line under a page's tab bar saying what the tab is for, in the consultant's words. Every tab has one, so a
 * newcomer never lands on a table without being told what it holds. A tab's own primary action can sit beside it.
 */
export function TabIntro({ children, action }: { children?: ReactNode; action?: ReactNode }) {
    return (
        <div className="tab-intro">
            <p>{children}</p>
            {action}
        </div>
    );
}

export function PageHeader({ title, subtitle, actions }: { title: ReactNode; subtitle?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="page-header">
            <div>
                <h1>{title}</h1>
                {subtitle && <p className="page-subtitle">{subtitle}</p>}
            </div>
            {actions && <div className="page-actions">{actions}</div>}
        </div>
    );
}

export function Toolbar({ children }: { children?: ReactNode }) {
    return <div className="toolbar">{children}</div>;
}

export function SearchInput({ value = '', onChange, placeholder }: { value?: string; onChange: (value: string) => void; placeholder?: string }) {
    const [text, setText] = useState(value);
    // Cleared from outside ("Ver todos" under an empty list): show it, rather than keep filtering by stale text.
    // Adjusted while rendering, not in an effect, so the box never paints the stale text first.
    const [shown, setShown] = useState(value);
    if (value !== shown) {
        setShown(value);
        setText(value);
    }
    useEffect(() => {
        const timer = setTimeout(() => text !== value && onChange(text), 300);
        return () => clearTimeout(timer);
    }, [text]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <input
            type="search"
            className="search"
            value={text}
            placeholder={placeholder || t('common.search')}
            onChange={(e) => setText(e.target.value)}
        />
    );
}

/**
 * Tabs switch between different tables or ranges, never filter one.
 *
 * - `variant="pill"` (default): a compact segmented control inside a section (the dashboard's 30/60/90 days).
 * - `variant="page"`: the page's own views, as a tab bar across the page right under its header. Give it an `id`
 *   and wrap the content in <TabPanel id={id} value={value}> so assistive technology links the two. Options may
 *   carry an `icon`.
 */
interface TabsProps<V extends string | number> {
    value: V;
    options: ReadonlyArray<{ value: V; label: ReactNode; icon?: IconName }>;
    onChange: (value: V) => void;
    variant?: 'pill' | 'page';
    id?: string;
    label?: string;
}

export function Tabs<V extends string | number>({ value, options, onChange, variant = 'pill', id, label }: TabsProps<V>) {
    const select = (index: number) => {
        const option = options[(index + options.length) % options.length];
        if (!option) return;
        onChange(option.value);
        document.getElementById(id ? `${id}-tab-${option.value}` : '')?.focus();
    };

    return (
        <div className={`tabs tabs-${variant}`} role="tablist" aria-label={label}>
            {options.map((option, index) => {
                const active = option.value === value;
                return (
                    <button
                        key={option.value}
                        id={id ? `${id}-tab-${option.value}` : undefined}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        aria-controls={id ? `${id}-panel` : undefined}
                        tabIndex={active ? 0 : -1}
                        className={`tab ${active ? 'tab-active' : ''}`}
                        onClick={() => onChange(option.value)}
                        // Arrow keys move between tabs, as in any tab bar.
                        onKeyDown={(event) => {
                            if (event.key === 'ArrowRight') select(index + 1);
                            if (event.key === 'ArrowLeft') select(index - 1);
                        }}
                    >
                        {option.icon && <Icon name={option.icon} size={18} />}
                        <span>{option.label}</span>
                    </button>
                );
            })}
        </div>
    );
}

/** The content a page-level <Tabs id=…> switches. */
export function TabPanel({ id, value, children }: { id: string; value: string | number; children?: ReactNode }) {
    return (
        <div id={`${id}-panel`} role="tabpanel" aria-labelledby={`${id}-tab-${value}`} className="tab-panel-page">
            {children}
        </div>
    );
}

export function Pager({ data, onPage }: { data: Pick<ListPage<unknown>, 'total' | 'perPage' | 'page'>; onPage: (page: number) => void }) {
    const pages = Math.max(1, Math.ceil(data.total / data.perPage));
    if (pages <= 1) return null;
    return (
        <div className="pager">
            <Button variant="ghost" size="sm" disabled={data.page <= 1} onClick={() => onPage(data.page - 1)}>
                {t('common.previous')}
            </Button>
            <span>{t('common.pageOf', { page: data.page, pages })}</span>
            <Button variant="ghost" size="sm" disabled={data.page >= pages} onClick={() => onPage(data.page + 1)}>
                {t('common.next')}
            </Button>
        </div>
    );
}

/**
 * A table row tinted by the item's status, so a list can be read by colour before it is read by word.
 *
 * `status` is the item's own status value (the same one the Badge gets); pass nothing for data that has no
 * status and the row stays plain. `muted` greys out a row that is disabled rather than in a status.
 */
type RowProps = HTMLAttributes<HTMLTableRowElement> & {
    status?: string | null;
    /** Required with `status`: the status in words. */
    label?: string | null;
    muted?: boolean;
};

export function Row({ status, label, muted = false, className = '', children, ...props }: RowProps) {
    const tone = status ? toneFor(status) : null;
    const classes = [tone && `row-tone row-tone-${tone}`, muted && 'row-inactive', className].filter(Boolean).join(' ');

    // Tables have no status column: the colour is the status. `label` says it in words, as a tooltip on the row and
    // as hidden text at the start of the first cell, so a screen reader announces it with the row.
    if (tone && !label && process.env.NODE_ENV !== 'production') {
        console.warn(`<Row status="${status}"> needs a label: the colour cannot be the only way to tell the status.`);
    }
    const cells = React.Children.toArray(children);
    const first = cells[0];
    if (label && React.isValidElement<{ children?: ReactNode }>(first)) {
        cells[0] = React.cloneElement(first, {}, <span className="visually-hidden">{`${label}: `}</span>, first.props.children);
    }

    return (
        <tr className={classes} title={label || undefined} {...props}>
            {cells}
        </tr>
    );
}

/**
 * The last cell of every row: everything the user can click lives here, under the "Acciones" header.
 * Icon buttons for actions an icon says on its own (edit, disable, download); worded buttons for the rest.
 */
export function Actions({ children }: { children?: ReactNode }) {
    return (
        <td className="actions">
            <div className="row-actions">{children}</div>
        </td>
    );
}

/**
 * The standard filter bar above a table: a search box, then one dropdown per filter.
 *
 * Every list has one, so people look for the same control in the same place on every screen.
 */
export interface Filter {
    name: string;
    label: ReactNode;
    value: string | number | undefined;
    onChange: (value: string) => void;
    options: ReadonlyArray<SelectOption>;
}

interface FilterBarProps {
    search?: string | number;
    onSearch?: (term: string) => void;
    searchPlaceholder?: string;
    filters?: ReadonlyArray<Filter>;
    children?: ReactNode;
}

export function FilterBar({ search, onSearch, searchPlaceholder, filters = [], children }: FilterBarProps) {
    return (
        <div className="toolbar">
            {/* Labelled like the dropdowns beside it, so every control in the bar says what it is and they line up. */}
            {onSearch && (
                <label className="filter-select filter-search">
                    <span className="filter-select-label">{t('common.searchLabel')}</span>
                    <SearchInput value={search === undefined ? '' : String(search)} onChange={onSearch} placeholder={searchPlaceholder} />
                </label>
            )}
            {filters.map((filter) => (
                <Select
                    key={filter.name}
                    label={filter.label}
                    value={filter.value}
                    options={filter.options}
                    onChange={filter.onChange}
                />
            ))}
            {children}
        </div>
    );
}

/**
 * The key to a table's row colours. Shown above any table whose rows are tinted, because colour on its own is
 * not something everyone can read.
 */
export function RowLegend({ statuses }: { statuses?: ReadonlyArray<{ value: string; label: ReactNode }> | null }) {
    if (!statuses || statuses.length === 0) return null;

    return (
        <p className="row-legend small muted">
            {/* Says what the chips are: without it they read as checkboxes to click. */}
            <span className="row-legend-title">{t('common.rowLegend')}</span>
            {statuses.map((status) => (
                <span key={status.value} className={`row-legend-item row-legend-${toneFor(status.value)}`}>
                    {status.label}
                </span>
            ))}
        </p>
    );
}

/** A labelled dropdown filter. The label stays visible: a bare select says nothing about what it filters. */
export interface SelectOption {
    value: string | number;
    label: ReactNode;
}

interface SelectProps {
    label: ReactNode;
    value: string | number | undefined;
    options: ReadonlyArray<SelectOption>;
    onChange: (value: string) => void;
}

export function Select({ label, value, options, onChange }: SelectProps) {
    return (
        <label className="filter-select">
            <span className="filter-select-label">{label}</span>
            <select value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

/**
 * The table itself: the data columns a page names, plus the actions column added last so every table in the app
 * calls it the same thing and puts it in the same place.
 *
 * ListView wraps this for a useList() result. A table whose rows are not a paginated list — a fixed set of rows,
 * a report's series — uses DataTable directly: hand-rolling <table> is how tables drift apart (see CLAUDE.md).
 * `actions={false}` for a table nothing can be done to.
 */
interface DataTableProps<T> {
    columns: ReadonlyArray<ReactNode>;
    rows: ReadonlyArray<T>;
    renderRow: (row: T, index: number) => ReactNode;
    actions?: boolean;
    className?: string;
}

export function DataTable<T>({ columns, rows, renderRow, actions = true, className = '' }: DataTableProps<T>) {
    const headers = actions ? [...columns, t('common.actions')] : columns;

    return (
        <div className={`table-wrap ${className}`.trim()}>
            <table className="table">
                <thead>
                    <tr>
                        {headers.map((column, index) => (
                            <th key={typeof column === 'string' && column ? column : index} className={actions && index === headers.length - 1 ? 'col-actions' : undefined}>
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>{rows.map(renderRow)}</tbody>
            </table>
        </div>
    );
}

/**
 * Loading / error / empty / table + pager for a useList() result.
 *
 * An empty list is the first thing a new consultant sees, so it always points somewhere:
 * - `showAll` is the value every filter takes on "Todos" (`{ status: '' }`). While the search box or any of them
 *   narrows the list, the empty state says so (`empty`) and offers "Ver todos", which clears them.
 * - Otherwise the list is empty for real: `emptyAll` says what the section is for and `emptyAction` (usually the
 *   table's primary button) sits under it. Without `emptyAll`, `empty` is shown either way.
 */
/** What ListView needs of a useList() result. */
export interface ListResult<T, F extends Filters = Filters> {
    data: ListPage<T> | null;
    error: unknown;
    loading: boolean;
    reload: () => void;
    filters: F;
    update: (patch: Partial<F>) => void;
    setPage: (page: number) => void;
}

interface ListViewProps<T, F extends Filters> {
    list: ListResult<T, F>;
    columns: ReadonlyArray<ReactNode>;
    renderRow: (row: T, index: number) => ReactNode;
    empty?: ReactNode;
    showAll?: Partial<F>;
    emptyAll?: ReactNode;
    emptyAction?: ReactNode;
    actions?: boolean;
}

export function ListView<T, F extends Filters>({ list, columns, renderRow, empty, showAll, emptyAll, emptyAction, actions = true }: ListViewProps<T, F>) {
    if (list.error) return <ErrorState error={list.error} onRetry={list.reload} />;
    if (!list.data) return <Loading />;
    if (list.data.items.length === 0) {
        const all = { ...(list.filters.q !== undefined ? { q: '' } : {}), ...(showAll || {}) } as Partial<F>;
        const filtered = Object.entries(all).some(([name, value]) => (list.filters[name] ?? '') !== value);
        if (filtered && showAll) {
            return (
                <EmptyState
                    action={
                        <Button variant="ghost" onClick={() => list.update(all)}>
                            {t('common.showAll')}
                        </Button>
                    }
                >
                    {empty}
                </EmptyState>
            );
        }
        return <EmptyState action={filtered ? null : emptyAction}>{filtered ? empty : emptyAll || empty}</EmptyState>;
    }

    return (
        <>
            <DataTable
                columns={columns}
                rows={list.data.items}
                renderRow={renderRow}
                actions={actions}
                className={list.loading ? 'is-reloading' : ''}
            />
            <Pager data={list.data} onPage={list.setPage} />
        </>
    );
}

/** size: "wide" for content that needs room, e.g. a document preview. */
interface ModalProps {
    title: string;
    onClose: () => void;
    size?: 'wide' | 'lg';
    children?: ReactNode;
}

export function Modal({ title, onClose, size, children }: ModalProps) {
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        document.body.classList.add('modal-open');
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.classList.remove('modal-open');
        };
    }, [onClose]);

    return (
        <div className="modal-backdrop" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
            <div className={`modal ${size ? `modal-${size}` : ''}`} role="dialog" aria-modal="true" aria-label={title}>
                <header className="modal-header">
                    <h2>{title}</h2>
                    <button type="button" className="icon-btn" onClick={onClose} aria-label={t('common.close')}>
                        ×
                    </button>
                </header>
                <div className="modal-body">{children}</div>
            </div>
        </div>
    );
}

interface FormModalProps extends ModalProps {
    onSubmit: () => void;
    /** A useSubmit() result: its busy state and form-level error. */
    submit: { busy: boolean; formError: string | null };
    submitLabel?: ReactNode;
}

export function FormModal({ title, onClose, onSubmit, submit, submitLabel, size, children }: FormModalProps) {
    return (
        <Modal title={title} onClose={onClose} size={size}>
            <form
                noValidate
                onSubmit={(event) => {
                    event.preventDefault();
                    onSubmit();
                }}
            >
                <Alert kind="error">{submit.formError}</Alert>
                <div className="form-grid">{children}</div>
                <div className="form-actions">
                    <Button variant="ghost" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button type="submit" busy={submit.busy}>
                        {submitLabel || t('common.save')}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

/** A button that opens a file picker and hands the chosen file(s) to onFiles. */
interface UploadButtonProps {
    accept?: string;
    multiple?: boolean;
    label: ReactNode;
    busy?: boolean;
    onFiles: (files: File[]) => void;
}

export function UploadButton({ accept, multiple = false, label, busy, onFiles }: UploadButtonProps) {
    const input = useRef<HTMLInputElement>(null);
    return (
        <>
            <input
                ref={input}
                type="file"
                hidden
                accept={accept}
                multiple={multiple}
                onChange={(event) => {
                    const files = Array.from(event.target.files || []);
                    event.target.value = '';
                    if (files.length > 0) onFiles(files);
                }}
            />
            <ActionButton action="setup" busy={busy} onClick={() => input.current?.click()}>
                {label}
            </ActionButton>
        </>
    );
}

/** Opens the attachment in a new tab. With `icon`, renders as an IconButton labelled `label`. */
interface AttachmentButtonProps {
    attachment: AttachmentLink | null | undefined;
    label?: string;
    icon?: IconName;
    onError?: (message: string) => void;
}

export function AttachmentButton({ attachment, label, icon, onError }: AttachmentButtonProps) {
    const [busy, setBusy] = useState(false);
    if (!attachment) return null;

    const open = async () => {
        setBusy(true);
        try {
            await openAttachment(attachment);
        } catch (error) {
            onError?.(errorMessage(error));
        } finally {
            setBusy(false);
        }
    };

    if (icon) {
        return <IconButton icon={icon} label={label || attachment.originalFilename} busy={busy} onClick={open} />;
    }

    return (
        <ActionButton action="file" busy={busy} title={attachment.originalFilename} onClick={open}>
            {label || attachment.originalFilename}
        </ActionButton>
    );
}

/** A [term, description] pair; a falsy entry is skipped, so an optional row can be written `cond && [...]`. */
export type DefinitionItem = readonly [ReactNode, ReactNode] | false | '' | 0 | null | undefined;

export function DefinitionList({ items }: { items: ReadonlyArray<DefinitionItem> }) {
    return (
        <dl className="definitions">
            {items.filter((item): item is readonly [ReactNode, ReactNode] => Boolean(item)).map(([term, description], index) => (
                <div key={typeof term === 'string' ? term : index}>
                    <dt>{term}</dt>
                    <dd>{description}</dd>
                </div>
            ))}
        </dl>
    );
}
