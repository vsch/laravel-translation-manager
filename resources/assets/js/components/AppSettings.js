import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import Dashboard from "./Dashboard";
import appSettings, { appSettingChecks, appSettingForcedChecks, appSettings_$ } from "../helpers/AppSettings";
import $ from "jquery";
import boxedImmutable from "boxed-immutable";
import DashboardComponent from "./DashboardComponent";
import { GLOBAL_SETTINGS_TRACE } from "../helpers/GlobalSetting";

const $_ = boxedImmutable.boxOut;

class AppSettings extends DashboardComponent {
    constructor(props) {
        super(props, 'appSettings');

        this.state = this.getState();

        $_(GLOBAL_SETTINGS_TRACE).eachProp((value, key) => {
            this.state["trace-" + key] = GLOBAL_SETTINGS_TRACE[key];
        });

        this.saveSettings = this.saveSettings.bind(this);
        this.xDebugSessionClear = this.xDebugSessionClear.bind(this);
        this.xDebugSessionChanged = this.xDebugSessionChanged.bind(this);
        this.handleStateChange = this.handleStateChange.bind(this);
        this.handleTraceChange = this.handleTraceChange.bind(this);
        this.handleSuffixesChange = this.handleSuffixesChange.bind(this);
    }

    getState() {
        const state = this.adjustState({
            isLoaded: appSettings_$.isLoaded(),
            isLoading: appSettings_$.isLoading(),
            isAdminEnabled: appSettings_$.isAdminEnabled(),
            xDebugSession: appSettings_$.uiSettings.xDebugSession(),
            defaultSuffixes: appSettings_$.uiSettings.defaultSuffixes() || '',
            showUnpublishedSite: appSettings_$.showUnpublishedSite() || false,
        });

        $_(GLOBAL_SETTINGS_TRACE).eachProp((value, key) => {
            state["trace-" + key] = !!this.state["trace-" + key];
        });

        $_(appSettingChecks).eachProp((value, key) => {
            if (appSettingForcedChecks.hasOwnProperty(key)) {
                state[key] = appSettingForcedChecks[key];
            } else {
                state[key] = !!appSettings_$.uiSettings[key]();
            }
        });
        return state;
    }

    reload() {
        appSettings.load();
    }

    handleSuffixesChange(e) {
        this.state_$.defaultSuffixes = e.target.value;
        this.state_$.save();
    }

    saveSettings() {
        appSettings_$.uiSettings._$(_$ => {
            _$.xDebugSession = this.state.xDebugSession;
            $_(appSettingChecks).eachProp((value, key) => {
                if (appSettingForcedChecks.hasOwnProperty(key)) {
                    _$[key] = appSettingForcedChecks[key];
                } else {
                    _$[key] = !!this.state_$[key]();
                }
            });
            _$.defaultSuffixes = this.state.defaultSuffixes;
        });

        appSettings_$.showUnpublishedSite = this.state.showUnpublishedSite;
        appSettings_$.save();

        // save trace values, they are not persisted
        $_(GLOBAL_SETTINGS_TRACE).eachProp((value, key) => {
            GLOBAL_SETTINGS_TRACE[key] = !!this.state["trace-" + key];
        });
    }

    xDebugSessionClear() {
        this.state_$.xDebugSession = null;
        this.state_$.save();
    }

    xDebugSessionChanged(e) {
        this.state_$.xDebugSession = e.target.value;
        this.state_$.save();
    }

    handleStateChange(e) {
        const $el = $(e.target);
        const stateKey = $el.data('state-key');
        this.state_$[stateKey] = $el.prop('checked');
        this.state_$.save();
    }

    handleTraceChange(e) {
        // need to make a copy or state_$ will not see any changes 
        let t = this.state;
        this.state_$._$["trace-" + e.target.name] = !!e.target.checked;
        this.state_$.save();
    }

