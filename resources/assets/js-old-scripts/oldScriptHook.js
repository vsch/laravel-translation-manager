/*jshint browser: true, jquery: true*/

// var CLIP_TEXT;
// var CURRENT_GROUP;
// var CURRENT_LOCALE;
// var HOOKUP_TRANSLATION_PAGE_EVENTS;
// var MARKDOWN_KEY_SUFFIX;
// var MISMATCHED_QUOTES_MESSAGE;
// var PRIMARY_LOCALE;
// var TITLE_CANCEL_CHANGES;
// var TITLE_CAPITALIZE;
// var TITLE_CAPITALIZE_FIRST_WORD;
// var TITLE_CLEAN_HTML_MARKDOWN;
// var TITLE_CONVERT_KEY;
// var TITLE_GENERATE_PLURALS;
// var TITLE_LOAD_LAST;
// var TITLE_LOWERCASE;
// var TITLE_RESET_EDITOR;
// var TITLE_SAVE_CHANGES;
// var TITLE_SIMULATED_COPY;
// var TITLE_SIMULATED_PASTE;
// var TITLE_TRANSLATE;
// var TRANS_FILTERS;
// var TRANSLATING_LOCALE;
// var URL_TRANSLATOR_ALL;
// var URL_TRANSLATOR_FILTERS;
// var URL_TRANSLATOR_GROUP;
// var URL_YANDEX_TRANSLATOR_KEY;
// var USER_LOCALES;
// var xtranslateService;
// var xtranslateText;
// var YANDEX_TRANSLATOR_KEY;

