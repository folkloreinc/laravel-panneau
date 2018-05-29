/* eslint-disable import/no-extraneous-dependencies */
const webpack = require('webpack');
const path = require('path');
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const eslintFormatter = require('react-dev-utils/eslintFormatter');

const ENV = process.env.NODE_ENV;
const isDevelopment = ENV === 'development';

const srcPath = path.join(__dirname, './');
const outputPath = path.join(process.env.PWD, './public/panneau/');
const publicPath = '/panneau/';

const extractPlugin = new ExtractTextPlugin({
    filename: '[name].css',
    allChunks: true,
    disable: isDevelopment,
});

const cssLoaders = [
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

const cssLocalLoaders = [].concat(cssLoaders);
cssLocalLoaders[0] = {
    loader: 'css-loader',
    options: {
        modules: true,
        sourceMap: true,
        importLoaders: 1,
        localIdentName: '[name]-[local]',
    },
};


module.exports = {

    context: srcPath,

    entry: isDevelopment ? [
        path.join(__dirname, './js/polyfills.js'),
        require.resolve('react-dev-utils/webpackHotDevClient'),
        path.join(__dirname, './js/index.js'),
    ] : [
        path.join(__dirname, './js/polyfills.js'),
        path.join(__dirname, './js/index.js'),
    ],

    devtool: isDevelopment ? 'cheap-eval-source-map' : false,

    output: {
        path: outputPath,
        filename: '[name].js',
        chunkFilename: '[name].chunk.js',
        jsonpFunction: 'flklrJsonp',
        publicPath,
    },

    plugins: [
        extractPlugin,
    ].concat(isDevelopment ? [
        new webpack.NamedModulesPlugin(),
        new webpack.DefinePlugin({
            'process.env': {
                NODE_ENV: JSON.stringify('development'),
            },
            __DEV__: JSON.stringify(true),
        }),
    ] : [
        new webpack.DefinePlugin({
            'process.env.NODE_ENV': JSON.stringify('production'),
            __DEV__: JSON.stringify(false),
        }),
        new webpack.optimize.ModuleConcatenationPlugin(),
        new webpack.optimize.UglifyJsPlugin({
            compress: {
                warnings: false,
                comparisons: false,
            },
            mangle: {
                safari10: true,
            },
            output: {
                comments: false,
                ascii_only: true,
            },
        }),
    ]),

    module: {
        rules: [
            {
                test: /\.(js|jsx|mjs)$/,
                enforce: 'pre',
                use: [
                    {
                        options: {
                            formatter: eslintFormatter,
                            eslintPath: require.resolve('eslint'),
                            rules: {
                                'no-console': ['warn', { allow: ['warn', 'error'] }],
                            },
                        },
                        loader: require.resolve('eslint-loader'),
                    },
                ],
                include: srcPath,
                exclude: [
                    /\/vendor\//,
                ],
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
                    {
                        test: /\.jsx?$/,
                        loader: 'babel-loader',
                        include: [
                            srcPath,
                            path.join(process.env.PWD, './node_modules/react-intl'),
                        ],
                        options: {
                            forceEnv: ENV,
                            cacheDirectory: true,
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
                        test: /\.global\.s[ac]ss$/,
                        include: srcPath,
                        use: isDevelopment ? ['style-loader?convertToAbsoluteUrls'].concat(cssLoaders) : extractPlugin.extract({
                            fallback: 'style-loader',
                            use: cssLoaders,
                        }),
                    },

                    {
                        test: /\.s[ac]ss$/,
                        include: srcPath,
                        exclude: /.global\.s[ac]ss$/,
                        use: isDevelopment ? ['style-loader?convertToAbsoluteUrls'].concat(cssLocalLoaders) : extractPlugin.extract({
                            fallback: 'style-loader',
                            use: cssLocalLoaders,
                        }),
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

    stats: {
        colors: true,
        modules: true,
        reasons: true,
        modulesSort: 'size',
        children: true,
        chunks: true,
        chunkModules: true,
        chunkOrigins: true,
        chunksSort: 'size',
    },

    performance: {
        maxAssetSize: 100000,
        maxEntrypointSize: 300000,
    },

    cache: true,
    watch: false,
};
