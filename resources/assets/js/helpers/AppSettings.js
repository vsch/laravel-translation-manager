import GlobalSetting, { UPDATE_IMMEDIATE, UPDATE_SERVER, UPDATE_STORE, UPDATE_THROTTLED } from './GlobalSetting';
import axios from "axios";
import { URL_GET_APP_SETTINGS, URL_POST_APP_SETTINGS } from "./ApiRoutes";
import boxedImmutable from "boxed-immutable";
import appEvents from './AppEvents';

const util = boxedImmutable.util;
const _$ = boxedImmutable.box;
const isArray = util.isArray;
const eachProp = util.eachProp;
const isFunction = util.isFunction;
const isObject = util.isObjectLike;
const UNDEFINED = util.UNDEFINED;

export const appSettingChecks = {
    autoUpdateViews: 'auto-update-views',
    autoUpdateTranslationTable: 'auto-update-translation-table',
    confirmDeleteGroup: 'confirm-delete-group',
    confirmImportGroup: 'confirm-import-group',
    confirmImportGroupReplace: 'confirm-import-group-replace',
    confirmImportGroupDelete: 'confirm-import-group-delete',
    confirmPublishGroup: 'confirm-publish-group',
    confirmImportAllGroups: 'confirm-import-all-groups',
    confirmImportAllGroupsReplace: 'confirm-import-all-groups-replace',
    confirmImportAllGroupsDelete: 'confirm-import-all-groups-delete',
    confirmAddReferences: 'confirm-add-references',
    confirmPublishAllGroups: 'confirm-publish-all-groups',
    confirmAddSuffixedKeys: 'confirm-add-suffixed-keys',
    confirmDeleteSuffixedKeys: 'confirm-delete-suffixed-keys',
    confirmClearUserUiSettings: 'confirm-clear-user-ui-settings',
};

export const appSettingForcedChecks = {
    confirmDeleteGroup: true,
    confirmAddReferences: true,
    confirmDeleteSuffixedKeys: true,
    confirmClearUserUiSettings: true,
};

// app route to dashboard settings & defaults
// "summary", "mismatches", "translationSettings", "userAdmin",
export const dashboardConfig = {
    dashboards: {
        summary: {
            index: 0,
            showState: "showSummaryDashboard",
            title: 'messages.stats',
            collapseState: "collapseSummaryDashboard",
            defaultCollapse: true,
            defaultShow: true,
            defaultInclude: true,
        },
        mismatches: {
            index: 1,
            showState: "showMismatchDashboard",
            title: 'messages.mismatches',
            collapseState: "collapseMismatchDashboard",
            defaultShow: true,
            defaultCollapse: true,
            defaultInclude: true,
        },
        userAdmin: {
            index: 2,
            showState: "showUserManagementDashboard",
            title: 'messages.user-admin',
            collapseState: "collapseUserManagementDashboard",
            defaultShow: true,
            defaultInclude: true,
            isAvailable: isAdminEnabled,
        },
        translationSettings: {
            index: 3,
            showState: "showTranslationSettings",
            title: 'messages.translation-settings',
            collapseState: "collapseTranslationSettings",
            defaultShow: true,
            defaultInclude: true,
        },
        yandex: {
            index: 4,
            showState: "showYandexTranslation",
            title: 'messages.translation-ops',
            collapseState: "collapseYandexTranslation",
            defaultShow: false,
            defaultInclude: true,
            isDisabled: missingYandexKey,
        },
        suffixedKeyOps: {
            index: 4,
            showState: "showSuffixedKeyOps",
            title: 'messages.suffixed-keyops',
            collapseState: "collapseSuffixedKeyOps",
            defaultShow: false,
            defaultInclude: false,
            isDisabled: missingYandexKey,
        },

        // not shown as dashboards but available if needed
        appSettings: {
            index: 10,
            showState: "showAppSettings",
            collapseState: "collapseAppSettings",
            title: "messages.app-settings",
            defaultShow: false,
            defaultInclude: false,
        },
        search: {
            index: 15,
            showState: "showSearchDashboard",
            collapseState: "collapseSearchDashboard",
            title: "messages.search-dashboard",
            defaultShow: false,
            defaultInclude: false,
        },
        translations: {
            index: 20,
            showState: "showTranslationTable",
            collapseState: "collapseTranslationTable",
            title: "messages.translation-table",
            defaultShow: false,
            defaultInclude: false,
        },
        groups: {
            index: 25,
            showState: "showGroupManagementDashboard",
            collapseState: "collapseGroupManagementDashboard",
            title: "messages.group-management-dashboard",
            defaultShow: false,
            defaultInclude: false,
        },
    },

    routeDashboards: {
        "": {
            settingsPrefix: "",
            includeDashboards: ["summary", "mismatches", "translationSettings", "userAdmin", 'yandex', 'suffixedKeyOps'],
            // excludeDashboards: [],
        },
        "users": {
            settingsPrefix: "users",
            includeDashboards: ["summary", "mismatches", "translationSettings",],
            excludeDashboards: ["userAdmin",],
        },
        "groups": {
            settingsPrefix: "groups",
            // includeDashboards: ["summary", "mismatches", "translationSettings"],
            excludeDashboards: ["summary", "mismatches", "userAdmin",],
        },
        "topics": {
            settingsPrefix: "topics",
            // includeDashboards: [],
            // excludeDashboards: [],
        },
        "settings": {
            settingsPrefix: "settings",
            includeDashboards: ["summary", "mismatches",],
            // excludeDashboards: ["translationSettings",],
        },
        "search": {
            settingsPrefix: "search",
            // includeDashboards: ["summary", "mismatches", "translationSettings",],
            excludeDashboards: ["userAdmin",],
        },
        "yandex": {
            settingsPrefix: "yandex",
            // includeDashboards: ["summary", "mismatches", "translationSettings",],
            excludeDashboards: ["Yandex",],
        },
    },
};

