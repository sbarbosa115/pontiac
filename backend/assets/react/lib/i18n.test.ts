/// <reference types="node" />
// Every string the UI names by a fixed key exists in the dictionary: a missing one shows as its key ("common.edit")
// on screen, which no build step notices. Keys built at runtime (`pages.status.${status}`) are checked where their
// values are known, by the component tests.
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { messages } from './i18n';

const root = join(process.cwd(), 'assets/react');
const files = readdirSync(root, { recursive: true, encoding: 'utf8' }).filter((file: string) => /\.tsx?$/.test(file) && !/\.test\.tsx?$/.test(file));

describe('i18n', () => {
    it('has every key the code asks for by name', () => {
        const missing = new Set<string>();
        for (const file of files) {
            const source = readFileSync(join(root, file), 'utf8');
            // t('key') and t('key', …), and keys written as strings where a component translates them (menus).
            for (const [, key] of source.matchAll(/\bt\(\s*'([a-zA-Z0-9_.]+)'/g)) {
                if (key && !(key in messages)) missing.add(`${key} (${file})`);
            }
            for (const [, key] of source.matchAll(/label: '((?:nav|roles)\.[a-zA-Z0-9_.]+)'/g)) {
                if (key && !(key in messages)) missing.add(`${key} (${file})`);
            }
        }
        expect([...missing]).toEqual([]);
    });
});
