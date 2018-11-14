/* eslint-disable import/no-extraneous-dependencies */
const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const safePostCssParser = require('postcss-safe-parser');
const OptimizeCSSAssetsPlugin = require('optimize-css-assets-webpack-plugin');

const ENV = process.env.NODE_ENV;
const isDevelopment = ENV === 'development';

const srcPath = path.join(__dirname, './');
const outputPath = path.join(process.env.PWD, './public/panneau/');
const publicPath = '/panneau/';

const cssLoaders = [
    !isDevelopment && {
        loader: MiniCssExtractPlugin.loader,
    },
    {
        loader: 'css-loader',
        options: {
            sourceMap: true,
        },
    },
    {
        loader: 'postcss-loader',
        options: {
            sourceMap: true,
            config: {
                path: path.join(__dirname, './postcss.config.js'),
                ctx: {
                    env: ENV,
                },
            },
        },
    },
].filter(Boolean);

const sassLoaders = [
    ...cssLoaders,
    {
        loader: 'sass-loader',
        options: {
            sourceMap: true,
            includePaths: [
                path.join(process.env.PWD, './node_modules'),
            ],
        },
    },
];

const sassLocalLoaders = [].concat(sassLoaders);
sassLocalLoaders[0] = {
    loader: 'css-loader',
    options: {
        modules: true,
        sourceMap: true,
        importLoaders: 1,
        localIdentName: '[name]-[local]',
    },
};