// these are in uiSettings

export class AppSettings extends GlobalSetting {
    constructor() {
        super("appSettings");

        let defaults_$ = _$({
            // default settings
            isAdminEnabled: false,
            connectionList: { "": "default" },
            connectionName: "",
            transFilters: null,
            markdownKeySuffix: "",
            showUsage: false,
            resetUsage: false,
            usageInfoEnabled: false,
            mismatchEnabled: false,
            userLocalesEnabled: false,
            currentLocale: 'en',
            primaryLocale: 'en',
            translatingLocale: 'en',
            locales: ['en'],
            userLocales: ['en'],
            displayLocales: ['en'],
            groups: [],
            yandexKey: '',
            showUnpublishedSite: false,
            uiSettings: {
                yandexText: { "@@": '', }, // placeholder so Php does not convert empty object to empty array
                group: null,
                xDebugSession: null,
                searchText: '',
                showPublishButtons: true,    // these are the buttons
                collapsePublishButtons: true, // these are the all groups buttons
                defaultSuffixes: '-type\n' +
                '-header\n' +
                '-heading\n' +
                '-description\n' +
                '-footer',
            },
            suffixedKeyOps: {
                keys: '',
                suffixes: '',
            },
        });

        const updateSettingsType = {
            connectionName: UPDATE_IMMEDIATE,
            showUsage: UPDATE_THROTTLED,
            resetUsage: UPDATE_IMMEDIATE,
            currentLocale: UPDATE_THROTTLED,
            primaryLocale: UPDATE_THROTTLED,
            translatingLocale: UPDATE_THROTTLED,
            displayLocales: UPDATE_THROTTLED,
            showUnpublishedSite: UPDATE_THROTTLED,
            uiSettings: UPDATE_SERVER, // throttled but update server response ignored
        };

        let transforms_$ = _$({
            isAdminEnabled: _$.transform.toBoolean,
            showUsage: _$.transform.toBoolean,
            locales: _$.transform.toDefault(''),
            resetUsage: _$.transform.toBoolean,
            usageInfoEnabled: _$.transform.toBoolean,
            mismatchEnabled: _$.transform.toBoolean,
            userLocalesEnabled: _$.transform.toBoolean,
            showUnpublishedSite: _$.transform.toBoolean,
            uiSettings: {
                showPublishButtons: _$.transform.toBoolean,
                collapsePublishButtons: _$.transform.toBoolean,
            }
        });

        // include default settings for dashboardShow, collapse
        eachProp.call(dashboardConfig.dashboards, (dashboard, dashboardName) => {
            dashboard.dashboardName = dashboardName;

            const showState = util.firstDefined(dashboard.defaultShow, false);
            const collapseState = util.firstDefined(dashboard.defaultCollapse, false);
            const transformShow = showState ? _$.transform.toBooleanDefaultTrue : _$.transform.toBoolean;
            const transformCollapse = collapseState ? _$.transform.toBooleanDefaultTrue : _$.transform.toBoolean;

            transforms_$.uiSettings[dashboard.showState] = transformShow;
            transforms_$.uiSettings[dashboard.collapseState] = transformCollapse;
            defaults_$.uiSettings[dashboard.showState] = showState;
            defaults_$.uiSettings[dashboard.collapseState] = collapseState;

            // include default settings for dashboardShow, collapse route specific
            eachProp.call(dashboardConfig.routeDashboards, (routeConfig, route) => {
                const explicitlyIncluded = routeConfig.includeDashboards && routeConfig.includeDashboards.indexOf(dashboardName) !== -1;
                const implicitlyIncluded = !routeConfig.includeDashboards || explicitlyIncluded;
                const explicitlyExcluded = routeConfig.excludeDashboards && routeConfig.excludeDashboards.indexOf(dashboardName) !== -1;
                if ((explicitlyIncluded || implicitlyIncluded && dashboard.defaultInclude) && !explicitlyExcluded) {
                    // dashboard available for this route, add it so it won't need searching
                    if (!routeConfig.dashboards) routeConfig.dashboards = [];
                    const settingsPrefix = routeConfig.settingsPrefix;

                    if (!settingsPrefix) {
                        routeConfig.dashboards.push(dashboard);
                    } else {
                        routeConfig.dashboards.push(Object.assign({}, dashboard, {
                            showState: `${settingsPrefix}.${dashboard.showState}`,
                            collapseState: `${settingsPrefix}.${dashboard.collapseState}`,
                        }));

                        transforms_$.uiSettings[settingsPrefix][dashboard.showState] = transformShow;
                        transforms_$.uiSettings[settingsPrefix][dashboard.collapseState] = transformCollapse;
                        defaults_$.uiSettings[settingsPrefix][dashboard.showState] = showState;
                        defaults_$.uiSettings[settingsPrefix][dashboard.collapseState] = collapseState;
                    }
                }
            });
        });

        // need to sort the dashboards in the arrays in order of their index
        eachProp.call(dashboardConfig.routeDashboards, (routeConfig, route) => {
            if (routeConfig.dashboards) {
                routeConfig.dashboards.sort((a, b) => a.index - b.index);
            }
        });

        eachProp.call(appSettingChecks, (value,key) => {
            if (appSettingForcedChecks.hasOwnProperty(key)) {
                const setTo = appSettingForcedChecks[key];
                transforms_$.uiSettings[key] = setTo ? _$.transform.toAlwaysTrue : _$.transform.toAlwaysFalse;
                defaults_$.uiSettings[key] = setTo;
            } else {
                transforms_$.uiSettings[key] = _$.transform.toBooleanDefaultTrue;
                defaults_$.uiSettings[key] = true;
            }
        });

        const defaultSettings = defaults_$.$_value;
        const transforms = transforms_$.$_value;
        this.setStateTransforms(transforms);
        this.setDefaultSettings(defaultSettings, updateSettingsType);

        window.setTimeout(()=>{
            this.load();
        }, 100);
    }
    
