import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import WorkingSet from "./WorkingSet";
import TranslationOptions from "./TranslationOptions";
import UsageInfoSettings from "./UsageInfoSettings";
import Dashboard from "./Dashboard";
import appSettings, { appSettings_$ } from "../helpers/AppSettings";
import PropTypes from "prop-types";
import DashboardComponent from "./DashboardComponent";

class TranslationSettings extends DashboardComponent {
    constructor(props) {
        super(props, 'translationSettings');

        this.state = this.getState();
    }

    getState() {
        return this.adjustState({
            isLoaded: appSettings_$.isLoaded(),
            isLoading: appSettings_$.isLoading(),
            isStaleData: appSettings_$.isStaleData(),
        });
    }

    reload() {
        appSettings.load();
    }

    render() {
        const { t } = this.props;

        if (this.noShow()) return null;

        return (
            <Dashboard headerChildren={t('messages.translation-settings')}
                {...this.getDashboardProps()}
            >
                <TranslationOptions/>
                <hr/>
                <WorkingSet/>
                <hr/>
                <UsageInfoSettings/>
            </Dashboard>
        );
    }
}

TranslationSettings.propTypes = {
    routeSettings: PropTypes.string, // settings prefix for show/collapse
    showDashboard: PropTypes.bool,
    noHide: PropTypes.bool,
};

export default compose(
    translate(),
    connect(),
)(TranslationSettings);
