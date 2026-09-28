/** "Finanzas Claras SAS" → "finanzas-claras-sas": a first suggestion for an address, which people can edit. */
export function suggestSlug(name: string): string {
    return name
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60);
}
