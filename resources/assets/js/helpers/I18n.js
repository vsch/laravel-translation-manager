import i18n from 'i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import { reactI18nextModule } from 'react-i18next';
import i18nextXHRBackend from 'i18next-xhr-backend';
import { absoluteUrlPrefix, apiURL, URL_GET_TRANSLATIONS } from "./ApiRoutes";

i18n
    .use(i18nextXHRBackend)
    .use(LanguageDetector)
    .use(reactI18nextModule)
    .init({
        fallbackLng: 'en',
        lng: 'en',
        debug: true,

        // have a common namespace used around the full app
        ns: ['laravel-translation-manager'],
        defaultNS: 'laravel-translation-manager',

        keySeparator: '.',

        interpolation: {
            escapeValue: false, // not needed for react!!
            formatSeparator: ','
        },

        preload: ['en'/*, 'fr', 'ru'*/],

        // saveMissing: true,
        // saveMissingTo: 'fallback',
        //
        // missingKeyHandler: (lngs, namespace, key, res) => {
        //     appTranslations.addMissingKey(lngs, namespace, key);
        // },

        backend: {
            // path where resources get loaded from, or a function
            // returning a path:
            // function(lngs, namespaces) { return customPath; }
            // the returned path will interpolate lng, ns if provided like giving a static path
            loadPath: apiURL(absoluteUrlPrefix(), URL_GET_TRANSLATIONS('{{ns}}::messages', '{{lng}}').url),
            // loadPath: '/locales/{{lng}}/{{ns}}.json',

            // allow cross domain requests
            crossDomain: true,

            // your backend server supports multiloading
            // /locales/resources.json?lng=de+en&ns=ns1+ns2
            allowMultiLoading: false, // set loadPath: '/locales/resources.json?lng={{lng}}&ns={{ns}}' to adapt to multiLoading

            // parse data after it has been fetched
            parse: function (data) {
                return JSON.parse(data);
            },

            // allow credentials on cross domain requests
            withCredentials: true,

            // define a custom xhr function
            // can be used to support XDomainRequest in IE 8 and 9
            // ajax: function (url, options, callback, data) {
            //     fetch(url, options)
            //         .then(res => {
            //             if (res.ok) {
            //                 return res.text()
            //                     .then((json) => {
            //                         callback(json, res);
            //                     });
            //             }
            //             return callback('', res);
            //         });
            // },

            // adds parameters to resource URL. 'example.com' -> 'example.com?v=1.3.5'
            //queryStringParams: {XDEBUG_SESSION_START: 16990}
        },

        react: { wait: true }

    });

export default i18n;
