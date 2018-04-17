import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import Dashboard from "./Dashboard";
import { appSettings_$ } from "../helpers/AppSettings";
import PropTypes from "prop-types";
import DashboardComponent from "./DashboardComponent";
import { URL_ADD_SUFFIXED_KEYS, URL_DELETE_SUFFIXED_KEYS } from "../helpers/ApiRoutes";
import SearchTranslations from "./SearchTranslations";
import appModal from '../helpers/AppModal';
import UrlButton from "./UrlButton";

const ADD_SUFFIXED_KEYS = "confirmAddSuffixedKeys";
const DELETE_SUFFIXED_KEYS = "confirmDeleteSuffixedKeys";

class KeyOperationsDashboard extends DashboardComponent {
    constructor(props) {
        super(props, 'suffixedKeyOps', true);

        this.handleKeysChange = this.handleKeysChange.bind(this);
        this.handleSuffixesChange = this.handleSuffixesChange.bind(this);
        this.addStandardSuffixes = this.addStandardSuffixes.bind(this);
        this.clearSuffixes = this.clearSuffixes.bind(this);
        this.clearKeys = this.clearKeys.bind(this);
        this.searchDialog = this.searchDialog.bind(this);

        this.state = this.getState();
        this.onReload = null;

        this.setStateToSettings({
            keys: state => appSettings_$.uiSettings.suffixedKeyOps.keys = state.keys,
            suffixes: state => appSettings_$.uiSettings.suffixedKeyOps.suffixes = state.suffixes,
            "": appSettings_$.save,   // function to invoke to apply changes
        });
    }

    getState() {
        const state = this.adjustState({
            error: null,
            isLoaded: appSettings_$.isLoaded(),
            isLoading: appSettings_$.isLoading(),
            isStaleData: appSettings_$.isStaleData(),
            group: appSettings_$.uiSettings.group(),
            keys: appSettings_$.uiSettings.suffixedKeyOps.keys() || '',
            suffixes: appSettings_$.uiSettings.suffixedKeyOps.suffixes() || '',
        });
        return state;
    }

    reload() {

    }

    handleKeysChange(e) {
        this.state_$.keys = e.target.value;
        this.state_$.save();
    }

    handleSuffixesChange(e) {
        this.state_$.suffixes = e.target.value;
        this.state_$.save();
    }

    addStandardSuffixes(e) {
        e.preventDefault();
        // this.state_$.suffixes = "-type\n-header\n-heading\n-description\n-footer" + (this.state.group === 'systemmessage-texts' ? '\n-footing' : '');
        this.state_$.suffixes = appSettings_$.uiSettings.defaultSuffixes();
        this.state_$.save();
    }

    clearSuffixes(e) {
        e.preventDefault();
        this.state_$.suffixes = "";
        this.state_$.save();
    }

    clearKeys(e) {
        e.preventDefault();
        this.state_$.keys = "";
        this.state_$.save();
    }

    searchDialog(e) {
        e.preventDefault();
        e.stopPropagation();
        if (appModal.inButtonOp) return;

        const onClose = function onClose(e, ok) {
            console.debug("Search closed", ok);
            appModal.inButtonOp = false;
        }.bind(this);

        const onGroup = function onGroup(e, group) {
            console.debug("Search closed by group", group);
            appModal.hideModal();
            appModal.inButtonOp = false;
        }.bind(this);

        appModal.showModal({
            onClose: onClose,
            onShown: () => {
                SearchTranslations.takeFocus(this.searchInput);
            },
            modalProps: {
                modalTitle: this.props.t('messages.search-translations'),
                modalType: '',
                modalDialogType: 'modal-dialog modal-lg',
                footer: null,
                backdrop: true,
            },
            modalBody: (
                <div style={{ background: "solid #fff" }}>
                    <SearchTranslations onLoad={(input) => {this.searchInput = input; }} onGroup={onGroup}/>
                </div>
            ),
        });
    }

    getConfirmationExtra() {
        const state = this.state;
        const keysList = state.keys.trim().split('\n').filter(item => !!item.trim());
        const suffixesList = state.suffixes.trim().split('\n').filter(item => !!item.trim());
        let body;

        const k = keysList.length;
        if (k > 0) {
            let columns = k > 4 ? 3 : k === 4 ? 2 : k === 3 ? 3 : k === 2 ? 2 : 1;
            let columnType = 'col col-sm-' + (12 / columns);

            const keys = [];
            keysList.forEach(key => {
                const suffixedKeys = [];
                if (suffixesList.length === 0) { 
                    suffixedKeys.push(key);
                } else {
                    suffixesList.forEach(suffix => {
                        suffixedKeys.push(key + suffix);
                    });
                }
                keys.push(suffixedKeys);
            });

            let kI = 0;
            let sI = 0;
            body = [];
            while (kI < keys.length) {
                const cols = [];
                for (let column = 0; column < columns; column++) {
                    let i = kI + column;
                    if (i < keys.length) {
                        const key = keys[i];

                        cols.push(
                            <div key={kI + '.' + sI + '.' + i} className={columnType}>
                                {key[sI]}
                            </div>,
                        );
                    }
                }

                body.push(
                    <div key={body.length} className='row'>
                        {cols}
                    </div>,
                );

                sI++;
                if (sI >= suffixesList.length) {
                    sI = 0;
                    kI += columns;
                    if (kI < keys.length) {
                        body.push(<hr key={body.length}/>);
                    }
                }
            }
            return body;
        }
    }

