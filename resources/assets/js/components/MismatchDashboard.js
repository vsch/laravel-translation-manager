import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import appSettings, { appSettings_$ } from "../helpers/AppSettings";
import globalMismatches, { globalMismatches_$ } from "../helpers/GlobalMismatches";
import Dashboard from "./Dashboard";
import PropTypes from "prop-types";
import DashboardComponent from "./DashboardComponent";
import TranslationMismatches from "./TranslationMismatches";

class MismatchDashboard extends DashboardComponent {
    constructor(props) {
        super(props,'mismatches');

        this.state = this.getState();
        this.usesSettings = [globalMismatches, appSettings];
    }

    getState() {
        return this.adjustState({
            error: null,
            isLoaded: globalMismatches_$.isLoaded() && appSettings_$.isLoaded(),
            isLoading: globalMismatches_$.isLoading(),
            isStaleData: globalMismatches_$.isStaleData() || appSettings_$.isStaleData(),
            translatingLocale: appSettings_$.translatingLocale(),
            primaryLocale: appSettings_$.primaryLocale(),
            userLocales: appSettings_$.userLocales(),
            mismatches: globalMismatches_$.mismatches(),
        });
    }

    reload() {
        globalMismatches.load();
    }
    
    render() {
        const {t} = this.props;

        if (this.noShow()) return null;

        return (
            <Dashboard headerChildren={t('messages.mismatches')} maxHeight='300px'
                {...this.getDashboardProps()}
            >
                <TranslationMismatches {...this.state}/>
            </Dashboard>
        );
    }
}

MismatchDashboard.propTypes = {
    routeSettings: PropTypes.string, // settings prefix for show/collapse
    showDashboard: PropTypes.bool,
    noHide: PropTypes.bool,
};

export default compose(translate(), connect())(MismatchDashboard);
