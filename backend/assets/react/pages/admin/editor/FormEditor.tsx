import React from 'react';
import { t } from '../../../lib/i18n';
import { type Catalog, type FormField, move, type PageContent } from '../../../lib/pages';
import type { Schema } from '../../../lib/types';
import { ActionButton, Checkbox, Field, IconButton, TabIntro } from '../../../components/ui';

type Category = Schema<'LeadCategoryOutput'>;

interface Props {
    catalog: Catalog;
    content: PageContent;
    categories: Category[];
    onChange: (content: PageContent) => void;
    errors: Record<string, string>;
}

/**
 * Formulario: the questions the page asks besides name, email, phone and consent (always there). A list's answers can
 * say which category the person belongs to.
 */
export default function FormEditor({ catalog, content, categories, onChange, errors }: Props) {
    const fields = content.form.fields;
    const set = (next: FormField[]) => onChange({ ...content, form: { fields: next } });
    const update = (index: number, change: Partial<FormField>) => set(fields.map((field, i) => (i === index ? { ...field, ...change } : field)));

    return (
        <>
            <TabIntro>{t('pageEditor.formIntro')}</TabIntro>
            {errors['form.fields'] && <span className="field-error">{errors['form.fields']}</span>}
            {fields.map((field, index) => {
                const path = `form.fields[${index}]`;
                const optionsError = Object.entries(errors).find(([key]) => key.startsWith(`${path}.options`))?.[1];
                return (
                    <fieldset key={field.key ?? `new-${index}`} className="items-field-item form-field-card">
                        <div className="items-field-inputs">
                            <div className="form-grid">
                                <Field label={t('pageEditor.fieldLabel')} error={errors[`${path}.label`]}>
                                    <input value={field.label} onChange={(event) => update(index, { label: event.target.value })} />
                                </Field>
                                <Field label={t('pageEditor.fieldType')} error={errors[`${path}.type`]}>
                                    <select value={field.type} onChange={(event) => update(index, { type: event.target.value })}>
                                        {catalog.fieldTypes.map((type) => (
                                            <option key={type} value={type}>
                                                {t(`pageEditor.fieldTypes.${type}`)}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                            <Checkbox label={t('pageEditor.fieldRequired')} checked={field.required} onChange={(required) => update(index, { required })} />
                            {field.type === 'select' && (
                                <>
                                    <Field label={t('pageEditor.fieldOptions')} error={optionsError} hint={t('pageEditor.fieldOptionsHint')}>
                                        <textarea
                                            rows={4}
                                            value={field.options.join('\n')}
                                            onChange={(event) => update(index, { options: event.target.value.split('\n') })}
                                        />
                                    </Field>
                                    {categories.length > 0 && field.options.filter(Boolean).length > 0 && (
                                        <div className="option-categories">
                                            <p className="small muted">{t('pageEditor.optionCategoriesIntro')}</p>
                                            {field.options.filter(Boolean).map((option) => (
                                                <label key={option} className="option-category">
                                                    <span>{option}</span>
                                                    <select
                                                        value={field.optionCategories[option] ?? ''}
                                                        onChange={(event) => {
                                                            const next = { ...field.optionCategories };
                                                            if (event.target.value) next[option] = event.target.value;
                                                            else delete next[option];
                                                            update(index, { optionCategories: next });
                                                        }}
                                                    >
                                                        <option value="">{t('pageEditor.noCategory')}</option>
                                                        {categories.map((category) => (
                                                            <option key={category.id} value={category.id}>
                                                                {category.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                        <div className="items-field-actions">
                            <IconButton icon="chevronUp" label={t('pageEditor.moveUp')} disabled={index === 0} onClick={() => set(move(fields, index, index - 1))} />
                            <IconButton icon="chevronDown" label={t('pageEditor.moveDown')} disabled={index === fields.length - 1} onClick={() => set(move(fields, index, index + 1))} />
                            <IconButton icon="close" label={t('pageEditor.removeField')} onClick={() => set(fields.filter((_, i) => i !== index))} />
                        </div>
                    </fieldset>
                );
            })}
            <ActionButton
                action="setup"
                disabled={fields.length >= catalog.maxExtraFields}
                onClick={() => set([...fields, { label: '', type: 'text', required: false, options: [], optionCategories: {} }])}
            >
                {t('pageEditor.addField')}
            </ActionButton>
        </>
    );
}