    render() {
        const { t } = this.props;
        const { isAdminEnabled, xDebugSession, showUnpublishedSite } = this.state;

        if (this.noShow()) return null;

        const state_$ = this.state_$;
        const buttons = Object.keys(appSettingChecks)/*.filter(key => !appSettingForcedChecks.hasOwnProperty(key))*/.map((key, index) => {
            const disabled = !!appSettingForcedChecks[key];
            const dashCase = appSettingChecks[key];
            let checkedState = !!state_$[key]();
            return <label key={index}>
                <input type="checkbox" disabled={disabled} name={dashCase}
                    data-state-key={key}
                    checked={checkedState} onChange={this.handleStateChange}/>
                {t('messages.' + dashCase)}
            </label>;
        });

        const rows = [];
        const iMax = buttons.length;
        for (let i = 0; i < iMax; i += 2) {
            const firstButton = buttons[i];
            const secondButton = buttons[i + 1];
            rows.push(i ?
                (
                    <div key={i + 'row'} className="row">
                        <div className=" col-sm-3">
                        </div>
                        <div className=" col-sm-4">
                            {firstButton}
                        </div>
                        <div className=" col-sm-5">
                            {secondButton}
                        </div>
                    </div>
                ) : (
                    <div key={i + 'row'} className="row">
                        <div className="col-sm-3">
                            <button type="button" className='btn btn-sm btn-primary'
                                onClick={this.saveSettings}>
                                {t("messages.save-settings")}
                            </button>
                        </div>
                        <div className=" col-sm-3">
                            <label>
                                <input type="checkbox"
                                    data-state-key='showUnpublishedSite'
                                    checked={showUnpublishedSite} onChange={this.handleStateChange}/>
                                {t('messages.show-unpublished-site')}
                            </label>
                        </div>
                        <div className=" col-sm-3">
                            {firstButton}
                        </div>
                        <div className=" col-sm-3">
                            {secondButton}
                        </div>
                    </div>
                ),
            );
        }

        const lastRow = [];
        lastRow.push(rows.shift());

        return (
            <Dashboard headerChildren={t('messages.application-settings')}
                {...this.getDashboardProps()}
            >
                {isAdminEnabled && (
                    <div>
                        <div className="row">
                            <div className=" col-sm-2">
                                <label>{t('messages.xdebug-session')}:</label>
                            </div>
                            <div className="col-sm-4">
                                <div className="input-group input-group-sm">
                                    <input className="form-control form-control-sm"
                                        value={xDebugSession || ''}
                                        onChange={this.xDebugSessionChanged} type="text"
                                        placeholder={t('messages.xdebug-session')}/>
                                    <div className="input-group-append">
                                        <button type="button"
                                            className="btn btn-outline-secondary"
                                            onClick={this.xDebugSessionClear}>&times;</button>
                                    </div>
                                </div>
                            </div>
                            <div className="col-sm-5">
                            </div>
                        </div>
                        <hr/>
                        <div className="row">
                            <div className=" col-sm-2">
                                <label>{t('messages.debug-trace')}:</label>
                            </div>
                            <div className="col-sm-10">
                                <div className="input-group-sm">
                                    {$_(GLOBAL_SETTINGS_TRACE).mapProps((value, globalKey) => (
                                        <label key={globalKey}>
                                            <input
                                                className='display-locale'
                                                name={globalKey} type="checkbox"
                                                value={globalKey}
                                                checked={!!this.state["trace-" + globalKey]}
                                                disabled={false}
                                                onChange={this.handleTraceChange}
                                            />
                                            {t("messages.trace-" + globalKey)}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                )}
                <hr/>
                {rows}
                <hr/>
                <div className='row'>
                    <div className='col col-sm-3'>
                        <div className='row'>
                            <div className='col col-sm-12'>
                                <label>{t('messages.default-suffixes')}:</label>
                            </div>
                        </div>
                        <div className='row'>
                            <div className='col col-sm-12'>
                                <p className='text-secondary'>{t('messages.default-suffixes-placeholder')}</p>
                            </div>
                        </div>
                    </div>
                    <div className='col col-sm-9'>
                        <textarea value={this.state.defaultSuffixes} className="form-control"
                            rows="6" style={{ resize: "vertical" }}
                            placeholder={t('messages.default-suffixes-placeholder')}
                            onChange={this.handleSuffixesChange}/>
                    </div>
                </div>
                <hr/>
                {lastRow}
            </Dashboard>
        );
    }
}

// AppSettings.propTypes = {
//     routeSettings: PropTypes.string, // settings prefix for show/collapse
//     showDashboard: PropTypes.bool,   // passed to dashboard, ignored cause of auto config
//     noHide: PropTypes.bool,         // passed to dashboard, disabled close button on
// dashboard when set };

export default compose(
    translate(),
    connect(),
)(AppSettings);
