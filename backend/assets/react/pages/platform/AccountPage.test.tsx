import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React, { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { FeatureChecks } from './AccountPage';

function Harness({ initial }: { initial: string[] }) {
    const [value, setValue] = useState(initial);
    return (
        <>
            <FeatureChecks value={value} onChange={setValue} />
            <output data-testid="value">{value.join(',')}</output>
        </>
    );
}

describe('FeatureChecks', () => {
    it('turns features on and off, keeping them in one order whatever order they are ticked in', async () => {
        render(<Harness initial={['portal']} />);

        expect(screen.getByLabelText('Portal de clientes')).toBeChecked();
        await userEvent.click(screen.getByLabelText('Flujos'));
        await userEvent.click(screen.getByLabelText('Agenda'));
        expect(screen.getByTestId('value')).toHaveTextContent('booking,portal,flows');

        await userEvent.click(screen.getByLabelText('Portal de clientes'));
        expect(screen.getByTestId('value')).toHaveTextContent('booking,flows');
    });
});
