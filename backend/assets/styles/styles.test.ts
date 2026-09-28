/// <reference types="node" />
// The stylesheet's colour rules, checked: every colour is a token, defined for both themes, and each pair the UI
// puts text on stays readable (WCAG 2.1 AA). A failure here is a colour someone added without its other half.
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const CSS = readFileSync(join(process.cwd(), 'assets/styles/app.css'), 'utf8');
const LIGHT = ":root,\n:root[data-theme='light'] {";
const DARK = ":root[data-theme='dark'] {";
const COLOUR = /#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i;

function block(selector: string): string {
    const start = CSS.indexOf(selector);
    expect(start, `${selector} is in app.css`).toBeGreaterThanOrEqual(0);
    return CSS.slice(start, CSS.indexOf('\n}', start));
}

function tokens(selector: string): Record<string, string> {
    return Object.fromEntries([...block(selector).matchAll(/(--[a-z-]+):\s*([^;]+);/g)].map(([, name, value]) => [name, (value ?? '').trim()]));
}

const themes = { light: tokens(LIGHT), dark: tokens(DARK) };

/** The hex value of a colour token in a theme; a missing token fails the test that asked for it. */
function colour(theme: Record<string, string>, name: string): string {
    const value = theme[`--color-${name}`];
    expect(value, `--color-${name}`).toMatch(/^#[0-9a-f]{6}$/i);
    return value ?? '';
}

/** The linear red, green and blue of "#rrggbb", each 0–1. */
function linear(hex: string): [number, number, number] {
    const channel = (i: number) => {
        const c = parseInt(hex.slice(i, i + 2), 16) / 255;
        return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    return [channel(1), channel(3), channel(5)];
}

function luminance(hex: string): number {
    const [r, g, b] = linear(hex);
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a: string, b: string): number {
    const [la, lb] = [luminance(a), luminance(b)];
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

function lab(hex: string): [number, number, number] {
    const [r, g, b] = linear(hex);
    const f = (t: number) => (t > 0.008856 ? Math.cbrt(t) : 7.787 * t + 16 / 116);
    const x = f((r * 0.4124 + g * 0.3576 + b * 0.1805) / 0.95047);
    const y = f(r * 0.2126 + g * 0.7152 + b * 0.0722);
    const z = f((r * 0.0193 + g * 0.1192 + b * 0.9505) / 1.08883);
    return [116 * y - 16, 500 * (x - y), 200 * (y - z)];
}

const HUES = ['primary', 'success', 'danger', 'info', 'accent', 'teal', 'indigo', 'warning', 'rose'];
// The eight kinds of action (CLAUDE.md, "Tables"): two next to each other must never read as the same colour.
const ACTIONS = ['success', 'danger', 'info', 'accent', 'teal', 'indigo', 'warning', 'rose'];

/** [text, background, minimum ratio]: 4.5 for text, 3 for a control's edge. */
const PAIRS: [string, string, number][] = [
    ...['bg', 'surface', 'surface-sunken', 'neutral-soft', ...HUES.map((hue) => `${hue}-soft`)].flatMap((bg): [string, string, number][] => [
        ['text', bg, 4.5],
        ['muted', bg, 4.5],
    ]),
    // An outlined action button on a card, and the same button hovered (its -soft background).
    ...HUES.flatMap((hue): [string, string, number][] => [
        [hue, 'surface', 4.5],
        [hue, `${hue}-soft`, 4.5],
    ]),
    ['on-primary', 'primary', 4.5],
    ['on-tooltip', 'tooltip', 4.5],
    ['neutral', 'neutral-soft', 4.5],
    ['border-strong', 'surface', 3],
];

describe('app.css colours', () => {
    it('defines every colour and shadow in both themes', () => {
        // Radius, font and spacing are the same in both, so only the light block has them.
        const themed = (theme: Record<string, string>) => Object.keys(theme).filter((name) => /^--(color|shadow)/.test(name)).sort();
        expect(themed(themes.dark)).toEqual(themed(themes.light));
    });

    it('names no colour outside the two token blocks', () => {
        const rest = CSS.replace(block(LIGHT), '').replace(block(DARK), '');
        const offending = rest.split('\n').filter((line: string) => COLOUR.test(line) && !line.trim().startsWith('*') && !line.trim().startsWith('/*'));
        expect(offending).toEqual([]);
    });

    for (const [name, theme] of Object.entries(themes)) {
        it(`keeps text readable in ${name}`, () => {
            const failing = PAIRS.map(([fg, bg, minimum]) => ({ pair: `${fg}/${bg}`, ratio: contrast(colour(theme, fg), colour(theme, bg)), minimum }))
                .filter(({ ratio, minimum }) => ratio < minimum);
            expect(failing).toEqual([]);
        });

        it(`keeps the action colours apart in ${name}`, () => {
            let closest = Infinity;
            for (const [i, a] of ACTIONS.entries()) {
                for (const b of ACTIONS.slice(i + 1)) {
                    const [la, lb] = [lab(colour(theme, a)), lab(colour(theme, b))];
                    closest = Math.min(closest, Math.hypot(la[0] - lb[0], la[1] - lb[1], la[2] - lb[2]));
                }
            }
            expect(closest).toBeGreaterThan(12);
        });
    }
});

describe('React code', () => {
    it('takes its colours from the tokens, never a literal', () => {
        const root = join(process.cwd(), 'assets/react');
        const files = readdirSync(root, { recursive: true, encoding: 'utf8' }).filter((file: string) => /\.tsx?$/.test(file) && !/\.test\.tsx?$/.test(file));
        const offending = files.filter((file: string) => /['"`]#[0-9a-f]{3,8}['"`]/i.test(readFileSync(join(root, file), 'utf8')));
        expect(offending).toEqual([]);
    });
});
