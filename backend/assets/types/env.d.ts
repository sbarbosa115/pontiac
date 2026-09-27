// What webpack (DefinePlugin) replaces at build time. The browser has no `process`: only this expression exists.
declare const process: { env: { NODE_ENV: 'development' | 'production' | 'test' } };

// Stylesheets are imported for their side effect: webpack extracts them. A dynamic import resolves to nothing useful.
declare module '*.css';

// Symfony's Stimulus bridge and UX React ship without types.
declare module '@symfony/stimulus-bridge';
declare module '@symfony/ux-react';
