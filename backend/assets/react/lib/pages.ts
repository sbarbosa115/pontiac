// A landing page's content as the editor works on it, and the moves the editor makes on it. The shape is the API's
// (src/Page/TemplateCatalog.php, ContentValidator): the server checks everything again on save.
import type { Schema } from './types';

export type Catalog = Schema<'PageCatalogOutput'>;
export type SectionType = Schema<'SectionTypeOutput'>;
export type FieldSpec = Schema<'FieldSpecOutput'>;
export type PageDetail = Schema<'PageDetailOutput'>;

// A list of ids: a payment section's plans.
export type FieldValue = string | null | string[] | Array<Record<string, string>>;

export interface Section {
    id: string;
    type: string;
    enabled: boolean;
    fields: Record<string, FieldValue>;
}

export interface FormField {
    key?: string;
    label: string;
    type: string;
    required: boolean;
    options: string[];
    optionCategories: Record<string, string>;
}

export interface PageContent {
    sections: Section[];
    form: { fields: FormField[] };
    seo: { title: string; description: string; imageId: string | null; index: boolean };
    settings: { defaultCategoryId: string | null; flowId?: string | null; accent: string };
}

/** The draft as the API sent it, typed. */
export function contentOf(page: PageDetail): PageContent {
    return page.draft as unknown as PageContent;
}

/** A copy of the list with the item at `from` moved to `to` (out of range: unchanged). */
export function move<T>(list: readonly T[], from: number, to: number): T[] {
    if (to < 0 || to >= list.length || from === to) return [...list];
    const copy = [...list];
    const [item] = copy.splice(from, 1);
    copy.splice(to, 0, item as T);
    return copy;
}

export function updateSection(content: PageContent, index: number, change: Partial<Section>): PageContent {
    return { ...content, sections: content.sections.map((section, i) => (i === index ? { ...section, ...change } : section)) };
}

export function setSectionField(content: PageContent, index: number, name: string, value: FieldValue): PageContent {
    const section = content.sections[index];
    if (!section) return content;
    return updateSection(content, index, { fields: { ...section.fields, [name]: value } });
}

/** An empty item for an "items" field: every field of the item, blank. */
export function emptyItem(spec: FieldSpec): Record<string, string> {
    return Object.fromEntries(spec.fields.map((field) => [field.name, '']));
}

/** The API's error paths ("sections[2].fields.items[0].title") that start with a prefix, for one part of the editor. */
export function errorsUnder(errors: Record<string, string>, prefix: string): Record<string, string> {
    return Object.fromEntries(Object.entries(errors).filter(([path]) => path === prefix || path.startsWith(`${prefix}.`) || path.startsWith(`${prefix}[`)));
}
