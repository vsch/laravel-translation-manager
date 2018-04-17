import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import $ from 'jquery';
import appSettings, { appSettings_$ } from "../helpers/AppSettings";
import Dashboard from "./Dashboard";
import PropTypes from "prop-types";
import { apiURL, RENAME_GROUP_URL } from "../helpers/ApiRoutes";
import DashboardComponent from "./DashboardComponent";

const DELETE_GROUP = 'confirmDeleteGroup';
const IMPORT_GROUP = 'confirmImportGroup';
const PUBLISH_GROUP = 'confirmPublishGroup';
const IMPORT_ALL_GROUPS = 'confirmImportAllGroups';
const ADD_REFERENCES = 'confirmAddReferences';
const PUBLISH_ALL_GROUPS = 'confirmPublishAllGroups';

const BUTTON_TYPES = {};
BUTTON_TYPES[DELETE_GROUP] = 'danger';
BUTTON_TYPES[IMPORT_GROUP] = 'success';
BUTTON_TYPES[PUBLISH_GROUP] = 'info';
BUTTON_TYPES[IMPORT_ALL_GROUPS] = 'success';
BUTTON_TYPES[ADD_REFERENCES] = 'danger';
BUTTON_TYPES[PUBLISH_ALL_GROUPS] = 'warning';

class GroupManagementDashboard extends DashboardComponent {
    constructor(props) {
        super(props, 'groups');

        this.state = this.getState();

        this.handleNameChange = this.handleNameChange.bind(this);
        this.handleNameReset = this.handleNameReset.bind(this);
        this.handleSelectionChange = this.handleSelectionChange.bind(this);
    }

    getState() {
        return this.adjustState({
            error: null,
            isLoaded: appSettings_$.isLoaded(),
            isLoading: appSettings_$.isLoading(),
            isStaleData: appSettings_$.isStaleData(),
            groups: appSettings_$.groups(),
            groupEdits: {},
        });
    }

    reload() {
        appSettings.load();
    }

    handleNameReset(e) {
        let $el = $(e.target);
        let groupName = $el.closest('td,th').data('original-name');
        const state_$ = this.state_$.cancel();
        state_$.groupEdits[groupName].newName = groupName;
        state_$.groupEdits[groupName].selected = false;
        state_$.save();
    }

    handleNameChange(e) {
        let $el = $(e.target);
        let groupName = $el.closest('td,th').data('original-name');
        let newName = $el.val();
        const state_$ = this.state_$.cancel();
        state_$.groupEdits[groupName].newName = newName;
        state_$.groupEdits[groupName].selected = newName !== '' && newName !== groupName;
        state_$.save();
    }

    handleSelectionChange(e) {
        let $el = $(e.target);
        let groupName = $el.closest('td,th').data('original-name');
        let selected = $el.prop('checked');
        let state_$ = this.state_$.cancel();
        const groupEdit_$ = state_$.groupEdits;
        if (groupName === '*') {
            let groups = this.state.groups;
            groups.forEach(groupName => groupEdit_$[groupName].selected = selected);
        } else {
            groupEdit_$[groupName].selected = selected;
        }
        state_$.save();
    }

    render() {
        const { t } = this.props;
        const { error, isLoaded, groups, } = this.state;

        if (this.noShow()) return null;

        let body;
        let allSelected = false;
        if (error) {
            body = <div>Error: {error.message}</div>;
        } else if (!isLoaded) {
            body = <tr>
                <td colSpan='4' width='100%' className='text-center'><div className='show-loading'/></td>
            </tr>;
        } else {
            const deleteGroupURL = (group, newGroup) => apiURL([RENAME_GROUP_URL, group, newGroup]);
            const groupEdit_$ = this.state_$.groupEdits;
            allSelected = true;

            body = [];
            let iMax = Math.ceil(groups.length / 2);
            for (let i = 0; i < iMax; i++) {
                let group = groups[i];
                let group2 = groups[i + iMax];

                let column = (function (group, index) {
                    let selected = !!groupEdit_$[group].selected();
                    let newName = groupEdit_$[group].newName();
                    newName = newName === undefined ? group || '' : newName || '';
                    if (!selected) allSelected = false;
                    return (
                        <td key={index + group} className={selected ? 'editing' : ''} data-original-name={group}>
                            <div className='row align-items-center'>
                                <div className='col-auto'>
                                    <div className='form-group mb-0'>
                                        <input className='form-control form-check-inline' type='checkbox' checked={selected} onChange={this.handleSelectionChange}/>
                                    </div>
                                </div>
                                <div className='col-11'>
                                    <div className='input-group mb-0'>
                                        <input type='text' className='form-control form-control-sm' value={newName} onChange={this.handleNameChange} placeholder={group}/>
                                        <div className="input-group-append">
                                            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={this.handleNameReset}>&times;</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {/*<input className="form-control form-control-sm border-primary" value={searchText || ''} onChange={this.searchTextChanged} onKeyPress={this.onKeyPress} type="search" placeholder={t('messages.search-text-placeholder')}/>*/}
                        </td>
                    )
                }).bind(this);

                body.push(
                    <tr key={body.length + group + '|' + group2}>
                        {column(group, i)}
                        {group2 ? column(group2, i + iMax) : null}
                    </tr>
                );
            }
        }

        return (
            <Dashboard headerChildren={t('messages.group-admin')}
                {...this.getDashboardProps()}
            >
                <table className="table table-sm table-striped table-translations">
                    <thead className='thead-light'>
                    <tr>
                        <th width="50%" data-original-name='*'>
                            <div className='row align-items-center'>
                                <div className='col-auto'>
                                    <div className='form-group mb-0'>
                                        <input className='form-control form-check-inline' type='checkbox' checked={allSelected} onChange={this.handleSelectionChange}/>
                                    </div>
                                </div>
                                <div className='col-11'>
                                    <div className='input-group mb-0'>
                                        {t('messages.group')}
                                    </div>
                                </div>
                            </div>
                        </th>
                        <th width="50%"/>
                        {/*<th width="40%">{t('messages.group-operations')}</th>*/}
                    </tr>
                    </thead>
                    <tbody>
                    {body}
                    </tbody>
                </table>
            </Dashboard>
        );
    }
}

GroupManagementDashboard.propTypes = {
    routeSettings: PropTypes.string, // settings prefix for show/collapse
    showDashboard: PropTypes.bool,
    noHide: PropTypes.bool,
};

export default compose(translate(), connect())(GroupManagementDashboard);
