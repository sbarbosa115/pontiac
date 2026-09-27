// Component and unit tests for the React side: `npm test` (Vitest + Testing Library, in jsdom).
// Test files sit next to what they test: components/DateInput.test.tsx.
import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['assets/**/*.test.{js,jsx,ts,tsx}'],
        setupFiles: ['assets/react/test/setup.ts'],
        css: false,
    },
});