(function ($) {
    'use strict';

    const oldVars = {
        CLIP_TEXT: {set: (val) => CLIP_TEXT = val, get: () => CLIP_TEXT,},
        CURRENT_GROUP: {set: (val) => CURRENT_GROUP = val, get: () => CURRENT_GROUP,},
        CURRENT_LOCALE: {set: (val) => CURRENT_LOCALE = val, get: () => CURRENT_LOCALE,},
        HOOKUP_TRANSLATION_PAGE_EVENTS: {run: () => HOOKUP_TRANSLATION_PAGE_EVENTS && HOOKUP_TRANSLATION_PAGE_EVENTS(), get: () => HOOKUP_TRANSLATION_PAGE_EVENTS,},
        MARKDOWN_KEY_SUFFIX: {set: (val) => MARKDOWN_KEY_SUFFIX = val, get: () => MARKDOWN_KEY_SUFFIX,},
        MISMATCHED_QUOTES_MESSAGE: {set: (val) => MISMATCHED_QUOTES_MESSAGE = val, get: () => MISMATCHED_QUOTES_MESSAGE,},
        PRIMARY_LOCALE: {set: (val) => PRIMARY_LOCALE = val, get: () => PRIMARY_LOCALE,},
        TITLE_CANCEL_CHANGES: {set: (val) => TITLE_CANCEL_CHANGES = val, get: () => TITLE_CANCEL_CHANGES,},
        TITLE_CAPITALIZE: {set: (val) => TITLE_CAPITALIZE = val, get: () => TITLE_CAPITALIZE,},
        TITLE_CAPITALIZE_FIRST_WORD: {set: (val) => TITLE_CAPITALIZE_FIRST_WORD = val, get: () => TITLE_CAPITALIZE_FIRST_WORD,},
        TITLE_CLEAN_HTML_MARKDOWN: {set: (val) => TITLE_CLEAN_HTML_MARKDOWN = val, get: () => TITLE_CLEAN_HTML_MARKDOWN,},
        TITLE_CONVERT_KEY: {set: (val) => TITLE_CONVERT_KEY = val, get: () => TITLE_CONVERT_KEY,},
        TITLE_GENERATE_PLURALS: {set: (val) => TITLE_GENERATE_PLURALS = val, get: () => TITLE_GENERATE_PLURALS,},
        TITLE_LOAD_LAST: {set: (val) => TITLE_LOAD_LAST = val, get: () => TITLE_LOAD_LAST,},
        TITLE_LOWERCASE: {set: (val) => TITLE_LOWERCASE = val, get: () => TITLE_LOWERCASE,},
        TITLE_RESET_EDITOR: {set: (val) => TITLE_RESET_EDITOR = val, get: () => TITLE_RESET_EDITOR,},
        TITLE_SAVE_CHANGES: {set: (val) => TITLE_SAVE_CHANGES = val, get: () => TITLE_SAVE_CHANGES,},
        TITLE_SIMULATED_COPY: {set: (val) => TITLE_SIMULATED_COPY = val, get: () => TITLE_SIMULATED_COPY,},
        TITLE_SIMULATED_PASTE: {set: (val) => TITLE_SIMULATED_PASTE = val, get: () => TITLE_SIMULATED_PASTE,},
        TITLE_TRANSLATE: {set: (val) => TITLE_TRANSLATE = val, get: () => TITLE_TRANSLATE,},
        TRANS_FILTERS: {set: (val) => TRANS_FILTERS = val, get: () => TRANS_FILTERS,},
        TRANSLATING_LOCALE: {set: (val) => TRANSLATING_LOCALE = val, get: () => TRANSLATING_LOCALE,},
        URL_TRANSLATOR_ALL: {set: (val) => URL_TRANSLATOR_ALL = val, get: () => URL_TRANSLATOR_ALL,},
        URL_TRANSLATOR_FILTERS: {set: (val) => URL_TRANSLATOR_FILTERS = val, get: () => URL_TRANSLATOR_FILTERS,},
        URL_TRANSLATOR_GROUP: {set: (val) => URL_TRANSLATOR_GROUP = val, get: () => URL_TRANSLATOR_GROUP,},
        URL_YANDEX_TRANSLATOR_KEY: {set: (val) => URL_YANDEX_TRANSLATOR_KEY = val, get: () => URL_YANDEX_TRANSLATOR_KEY,},
        USER_LOCALES: {set: (val) => USER_LOCALES = val, get: () => USER_LOCALES,},
        xtranslateService: {set: (val) => xtranslateService = val, get: () => xtranslateService,},
        xtranslateText: {set: (val) => xtranslateText = val, get: () => xtranslateText,},
        YANDEX_TRANSLATOR_KEY: {set: (val) => YANDEX_TRANSLATOR_KEY = val, get: () => YANDEX_TRANSLATOR_KEY,},
    };

    $.fn.OldScriptHooks = function (op, options) {
        let result = {};
        for (let item in options) {
            if (!options.hasOwnProperty(item)) continue;
            if (!oldVars.hasOwnProperty(item)) continue;

            let varOps = oldVars[item];

            try {
                if (!varOps.hasOwnProperty(op)) {
                    result[item] = {error: 'no such op', data: undefined};
                } else {
                    const params = options[item];
                    let opVal = varOps[op](params);
                    result[item] = {result: opVal};
                }
            } catch (e) {
                result[item] = {error: 'exception', data: e};
            }
        }
        
        let test = PRIMARY_LOCALE;
        return;
    };

    $.fn.OldScriptHooks.CLIP_TEXT = undefined;
    $.fn.OldScriptHooks.CURRENT_GROUP = undefined;
    $.fn.OldScriptHooks.CURRENT_LOCALE = undefined;
    $.fn.OldScriptHooks.HOOKUP_TRANSLATION_PAGE_EVENTS = undefined;
    $.fn.OldScriptHooks.UNHOOK_TRANSLATION_PAGE_EVENTS = undefined;
    $.fn.OldScriptHooks.MARKDOWN_KEY_SUFFIX = undefined;
    $.fn.OldScriptHooks.MISMATCHED_QUOTES_MESSAGE = undefined;
    $.fn.OldScriptHooks.PRIMARY_LOCALE = undefined;
    $.fn.OldScriptHooks.TITLE_CANCEL_CHANGES = undefined;
    $.fn.OldScriptHooks.TITLE_CAPITALIZE = undefined;
    $.fn.OldScriptHooks.TITLE_CAPITALIZE_FIRST_WORD = undefined;
    $.fn.OldScriptHooks.TITLE_CLEAN_HTML_MARKDOWN = undefined;
    $.fn.OldScriptHooks.TITLE_CONVERT_KEY = undefined;
    $.fn.OldScriptHooks.TITLE_GENERATE_PLURALS = undefined;
    $.fn.OldScriptHooks.TITLE_LOAD_LAST = undefined;
    $.fn.OldScriptHooks.TITLE_LOWERCASE = undefined;
    $.fn.OldScriptHooks.TITLE_RESET_EDITOR = undefined;
    $.fn.OldScriptHooks.TITLE_SAVE_CHANGES = undefined;
    $.fn.OldScriptHooks.TITLE_SIMULATED_COPY = undefined;
    $.fn.OldScriptHooks.TITLE_SIMULATED_PASTE = undefined;
    $.fn.OldScriptHooks.TITLE_TRANSLATE = undefined;
    $.fn.OldScriptHooks.TRANS_FILTERS = undefined;
    $.fn.OldScriptHooks.TRANSLATING_LOCALE = undefined;
    $.fn.OldScriptHooks.URL_TRANSLATOR_ALL = undefined;
    $.fn.OldScriptHooks.URL_TRANSLATOR_FILTERS = undefined;
    $.fn.OldScriptHooks.URL_TRANSLATOR_GROUP = undefined;
    $.fn.OldScriptHooks.URL_YANDEX_TRANSLATOR_KEY = undefined;
    $.fn.OldScriptHooks.USER_LOCALES = undefined;
    $.fn.OldScriptHooks.xtranslateService = undefined;
    $.fn.OldScriptHooks.xtranslateText = undefined;
    $.fn.OldScriptHooks.YANDEX_TRANSLATOR_KEY = undefined;
    $.fn.OldScriptHooks.GLOBAL_SETTINGS_CHANGED = undefined;
    $.fn.OldScriptHooks.GLOBAL_TRANSLATION_CHANGED = undefined;
    $.fn.OldScriptHooks.TRANSLATION_TITLE = undefined;
    $.fn.OldScriptHooks.APP_URL = undefined;
}(window.jQuery));
