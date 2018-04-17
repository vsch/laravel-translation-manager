let mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.react('resources/assets/js/index.js', 'public/js')
    .sass('resources/assets/sass/index.scss', 'public/css')
    .setResourceRoot('/vendor/laravel-translation-manager/')
    .setPublicPath('public/')
;

/*
// add the following lines to your Laravel project's webpack.mix.js to have LTM React files copied and added to the mix-manifest.json
mix.copy(['vendor/vsch/laravel-translation-manager/public/js/index.js'], 'public/vendor/laravel-translation-manager/js/index.js')
    .copy(['vendor/vsch/laravel-translation-manager/public/css/index.css'], 'public/vendor/laravel-translation-manager/css/index.css')
    .copy(['vendor/vsch/laravel-translation-manager/public/images'], 'public/vendor/laravel-translation-manager/images')
;
*/

if (!mix.inProduction()) {
    mix.webpackConfig({
        devtool: 'source-map'
    })
        .sourceMaps();
} 
