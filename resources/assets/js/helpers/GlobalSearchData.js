import GlobalSetting from './GlobalSetting';
import appSettings, { appSettings_$ } from './AppSettings';
import axios from "axios";
import store from "./CreateAppStore";
import { apiURL, GET_SEARCH } from "./ApiRoutes";
import { isNullOrUndefined, noneNullOrUndefined } from "./helpers";

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
                    const displayLocales = appSettings_$.displayLocales.$_ifArray(Array.prototype.join, ',');
                    const userLocales = appSettings_$.userLocales.$_ifArray(Array.prototype.join, ',');
                    const connectionName = this.connectionName !== appSettings_$.connectionName();
                    const searchText = this.searchText !== (appSettings_$.uiSettings.searchText.$_value || '');
                    const displayLocaleDiff = this.displayLocales.join(',') !== displayLocales;
                    const userLocaleDiff = this.userLocales.join(',') !== userLocales;
                    if ((!this.displayLocales || !this.userLocales ||
                        connectionName ||
                        searchText ||
                        displayLocaleDiff ||
                        userLocaleDiff
                    )) {
                        if (!this.isStaleData) {
                            this.isStaleData = true;
                            const action = this.reduxAction(this.getState());
                            store.dispatch(action);
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

        // this.load();
    }

    // implement to test if can request settings from server
    serverCanLoad(searchText) {
        // searchText = searchText || appSettings.getBoxed().uiSettings.searchText.valueOf();
        // return searchText;
        return true;
    }

    // implement to request settings from server
    serverLoad(searchText) {
        searchText = searchText || appSettings_$.uiSettings.searchText();
        if (searchText) {
            const url = apiURL(GET_SEARCH, { q: searchText });
            axios.get(url)
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
            this.connectionName = appSettings_$.connectionName();
            this.displayLocales = appSettings_$.displayLocales();
            this.userLocales = appSettings_$.userLocales();
            this.searchText = searchText;
            this.processServerUpdate({
                searchText: this.searchText,
            });
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
