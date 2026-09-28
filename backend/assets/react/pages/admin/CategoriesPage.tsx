import React, { useState } from 'react';
import { api } from '../../lib/api';
import { useForm, useList, useSubmit } from '../../lib/hooks';
import { errorMessage, t } from '../../lib/i18n';
import type { Schema } from '../../lib/types';
import { Actions, Alert, Badge, Button, Field, FilterBar, FormModal, IconButton, ListView, Row, RowLegend, TabIntro } from '../../components/ui';

type Category = Schema<'LeadCategoryOutput'>;

export const CATEGORY_COLORS = ['info', 'success', 'warning', 'accent', 'teal', 'indigo', 'rose', 'neutral'] as const;

/** Ajustes › Categorías: how the consultant sorts their prospectos. */
export default function CategoriesPage() {
    const list = useList<Category>('/api/admin/categories', { q: '', includeInactive: '' });
    const [editing, setEditing] = useState<Partial<Category> | null>(null);
    const [busyId, setBusyId] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const toggle = async (category: Category) => {
        setBusyId(category.id);
        setError(null);
        try {
            await (category.active ? api.del(`/api/admin/categories/${category.id}`) : api.post(`/api/admin/categories/${category.id}/enable`));
            list.reload();
        } catch (err) {
            setError(errorMessage(err));
        } finally {
            setBusyId(null);
        }
    };

    const newButton = <Button onClick={() => setEditing({})}>{t('categories.new')}</Button>;

    return (
        <>
            <TabIntro>{t('categories.intro')}</TabIntro>
            <FilterBar
                search={list.filters.q}
                onSearch={(q) => list.update({ q })}
                searchPlaceholder={t('categories.searchPlaceholder')}
                filters={[
                    {
                        name: 'status',
                        label: t('media.status'),
                        value: list.filters.includeInactive ? 'all' : 'active',
                        onChange: (value) => list.update({ includeInactive: value === 'all' ? '1' : '' }),
                        options: [
                            { value: 'active', label: t('media.onlyActive') },
                            { value: 'all', label: t('media.all') },
                        ],
                    },
                ]}
            >
                {newButton}
            </FilterBar>
            <Alert kind="error" onDismiss={() => setError(null)}>
                {error}
            </Alert>
            <RowLegend statuses={[{ value: 'active', label: t('media.active') }, { value: 'inactive', label: t('media.inactive') }]} />
            <ListView
                list={list}
                empty={t('categories.empty')}
                showAll={{ q: '', includeInactive: '' }}
                emptyAll={t('categories.emptyAll')}
                emptyAction={newButton}
                columns={[t('categories.name'), t('categories.color')]}
                renderRow={(category) => (
                    <Row key={category.id} status={category.active ? 'active' : 'inactive'} label={category.active ? t('media.active') : t('media.inactive')} muted={!category.active}>
                        <td className="strong">{category.name}</td>
                        <td>
                            <Badge tone={category.color}>{t(`categories.colors.${category.color}`)}</Badge>
                        </td>
                        <Actions>
                            <IconButton icon="pencil" label={t('common.edit')} onClick={() => setEditing(category)} />
                            <IconButton icon={category.active ? 'ban' : 'check'} label={category.active ? t('common.disable') : t('common.enable')} busy={busyId === category.id} onClick={() => toggle(category)} />
                        </Actions>
                    </Row>
                )}
            />
            {editing && (
                <CategoryForm
                    category={editing}
                    onClose={() => setEditing(null)}
                    onSaved={() => {
                        setEditing(null);
                        list.reload();
                    }}
                />
            )}
        </>
    );
}

function CategoryForm({ category, onClose, onSaved }: { category: Partial<Category>; onClose: () => void; onSaved: () => void }) {
    const form = useForm({ name: category.name ?? '', color: category.color ?? 'info' });
    const submit = useSubmit();

    const save = async () => {
        const result = await submit.run(() => (category.id ? api.put(`/api/admin/categories/${category.id}`, form.values) : api.post('/api/admin/categories', form.values)));
        if (result.ok) onSaved();
    };

    return (
        <FormModal title={category.id ? t('categories.edit') : t('categories.new')} onClose={onClose} onSubmit={save} submit={submit}>
            <Field label={t('categories.name')} error={submit.errors.name}>
                <input {...form.bind('name')} autoFocus maxLength={80} />
            </Field>
            <fieldset className="color-picker">
                <legend>{t('categories.color')}</legend>
                {CATEGORY_COLORS.map((color) => (
                    <label key={color} className={`color-option${form.values.color === color ? ' is-selected' : ''}`}>
                        <input type="radio" name="color" value={color} checked={form.values.color === color} onChange={() => form.set('color', color)} />
                        <Badge tone={color}>{form.values.name || t(`categories.colors.${color}`)}</Badge>
                    </label>
                ))}
                {submit.errors.color && <span className="field-error">{submit.errors.color}</span>}
            </fieldset>
        </FormModal>
    );
}