    render() {
        const { t } = this.props;
        const { error, isLoaded, group, keys, suffixes } = this.state;
        const disabled = !group;

        if (this.noShow()) return null;

        let body;
        if (error) {
            body = <div>Error: {error.message}</div>;
        } else if (!isLoaded) {
            body = <tr>
                <div className='text-center mx-auto'>
                    <div className='show-loading'/>
                </div>
            </tr>;
        } else {
            const postAddSuffixedKeys = URL_ADD_SUFFIXED_KEYS(group, this.state.keys, this.state.suffixes, appSettings_$.connectionName());
            const postDeleteSuffixedKeys = URL_DELETE_SUFFIXED_KEYS(group, this.state.keys, this.state.suffixes, appSettings_$.connectionName());

            const keysDisabled = (!keys.trim() ? "disabled " : "");
            const suffixesDisabled = (!suffixes.trim() ? "disabled " : "");

            const confirmationExtra = this.getConfirmationExtra();

            body = (
                <div>
                    <div className="row">
                        <div className="col-sm-6">
                            <label>{t('messages.keys')}:</label>
                            <textarea value={keys} id='keyop-keys' className="form-control" rows="4" style={{ resize: "vertical" }} placeholder={t('messages.addkeys-placeholder')} onChange={this.handleKeysChange}/>
                            <div style={{ minHeight: "10px" }}/>
                            <div className="row">
                                <div className="col-sm-8">
                                    <UrlButton
                                        className={keysDisabled + " btn btn-sm btn-primary mr-2"}
                                        disabled={!!keysDisabled}
                                        dataUrl={postAddSuffixedKeys}
                                        confirmationKey={ADD_SUFFIXED_KEYS}
                                        invalidateGroup={group}
                                        disableWith={t('messages.busy-processing')}
                                        confirmationBody={(defaultBody) =>
                                            <div>
                                                <p>{defaultBody}</p>
                                                {confirmationExtra}
                                            </div>
                                        }
                                    >{t('messages.addkeys')}</UrlButton>
                                    <button type="button" className={keysDisabled + "btn btn-sm btn-outline-secondary"} onClick={keysDisabled ? null : this.clearKeys}>{t('messages.clearkeys')}</button>
                                </div>
                                <div className="col-sm-4">
                                    <span style={{ float: "right", display: "inline" }}>
                                        <UrlButton
                                            className={keysDisabled + "btn btn-sm btn-danger"}
                                            disabled={!!keysDisabled}
                                            dataUrl={postDeleteSuffixedKeys}
                                            confirmationKey={DELETE_SUFFIXED_KEYS}
                                            invalidateGroup={group}
                                            disableWith={t('messages.busy-processing')}
                                            confirmationBody={(defaultBody) =>
                                                <div>
                                                    <p>{defaultBody}</p>
                                                    {confirmationExtra}
                                                </div>
                                            }
                                        >{t('messages.deletekeys')}</UrlButton>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div className="col-sm-6">
                            <label>{t('messages.suffixes')}:</label>
                            <textarea value={suffixes} id='keyop-suffixes' className="form-control" rows="4" style={{ resize: "vertical" }} placeholder={t('messages.addsuffixes-placeholder')} onChange={this.handleSuffixesChange}/>
                            <div style={{ minHeight: "10px" }}/>
                            <div className="row">
                                <div className="col-sm-8">
                                    <button type="button" className="btn btn-sm btn-outline-secondary mr-2" onClick={this.addStandardSuffixes}>{t('messages.addsuffixes')}</button>
                                    <button type="button" className={suffixesDisabled + "btn btn-sm btn-outline-secondary"} onClick={suffixesDisabled ? null : this.clearSuffixes}>{t('messages.clearsuffixes')}</button>
                                </div>
                                <div className="col-sm-4">
                                    <span style={{ float: "right", display: "inline" }}>
                                        <button type="button" className="btn btn-sm btn-outline-primary" onClick={this.searchDialog}>{t('messages.search')}</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        // {/*{translatingLocale}&nbsp;<i className="fa fa-share"/>&nbsp;{primaryLocale}</button>*/}
        return (
            <Dashboard headerChildren={t('messages.suffixed-keyops')}
                {...this.getDashboardProps()}
            >
                {body}
            </Dashboard>
        );
    }
}

KeyOperationsDashboard.propTypes = {
    routeSettings: PropTypes.string, // settings prefix for show/collapse
    showDashboard: PropTypes.bool,
    noHide: PropTypes.bool,
};

export default compose(translate(), connect())(KeyOperationsDashboard);
