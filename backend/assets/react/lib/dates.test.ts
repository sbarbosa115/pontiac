import { describe, expect, it } from 'vitest';
import { partsOrder, toIso, toText } from './dates';

const DMY = partsOrder('es-CO');

describe('dates', () => {
    it('reads the order of the parts from the locale', () => {
        expect(DMY).toEqual(['day', 'month', 'year']);
        expect(partsOrder('en-US')).toEqual(['month', 'day', 'year']);
    });

    it('turns what the account types into the date the API expects', () => {
        expect(toIso('5/3/2027', DMY)).toBe('2027-03-05');
        expect(toIso('05-03-2027', DMY)).toBe('2027-03-05');
        expect(toIso('  ', DMY)).toBe('');
    });

    it('refuses a date that does not exist instead of rolling it into the next month', () => {
        expect(toIso('31/02/2027', DMY)).toBeNull();
        expect(toIso('29/02/2028', DMY)).toBe('2028-02-29');
        expect(toIso('29/02/2027', DMY)).toBeNull();
    });

    it('waits for a whole date', () => {
        expect(toIso('5/3', DMY)).toBeNull();
        expect(toIso('5/3/27', DMY)).toBeNull();
    });

    it('writes an API date back in the account order', () => {
        expect(toText('2027-03-05', DMY)).toBe('05/03/2027');
        expect(toText('2027-03-05', partsOrder('en-US'))).toBe('03/05/2027');
        expect(toText('', DMY)).toBe('');
    });
});
