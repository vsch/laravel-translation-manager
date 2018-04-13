import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import Dashboard from "./Dashboard";
import { appSettings_$ } from "../helpers/AppSettings";
import PropTypes from "prop-types";
import DashboardComponent from "./DashboardComponent";
import ModalDialog from "./ModalDialog";
import { apiURL, POST_ADD_SUFFIXED_KEYS, POST_DELETE_SUFFIXED_KEYS } from "../helpers/ApiRoutes";
import SearchTranslations from "./SearchTranslations";

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
            showSearch: this.state.showSearch || false,
            hideSearch: this.state.hideSearch || false,
        });

        state.suffixedKeysExtraFields = {
            keys: state.keys,
            suffixes: state.suffixes,
        };

        state.confirmationExtra = null;
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
                suffixesList.forEach(suffix => {
                    suffixedKeys.push(key + suffix);
                });
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
                            </div>
                        );
                    }
                }

                body.push(
                    <div key={body.length} className='row'>
                        {cols}
                    </div>
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
            state.confirmationExtra = body;
        }

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
        if (this.inButtonOp) return;

        const onClose = function onClose(e, ok) {
            console.log("Modal closed", ok);
            this.state_$.showModal = false;
            this.state_$.save();
            this.inButtonOp = false;
        }.bind(this);

        const onGroup = function onGroup(e, group) {
            console.log("Modal closed by group", group);
            this.state_$.showModal = false;
            this.state_$.hideSearch = true;
            this.state_$.save();
            this.inButtonOp = false;
        }.bind(this);

        this.state_$.showModal = true;
        this.state_$.modalProps = {
            modalTitle: this.props.t('messages.search-translations'),
            modalType: '',
            modalDialogType: 'modal-dialog modal-lg',
            onClose: onClose,
            onNotShown: () => {
                if (this.state.hideSearch) {
                    this.state_$.hideSearch = false;
                    this.state_$.save();
                    return true;
                }
            },
            footer: null,
        };
        this.state_$.modalBody = (
            <div style={{ background: "solid #fff" }}>
                <SearchTranslations onGroup={onGroup}/>
            </div>
        );

        this.state_$.save();
    }

    render() {
        const { t } = this.props;
        const { error, isLoaded, group, keys, suffixes, } = this.state;
        const disabled = !group;

        if (this.noShow()) return null;

        let body;
        if (error) {
            body = <div>Error: {error.message}</div>;
        } else if (!isLoaded) {
            body = <tr>
                <div className='text-center mx-auto'><img src='../images/loading.gif'/></div>
            </tr>;
        } else {
            const postAddSuffixedKeysUrl = (group) => apiURL(POST_ADD_SUFFIXED_KEYS, group);
            const postDeleteSuffixedKeysUrl = (group) => apiURL(POST_DELETE_SUFFIXED_KEYS, group);
            const suffixedKeysExtraFields = JSON.stringify({
                keys: this.state.keys,
                suffixes: this.state.suffixes,
            });

            const keysDisabled = (!keys.trim() ? "disabled " : "");
            const suffixesDisabled = (!suffixes.trim() ? "disabled " : "");
            body = (
                <div>
                    <div className="row">
                        <div className="col-sm-6">
                            <label>{t('messages.keys')}:</label>
                            <textarea value={keys} id='keyop-keys' className="form-control" rows="4" style={{ resize: "vertical" }} placeholder={t('messages.addkeys-placeholder')} onChange={this.handleKeysChange}/>
                            <div style={{ minHeight: "10px" }}/>
                            <div className="row">
                                <div className="col-sm-8">
                                    <button type="button"
                                        className={keysDisabled + " btn btn-sm btn-primary mr-2"}
                                        onClick={keysDisabled ? null : this.handleButtonClick}
                                        data-post-url={postAddSuffixedKeysUrl(group)}
                                        data-confirmation-key={ADD_SUFFIXED_KEYS}
                                        data-extra-fields='suffixedKeysExtraFields'
                                        data-invalidate-group={group}
                                        data-disable-with={t('messages.busy-processing')}>{t('messages.addkeys')}</button>
                                    <button type="button" className={keysDisabled + "btn btn-sm btn-outline-secondary"} onClick={keysDisabled ? null : this.clearKeys}>{t('messages.clearkeys')}</button>
                                </div>
                                <div className="col-sm-4">
                                    <span style={{ float: "right", display: "inline" }}>
                                        <button type="button"
                                            className={keysDisabled + "btn btn-sm btn-danger"}
                                            onClick={keysDisabled ? null : this.handleButtonClick}
                                            data-post-url={postDeleteSuffixedKeysUrl(group)}
                                            data-confirmation-key={DELETE_SUFFIXED_KEYS}
                                            data-extra-fields='suffixedKeysExtraFields'
                                            data-invalidate-group={group}
                                            data-disable-with={t('messages.busy-processing')}>{t('messages.deletekeys')}</button>
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

                <ModalDialog {...this.state.modalProps} showModal={this.state.showModal}>
                    {this.state.modalBody}
                </ModalDialog>
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
