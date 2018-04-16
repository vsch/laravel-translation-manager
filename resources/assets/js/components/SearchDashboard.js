import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import appSettings, { appSettings_$ } from "../helpers/AppSettings";
import globalSearchData, { globalSearchData_$ } from "../helpers/GlobalSearchData";
import Dashboard from "./Dashboard";
import PropTypes from "prop-types";
import DashboardComponent from "./DashboardComponent";
import SearchTranslations from "./SearchTranslations";

class SearchDashboard extends DashboardComponent {
    constructor(props) {
        super(props, 'search');

        this.state = this.getState();

        this.usesSettings = [globalSearchData, appSettings];
    }

    getState() {
        return this.adjustState({
            error: null,
            isLoaded: globalSearchData_$.isLoaded() && appSettings_$.isLoaded(),
            isLoading: globalSearchData_$.isLoading()/* || appSettings_$.isLoading()*/,
            isStaleData: globalSearchData_$.isStaleData() || appSettings_$.isStaleData(),
        });
    }

    reload() {
        globalSearchData.load();
    }

    render() {
        if (this.noShow()) return null;
        const {t} = this.props;

        return (
            <Dashboard headerChildren={t('messages.search-translations')}
                {...this.getDashboardProps()}
            >
                <SearchTranslations onLoad={SearchTranslations.takeFocus}/>
            </Dashboard>
        );
    }
}

SearchDashboard.propTypes = {
    routeSettings: PropTypes.string, // settings prefix for show/collapse
    showDashboard: PropTypes.bool,
    noHide: PropTypes.bool,
};

export default compose(translate(), connect())(SearchDashboard);
