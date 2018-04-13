import GlobalSetting, { UPDATE_SERVER } from './GlobalSetting';
import appSettings, { appSettings_$ } from './AppSettings';
import store from './CreateAppStore';
import axios from "axios";
import { apiURL, GET_TRANSLATION_TABLE, POST_MISSING_KEYS } from "./ApiRoutes";
import { anyNullOrUndefined } from "./helpers";
import { _$ } from 'boxed-immutable';

export class GlobalTranslations extends GlobalSetting {
    constructor() {
        super("globalTranslations", {
            // default settings
            connectionName: "default",
            displayLocales: [],
            yandexKey: null,
            translations: {},
            missingKeys: {}, // missing keys
        }, {
            // false means throttled, true - immediate update, commented or null - no server update
            // yandexKey: null,
            // translations: {},
            missingKeys: UPDATE_SERVER,
            //knownMissingKeys: {}, // missing keys
        }, 2000);

        this.connectionName = this.defaultSettings.connectionName;
        this.displayLocales = this.defaultSettings.displayLocales;
        this.group = null;
        this.primaryLocale = null;
        this.translatingLocale = null;
        this.locales = null;
        this.knownMissingKeys = {};
        this.missingKeys = {};
        this.missingKeyTimer = null;

        this.addMissingKey = this.addMissingKey.bind(this);

        this.unsubscribe = appSettings.subscribeLoaded(() => {
            // start the load if group changed
            if (!anyNullOrUndefined(appSettings_$.uiSettings.group(), appSettings_$.primaryLocale(), appSettings_$.translatingLocale())) {
                if (anyNullOrUndefined(this.group, this.primaryLocale, this.translatingLocale)) {
                    // first load
                    const group = appSettings_$.uiSettings.group();
                    this.load(group);
                } else {
                    if (this.primaryLocale !== appSettings_$.primaryLocale() ||
                        this.translatingLocale !== appSettings_$.translatingLocale() ||
                        !this.displayLocales ||
                        this.connectionName !== appSettings_$.connectionName() ||
                        this.displayLocales.join(',') !== appSettings_$.displayLocales.$_ifArray(Array.prototype.join, ',')) {

                        this.staleData(appSettings_$.uiSettings.autoUpdateTranslationTable());
                    }
                }
            } else {
                if (appSettings_$.uiSettings() && !appSettings_$.uiSettings.group() && appSettings_$.groups[0]()) {
                    // no data, take the first group.
                    let appSettingsState = appSettings.getState();
                    let appSettings_$State = appSettings_$.$_value;
                    let uiSettings = appSettings_$.uiSettings();
                    appSettings_$.uiSettings.group = appSettings_$.groups[0];
                    let delta = appSettings_$.$_delta;
                    let delta2 = appSettings_$.$_delta;
                    appSettings_$.save();
                }
            }
        });

        this.load();
    }
    
    changeGroup(group) {
        if (group && group !== this.group) {
            this.load(group);
        }
    }

    // implement to test if can request settings from server
    serverCanLoad(group) {
        group = group || appSettings_$.uiSettings.group();
        return !!group;
    }

    // implement to request settings from server
    serverLoad(group) {
        group = group || appSettings_$.uiSettings.group();
        axios.get(apiURL(GET_TRANSLATION_TABLE, group))
            .then((result) => {
                this.displayLocales = result.data.displayLocales;
                this.connectionName = result.data.connectionName;
                this.group = result.data.group;
                this.locales = result.data.locales;
                this.primaryLocale = result.data.primaryLocale;
                this.translatingLocale = result.data.translatingLocale;
                this.processServerUpdate(result.data);

                appSettings_$.uiSettings.group = this.group;
                // TODO: when we have server updated flag then we can do this, otherwise we wind up overwriting pending values.
                // if (this.locales !== settings.locales) newState.locales = this.locales;
                // if (this.displayLocales !== settings.displayLocales) newState.displayLocales = this.displayLocales;
                appSettings_$.save();
            });
    }

    // implement to send server request
    updateServer(settings, frameId) {
        const missingKeys = Object.keys(settings.missingKeys);
        if (missingKeys) {
            axios
                .post(apiURL(POST_MISSING_KEYS), JSON.stringify({ missingKeys: missingKeys }))
                .then((result) => {
                    let state = this.getState();
                    this.knownMissingKeys = Object.assign({}, this.knownMissingKeys, missingKeys);
                    const affectedGroups = result.data.affectedGroups;
                    delete result.data.affectedGroups;
                    this.processServerUpdate(Object.assign({}, state, result.data), frameId);
                    if (affectedGroups && affectedGroups.indexOf(this.group) !== -1) {
                        // invalidate this view
                        this.staleData(appSettings_$.uiSettings.autoUpdateTranslationTable());
                    }
                });
        } else {
            // should not happen
            throw "IllegalState, missingKeys is false, should not call translations updateServer";
            // this.processServerUpdate(Object.assign(this.getState(), { missingKeys: {} }), frameId);
        }
    }

    update(translations) {
        const copy = Object.assign({}, translations);
        delete copy['isLoaded'];
        // delete copy['group'];
        let missingKeys = copy.missingKeys;

        delete copy.missingKeys;
        appSettings.update(copy);

        translations = copy;
        if (translations && translations.group && translations.group !== this.getState().group) {
            this.load(translations.group);
        }

        if (missingKeys) {
            // update on server
            this.throttledUpdate({ missingKeys: missingKeys });
        }
    }

    /**
     * Consumer: callback for each translation
     * @param group to modify
     * @param keyFilter filter function returning true if the key is of interest
     * @param transactionUpdater  function taking a locale and translation, returning a possibly changed copy of the translation for the locale or null if should delete the locale's translation
     */
    changeTranslations(group, keyFilter, transactionUpdater) {
        if (group === appTranslations_$.group()) {
            appTranslations_$.translations.forEachKey_$((key, translationEntry_$) => {
                if (keyFilter(key)) {
                    // pass translations for the given key to consumer, one for every locale
                    translationEntry_$.forEachKey_$((locale, translation_$) => {
                        transactionUpdater(locale, translation_$);
                    });
                }
            });

            if (appTranslations_$.$_modified) {
                let action = appTranslations.reduxAction(appTranslations_$.$_modified);
                store.dispatch(action);
            }
        }
    }

    addMissingKey(lngs, namespace, key) {
        let groupKey = namespace + "::" + key;

        if (!this.knownMissingKeys || !this.knownMissingKeys[groupKey]) {
            this.knownMissingKeys[groupKey] = lngs;
            const missingKeys_$ = _$(this.missingKeys);
            missingKeys_$[groupKey][-1] = lngs;
            if (missingKeys_$.$_modified) {
                if (this.missingKeyTimer) {
                    window.clearTimeout(this.missingKeyTimer);
                    this.missingKeyTimer = null;
                }

                this.missingKeys = missingKeys_$.$_modified;
                this.missingKeyTimer = window.setTimeout(() => {
                    this.missingKeyTimer = null;
                    this.update({ missingKeys: this.missingKeys });
                }, 1000);
            }
        } else {
            // add resources i18n
            // i18n.addResource(lngs, namespace, key, key, { silent: true });
        }
    }
}

const appTranslations = new GlobalTranslations();
export const appTranslations_$ = appTranslations.getBoxed();

export default appTranslations;

