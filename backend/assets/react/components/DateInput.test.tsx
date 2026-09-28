import React, { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import DateInput, { type DateChange } from './DateInput';

// The account's locale comes from /api/me in the app; here it is Colombia's.
vi.mock('../lib/auth', () => ({ useLocaleSettings: () => ({ locale: 'es-CO' }) }));

/** A form holding the value, the way form.bind() does, so the test sees what would be submitted. */
function Form({ initial = '' }: { initial?: string }) {
    const [value, setValue] = useState(initial);
    return (
        <>
            <DateInput name="startDate" value={value} onChange={(event: DateChange) => setValue(event.target.value)} />
            <output data-testid="submitted">{value}</output>
        </>
    );
}

describe('DateInput', () => {
    it('shows the account order in the placeholder', () => {
        render(<Form />);
        expect(screen.getByRole('textbox')).toHaveAttribute('placeholder', 'dd/mm/aaaa');
    });

    it('sends YYYY-MM-DD as soon as a whole date is typed, and tidies the text on blur', async () => {
        render(<Form />);
        const box = screen.getByRole('textbox');
        await userEvent.type(box, '5/3/2027');
        expect(screen.getByTestId('submitted')).toHaveTextContent('2027-03-05');
        await userEvent.tab();
        expect(box).toHaveValue('05/03/2027');
    });

    it('sends nothing for an impossible date, so the API reports it', async () => {
        render(<Form />);
        await userEvent.type(screen.getByRole('textbox'), '31/02/2027');
        expect(screen.getByTestId('submitted')).toBeEmptyDOMElement();
    });

    it('keeps what is being typed while the date is still incomplete', async () => {
        render(<Form />);
        const box = screen.getByRole('textbox');
        await userEvent.type(box, '5/');
        expect(box).toHaveValue('5/');
    });

    it('shows a value set from outside, such as an edit form opening', () => {
        render(<Form initial="2026-09-30" />);
        expect(screen.getByRole('textbox')).toHaveValue('30/09/2026');
    });
});
