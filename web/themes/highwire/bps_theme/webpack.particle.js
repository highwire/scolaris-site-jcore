/**
 * Particle base config.
 *
 * The shared loaders, plugins, and processing that all our "apps" should use.
 * This file is not used directly. See particle.js for usage.
 */

// Library Imports
const { ProgressPlugin, ProvidePlugin } = require('webpack');

// Plugins
const VueLoaderPlugin = require('vue-loader/lib/plugin');
const TerserPlugin = require('terser-webpack-plugin');

// Constants: environment
// NODE_ENV is set within all NPM scripts before running Webpack, eg:
//
//  "NODE_ENV='development' webpack-dev-server --config ./apps/pl/webpack.config.js --hot",
//
// NODE_ENV is either:
// - development
// - production
// Defaults to 'production'
const { NODE_ENV = 'development' } = process.env;

// Enable to track down deprecation during development
// process.traceDeprecation = true;

module.exports = {
  // entry: {}, // See entryPrepend() and particle() below for entry details
  mode: NODE_ENV, // development|production
  output: {
    filename: '[name].js',
  },
  devtool: NODE_ENV === 'development' ? 'eval' : 'source-map',
  module: {
    rules: [
      {
        test: /\.vue$/,
        loader: 'vue-loader',
        options: {
          loaders: {
            js: 'babel-loader',
          },
        },
      },
      {
        test: /\.css$/,
        use: [
          {
            loader: 'css-loader',
            options: {
              sourceMap: true,
            },
          },
          {
            loader: 'resolve-url-loader',
            options: {
              sourceMap: true,
              root: '',
            },
          },
          {
            // PostCSS config at ./postcss.config.js
            loader: 'postcss-loader',
            options: {
              sourceMap: true,
              ident: 'postcss',
            },
          },
        ],
      },
      {
        test: /\.(js|vue)$/,
        enforce: 'pre',
        exclude: /node_modules/,
        loader: 'eslint-loader',
        options: {
          emitWarning: true, // development only
          // emitWarning: false, // production only
        },
      },
      {
        test: /\.js$/,
        // @babel runtime and core must NOT be transformed by babel
        exclude: /@babel(?:\/|\\{1,2})runtime|core-js/,
        use: {
          loader: 'babel-loader',
        },
      },
      {
        test: /\.(png|jpg|gif)$/,
        loader: 'file-loader',
        options: {
          name: 'images/[name].[ext]?[hash]',
        },
      },
      {
        test: /\.(woff|woff2|eot|ttf|otf)(\?v=[0-9]\.[0-9]\.[0-9])?$/,
        use: [
          {
            loader: 'file-loader',
            options: {
              name: 'fonts/[name].[ext]?[hash]',
            },
          },
        ],
      },
      // Non-standard assets on the dependency chain
      {
        test: /\.twig$/,
        loader: 'file-loader',
        options: {
          emitFile: false,
        },
      },
    ],
  },
  optimization: {
    minimizer: [
      new TerserPlugin({
        sourceMap: NODE_ENV === 'production',
      }),
    ],
  },
  plugins: [
    // Provides "global" vars mapped to an actual dependency. Allows e.g. jQuery
    // plugins to assume that `window.jquery` is available
    new ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
      'window.jQuery': 'jquery',
    }),
    // Handle .vue files
    new VueLoaderPlugin(),
    // Only add ProgressPlugin for non-production env.
    ...(NODE_ENV === 'production'
      ? []
      : [new ProgressPlugin({ profile: false })]),
  ],
  resolve: {
    alias: {
      // Since we operate in a world where random Vue templates might have to
      // be output via twig, we need the Vue build that includes the whole
      // template compiling engine. If we are on a build that will NEVER read
      // HTML from the DOM and use it as a template, then remove this line.
      vue$: 'vue/dist/vue.esm.js',
    },
  },
};
