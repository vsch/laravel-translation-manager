import React from "react";
import { boxState } from 'boxed-immutable';
import DelayedTask from "../helpers/DelayedTask";
import { isFunction } from "../helpers/helpers";
import appSettings from "../helpers/AppSettings";
import languageSynchronizer from "../helpers/LanguageSynchronizer";
import { Subscriber } from "../helpers/Subscriptions";

const APPLY_SETTINGS_PROP = "";  // property to hold function which applies all changes accumulated for state to settings map

class BoxedStateComponent extends React.Component {
    constructor(props) {
        super(props);

        this.setState = this.setState.bind(this);
        this.applyPendingSettings = this.applyPendingSettings.bind(this);

        this.state_$ = boxState(() => this.state, (modified, boxed) => {
            this.setState(boxed.$_delta);
        });

        this.usesSettings = [appSettings];
        this.usesOldScripts = false;

        this.settingsUpdater = new DelayedTask(500);
        this.stateToSettings = null; // no default state to settings updates
        this.stateToSettingsProps = null; // no default state to settings updates
        this.pendingStateSettings = null; // values of state properties waiting to be updated in settings, will override state returned from settings until they are updated 
        this.oldScriptHookerId = null;
    }

    componentDidMount() {
        this.subscriber = new Subscriber(...this.usesSettings, () => {
            this.state_$.cancel();
            this.setStateFromSettings(this.getState());
        });

        this.haveMounted = true;
        this.oldScriptHookerId = languageSynchronizer.hookOnComponentDidMount(this.oldScriptHookerId, this.usesOldScripts);
    }

    componentWillUnmount() {
        this.haveMounted = false;
        this.subscriber.unsubscribe();
        this.oldScriptHookerId = languageSynchronizer.hookOnComponentWillUnmount(this.oldScriptHookerId, this.usesOldScripts);
    }

    componentDidUpdate() {
        this.oldScriptHookerId = languageSynchronizer.hookOnComponentDidUpdate(this.oldScriptHookerId, this.usesOldScripts);
    }

    setStateTransforms(transforms) {
        let stateTransforms = transforms;
        if (!transforms.hasOwnProperty('getTransforms') && !transforms.hasOwnProperty('setTransforms')) {
            // we will wrap them in getTransforms
            stateTransforms = { /*getTransforms: transforms,*/ setTransforms: transforms, };
        }
        this.state_$.boxOptions(stateTransforms);
    }

    setStateToSettings(stateToSettings) {
        this.stateToSettings = stateToSettings;
        this.pendingStateSettings = null;

        if (stateToSettings) {
            if (!stateToSettings.hasOwnProperty(APPLY_SETTINGS_PROP)) {
                throw "IllegalArgument, setStateToSettings argument must have an '" + APPLY_SETTINGS_PROP + "' property function to save settings";
            }
            if (!isFunction(stateToSettings[APPLY_SETTINGS_PROP])) {
                throw "IllegalArgument, setStateToSettings argument '" + APPLY_SETTINGS_PROP + "' property is not a function";
            }
            this.stateToSettingsProps = Object.keys(stateToSettings);
            this.stateToSettingsProps.splice(this.stateToSettingsProps.indexOf(APPLY_SETTINGS_PROP), 1);
        }
    }

    applyPendingSettings() {
        const pendingStateSettings = this.pendingStateSettings;
        this.pendingStateSettings = null;

        if (pendingStateSettings) {
            this.stateToSettingsProps.forEach(prop => {
                const updater = this.stateToSettings[prop];
                if (isFunction(updater)) {
                    updater.call(null, pendingStateSettings);
                }
            });

            this.stateToSettings[APPLY_SETTINGS_PROP]();
        }
    }

    /**
     * Adjust state for pending settings update
     *
     * Subclass should call this before returning value from getState which converts settings to state
     *
     * @param state
     * @param args     ignored but may be used by subclass
     * @return state adjusted for pending settings updates
     */
    adjustState(state, ...args) {
        return this.pendingStateSettings ? Object.assign(state, this.pendingStateSettings) : state;
    }

    setState(newState) {
        this.state_$.cancel();
        this.settingsUpdater.cancel();

        if (newState) {
            // create a copy of delayed update for state to settings, also used to overwrite state values
            // until these are applied.
            if (this.stateToSettings) {
                if (isFunction(newState)) {
                    const func = newState;
                    React.Component.prototype.setState.call(this, (prevState, props) => {
                        newState = func(prevState, props);
                        this.updateStateSettings(newState);
                        return newState;
                    });
                } else {
                    this.updateStateSettings(newState);
                    React.Component.prototype.setState.call(this, newState);
                }
            } else {
                React.Component.prototype.setState.call(this, newState);
            }
        }
    }

    /**
     * Update without stateToSettings since this is from settings
     * @param newState
     */
    setStateFromSettings(newState) {
        this.state_$.cancel();
        this.settingsUpdater.cancel();

        if (newState) {
            React.Component.prototype.setState.call(this, newState);
        }
    }

    updateStateSettings(newState) {
        const pendingStateSettings = {};
        let result = null;

        this.stateToSettingsProps.forEach(prop => {
            if (newState.hasOwnProperty(prop)) {
                // take new setting
                pendingStateSettings[prop] = newState[prop];
                result = pendingStateSettings;
            } else if (this.pendingStateSettings && this.pendingStateSettings.hasOwnProperty(prop)) {
                // if missing in new take pending
                pendingStateSettings[prop] = this.pendingStateSettings[prop];
                result = pendingStateSettings;
            } else {
                // otherwise take current state
                pendingStateSettings[prop] = this.state[prop];
            }
        });
        this.pendingStateSettings = result;

        if (this.pendingStateSettings) {
            this.settingsUpdater.restart(this.applyPendingSettings);
        }
    }
}

export default BoxedStateComponent;
