// Loaded before every Vitest file: DOM matchers (toBeInTheDocument…) and a clean DOM between tests.
import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

afterEach(() => cleanup());
