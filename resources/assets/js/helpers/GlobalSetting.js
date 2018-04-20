import store, { registerGlobalSettingsHandler } from './CreateAppStore';
import { informListeners, subscribeListener } from "./Subscriptions";
import boxedImmutable from "boxed-immutable";
import DelayedTask from "./DelayedTask";
import { appSettings_$ } from './AppSettings';

const _$ = boxedImmutable.box;
const boxState = boxedImmutable.boxState;
const util = boxedImmutable.util;
const isArray = util.isArray;
const eachProp = util.eachProp;
const copyFiltered = util.copyFiltered;
const copyFilteredNot = util.copyFilteredNot;
const isFunction = util.isFunction;
const isObject = util.isObjectLike;
const UNDEFINED = util.UNDEFINED;

const NON_UPDATE_FIELDS = [
    'isLoaded',
    'isLoading',
    'isUpdating',
    'type',
    'settingsFrameID',
];

let settingOptions = {
    globalKey: "",              // unique key to identify this global setting
    defaultSettings: {},        // defaults to load 
    updateSettingsType: {},     // settings with their load type flag: true: immediate false: throttle
    updateDelay: 250,          // delay before sending updates to server
    reloadDeadTime: 1000,       // dead time after load request to server, no loads will happen before this
};

export const GLOBAL_SETTINGS_TRACE = {
    appSettings: false,
    globalTranslations: false,
    globalMismatches: false,
    globalSearchData: false,
    globalSummary: false,
    globalUserLocales: false,
};

export function isTraceEnabled(globalKey) {
    return !!GLOBAL_SETTINGS_TRACE[globalKey];
}

export const UPDATE_IMMEDIATE = 0;  // these update server immediately
export const UPDATE_THROTTLED = 1;  // these update server with delay
export const UPDATE_SERVER = 2;     // these update server with delay, but ignored when received from a server update, only local update and server load values are used
export const UPDATE_STORE = 3;      // these don't update server but update settings

export class GlobalSetting {
    constructor(globalKey, defaultSettings, updateSettingsType, updateDelay = 1000) {
        // this.fireSettingsChanged = this.fireSettingsChanged.bind(this);
        // this.fireSettingsLoaded = this.fireSettingsLoaded.bind(this);
        // this.getBoxed = this.getBoxed.bind(this);
        // this.getState = this.getState.bind(this);
        // this.load = this.load.bind(this);
        // this.processServerUpdate = this.processServerUpdate.bind(this);
        // this.reduxAction = this.reduxAction.bind(this);
        // this.serverCanLoad = this.serverCanLoad.bind(this);
        // this.serverLoad = this.serverLoad.bind(this);
        // this.updateServer = this.updateServer.bind(this);
        // this.subscribe = this.subscribe.bind(this);
        // this.subscribeLoaded = this.subscribeLoaded.bind(this);
        // this.throttledUpdate = this.throttledUpdate.bind(this);
        // this.update = this.update.bind(this);
        // this.staleData = this.staleData.bind(this);

        this.globalKey = globalKey;
        this.settingsFrameID = 0;
        this.storeSubscribers = [];
        this.serverLoadSubscribers = [];
        this.serverUpdateFrameId = 0;

        this.serverUpdater = new DelayedTask(updateDelay || 1000);
        this.loadThrottler = new DelayedTask(updateDelay || 1000);

        this.pendingLoad = null;  // arguments for skipped load because it was within loadDelay, will be used to trigger load with these args
        this.sentUpdates = null;      // settings sent, but not yet received, these will overlay on current values
        this.sentUpdateFrameIds = null; // sent properties to settings map from which they were sent so that on reception we will only remove sentUpdate props if same property here matches 
        this.pendingUpdates = null;   // settings waiting to be sent that we will overlay on current and sent value
        this.serverUpdates = null;    // settings waiting to be sent to server, not used in overlays
        this.lastSettingsFrameID = 0;
        this.isLoaded = false;      // set on first load from server 
        this.isLoading = false;     // set when waiting for data from server (load or update reception pending)
        this.isUpdating = false;    // set when waiting for server update to be completed
        this.isStaleData = true;    // reset when server data received and no sentUpdates waiting to be received

        this.state_$ = boxState(() => {
            return this.getState()
        }, (modified, boxed) => {
            this.update(boxed.$_delta)
        });

        // add it to the list of handlers
        registerGlobalSettingsHandler(this);

        this.storeUnsubscribe = store.subscribe(() => {
            this.state_$.cancel();
            this.fireSettingsChanged();
        });

        if (defaultSettings || updateSettingsType) {
            this.setDefaultSettings(defaultSettings || {}, updateSettingsType || {});
        }
    }

