import React from "react";
import { Link, withRouter } from "react-router-dom";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import appSettings, { appSettings_$ } from "../helpers/AppSettings";
import globalSearchData, { globalSearchData_$ } from "../helpers/GlobalSearchData";
import TransXEditable from "./TransXEditable";
import appTranslations from "../helpers/GlobalTranslations";
import BoxedStateComponent from "./BoxedStateComponent";
import PropTypes from "prop-types";
import { boxedImmutable } from "../helpers/helpers";
import appEvents from '../helpers/AppEvents';
import globalMismatches from '../helpers/GlobalMismatches';

class SearchTranslations extends BoxedStateComponent {
    constructor(props) {
        super(props);

        this.state = this.getState();

        this.searchTextChanged = this.searchTextChanged.bind(this);
        this.showGroup = this.showGroup.bind(this);
        this.onKeyPress = this.onKeyPress.bind(this);

        this.usesSettings = [globalSearchData, appSettings];

        this.setStateToSettings({
            searchText: state => appSettings_$.uiSettings.searchText = state.searchText,
            "": appSettings_$.save,   // function to invoke to apply changes
        });
    }

    getState() {
        return this.adjustState({
            error: null,
            isLoaded: globalSearchData_$.isLoaded() && appSettings_$.isLoaded(),
            isLoading: globalSearchData_$.isLoading()/* || appSettings_$.isLoading()*/,
            isStaleData: globalSearchData_$.isStaleData() || appSettings_$.isStaleData(),
            userLocales: appSettings_$.userLocales(),
            displayLocales: appSettings_$.displayLocales(),
            loadedSearchText: appSettings_$.uiSettings.loadedSearchText(),
            searchText: appSettings_$.uiSettings.searchText(),
            searchData: globalSearchData_$.searchData(),
        });
    }

    componentDidMount() {
        BoxedStateComponent.prototype.componentDidMount.call(this);
        if (this.props.onLoad) {
            this.props.onLoad(this.input);
        }
        
        this.invalidateTranslations = appEvents.subscribe('invalidate.translations', (group) => {
            if (group) {
                globalSearchData.staleData();
            }
        });
    }

    componentWillUnmount() {
        BoxedStateComponent.prototype.componentWillUnmount.call(this);
        if (this.invalidateTranslations) this.invalidateTranslations();
    }
    
    static takeFocus(searchInput) {
        if (searchInput) {
            window.setTimeout(()=>{
                searchInput.focus();
                const value = searchInput.value;
                if (value) {
                    // this.searchInput.value = '';
                    // this.searchInput.value = value;
                    searchInput.setSelectionRange(0, value.length)
                }
            },100);
        }
    }

    reload() {
        globalSearchData.load();
    }

    onKeyPress(e) {
        const key = e.key;
        if (key === "Enter") {
            globalSearchData.load();
        }
    }

    showGroup(e, group) {
        // set group
        this.props.history.push('/');
        appTranslations.changeGroup(group);
        boxedImmutable.util.isFunction(this.props.onGroup) && this.props.onGroup(e, group);
    }

    searchTextChanged(e) {
        let searchText = e.target.value || '';
        this.state_$.searchText = searchText;
        this.state_$.save();

        appSettings_$.uiSettings.searchText = searchText;
        appSettings_$.save();
    }

    render() {
        const { t } = this.props;
        let { error, loadedSearchText, searchText, isLoaded, isLoading, isStaleData, searchData, userLocales } = this.state;

        let results;
        if (error) {
            results = <h4>Error: {error.message}</h4>;
        } else if (isLoading && !isLoaded) {
            results = (
                <div className='text-center'>
                    <div className='show-loading'/>
                </div>
            );
        } else if (!searchData || !searchData.length) {
            results = searchText && !isStaleData ? <h4 className='text-center'>{t('messages.no-results')}</h4> : "";
            if (!results) {
                isStaleData = false;
            }
        } else {
            let body = searchData.map((item, index) => {
                const $t = item;

                let $locale = $t.locale;
                let $isLocaleEnabled = userLocales.indexOf($locale) > -1;

                let $borderTop = 'no-border-top';
                return (
                    <tr key={index + ':' + $t.group + '.' + $t.key} id={$t.key.replace('.', '-')} className={$borderTop}>
                        <td><Link to={"/"} onClick={(e) => this.showGroup(e, $t.group)}>{$t.group}</Link></td>
                        <td>{$t.key}</td>
                        <td>{$t.locale}</td>
                        <td>{$isLocaleEnabled ? TransXEditable.transXEditLink($t.group, $t.key, $t.locale, $t, false) : $t.value}</td>
                    </tr>
                );
            });

            results = (
                <table key={1} className={"table table-sm table-hover table-striped table-bordered table-translations" + (this.state.isStaleData ? " stale-data" : "")}>
                    <thead className='thead-light'>
                    <tr key={"heading1"}>
                        <th colSpan="4">[{searchData.length}]: {/*{i18n.exists('messages.search-header-prefix') ? t('messages.search-header-prefix') : ''}*/}
                            &nbsp;<code>{loadedSearchText}</code>&nbsp;
                            {/*{i18n.exists('messages.search-header-prefix') ? t('messages.search-header-suffix') : ''}*/}
                        </th>
                    </tr>
                    <tr key={"heading2"}>
                        <th width="20%">{t('messages.group')}</th>
                        <th width="25%">{t('messages.key')}</th>
                        <th width=" 5%">{t('messages.locale')}</th>
                        <th width="50%">{t('messages.translation')}</th>
                    </tr>
                    </thead>
                    <tbody>
                    {body}
                    </tbody>
                </table>
            );
        }

        return (
            <div className='row'>
                <div className='col col-12'>
                    <div className="input-group input-group-sm mb-3">
                        <div className="input-group-prepend">
                            <button type="button" className="btn btn-outline-primary" onClick={this.reload}>{t('messages.search')}</button>
                        </div>
                        <input ref={(input) => { this.input = input; }} className="form-control form-control-sm border-primary" value={searchText || ''} onChange={this.searchTextChanged} onKeyPress={this.onKeyPress} type="search" placeholder={t('messages.search-text-placeholder')}/>
                    </div>
                    {results}
                </div>
            </div>
        );
    }
}

SearchTranslations.propTypes = {
    onGroup: PropTypes.func,  // func to call when clicked on group
    onLoad: PropTypes.func,  // func to call when component updated and has ref to input
};

export default compose(translate(), connect())(withRouter(SearchTranslations));