    // implement to test if can request settings from server
    serverCanLoad() {
        return true;
    }
    
    adjustServerData(result, isLoad) {
        let data = result.data;
        
        if (isLoad) {
            const uiSettings = data.uiSettings;
            if (!uiSettings) {
                data.uiSettings = data.appSettings;
            }
            if (uiSettings && uiSettings.yandexText !== undefined && (!isObject(uiSettings.yandexText) || isArray(uiSettings.yandexText))) {
                // php converting empty objects to arrays if using prefer assoc option
                uiSettings.yandexText = {};
            }
            data.uiSettings = uiSettings;

            // merge all the default uiSettings the server may not have
            data = util.mergeDefaults.call(result.data, { uiSettings: appSettings.getState().uiSettings });
        }

        data && delete data.appSettings;
        return data;
    }

    // implement to request settings from server
    serverLoad() {
        axios.get(URL_GET_APP_SETTINGS().url)
            .then((result) => {
                // server may not have defaults, fill in from settings with fallback to defaults
                const data = this.adjustServerData(result, true);
                this.processServerUpdate(data);
            });
    }

    // implement to send server request
    updateServer(settings, frameId) {
        const api = URL_POST_APP_SETTINGS(settings);
        axios
            .post(api.url, api.data)
            .then((result) => {
                const data = this.adjustServerData(result, false);
                this.processServerUpdate(data, frameId);
            });
    }

    getRouteDashboard(dashboardRoute) {
        // may need to filter out ones not enabled
        if (isObject(dashboardRoute)) {
            dashboardRoute = dashboardRoute.path.substr(1); // chop off the leading / and we're good to go
            const type = typeof dashboardRoute;
            let tmp = 0;
        }

        const routeDashboards = dashboardConfig.routeDashboards[dashboardRoute];

        if (routeDashboards && routeDashboards.dashboards) {
            const dashboards = routeDashboards.dashboards.filter(dashboard => dashboard.isAvailable === UNDEFINED || dashboard.isAvailable());
            return dashboards;
        }
        return null;
    }

    getRouteSettingPrefix(dashboardRoute) {
        // may need to filter out ones not enabled
        dashboardRoute = this.dashboardRoute(dashboardRoute);

        const routeDashboards = dashboardConfig.routeDashboards[dashboardRoute];

        if (routeDashboards && routeDashboards.settingsPrefix) {
            return routeDashboards.settingsPrefix;
        }
        return '';
    }

    dashboardRoute(dashboardRoute) {
        if (isObject(dashboardRoute)) {
            dashboardRoute = dashboardRoute.path && dashboardRoute.path.substr(1) || ''; // chop off the leading / and we're good to go
            const type = typeof dashboardRoute;
            let tmp = 0;
        }
        return dashboardRoute;
    }
}

const appSettings = new AppSettings();
export const appSettings_$ = appSettings.getBoxed();

export function isAdminEnabled() {
    return appSettings_$.isAdminEnabled();
}

export function missingYandexKey() {
    const yandexKey = appSettings_$.yandexKey();
    return !yandexKey;
}

export default appSettings;