    setDefaultSettings(defaultSettings, updateSettingsType) {
        // how to process updates
        this.immediateProps = [];   // these update server immediately and overlay until received updated values from server
        this.throttledProps = [];   // these update server with delay and overlay until received updated values from server
        this.serverProps = [];      // these update server with delay, used to update store, ignored when received from a server update, only used in server load
        this.storeProps = [];       // these don't update server but update store

        this.updateSettingsType = {};
        copyFilteredNot.call(this.updateSettingsType, updateSettingsType, NON_UPDATE_FIELDS);

        eachProp.call(this.updateSettingsType, (keyValue, key) => {
            switch (keyValue) {
                case true:
                case UPDATE_IMMEDIATE:
                    this.immediateProps.push(key);
                    break;
                case false: // backward compatibility
                case UPDATE_THROTTLED:
                    this.throttledProps.push(key);
                    break;
                case UPDATE_SERVER:
                    this.serverProps.push(key);
                    break;
                case null:
                case UPDATE_STORE:
                    this.storeProps.push(key);
                    break;

                default:
                    throw "InvalidArgument for update setting type expected [0,1,2,3,true,false,null], got " + keyValue;
            }
        });

        let action = this.reduxAction(defaultSettings || {});
        let defaults = Object.assign({}, action);
        delete defaults['type'];

        this.defaultSettings = defaults;

        // add defaults to global state
        window.setTimeout(() => {
            store.dispatch(action);
        }, 0);
    }

    getState() {
        let state = store.getState();
        return state[this.globalKey] || this.defaultSettings;
    }

    getBoxed() {
        return this.state_$;
    }

    setStateTransforms(transforms) {
        const stateTransform = {
            isLoaded: _$.transform.toBoolean,
            isLoading: _$.transform.toBoolean,
            isUpdating: _$.transform.toBoolean,
            isStaleData: _$.transform.toBoolean,
        };

        const stateTransforms = {
            // getTransforms: Object.assign({}, transforms, stateTransform),
            setTransforms: Object.assign({}, transforms, stateTransform),
        };

        this.state_$.boxOptions(stateTransforms);
    }

    // override to customize action fields if needed for afterDispatch processing
    reduxAction(settings, ...extras) {
        return Object.assign({}, settings, {
            type: this.globalKey,
            isLoaded: this.isLoaded,
            isLoading: this.isLoading,
            isUpdating: this.isUpdating,
            isStaleData: this.isStaleData,
            settingsFrameID: this.settingsFrameID++,
        }, ...extras);
    }

    // implement to test if can request settings from server now or should reschedule
    serverCanLoad() {
        throw "serverCanLoad: Must be implemented in subclass";
    }

    serverLoad() {
        throw "serverLoad: Must be implemented in subclass";
    }

    /**
     * implement to send server request
     *
     * @param settings settings to send
     * @param frameId  frameId to prove for processing server response
     */
    updateServer(settings, frameId) {
        throw "serverUpdate: Must be implemented in subclass";
    }

