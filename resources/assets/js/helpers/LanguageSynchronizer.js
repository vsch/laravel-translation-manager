import i18n from './I18n';
import appSettings, { appSettings_$ } from './AppSettings';
import appTranslations from "./GlobalTranslations";
import jQuery from 'jquery';
import { absoluteUrlPrefix, apiURL, POST_TRANS_FILTER } from "./ApiRoutes";
import boxedImmutable from "boxed-immutable";
import DelayedTask from "./DelayedTask";
import appEvents from './AppEvents';

// old script globals
class LanguageSynchronizer {
    constructor() {
        this.oldLanguage = null;
        this.isLoaded = {};
        this.scriptHooker = new DelayedTask(500);

        this.globalSettingsChanged = this.globalSettingsChanged.bind(this);
        this.globalTranslationChanged = this.globalTranslationChanged.bind(this);
        this.unhookOldScripts = this.unhookOldScripts.bind(this);
        this.hookOldScripts = this.hookOldScripts.bind(this);

        i18n.on('loaded', (loaded) => {
            for (let lng in loaded) {
                if (!loaded.hasOwnProperty(lng)) continue;
                this.isLoaded[lng] = true;
            }
            let settings = appSettings.getState();
            this.updateGlobalVars(i18n, settings);
        });

        this.scriptsHooked = false;

        this.unsubscribe = appSettings.subscribe(() => {
            let settings = appSettings.getState();
            let language = i18n.language;
            if (settings.currentLocale !== language) {
                i18n.changeLanguage(settings.currentLocale);
            }

            this.updateGlobalVars(i18n);
        });

        i18n.on('missingKey', function (lngs, namespace, key, res) {
            appTranslations.addMissingKey(lngs, namespace, key);
        });

        this.oldScriptHookers = {};
        this.oldScriptHookerId = 0;
    }

    globalSettingsChanged(params) {
        // here we deep merge UI settings
        if (params.hasOwnProperty("uiSettings")) {
            if (params.uiSettings.hasOwnProperty("yandexPrimaryText")) {
                // this should be an array
                const primaryLocale = appSettings_$.primaryLocale();
                appSettings_$.uiSettings.yandexText[primaryLocale] = params.uiSettings.yandexPrimaryText;
                params.uiSettings.yandexText = appSettings_$.uiSettings.yandexText();
                delete params.uiSettings.yandexPrimaryText;
                appSettings_$.cancel();
            }
            if (params.uiSettings.hasOwnProperty("yandexTranslatingText")) {
                // this should be an array
                const translatingLocale = appSettings_$.translatingLocale();
                appSettings_$.uiSettings.yandexText[translatingLocale] = params.uiSettings.yandexTranslatingText;
                params.uiSettings.yandexText = appSettings_$.uiSettings.yandexText();
                delete params.uiSettings.yandexTranslatingText;
                appSettings_$.cancel();
            }

            params.uiSettings = boxedImmutable.util.mergeDefaults.call(params.uiSettings, appSettings.getState().uiSettings, 99);
        }
        appSettings.update(params);
    }

    globalTranslationChanged(group, key, locale, value, callback) {
        const tmp = 0;
        let result = 0;

        if (group === appTranslations.getState().group) {
            appTranslations.changeTranslations(group, (transKey) => {
                return transKey === key;
            }, (transLocale, trans_$) => {
                if (locale === transLocale) {
                    // our value
                    value = value === "" ? null : value;
                    trans_$.value = value;
                    let savedValue = trans_$.saved_value();
                    result = trans_$.status = value === savedValue ? 0 : 1;
                    callback(result);
                }
            });
        } else {
            callback(1);
            appEvents.fireEvent('invalidate.translations', group);
        }
    }

    unhookOldScripts(hookerId) {
        if (this.oldScriptHookers[hookerId]) {
            delete this.oldScriptHookers[hookerId];

            if (!boxedImmutable.util.hasOwnProperties.call(this.oldScriptHookers)) {
                this.scriptHooker.cancel();
                $.fn.OldScriptHooks.UNHOOK_TRANSLATION_PAGE_EVENTS && $.fn.OldScriptHooks.UNHOOK_TRANSLATION_PAGE_EVENTS();
            } else {
                // should still be hooked for the others but some html changed, re-hook
                // this.hookOldScriptsRaw(hookerId, 'unhook');
            }
        }
    }

    hookOldScripts(hookerId) {
        if (!hookerId) {
            hookerId = ++this.oldScriptHookerId;
        }
        this.oldScriptHookers[hookerId] = true;
        this.hookOldScriptsRaw(hookerId, 'hook');

        return hookerId;
    }

