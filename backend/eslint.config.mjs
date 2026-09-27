// Static analysis for the React side: `npm run lint` (see CLAUDE.md, "Checks").
//
// The rules that matter most here are the ones webpack does not catch: react/jsx-no-undef (a component used but
// not imported compiles and renders a blank screen) and the hooks rules (a hook called conditionally, an effect
// missing a dependency). Everything else is the recommended sets.
import js from '@eslint/js';
import globals from 'globals';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['public/**', 'node_modules/**', 'vendor/**', 'var/**', 'assets/types/api.d.ts', 'e2e/.results/**'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['assets/**/*.{js,jsx,ts,tsx}', 'e2e/**/*.ts', '*.config.{js,mjs,ts,mts}'],
        plugins: { react, 'react-hooks': reactHooks },
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            parserOptions: { ecmaFeatures: { jsx: true } },
            globals: { ...globals.browser, ...globals.node },
        },
        settings: { react: { version: 'detect' } },
        rules: {
            ...react.configs.recommended.rules,
            ...reactHooks.configs.recommended.rules,
            'react/jsx-no-undef': 'error',
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',
            // An unused name is an error; a leading underscore marks one that is unused on purpose.
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrors: 'none' }],
            '@typescript-eslint/no-require-imports': 'off',
        },
    },
    {
        files: ['**/*.test.{js,jsx,ts,tsx}'],
        languageOptions: { globals: { ...globals.node } },
    },
);
