import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import Dashboard from "./Dashboard";
import { appSettings_$ } from "../helpers/AppSettings";
import PropTypes from "prop-types";
import DashboardComponent from "./DashboardComponent";

class YandexTranslationDashboard extends DashboardComponent {
    constructor(props) {
        super(props, 'yandex', true);

        this.handlePrimaryTextChange = this.handlePrimaryTextChange.bind(this);
        this.handleTranslatingTextChange = this.handleTranslatingTextChange.bind(this);
        this.state = this.getState();
        this.onReload = null;
        // this.translatingTextUpdater = new DelayedTask(1000);
        this.setStateToSettings({
            primaryLocale: null,     // marks dependencies, not for update
            translatingLocale: null, // marks dependencies, not for update
            yandexPrimaryText: state => appSettings_$.uiSettings.yandexText[state.primaryLocale] = state.yandexPrimaryText,
            yandexTranslatingText: state => appSettings_$.uiSettings.yandexText[state.translatingLocale] = state.yandexTranslatingText,
            "": appSettings_$.save,   // function to invoke to apply changes
        });
    }

    getState() {
        const translatingLocale = appSettings_$.translatingLocale();
        const primaryLocale = appSettings_$.primaryLocale();
        const yandexText = appSettings_$.uiSettings.yandexText;
        const state = this.adjustState({
            error: null,
            isLoaded: appSettings_$.isLoaded(),
            isLoading: appSettings_$.isLoading()/* || appSettings_$.isLoading()*/,
            isStaleData: appSettings_$.isStaleData() || appSettings_$.isStaleData(),
            yandexKey: appSettings_$.yandexKey(),
            primaryLocale: primaryLocale,
            translatingLocale: translatingLocale,
            yandexPrimaryText: yandexText[primaryLocale]() || '',
            yandexTranslatingText: yandexText[translatingLocale]() || '',
        });

        return state;
    }

    reload() {

    }

    handlePrimaryTextChange(e) {
        this.state_$.yandexPrimaryText = e.target.value;
        this.state_$.save();
    }

    handleTranslatingTextChange(e) {
        this.state_$.yandexTranslatingText = e.target.value;
        this.state_$.save();
    }

    render() {
        const { t } = this.props;
        const { error, isLoaded, yandexKey, yandexPrimaryText, yandexTranslatingText, primaryLocale, translatingLocale } = this.state;

        if (this.noShow() || !yandexKey) return null;

        let body;
        if (error) {
            body = <div>Error: {error.message}</div>;
        } else if (!isLoaded) {
            body = <tr>
                <div className='text-center mx-auto'><div className='show-loading'/></div>
            </tr>;
        } else {
            body = (
                <div className="row">
                    <div className="col-sm-6">
                        <textarea value={yandexPrimaryText} id="primary-text" className="form-control" rows="3" name="keys" style={{ resize: "vertical" }} placeholder={primaryLocale} onChange={this.handlePrimaryTextChange}/>
                        <div style={{ minHeight: "10px" }}/>
                        <span dangerouslySetInnerHTML={{ __html: t('messages.powered-by-yandex') }}/>
                        <span style={{ float: "right", display: "inline" }}>
                           <button id="translate-primary-current" type="button" className="btn btn-sm btn-outline-secondary">
                               {primaryLocale}&nbsp;<i className="fa fa-share"/>&nbsp;{translatingLocale}</button>
                        </span>
                    </div>
                    <div className="col-sm-6">
                        <textarea value={yandexTranslatingText} id="current-text" className="form-control" rows="3" name="keys" style={{ resize: "vertical" }} placeholder={translatingLocale} onChange={this.handleTranslatingTextChange}/>
                        <div style={{ minHeight: "10px" }}/>
                        <button id="translate-current-primary" type="button" className="btn btn-sm btn-outline-secondary">
                            {primaryLocale}&nbsp;<i className="fa fa-reply"/>&nbsp;{translatingLocale}</button>
                        <span style={{ float: "right", display: "inline" }} dangerouslySetInnerHTML={{ __html: t('messages.powered-by-yandex') }}/>
                    </div>
                </div>
            );
        }

        // {/*{translatingLocale}&nbsp;<i className="fa fa-share"/>&nbsp;{primaryLocale}</button>*/}
        return (
            <Dashboard headerChildren={t('messages.translation-ops')}
                {...this.getDashboardProps()}
            >
                {body}
            </Dashboard>
        );
    }
}

YandexTranslationDashboard.propTypes = {
    routeSettings: PropTypes.string, // settings prefix for show/collapse
    showDashboard: PropTypes.bool,
    noHide: PropTypes.bool,
};

export default compose(translate(), connect())(YandexTranslationDashboard);
