import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import XEditable from './XEditable';
import $ from 'jquery';
import globalUserLocales, { globalUserLocales_$ } from "../helpers/GlobalUserLocales";
import appSettings, { appSettings_$ } from "../helpers/AppSettings";
import Dashboard from "./Dashboard";
import PropTypes from "prop-types";
import { absoluteUrlPrefix, apiURL, POST_ADD_SUFFIXED_KEYS, POST_CLEAR_USER_UI_SETTINGS, POST_USER_LOCALES } from "../helpers/ApiRoutes";
import DashboardComponent from "./DashboardComponent";
import ModalDialog from "./ModalDialog";

const CLEAR_USER_UI_SETTINGS = "confirmClearUserUiSettings";
class UserManagementDashboard extends DashboardComponent {
    constructor(props) {
        super(props, 'userAdmin');

        this.state = this.getState();
        this.usesSettings = [appSettings, globalUserLocales];
    }

    getState() {
        return this.adjustState({
            error: null,
            isLoaded: globalUserLocales_$.isLoaded() && appSettings_$.isLoaded(),
            isLoading: globalUserLocales_$.isLoading()/* || appSettings_$.isLoading()*/,
            isStaleData: globalUserLocales_$.isStaleData() || appSettings_$.isStaleData(),
            displayLocales: appSettings_$.displayLocales(),
            userLocaleList: globalUserLocales_$.userLocaleList(),
        });
    }

    getEntry(stat) {
        return {
            id: stat.hasOwnProperty('id') ? stat.id : null,
            email: stat.hasOwnProperty('email') ? stat.email : null,
            name: stat.hasOwnProperty('name') ? stat.name : null,
            locales: stat.hasOwnProperty('locales') ? stat.locales.replace(/,/g, ', ') : null,
        };
    }

    reload() {
        globalUserLocales.load();
    }

    render() {
        const { t, showDashboard, noHide } = this.props;
        const { error, isLoaded, userLocaleList, displayLocales } = this.state;

        if (this.noShow()) return null;

        let body;
        if (error) {
            body = <div>Error: {error.message}</div>;
        } else if (!isLoaded) {
            body = <tr>
                <td colSpan='4' width='100%' className='text-center'><img src='../images/loading.gif'/></td>
            </tr>;
        } else {
            const url = apiURL(absoluteUrlPrefix(), POST_USER_LOCALES);

            let allLocalesList = [...displayLocales];
            userLocaleList.forEach(item => {
                item.locales && allLocalesList.push(...item.locales.split(',').filter(item => item && allLocalesList.indexOf(item) === -1));
            });

            allLocalesList = allLocalesList.sort();

            const USER_LOCALES = [];
            let i = 1;
            for (let userLocale in allLocalesList) {
                if (!allLocalesList.hasOwnProperty(userLocale)) continue;
                const localeName = allLocalesList[userLocale];
                USER_LOCALES.push({ value: localeName, text: localeName });
            }

            const defaultOptions = {
                // editableform: {
                //     template: '' +
                //     '<form class="editableform">' +
                //     '<div class="control-group">' +
                //     '<div><div id="x-trans-edit" class="editable-input"></div></div>' +
                //     '<div class="editable-error-block"></div>' +
                //     '</div>' +
                //     '</form>'
                // },
                source: USER_LOCALES
                , placement: 'bottom'
                , emptytext: 'All'
                , showbuttons: false
                , display: function (value, sourceData) {
                    //display checklist as comma-separated values
                    let html = [],
                        checked = $.fn.editableutils.itemsByValue(value, sourceData);

                    if (checked.length) {
                        $.each(checked, function (i, v) {
                            html.push($.fn.editableutils.escape(v.text));
                        });
                        $(this).html(html.join(', '));
                    } else {
                        $(this).empty();
                    }
                }
            };

            const postClearUserSettingsUrl = (user) => apiURL(POST_CLEAR_USER_UI_SETTINGS, {user_id: user});
            
            body = userLocaleList.map((item, index) => {
                const $user = this.getEntry(item);
                const options = { ...defaultOptions };

                // to disable this users
                const deleteDisabled = ($user === this.state.user ? "disabled " : "");
                
                return (
                    <tr key={index + '.' + $user.id}>
                        <td className='align-right'>{$user.id}</td>
                        <td>{$user.email}</td>
                        <td>{$user.name}</td>
                        <td>
                            <XEditable to="#" className="user-locales" data-type="checklist" data-pk={$user.id} data-url={url} data-title="Select User Locales" data-value={$user.locales} defaultOptions={options}>
                                {$user.locales}
                            </XEditable>
                        </td>
                        <td>
                            <button type="button"
                                className={deleteDisabled + "btn btn-sm btn-outline-primary"}
                                onClick={this.handleButtonClick}
                                data-post-url={postClearUserSettingsUrl($user.id)}
                                data-confirmation-key={CLEAR_USER_UI_SETTINGS}
                                data-disable-with={t('messages.busy-processing')}>{t('messages.delete-uisettings')}</button>
                        </td>
                    </tr>
                )
            });
        }

        return (
            <Dashboard headerChildren={t('messages.user-admin')}
                {...this.getDashboardProps()}
            >
                <table className="table table-sm table-hover translation-stats">
                    <thead className='thead-light'>
                    <tr>
                        <th width="0%" className='align-right'>{t('messages.user-locales-user-id')}</th>
                        <th width="39%">{t('messages.user-email')}</th>
                        <th width="30%">{t('messages.user-name')}</th>
                        <th width="20%">{t('messages.user-locales')}</th>
                        <th width="0%"/>
                    </tr>
                    </thead>
                    <tbody>
                    {body}
                    </tbody>
                </table>

                <ModalDialog {...this.state.modalProps} showModal={this.state.showModal}>
                    {this.state.modalBody}
                </ModalDialog>
            </Dashboard>
        );
    }
}

UserManagementDashboard.propTypes = {
    routeSettings: PropTypes.string, // settings prefix for show/collapse
    showDashboard: PropTypes.bool,
    noHide: PropTypes.bool,
};

export default compose(translate(), connect())(UserManagementDashboard);