module.exports = {
    mode: isDevelopment ? 'development' : 'production',

    devtool: isDevelopment ? 'cheap-eval-source-map' : false,

    bail: !isDevelopment,

    entry: isDevelopment ? [
        require.resolve('react-dev-utils/webpackHotDevClient'),
        path.join(__dirname, './js/index.js'),
    ] : [
        path.join(__dirname, './js/index.js'),
    ],

    output: {
        path: outputPath,
        filename: 'main.js',
        chunkFilename: '[name].chunk.js',
        publicPath,
    },

    optimization: {
        minimizer: !isDevelopment ? [
            new TerserPlugin({
                terserOptions: {
                    parse: {
                        // we want terser to parse ecma 8 code. However, we don't want it
                        // to apply any minfication steps that turns valid ecma 5 code
                        // into invalid ecma 5 code. This is why the 'compress' and 'output'
                        // sections only apply transformations that are ecma 5 safe
                        // https://github.com/facebook/create-react-app/pull/4234
                        ecma: 8,
                    },
                    compress: {
                        ecma: 5,
                        warnings: false,
                        // Disabled because of an issue with Uglify breaking seemingly valid code:
                        // https://github.com/facebook/create-react-app/issues/2376
                        // Pending further investigation:
                        // https://github.com/mishoo/UglifyJS2/issues/2011
                        comparisons: false,
                        // Disabled because of an issue with Terser breaking valid code:
                        // https://github.com/facebook/create-react-app/issues/5250
                        // Pending futher investigation:
                        // https://github.com/terser-js/terser/issues/120
                        inline: 2,
                    },
                    mangle: {
                        safari10: true,
                    },
                    output: {
                        ecma: 5,
                        comments: false,
                        // Turned on because emoji and regex is not minified properly using default
                        // https://github.com/facebook/create-react-app/issues/2488
                        ascii_only: true,
                    },
                },
                // Use multi-process parallel running to improve the build speed
                // Default number of concurrent runs: os.cpus().length - 1
                parallel: true,
                // Enable file caching
                cache: true,
                sourceMap: false,
            }),
            new OptimizeCSSAssetsPlugin({
                cssProcessorOptions: {
                    parser: safePostCssParser,
                    map: false,
                },
            }),
        ] : [],
        // Automatically split vendor and commons
        // https://twitter.com/wSokra/status/969633336732905474
        // https://medium.com/webpack/webpack-4-code-splitting-chunk-graph-and-the-splitchunks-optimization-be739a861366
        splitChunks: {
            chunks: 'async',
            name: false,
        },
        // Keep the runtime chunk seperated to enable long term caching
        // https://twitter.com/wSokra/status/969679223278505985
        runtimeChunk: false,
    },

    plugins: [
        !isDevelopment && new MiniCssExtractPlugin({
            filename: '[name].css',
            chunkFilename: '[name].[contenthash:8].chunk.css',
        }),
        // Moment.js is an extremely popular library that bundles large locale files
        // by default due to how Webpack interprets its code. This is a practical
        // solution that requires the user to opt into importing specific locales.
        // https://github.com/jmblog/how-to-optimize-momentjs-with-webpack
        // You can remove this if you don't use Moment.js:
        new webpack.IgnorePlugin(/^\.\/locale$/, /moment$/),
        // Ignore react-intl locale except fr and en
        new webpack.IgnorePlugin(/(?!fr|en)([a-z]{2,3})/, /locale-data/),
    ].filter(Boolean),

    module: {
        rules: [
            {
                test: /\.(js|jsx|mjs)$/,
                enforce: 'pre',
                use: [
                    {
                        options: {
                            formatter: require.resolve('react-dev-utils/eslintFormatter'),
                            eslintPath: require.resolve('eslint'),
                            rules: isDevelopment ? {
                                'no-console': ['warn', { allow: ['warn', 'error'] }],
                            } : {},
                        },
                        loader: require.resolve('eslint-loader'),
                    },
                ],
                include: srcPath,
            },
            {
                // "oneOf" will traverse all following loaders until one will
                // match the requirements. When no loader matches it will fall
                // back to the "file" loader at the end of the loader list.
                oneOf: [
                    {
                        test: /\.(png|gif|jpg|svg)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
                        loader: 'url-loader',
                        include: srcPath,
                        options: {
                            limit: 1000,
                            name: 'img/[name].[ext]',
                            publicPath: '',
                        },
                    },

                    {
                        test: /\.(ttf|eot|woff|woff2|otf)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
                        loader: 'url-loader',
                        include: srcPath,
                        options: {
                            limit: 1000,
                            name: 'fonts/[name].[ext]',
                            publicPath: '',
                        },
                    },
                    // Process JS with Babel.
                    {
                        test: /\.(js|mjs|jsx|ts|tsx)$/,
                        include: srcPath,

                        loader: require.resolve('babel-loader'),
                        options: {
                            customize: require.resolve('babel-preset-react-app/webpack-overrides'),
                            plugins: [
                                [
                                    require.resolve('babel-plugin-named-asset-import'),
                                    {
                                        loaderMap: {
                                            svg: {
                                                ReactComponent:
                                                    '@svgr/webpack?-prettier,-svgo![path]',
                                            },
                                        },
                                    },
                                ],
                            ],
                            cacheDirectory: true,
                            // Save disk space when time isn't as important
                            cacheCompression: !isDevelopment,
                            compact: !isDevelopment,
                        },
                    },
                    // For dependencies
                    {
                        test: /\.(js|mjs)$/,
                        exclude: /@babel(?:\/|\\{1,2})runtime/,
                        loader: require.resolve('babel-loader'),
                        options: {
                            babelrc: false,
                            configFile: false,
                            compact: false,
                            presets: [
                                [
                                    require.resolve('babel-preset-react-app/dependencies'),
                                    { helpers: true },
                                ],
                            ],
                            cacheDirectory: true,
                            // Save disk space when time isn't as important
                            cacheCompression: !isDevelopment,
                            // If an error happens in a package, it's possible to be
                            // because it was compiled. Thus, we don't want the browser
                            // debugger to show the original code. Instead, the code
                            // being evaluated would be much more helpful.
                            sourceMaps: false,
                        },
                    },
                    {
                        test: /\.json$/,
                        loader: 'json-loader',
                        include: srcPath,
                    },
                    {
                        test: /\.html$/,
                        loader: 'html-loader',
                        include: srcPath,
                    },

                    {
                        test: /\.css$/,
                        use: isDevelopment ? ['style-loader?convertToAbsoluteUrls'].concat(cssLoaders) : cssLoaders,
                        // Don't consider CSS imports dead code even if the
                        // containing package claims to have no side effects.
                        // Remove this when webpack adds a warning or an error for this.
                        // See https://github.com/webpack/webpack/issues/6571
                        sideEffects: true,
                    },

                    {
                        test: /\.global\.s[ac]ss$/,
                        include: srcPath,
                        use: isDevelopment ? ['style-loader?convertToAbsoluteUrls'].concat(sassLoaders) : sassLoaders,
                        // Don't consider CSS imports dead code even if the
                        // containing package claims to have no side effects.
                        // Remove this when webpack adds a warning or an error for this.
                        // See https://github.com/webpack/webpack/issues/6571
                        sideEffects: true,
                    },

                    {
                        test: /\.s[ac]ss$/,
                        include: srcPath,
                        exclude: /.global\.s[ac]ss$/,
                        use: isDevelopment ? ['style-loader?convertToAbsoluteUrls'].concat(sassLocalLoaders) : sassLocalLoaders,
                    },
                ],
            },
        ],
    },

    resolve: {
        extensions: ['.js', '.jsx'],
        modules: [
            path.join(process.env.PWD, './node_modules'),
        ],
    },

    // Some libraries import Node modules but don't use them in the browser.
    // Tell Webpack to provide empty mocks for them so importing them works.
    node: {
        dgram: 'empty',
        fs: 'empty',
        net: 'empty',
        tls: 'empty',
        child_process: 'empty',
    },
    // Turn off performance processing because we utilize
    // our own hints via the FileSizeReporter
    performance: false,
};
