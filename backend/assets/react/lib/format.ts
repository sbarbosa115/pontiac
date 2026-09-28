import type { Money } from './types';

// Locale-aware formatting. Money stays a decimal string end to end; it is never parsed into a float.

/** The account's group and decimal separators, e.g. { group: '.', decimal: ',' } for es-CO. */
export function separators(locale: string): { group: string; decimal: string } {
    const parts = new Intl.NumberFormat(locale).formatToParts(12345.6);
    return {
        group: parts.find((p) => p.type === 'group')?.value ?? ',',
        decimal: parts.find((p) => p.type === 'decimal')?.value ?? '.',
    };
}

/** { amount: "2500000.00", currency: "COP" } → "$ 2.500.000" (es-CO). */
export function formatMoney(money: Money | null | undefined, locale: string): string {
    if (!money) return '—';
    const { amount, currency } = money;
    const whole = !amount.includes('.') || /\.0+$/.test(amount);
    try {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            minimumFractionDigits: whole ? 0 : 2,
            maximumFractionDigits: whole ? 0 : 2,
        }).format(amount as Intl.StringNumericLiteral); // strings are formatted exactly by modern Intl
    } catch {
        return `${amount} ${currency}`;
    }
}

/** "2500000.50" → "2.500.000,50" for an editable input in the account's locale. */
export function formatAmountForInput(amount: string | number | null | undefined, locale: string): string {
    if (amount === null || amount === undefined || amount === '') return '';
    const [integer = '', fraction = ''] = String(amount).split('.');
    const { group, decimal } = separators(locale);
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, group);
    return /^0*$/.test(fraction) ? grouped : `${grouped}${decimal}${fraction}`;
}

/**
 * User input in the account's locale → API decimal string.
 * Returns null when empty and undefined when invalid.
 */
export function parseAmountInput(input: string | number | null | undefined, locale: string): string | null | undefined {
    const { group, decimal } = separators(locale);
    let value = String(input ?? '').trim().replace(/[\s\u00A0\u202F$]/g, '');
    if (value === '') return null;
    value = value.split(group).join('');
    if (decimal !== '.') value = value.replace(decimal, '.');
    return /^\d{1,13}(\.\d{1,2})?$/.test(value) ? value : undefined;
}

/**
 * An average the app worked out (revenue per client): to the whole unit, so it does not show cents nobody
 * charged ("$ 1.392.857", not "$ 1.392.857,14").
 */
export function formatMoneyRounded(money: Money | null | undefined, locale: string): string {
    if (!money) return '—';
    return formatMoney({ ...money, amount: String(Math.round(Number(money.amount))) }, locale);
}

/** "45000.50" (an API decimal string) → 4500050, so the UI can add amounts up without floats. */
export function amountToCents(amount: string | number): number {
    const [integer, fraction = ''] = String(amount).split('.');
    return Number(integer) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2));
}

/** 4500050 → "45000.50". */
export function centsToAmount(cents: number): string {
    return `${Math.trunc(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;
}

/** A rate from the API (0.9312) → "93,1 %". Null means nobody can state it, not zero. */
export function formatPercent(rate: number | null | undefined, locale: string, decimals = 1): string {
    if (rate === null || rate === undefined) return '—';
    return new Intl.NumberFormat(locale, { style: 'percent', minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(rate);
}

/** A month, "2026-09" → "sept 2026". */
export function formatMonth(month: string | null | undefined, locale: string): string {
    if (!month) return '—';
    return new Intl.DateTimeFormat(locale, { month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${month}-01T00:00:00Z`));
}

/** "2026-09-13" (a calendar date, no timezone) → "13 sept 2026". */
export function formatDate(date: string | null | undefined, locale: string): string {
    if (!date) return '—';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`));
}

/** ISO timestamp → date and time in the account's timezone. */
export function formatDateTime(iso: string | null | undefined, locale: string, timeZone?: string): string {
    if (!iso) return '—';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short', timeZone }).format(new Date(iso));
}

/** Today's date as YYYY-MM-DD in a given timezone. */
export function todayIn(timeZone: string): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}

export function daysBetween(from: string, to: string): number {
    return Math.round((Date.parse(`${to}T00:00:00Z`) - Date.parse(`${from}T00:00:00Z`)) / 86_400_000);
}

export function formatFileSize(bytes: number, locale: string): string {
    const units = ['byte', 'kilobyte', 'megabyte'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }
    // Spanish's short form of "byte" is the singular "byte" ("193 byte"); the long one agrees with the number.
    const unitDisplay = unit === 0 ? 'long' : 'short';
    return new Intl.NumberFormat(locale, { style: 'unit', unit: units[unit], unitDisplay, maximumFractionDigits: 1 }).format(value);
}
