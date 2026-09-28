import React, { useEffect, useRef, useState } from 'react';
import { useLocaleSettings } from '../lib/auth';
import { t } from '../lib/i18n';
import { partsOrder, toIso, toText } from '../lib/dates';
import Icon from './Icon';

/**
 * Every date field in the app. A native <input type="date"> is written in the *browser's* language — mm/dd/yyyy
 * on an English Chrome — while the rest of the screen writes dates the account's way; this one is typed in the
 * account's order (dd/mm/aaaa in Colombia), with the browser's calendar one click away.
 *
 * A drop-in for the native input: `value` and the `onChange` event carry YYYY-MM-DD, so `{...form.bind('x')}`
 * works as before. A date typed halfway (or impossible) reaches the form as '', so the API's own validation
 * answers it on submit. `min`/`max` bound the calendar; the API still checks typed dates.
 */
/** What a form's onChange receives: shaped like an input event, so `form.bind()` needs nothing special. */
export interface DateChange {
    target: { name?: string; value: string; type: 'date' };
}

export interface DateInputProps {
    value?: string;
    onChange?: (event: DateChange) => void;
    name?: string;
    min?: string;
    max?: string;
    required?: boolean;
    disabled?: boolean;
    id?: string;
}

export default function DateInput({ value = '', onChange, name, min, max, required, disabled, id }: DateInputProps) {
    const { locale } = useLocaleSettings();
    const order = partsOrder(locale);
    const [text, setText] = useState(() => toText(value, order));
    const emitted = useRef(value);
    const picker = useRef<HTMLInputElement>(null);

    // A value set from outside (a form reset, the calendar, an edit modal opening) replaces what is typed; the
    // echo of what this box just sent does not, or typing "1/" would be wiped as soon as it emitted ''.
    useEffect(() => {
        if (value !== emitted.current) {
            emitted.current = value;
            setText(toText(value, order));
        }
    }, [value]); // eslint-disable-line react-hooks/exhaustive-deps

    const emit = (iso: string) => {
        emitted.current = iso;
        onChange?.({ target: { name, value: iso, type: 'date' } });
    };

    const placeholder = order.map((type) => t(`common.datePart.${type}`)).join('/');

    return (
        <span className="date-input">
            <input
                id={id}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                name={name}
                value={text}
                placeholder={placeholder}
                required={required}
                disabled={disabled}
                onChange={(event) => {
                    setText(event.target.value);
                    const iso = toIso(event.target.value, order);
                    emit(iso ?? '');
                }}
                onBlur={() => {
                    const iso = toIso(text, order);
                    if (iso) setText(toText(iso, order));
                }}
            />
            <button
                type="button"
                className="date-input-picker"
                aria-label={t('common.pickDate')}
                data-tooltip={t('common.pickDate')}
                disabled={disabled}
                onClick={() => {
                    try {
                        picker.current?.showPicker();
                    } catch {
                        picker.current?.focus();
                    }
                }}
            >
                <Icon name="calendar" size={16} />
            </button>
            {/* The browser's own calendar, opened by the button above; never shown or tabbed to itself. */}
            <input
                ref={picker}
                type="date"
                className="date-input-native"
                tabIndex={-1}
                aria-hidden="true"
                value={toIso(text, order) || ''}
                min={min}
                max={max}
                onChange={(event) => {
                    setText(toText(event.target.value, order));
                    emit(event.target.value);
                }}
            />
        </span>
    );
}