    load() {
        if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[load request]:`);
        if (!this.loadThrottler.isPending() && this.serverCanLoad(...arguments)) {
            this.loadThrottler.restart(() => {
                if (this.pendingLoad) {
                    if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[load request]: delayed request running now`);
                    let loadArgs = this.pendingLoad;
                    this.pendingLoad = null;
                    this.load();
                }
            });

            this.pendingLoad = null;
            this.isLoading = true;

            if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[load request]: dispatching to store`);
            const action = this.reduxAction(this.getState());
            store.dispatch(action);

            if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[load request]: requesting from server`,...arguments);
            this.serverLoad();
        } else {
            if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[load request]: scheduled for later`);
            this.pendingLoad = true;
        }
    }

    canAutoRefresh() {
        return appSettings_$.uiSettings.autoUpdateViews();
    }

    staleData(canLoad) {
        this.isStaleData = true;
        if (canLoad || (!util.isValid(canLoad) && this.canAutoRefresh())) {
            this.load();
        } else {
            const action = this.reduxAction(this.getState());
            store.dispatch(action);
        }
    }

    // override to customize
    update(settingsUpdate) {
        if (settingsUpdate !== this.getState()) {
            this.throttledUpdate(settingsUpdate);
        }
    }

    throttledUpdate(settingsUpdate) {
        if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[next: ${this.serverUpdateFrameId + 1}]: throttled update request:`, settingsUpdate);

        let immediateValues = copyFiltered.call(undefined,settingsUpdate, this.immediateProps);
        let pendingValues = copyFiltered.call(undefined,settingsUpdate, this.throttledProps, this.pendingUpdates);
        let serverValues = copyFiltered.call(undefined,settingsUpdate, this.serverProps, this.serverUpdates);
        let storeValues = copyFiltered.call(undefined,settingsUpdate, this.storeProps);

        if (storeValues && serverValues) {
            // need to combine them
            Object.assign(storeValues, serverValues);
        } else {
            storeValues = serverValues || storeValues;
        }

        if (immediateValues || pendingValues || serverValues) {
            // have something new to send to server
            // cancel any pending updates
            this.serverUpdater.cancel();

            this.pendingUpdates = pendingValues || this.pendingUpdates;
            this.serverUpdates = serverValues || this.serverUpdates;

            this.isUpdating = !!(immediateValues || this.pendingUpdates || this.serverUpdates);
            if ((this.pendingUpdates || immediateValues) && settingsUpdate.isLoading !== false) this.isLoading = true;

            if (immediateValues) {
                // if sending some to server then send all

                immediateValues = Object.assign(immediateValues, this.pendingUpdates);
                this.sentUpdates = Object.assign({}, this.sentUpdates, immediateValues);

                // all values that need to the server
                immediateValues = Object.assign(immediateValues, this.serverUpdates);
                this.pendingUpdates = null;
                this.serverUpdates = null;

                const sentUpdateFrameId = this.updateSentUpdateFrameId(immediateValues);
                if (isTraceEnabled(this.globalKey)) window.console.debug(`${sentUpdateFrameId}: immediate update: `, immediateValues);
                this.updateServer(immediateValues, sentUpdateFrameId);
            } else if (this.pendingUpdates || this.serverUpdates) {
                // schedule these for later
                if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[next: ${this.serverUpdateFrameId + 1}]: scheduling pending server update:`, this.pendingUpdates, this.serverUpdates);
                this.serverUpdater.restart(() => {
                    if (this.pendingUpdates || this.serverUpdates) {

                        let serverUpdates = Object.assign({}, this.serverUpdates, this.pendingUpdates);
                        this.sentUpdates = Object.assign({}, this.sentUpdates, this.pendingUpdates);
                        this.serverUpdates = null;
                        this.pendingUpdates = null;

                        const sentUpdateFrameId = this.updateSentUpdateFrameId(serverUpdates);
                        if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[${sentUpdateFrameId}]: pending update:`, serverUpdates, this.sentUpdates);
                        this.updateServer(serverUpdates, sentUpdateFrameId);
                    }
                });
            }

            let action = this.reduxAction(this.getState(), this.sentUpdates, this.pendingUpdates, storeValues);
            if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[next ${this.serverUpdateFrameId + 1}]:store update:`, action, this.sentUpdates, this.pendingUpdates, storeValues);
            store.dispatch(action);
        } else if (storeValues) {
            let action = this.reduxAction(this.getState(), this.sentUpdates, this.pendingUpdates, storeValues);
            if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[next ${this.serverUpdateFrameId + 1}]:store update:`, action, this.sentUpdates, this.pendingUpdates, storeValues);
            store.dispatch(action);
        }
    }

    /**
     * Set properties in sentUpdatesSource properties to this latest update
     * @param settingsUpdate
     */
    updateSentUpdateFrameId(settingsUpdate) {
        if (!this.sentUpdateFrameIds) this.sentUpdateFrameIds = {};
        const sentUpdateFrameId = ++this.serverUpdateFrameId;

        eachProp.call(settingsUpdate, (value, key) => {
            if (this.sentUpdates.hasOwnProperty(key)) {
                this.sentUpdateFrameIds[key] = sentUpdateFrameId;
            }
        });

        return sentUpdateFrameId;
    }

    // called from subclasses after receiving and shaping server data to action shape
    /**
     * process data from server, if processing server update response, settings which are UPDATE_SERVER are removed from update
     *
     * @param settings          received settings, mutable
     * @param serverUpdateFrameId  id of server update send data
     */
    processServerUpdate(settings, serverUpdateFrameId) {
        let action;

        if (serverUpdateFrameId) {
            // overwrite received data which is server update only, ignored on reception with current data 
            util.copyFiltered.call(settings, this.getState(), this.serverProps);

            // set new state with already sent but not yet received settings and pending settings
            // remove received settings from sentUpdates and return
            if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[${serverUpdateFrameId}]: processing server update:`, settings);
            const sentUpdates = this.sentUpdates;
            const sentUpdateFrameIds = this.sentUpdateFrameIds;
            const remainingUpdates = {}; // Object.assign({}, this.sentUpdates);
            const remainingUpdateSource = {}; // Object.assign({}, this.sentUpdates);

            let isUpdating = false;

            if (sentUpdates) {
                eachProp.call(sentUpdates, (value, key) => {
                    if (value !== UNDEFINED) {
                        if (sentUpdateFrameIds.hasOwnProperty(key)) {
                            const sentUpdateFrameId = sentUpdateFrameIds[key];
                            if (sentUpdateFrameId === serverUpdateFrameId) {
                                // remove this one, it is the latest one
                            } else {
                                remainingUpdates[key] = value;
                                remainingUpdateSource[key] = sentUpdateFrameId;
                                isUpdating = true;
                            }
                        }
                    }
                });
            }

            this.sentUpdates = isUpdating ? remainingUpdates : null;
            this.sentUpdateFrameIds = isUpdating ? remainingUpdateSource : null;
            const tmp = 0;
        } else {
            if (isTraceEnabled(this.globalKey)) window.console.debug(`${this.globalKey}[]: load processing: `, settings);
        }

        // server load has occurred
        this.isLoaded = true;

        if (this.sentUpdates) {
            this.isLoading = true;
            this.isUpdating = true;
        } else {
            // not waiting to receive anything from server, no longer stale or loading
            this.isLoading = false;
            this.isStaleData = false;
            this.isUpdating = !!(this.pendingUpdates || this.serverUpdates);
        }

        // need to keep our store only update properties
        util.copyFiltered.call(settings, this.getState(), this.storeProps);
        action = this.reduxAction(settings, this.sentUpdates, this.pendingUpdates);
        store.dispatch(action);

        // inform of server load having occurred
        this.fireSettingsLoaded();
    }

    subscribe(listener) {
        return subscribeListener(this.storeSubscribers, listener);
    }

    fireSettingsChanged() {
        const settings = this.getState();

        if (this.lastSettingsFrameID !== settings.settingsFrameID) {
            this.lastSettingsFrameID = settings.settingsFrameID;
            informListeners(this.storeSubscribers);
        }
    }

    subscribeLoaded(listener) {
        return subscribeListener(this.serverLoadSubscribers, listener);
    }

    fireSettingsLoaded() {
        informListeners(this.serverLoadSubscribers);
    }
}

export default GlobalSetting;

