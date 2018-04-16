import GlobalSetting from './GlobalSetting';
import appSettings, { appSettings_$ } from './AppSettings';
import axios from "axios";
import { URL_GET_MISMATCHES } from "./ApiRoutes";
import { anyNullOrUndefined } from "./helpers";
import appEvents from './AppEvents';

export class GlobalMismatches extends GlobalSetting {
    constructor() {
        super("globalMismatches", {
            // default settings
            connectionName: "default",
            mismatches: [],
        }, {
            // false means throttled, true - immediate update, commented or null - no server update
            // mismatches: [],
        });

        this.primaryLocale = null;
        this.translatingLocale = null;
        this.connectionName = this.defaultSettings.connectionName;

        this.unsubscribe = appSettings.subscribe(() => {
            if (!anyNullOrUndefined(appSettings_$.uiSettings.group(), appSettings_$.primaryLocale(), appSettings_$.translatingLocale())) {
                if (anyNullOrUndefined(this.primaryLocale, this.translatingLocale)) {
                    // first load
                    this.load(appSettings_$.uiSettings.group());
                } else {
                    if (this.primaryLocale !== appSettings_$.primaryLocale()
                        || this.translatingLocale !== appSettings_$.translatingLocale()
                        || this.connectionName !== appSettings_$.connectionName()) {
                        this.staleData();
                    }
                }
            }
        });

        this.load();
    }

    // implement to test if can request settings from server
    serverCanLoad() {
        let { primaryLocale, translatingLocale, } = appSettings.getState();
        return primaryLocale && translatingLocale && primaryLocale !== translatingLocale;
    }

    // implement to request settings from server
    serverLoad() {
        let { primaryLocale, translatingLocale, connectionName } = appSettings.getState();
        const api = URL_GET_MISMATCHES(connectionName, primaryLocale, translatingLocale);
        axios.post(api.url, api.data)
            .then((result) => {
                this.connectionName = result.data.connectionName;
                this.primaryLocale = result.data.primaryLocale;
                this.translatingLocale = result.data.translatingLocale;
                this.processServerUpdate(result.data);
            });
    }

    // implement to send server request
    updateServer(settings, frameId) {
        throw "globalMismatches has no update";
    }
}

const globalMismatches = new GlobalMismatches();
export const globalMismatches_$ = globalMismatches.getBoxed();
export default globalMismatches;
