import GlobalSetting from './GlobalSetting';
import appSettings, { appSettings_$ } from './AppSettings';
import axios from "axios";
import store from "./CreateAppStore";
import { URL_GET_SEARCH } from "./ApiRoutes";
import { isNullOrUndefined, noneNullOrUndefined } from "./helpers";
import appEvents from './AppEvents';
import globalMismatches from './GlobalMismatches';

export class GlobalSearchData extends GlobalSetting {
    constructor() {
        super("globalSearchData", {
            // default settings
            connectionName: "default",
            displayLocales: appSettings.defaultSettings.displayLocales,
            userLocales: appSettings.defaultSettings.userLocales,
            searchData: [],
        }, {
            // false means throttled, true - immediate update, commented or null - no server update
            // searchData: [],
        });

        this.connectionName = this.defaultSettings.connectionName;
        this.displayLocales = this.defaultSettings.displayLocales;
        this.userLocales = this.defaultSettings.userLocales;
        this.searchData = this.defaultSettings.searchData;
        this.searchText = null;
        this.isStaleData = false;
        this.isLoaded = true;
        this.isLoading = false;
        this.isULoading = false;

        this.unsubscribe = appSettings.subscribe(() => {
            if (noneNullOrUndefined(appSettings_$.displayLocales(), appSettings_$.uiSettings.searchText())) {
                if (!isNullOrUndefined(appSettings_$.primaryLocale())) {
                    const displayLocales = appSettings_$.displayLocales.$_array.join(',');
                    // const userLocales = appSettings_$.userLocales.$_ifArray(Array.prototype.join, ',');
                    const connectionNameDiff = this.connectionName !== appSettings_$.connectionName();
                    const searchText = (appSettings_$.uiSettings.searchText.$_value || '');
                    const searchTextDiff = this.searchText !== searchText;
                    const displayLocaleDiff = this.displayLocales.join(',') !== displayLocales;
                    // const userLocaleDiff = this.userLocales.join(',') !== userLocales;
                    if (searchText && (!this.displayLocales || !this.userLocales ||
                        connectionNameDiff ||
                        searchTextDiff ||
                        displayLocaleDiff
                        // || userLocaleDiff
                    )) {
                        if (!this.isStaleData) {
                            this.staleData();
                        }
                    } else {
                        if (this.isStaleData) {
                            this.isStaleData = false;
                            const action = this.reduxAction(this.getState());
                            store.dispatch(action);
                        }
                    }
                }
            }
        });

        this.invalidateTranslations = appEvents.subscribe('invalidate.translations', (group) => {
            this.staleData();
        });
        // this.load();
    }

    // implement to test if can request settings from server
    serverCanLoad(searchText) {
        // searchText = searchText || appSettings.getBoxed().uiSettings.searchText.valueOf();
        // return searchText;
        return true;
    }

    canAutoRefresh() {
        return false;
    }

    // implement to request settings from server
    serverLoad(searchText) {
        const { connectionName, displayLocales, } = appSettings.getState();
        searchText = searchText || appSettings_$.uiSettings.searchText();
        if (searchText) {
            const api = URL_GET_SEARCH(connectionName, displayLocales, searchText);
            axios.post(api.url, api.data)
                .then((result) => {
                    this.connectionName = result.data.connectionName;
                    this.displayLocales = result.data.displayLocales;
                    this.userLocales = result.data.userLocales;
                    this.searchText = result.data.searchText;
                    this.processServerUpdate(result.data);

                    appSettings_$.uiSettings.searchText = this.searchText;
                    appSettings_$.uiSettings.loadedSearchText = this.searchText;
                    appSettings_$.save();
                });
        } else {
            window.setTimeout(() => {
                this.connectionName = appSettings_$.connectionName();
                this.displayLocales = appSettings_$.displayLocales();
                this.userLocales = appSettings_$.userLocales();
                this.searchText = searchText;
                this.processServerUpdate({
                    searchText: this.searchText,
                    loadedSearchText: this.searchText,
                });
            }, 50);
        }
    }

    // implement to send server request
    updateServer(settings, frameId) {
        throw "globalSearchData has no update";
    }
}

const globalSearchData = new GlobalSearchData();
export const globalSearchData_$ = globalSearchData.getBoxed();

export default globalSearchData;
