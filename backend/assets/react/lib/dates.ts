/**
 * Dates as the account types and reads them, and as the API carries them (YYYY-MM-DD). Kept apart from the
 * DateInput component so the rules — order by locale, no rolling 31/02 over into March — are unit-tested alone.
 */

export type DatePart = 'day' | 'month' | 'year';

/**
 * The order the account's locale writes a date in: ['day', 'month', 'year'] for es-CO, ['month', 'day', 'year']
 * for en-US. Taken from Intl rather than assumed, so a new locale needs nothing here.
 */
export function partsOrder(locale: string): DatePart[] {
    try {
        return new Intl.DateTimeFormat(locale, { day: '2-digit', month: '2-digit', year: 'numeric' })
            .formatToParts(new Date(Date.UTC(2000, 10, 22)))
            .map((part) => part.type)
            .filter((type): type is DatePart => type === 'day' || type === 'month' || type === 'year');
    } catch {
        return ['day', 'month', 'year'];
    }
}

/** YYYY-MM-DD → the text the account reads ("22/11/2000"); '' for anything else. */
export function toText(iso: string | null | undefined, order: DatePart[]): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso ?? '');
    if (!match) return '';
    const parts: Record<DatePart, string> = { year: match[1]!, month: match[2]!, day: match[3]! };
    return order.map((type) => parts[type]).join('/');
}

/**
 * The text typed → YYYY-MM-DD; '' for an empty box, null while it is not a whole, real date yet ("31/02/2026" is
 * null, not the 3rd of March). Any of / - . or a space separates the parts.
 */
export function toIso(text: string, order: DatePart[]): string | null {
    const trimmed = text.trim();
    if (trimmed === '') return '';
    const pieces = trimmed.split(/[/\-.\s]+/);
    if (pieces.length !== 3) return null;
    const parts = Object.fromEntries(order.map((type, index) => [type, pieces[index] ?? ''])) as Record<DatePart, string>;
    if (!/^\d{4}$/.test(parts.year) || !/^\d{1,2}$/.test(parts.month) || !/^\d{1,2}$/.test(parts.day)) return null;
    const [year, month, day] = [Number(parts.year), Number(parts.month), Number(parts.day)];
    const date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null;
    return `${parts.year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}
