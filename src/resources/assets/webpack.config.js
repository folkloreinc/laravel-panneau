/* eslint-disable import/no-extraneous-dependencies */
const webpack = require('webpack');
const path = require('path');
const ExtractTextPlugin = require('extract-text-webpack-plugin');
const UglifyJSPlugin = require('uglifyjs-webpack-plugin');
/* eslint-enable import/no-extraneous-dependencies */

module.exports = (env) => {
    const CONTEXT_PATH = path.join(__dirname, './');
    const OUTPUT_PATH = path.join(__dirname, '../../../../public/panneau/');
    const PUBLIC_PATH = '/panneau/';
    const CSS_FILENAME = env === 'dev' ? '[name]-[contenthash].css' : '[name].css';
    const CSS_NAME = '[name]-[local]';
    const IMAGE_FILENAME = env === 'dev' ? 'img/[name]-[hash:6].[ext]' : 'img/[name].[ext]';
    const FONT_FILENAME = env === 'dev' ? 'fonts/[name]-[hash:6].[ext]' : 'fonts/[name].[ext]';
    const IMAGE_PUBLIC_PATH = '';
    const FONT_PUBLIC_PATH = '';

    const extractPlugin = new ExtractTextPlugin({
        filename: CSS_FILENAME,
        allChunks: true,
        disable: env === 'dev',
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
                        env,
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
            localIdentName: CSS_NAME,
        },
    };

    return {

        context: CONTEXT_PATH,

        entry: {
            main: './js/index',
        },

        devtool: 'source-map',

        output: {
            path: OUTPUT_PATH,
            filename: '[name].js',
            chunkFilename: '[name].js',
            jsonpFunction: 'flklrJsonp',
            publicPath: PUBLIC_PATH,
        },

        plugins: [
            extractPlugin,
        ].concat(env === 'dev' ? [
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
            new UglifyJSPlugin({
                beautify: false,
                sourceMap: true,
                mangle: {
                    screw_ie8: true,
                    keep_fnames: true,
                },
                compress: {
                    screw_ie8: true,
                    warnings: false,
                },
                comments: false,
            }),
            new webpack.SourceMapDevToolPlugin({
                filename: '[file].map',
                exclude: [/vendor\//],
            }),
        ]),

        module: {
            rules: [
                {
                    test: /\.jsx?$/,
                    loader: 'babel-loader',
                    include: CONTEXT_PATH,
                    options: {
                        forceEnv: env,
                        cacheDirectory: true,
                    },
                },
                {
                    test: /\.json$/,
                    loader: 'json-loader',
                    include: CONTEXT_PATH,
                },
                {
                    test: /\.html$/,
                    loader: 'html-loader',
                    include: CONTEXT_PATH,
                },
                {
                    test: /\.svg$/,
                    include: CONTEXT_PATH,
                    exclude: [
                        /\/img\//,
                    ],
                    use: [
                        `babel-loader?forceEnv=${env}&cacheDirectory`,
                        'svg-react-loader',
                    ],
                },

                {
                    test: /\.global\.s[ac]ss$/,
                    include: CONTEXT_PATH,
                    use: env === 'dev' ? ['style-loader?convertToAbsoluteUrls'].concat(cssLoaders) : extractPlugin.extract({
                        fallback: 'style-loader',
                        use: cssLoaders,
                    }),
                },

                {
                    test: /\.s[ac]ss$/,
                    include: CONTEXT_PATH,
                    exclude: /.global\.s[ac]ss$/,
                    use: env === 'dev' ? ['style-loader?convertToAbsoluteUrls'].concat(cssLocalLoaders) : extractPlugin.extract({
                        fallback: 'style-loader',
                        use: cssLocalLoaders,
                    }),
                },

                {
                    test: /\.(png|gif|jpg|svg)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
                    loader: 'url-loader',
                    include: CONTEXT_PATH,
                    exclude: /fonts\//,
                    options: {
                        limit: 1000,
                        name: IMAGE_FILENAME,
                        publicPath: IMAGE_PUBLIC_PATH,
                    },
                },

                {
                    test: /fonts\/(.*?)\.(ttf|eot|woff|woff2|otf|svg)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
                    loader: 'url-loader',
                    include: CONTEXT_PATH,
                    options: {
                        limit: 1000,
                        name: FONT_FILENAME,
                        publicPath: FONT_PUBLIC_PATH,
                    },
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
};