    hookOldScriptsRaw(hookerId, type) {

        this.scriptHooker.restart(() => {
            jQuery.fn.OldScriptHooks.GLOBAL_SETTINGS_CHANGED = this.globalSettingsChanged;
            jQuery.fn.OldScriptHooks.GLOBAL_TRANSLATION_CHANGED = this.globalTranslationChanged;
            window.console.debug(`${type}: hooking old scripts for ${hookerId}`);
            this.hookScripts();
        });

        // if (!this.scriptHooker.isPending()) {
        //     // run right away and start timeout for next one not to run
        //     this.hookScripts();
        //     this.scriptHooker.restart(() => {});
        // } else {
        //     // this.scriptHooker.restart(() => {});
        //     this.scriptHooker.restart(() => {
        //         this.hookScripts();
        //     });
        // }
    }

    hookOnComponentDidMount(hookerId, hookOldScripts) {
        if (hookOldScripts) {
            hookerId = languageSynchronizer.hookOldScripts(hookerId);
        } else {
            hookerId = null;
        }
        return hookerId;
    }

    hookOnComponentDidUpdate(hookerId, hookOldScripts) {
        if (hookOldScripts && hookerId) {
            hookerId = languageSynchronizer.hookOldScripts(hookerId);
        }
        return hookerId;
    }

    hookOnComponentWillUnmount(hookerId, hookOldScripts) {
        if (hookOldScripts && hookerId) {
            languageSynchronizer.unhookOldScripts(hookerId);
            hookerId = null;
        }
        return hookerId;
    }

    hookScripts() {
        const vars = jQuery.fn.OldScriptHooks;
        vars.YANDEX_TRANSLATOR_KEY = appSettings_$.yandexKey();
        vars.PRIMARY_LOCALE = appSettings_$.primaryLocale();
        vars.TRANSLATING_LOCALE = appSettings_$.translatingLocale();

        if (!vars.TRANS_FILTERS && appSettings_$.transFilters()) {
            vars.TRANS_FILTERS = appSettings_$.transFilters();
        }
        vars.CURRENT_GROUP = appSettings_$.uiSettings().group;
        vars.USER_LOCALES = appSettings_$.userLocales();
        vars.URL_TRANSLATOR_FILTERS = apiURL(absoluteUrlPrefix(), POST_TRANS_FILTER);
        vars.HOOKUP_TRANSLATION_PAGE_EVENTS && vars.HOOKUP_TRANSLATION_PAGE_EVENTS();
    }

    updateGlobalVars(i18n) {
        const vars = jQuery.fn.OldScriptHooks;

        if (i18n.language !== this.oldLanguage && this.isLoaded[i18n.language]) {
            this.oldLanguage = i18n.language;
            vars.MISMATCHED_QUOTES_MESSAGE = i18n.t('messages.mismatched-quotes');
            vars.TITLE_SAVE_CHANGES = i18n.t('messages.title-save-changes');
            vars.TITLE_CANCEL_CHANGES = i18n.t('messages.title-cancel-changes');
            vars.TITLE_TRANSLATE = i18n.t('messages.title-translate');
            vars.TITLE_CONVERT_KEY = i18n.t('messages.title-convert-key');
            vars.TITLE_GENERATE_PLURALS = i18n.t('messages.title-generate-plurals');
            // vars.TITLE_GENERATE_PLURALS_COUNT = i18n.t('messages.title-generate-plurals-count');
            vars.TITLE_CLEAN_HTML_MARKDOWN = i18n.t('messages.title-clean-html-markdown');
            vars.TITLE_CAPITALIZE = i18n.t('messages.title-capitalize');
            vars.TITLE_LOWERCASE = i18n.t('messages.title-lowercase');
            vars.TITLE_CAPITALIZE_FIRST_WORD = i18n.t('messages.title-capitalize-first-word');
            vars.TITLE_SIMULATED_COPY = i18n.t('messages.title-simulated-copy');
            vars.TITLE_SIMULATED_PASTE = i18n.t('messages.title-simulated-paste');
            vars.TITLE_RESET_EDITOR = i18n.t('messages.title-reset-editor');
            vars.TITLE_LOAD_LAST = i18n.t('messages.title-load-last');
            vars.TRANSLATION_TITLE = i18n.t('messages.enter-translation');

            vars.UpdateXEditButtonTitles && vars.UpdateXEditButtonTitles();
        }
    }
}

const languageSynchronizer = new LanguageSynchronizer();

export default languageSynchronizer;


