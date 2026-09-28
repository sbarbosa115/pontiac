import React, { useState } from 'react';
import { api } from '../../../lib/api';
import { useApi } from '../../../lib/hooks';
import type { Get } from '../../../lib/types';
import { t } from '../../../lib/i18n';
import { type Catalog, emptyItem, type FieldSpec, type FieldValue, move, type PageContent, setSectionField, updateSection } from '../../../lib/pages';
import DateInput from '../../../components/DateInput';
import { ImageField } from '../../../components/MediaPicker';
import { ActionButton, Checkbox, Field, IconButton } from '../../../components/ui';

interface Props {
    catalog: Catalog;
    content: PageContent;
    onChange: (content: PageContent) => void;
    errors: Record<string, string>;
}

/**
 * Contenido: the template's sections in page order. Each can be turned off and moved up or down; opening one edits
 * its fields. Sections cannot be added or removed (the template decides which there are).
 */
export default function SectionsEditor({ catalog, content, onChange, errors }: Props) {
    const [open, setOpen] = useState<string | null>(content.sections[0]?.id ?? null);

    return (
        <div className="section-list">
            {content.sections.map((section, index) => {
                const type = catalog.sectionTypes.find((candidate) => candidate.type === section.type);
                const path = `sections[${index}]`;
                const hasErrors = Object.keys(errors).some((field) => field.startsWith(path + '.'));
                const expanded = open === section.id;
                return (
                    <div key={section.id} className={`section-card${section.enabled ? '' : ' is-off'}${hasErrors ? ' has-errors' : ''}`}>
                        <div className="section-card-header">
                            <button type="button" className="section-card-title" aria-expanded={expanded} onClick={() => setOpen(expanded ? null : section.id)}>
                                <span className="strong">{t(`pageEditor.sectionType.${section.type}`)}</span>
                                <span className="small muted">{summary(section.fields)}</span>
                            </button>
                            <Checkbox label={t('pageEditor.visible')} checked={section.enabled} onChange={(enabled) => onChange(updateSection(content, index, { enabled }))} />
                            <IconButton icon="chevronUp" label={t('pageEditor.moveUp')} disabled={index === 0} onClick={() => onChange({ ...content, sections: move(content.sections, index, index - 1) })} />
                            <IconButton icon="chevronDown" label={t('pageEditor.moveDown')} disabled={index === content.sections.length - 1} onClick={() => onChange({ ...content, sections: move(content.sections, index, index + 1) })} />
                        </div>
                        {expanded && type && (
                            <div className="section-card-body">
                                {type.fields.map((spec) => (
                                    <SpecField
                                        key={spec.name}
                                        spec={spec}
                                        value={section.fields[spec.name] ?? null}
                                        path={`${path}.fields.${spec.name}`}
                                        errors={errors}
                                        onChange={(value) => onChange(setSectionField(content, index, spec.name, value))}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

/** The first bit of text of a section, so a closed card says which one it is. */
function summary(fields: Record<string, FieldValue>): string {
    const text = typeof fields.heading === 'string' && fields.heading ? fields.heading : typeof fields.body === 'string' ? fields.body : '';
    return text.length > 60 ? `${text.slice(0, 60)}…` : text;
}

interface SpecFieldProps {
    spec: FieldSpec;
    value: FieldValue;
    path: string;
    errors: Record<string, string>;
    onChange: (value: FieldValue) => void;
}

function SpecField({ spec, value, path, errors, onChange }: SpecFieldProps) {
    const label = t(`pageEditor.field.${spec.name}`);
    const error = errors[path];
    const optional = !spec.required;

    if (spec.kind === 'items') {
        const items = Array.isArray(value) ? value.filter((item): item is Record<string, string> => typeof item === 'object') : [];
        return (
            <fieldset className="items-field">
                <legend>{label}</legend>
                {error && <span className="field-error">{error}</span>}
                {items.map((item, index) => (
                    <div key={index} className="items-field-item">
                        <div className="items-field-inputs">
                            {spec.fields.map((itemSpec) => (
                                <TextField
                                    key={itemSpec.name}
                                    label={t(`pageEditor.field.${itemSpec.name}`)}
                                    multiline={itemSpec.kind === 'textarea'}
                                    max={itemSpec.max ?? undefined}
                                    optional={!itemSpec.required}
                                    error={errors[`${path}[${index}].${itemSpec.name}`]}
                                    value={item[itemSpec.name] ?? ''}
                                    onChange={(text) => onChange(items.map((current, i) => (i === index ? { ...current, [itemSpec.name]: text } : current)))}
                                />
                            ))}
                        </div>
                        <div className="items-field-actions">
                            <IconButton icon="chevronUp" label={t('pageEditor.moveUp')} disabled={index === 0} onClick={() => onChange(move(items, index, index - 1))} />
                            <IconButton icon="chevronDown" label={t('pageEditor.moveDown')} disabled={index === items.length - 1} onClick={() => onChange(move(items, index, index + 1))} />
                            <IconButton icon="close" label={t('pageEditor.removeItem')} onClick={() => onChange(items.filter((_, i) => i !== index))} />
                        </div>
                    </div>
                ))}
                <ActionButton action="setup" disabled={items.length >= (spec.maxItems ?? 0)} onClick={() => onChange([...items, emptyItem(spec)])}>
                    {t('pageEditor.addItem')}
                </ActionButton>
            </fieldset>
        );
    }

    if (spec.kind === 'image') {
        return (
            <Field label={label} error={error} optional>
                <ImageField value={typeof value === 'string' ? value : null} onChange={onChange} />
            </Field>
        );
    }

    if (spec.kind === 'plans') {
        return <PlansField label={label} error={error} max={spec.maxItems ?? 3} value={Array.isArray(value) ? value.filter((id): id is string => typeof id === 'string') : []} onChange={onChange} />;
    }

    if (spec.kind === 'plan') {
        return <PlanField label={label} error={error} value={typeof value === 'string' ? value : ''} onChange={onChange} />;
    }

    if (spec.kind === 'date') {
        return (
            <Field label={label} error={error} optional>
                <DateInput value={typeof value === 'string' ? value : ''} onChange={(event) => onChange(event.target.value || null)} />
            </Field>
        );
    }

    return (
        <TextField
            label={label}
            multiline={spec.kind === 'textarea'}
            type={spec.kind === 'url' ? 'url' : 'text'}
            max={spec.max ?? undefined}
            optional={optional}
            hint={spec.kind === 'url' ? t(`pageEditor.hint.${spec.name}`) : undefined}
            error={error}
            value={typeof value === 'string' ? value : ''}
            onChange={onChange}
        />
    );
}

/** The free plan a Reserva section books: only an active free plan can be booked straight from a page. */
function PlanField({ label, error, value, onChange }: { label: string; error?: string; value: string; onChange: (value: FieldValue) => void }) {
    const plans = useApi(() => api.get<Get<'/api/admin/plans/all'>>('/api/admin/plans/all'), []);
    const bookable = (plans.data?.items ?? []).filter((plan) => plan.free && (plan.active || plan.id === value));

    return (
        <Field label={label} error={error} hint={plans.data && bookable.length === 0 ? t('pageEditor.hint.noFreePlan') : t('pageEditor.hint.planId')}>
            <select value={value} onChange={(event) => onChange(event.target.value || null)}>
                <option value="">{t('pageEditor.pickPlan')}</option>
                {bookable.map((plan) => (
                    <option key={plan.id} value={plan.id}>
                        {plan.active ? plan.name : `${plan.name} (${t('common.inactive')})`}
                    </option>
                ))}
            </select>
        </Field>
    );
}

/** The paid plans a Precios y pago section sells: one to `max` active paid plans, in the order ticked. */
function PlansField({ label, error, max, value, onChange }: { label: string; error?: string; max: number; value: string[]; onChange: (value: FieldValue) => void }) {
    const plans = useApi(() => api.get<Get<'/api/admin/plans/all'>>('/api/admin/plans/all'), []);
    const paid = (plans.data?.items ?? []).filter((plan) => !plan.free && (plan.active || value.includes(plan.id)));
    const toggle = (id: string, on: boolean) => onChange(on ? [...value, id] : value.filter((current) => current !== id));

    return (
        <fieldset className="items-field">
            <legend>{label}</legend>
            {error && <span className="field-error">{error}</span>}
            {plans.data && paid.length === 0 && <p className="small muted">{t('pageEditor.hint.noPaidPlan')}</p>}
            {paid.map((plan) => (
                <Checkbox
                    key={plan.id}
                    label={plan.active ? plan.name : `${plan.name} (${t('common.inactive')})`}
                    checked={value.includes(plan.id)}
                    onChange={(on) => (!on || value.length < max ? toggle(plan.id, on) : undefined)}
                />
            ))}
            <span className="field-hint">{t('pageEditor.hint.planIds', { max })}</span>
        </fieldset>
    );
}

interface TextFieldProps {
    label: string;
    value: string;
    onChange: (value: string) => void;
    multiline?: boolean;
    type?: string;
    max?: number;
    optional?: boolean;
    hint?: string;
    error?: string;
}

/** A text box with its character count, so a limit is seen before it is hit. */
export function TextField({ label, value, onChange, multiline = false, type = 'text', max, optional = false, hint, error }: TextFieldProps) {
    const counter = max ? t('pageEditor.counter', { used: value.length, max }) : undefined;
    return (
        <Field label={label} error={error} hint={[hint, counter].filter(Boolean).join(' · ') || undefined} optional={optional}>
            {multiline ? (
                <textarea rows={value.length > 200 ? 6 : 3} value={value} onChange={(event) => onChange(event.target.value)} />
            ) : (
                <input type={type} value={value} onChange={(event) => onChange(event.target.value)} />
            )}
        </Field>
    );
}
