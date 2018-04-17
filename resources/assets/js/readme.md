# React App UI for Laravel Translation Manager

To have the pre-compiled files included in your mix based asset compilation add the following
lines to your Laravel project's `webpack.mix.js`, after compilation of your assets. 

```js
mix.copy(['vendor/vsch/laravel-translation-manager/public/js/index.js'], 'public/vendor/laravel-translation-manager/js/index.js')
    .copy(['vendor/vsch/laravel-translation-manager/public/css/index.css'], 'public/vendor/laravel-translation-manager/css/index.css')
    .copy(['vendor/vsch/laravel-translation-manager/public/images'], 'public/vendor/laravel-translation-manager/images')
;
```

If you want to build this app as part of your asset compilation then you will need to add the
following to your `webpack.mix.js` (assuming this package is under
`vendor/vsch/laravel-translation-manager` directory):

```js
mix.react('vendor/vsch/laravel-translation-manager/resources/assets/js/index.js', 'public/vendor/laravel-translation-manager/js')
    .sass('vendor/vsch/laravel-translation-manager/resources/assets/sass/index.scss', 'public/vendor/laravel-translation-manager/css')
    .setResourceRoot('/vendor/laravel-translation-manager/')
;
```

If you are not using mix compilation and the `public/mix-manifest.json` does not exist or does
not get modified then you need to add the following lines to this file:

```json
{
    "/vendor/laravel-translation-manager/js/index.js": "/vendor/laravel-translation-manager/js/index.js",
    "/vendor/laravel-translation-manager/css/index.css": "/vendor/laravel-translation-manager/css/index.css",
}
```

