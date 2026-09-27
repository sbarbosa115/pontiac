import Encore from '@symfony/webpack-encore';

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or subdirectory deploy
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
     */
    // The React app: admin, platform and client portal. Public pages are Twig and do not load it.
    .addEntry('app', './assets/app.ts')

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // Babel 8's React preset otherwise picks dev mode from BABEL_ENV/NODE_ENV (unset during
    // "encore production") and emits jsxDEV calls, which React's production build doesn't have.
    .enableReactPreset((options) => {
        options.development = !Encore.isProduction();
    })
    // .ts/.tsx compile through Babel like the .jsx next to them; types are checked apart, by `npm run typecheck`.
    .enableBabelTypeScriptPreset()

    // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
    .enableStimulusBridge('./assets/controllers.json')

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()

    // Displays build status system notifications to the user
    // .enableBuildNotifications()

    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    .enableVersioning(Encore.isProduction())

    // Configure JS and CSS minimizers
    // .configureJsMinimizerPlugin((options, MinimizerPlugin) => {
    //     options.minify = MinimizerPlugin.esbuildMinify
    // })
    // .configureCssMinimizerPlugin((options, MinimizerPlugin) => {
    //     options.minify = MinimizerPlugin.lightningCssMinify;
    // })

    // configure Babel
    .configureBabel((config) => {
        config.plugins.push(['polyfill-corejs3', { method: 'usage-global', version: '3.49' }]);
    })

    // enables Sass/SCSS support
    //.enableSassLoader()

    // uncomment if you use TypeScript
    //.enableTypeScriptLoader()

    // uncomment if you use React
    //.enableReactPreset()

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    //.enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    //.autoProvidejQuery()
;

export default await Encore.getWebpackConfig();
