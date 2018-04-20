import GlobalSetting, { isTraceEnabled, UPDATE_SERVER } from './GlobalSetting';
import appSettings, { appSettings_$ } from './AppSettings';
import store from './CreateAppStore';
import axios from "axios";
import { URL_GET_TRANSLATION_TABLE, URL_POST_MISSING_KEYS } from "./ApiRoutes";
import { anyNullOrUndefined } from "./helpers";
import { _$ } from 'boxed-immutable';
import appEvents from './AppEvents';


export function filterDisplayLocale(group, locale) {
    return group ==='JSON' || locale !== 'json'; 
}

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
        }, 500);

        this.knownMissingKeys = {};
        this.missingKeys = {};
        this.missingKeyTimer = null;

        this.addMissingKey = this.addMissingKey.bind(this);

        this.unsubscribe = appSettings.subscribeLoaded(() => {
            // start the load if group changed
            const group = appSettings_$.uiSettings.group();
            const groups = appSettings_$.groups.$_array;
            const state = this.getState();
            const groupIndex = groups.indexOf(group);
            if (!anyNullOrUndefined(appSettings_$.primaryLocale(), appSettings_$.translatingLocale()) && groups.length && group && groupIndex !== -1) {
                if (!state.group || anyNullOrUndefined(state.primaryLocale, state.translatingLocale)) {
                    // first load
                    this.load(group);
                } else {
                    const appState = appSettings.getState();
                    if (state.primaryLocale !== appState.primaryLocale ||
                        state.translatingLocale !== appState.translatingLocale ||
                        state.group !== group ||
                        state.connectionName !== appState.connectionName ||
                        !state.displayLocales || state.displayLocales.filter(locale => filterDisplayLocale(group, locale)).join(',') !== appSettings_$.displayLocales.$_array.filter(locale => filterDisplayLocale(group, locale)).join(',')) {

                        this.staleData();
                    }
                }
            } else {
                // const firstGroup = appSettings_$.groups[0]();
                if (appSettings_$.uiSettings() && (group && groupIndex === -1)) {
                    // no data, take the first group.

                    appSettings_$.uiSettings.group = '';
                    appSettings_$.save();
                    this.staleData();
                }
            }
        });

        appEvents.registerEvent('invalidate.groups');
        appEvents.registerEvent('invalidate.group');
        appEvents.registerEvent('invalidate.translations');

        this.invalidateGroup = appEvents.subscribe('invalidate.group', () => {
            this.staleData();
            appEvents.fireEvent('invalidate.translations');
        });

        this.invalidateGroups = appEvents.subscribe('invalidate.groups', () => {
            appSettings_$.isLoaded = false;
            appSettings_$.uiSettings.group = null;
            appSettings_$.groups = [];
            appSettings_$.save();
            appSettings.staleData();
            appEvents.fireEvent('invalidate.translations');
        });

        this.load();
    }

    canAutoRefresh() {
        return appSettings_$.uiSettings.autoUpdateTranslationTable();
    }

    changeGroup(group) {
        appSettings_$.uiSettings.group = group;
        appSettings_$.save();
    }

    // implement to test if can request settings from server
    serverCanLoad() {
        return !!appSettings_$.uiSettings.group();
    }

    // implement to request settings from server
    serverLoad() {
        const { connectionName, primaryLocale, translatingLocale, displayLocales } = appSettings_$._$();
        const group = appSettings_$.uiSettings.group();

        if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[Translations load request]: ${appSettings_$.translatingLocale()} requesting from server `, group);
        const api = URL_GET_TRANSLATION_TABLE(group, connectionName, primaryLocale, translatingLocale, displayLocales);
        axios.post(api.url, api.data)
            .then((result) => {
                this.processServerUpdate(result.data);
            });
    }

    // implement to send server request
    updateServer(settings, frameId) {
        const missingKeys = Object.keys(settings.missingKeys);
        if (missingKeys) {
            const api = URL_POST_MISSING_KEYS(missingKeys);
            axios
                .post(api.url, api.data)
                .then((result) => {
                    let state = this.getState();
                    this.knownMissingKeys = Object.assign({}, this.knownMissingKeys, missingKeys);
                    const affectedGroups = result.data.affectedGroups;
                    delete result.data.affectedGroups;
                    this.processServerUpdate(Object.assign({}, state, result.data), frameId);
                    if (affectedGroups && affectedGroups.indexOf(this.getState().group) !== -1) {
                        // invalidate this view
                        this.staleData();
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
        let missingKeys = copy.missingKeys;

        delete copy['isLoaded'];
        delete copy.missingKeys;
        appSettings.update(copy);

        if (missingKeys) {
            // update on server
            this.throttledUpdate({ missingKeys: missingKeys });
        }
    }

    /**
     * Consumer: callback for each translation
     * @param group to modify
     * @param keyFilter filter function returning true if the key is of interest
     * @param transactionUpdater  function taking a locale and translation, returning a
     *     possibly changed copy of the translation for the locale or null if should delete the
     *     locale's translation
     */
    changeTranslations(group, keyFilter, transactionUpdater) {
        if (group && group === this.getState().group) {
            const appTranslations_$ = this.getBoxed();
            appTranslations_$.translations.forEachKey_$((key, translationEntry_$) => {
                if (keyFilter(key)) {
                    // pass translations for the given key to consumer, one for every locale
                    translationEntry_$.forEachKey_$((locale, translation_$) => {
                        transactionUpdater(locale, translation_$);
                    });
                }
            });

            const modified = appTranslations_$.$_modified;
            appTranslations_$.cancel();

            if (modified) {
                let action = appTranslations.reduxAction(modified);
                store.dispatch(action);

                appEvents.fireEvent('invalidate.translations', group);
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

