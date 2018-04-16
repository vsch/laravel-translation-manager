import GlobalSetting from './GlobalSetting';
import axios from "axios";
import appSettings, { appSettings_$ } from "./AppSettings";
import { apiURL, GET_SUMMARY_DATA, URL_GET_SUMMARY_DATA } from "./ApiRoutes";
import appEvents from './AppEvents';

export class GlobalSummary extends GlobalSetting {
    constructor() {
        super("globalSummary", {
            // default settings
            connectionName: "default",
            displayLocales: null,
            summary: [],
        }, {
            // false means throttled, true - immediate update, commented or null - no server update
            // data: [],
        });

        this.connectionName = this.defaultSettings.connectionName;
        this.displayLocales = this.defaultSettings.displayLocales;

        this.unsubscribe = appSettings.subscribeLoaded(() => {
            if (appSettings_$.displayLocales[0]()) {
                if (this.displayLocales === null) {
                    // first load
                    this.load();
                } else {
                    if (!this.displayLocales ||
                        this.connectionName !== appSettings_$.connectionName() ||
                        this.displayLocales.join(',') !== appSettings_$.displayLocales.$_array.join(',')) {
                        this.staleData();
                    }
                }
            }
        });

        this.invalidateTranslations = appEvents.subscribe('invalidate.translations', () => {
            this.staleData();
        });

        this.invalidateGroup = appEvents.subscribe('invalidate.group', () => {
            this.staleData();
        });
        
        this.invalidateGroups = appEvents.subscribe('invalidate.groups', () => {
            this.staleData();
        });
        
        this.load();
    }

    // implement to test if can request settings from server
    serverCanLoad() {
        return appSettings_$.displayLocales[0]();
    }

    // implement to request settings from server
    serverLoad() {
        const {connectionName, displayLocales} = appSettings.getState();
        const api = URL_GET_SUMMARY_DATA(connectionName, displayLocales);
        axios.post(api.url, api.data)
            .then((result) => {
                this.displayLocales = result.data.displayLocales;
                this.connectionName = result.data.connectionName;
                this.processServerUpdate(result.data);
            });
    }

    // implement to send server request
    updateServer(settings, frameId) {
        throw "globalSummary has no update";
    }
}

const globalSummary = new GlobalSummary();
export const globalSummary_$ = globalSummary.getBoxed();

export default globalSummary;

