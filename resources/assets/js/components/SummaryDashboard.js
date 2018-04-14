import React from "react";
import { Link, withRouter } from "react-router-dom";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import appTranslations from "../helpers/GlobalTranslations";
import globalSummary, { globalSummary_$ } from "../helpers/GlobalSummary";
import Dashboard from "./Dashboard";
import appSettings, { appSettings_$ } from "../helpers/AppSettings";
import PropTypes from "prop-types";
import DashboardComponent from "./DashboardComponent";

function  StatColumn(props) {
    const { type: columnType, count } = props;
    return !count ? (
        <td className={columnType}>
            &nbsp;
        </td>
    ) : (<td className={columnType}>{count}</td>);
}

function  GroupColumn(props) {
    const stat = props.stat;
    const action = props.action;
    let type, count;

    if (stat.deleted) {
        type = 'deleted';
        count = stat.deleted;
    } else if (stat.missing) {
        type = 'missing';
        count = stat.missing;
    } else if (stat.changed) {
        type = 'changed';
        count = stat.changed;
    } else if (stat.cached) {
        type = 'cached';
        count = stat.cached;
    } else {
        type = '';
        count = '';
    }

    return (
        <td className={"group " + type}>
            <Link to="#" onClick={() => {props.callBack(stat.group)}}>{stat.group}</Link>
        </td>
    );
}

class SummaryDashboard extends DashboardComponent {
    constructor(props) {
        super(props,'summary');

        this.state = this.getState();

        this.showGroup = this.showGroup.bind(this);
        this.usesSettings = [globalSummary, appSettings];
    }

    getState() {
        return this.adjustState({
            error: null,
            isLoaded: globalSummary_$.isLoaded(),
            isLoading: globalSummary_$.isLoading()/* || appSettings_$.isLoading()*/,
            isStaleData: globalSummary_$.isStaleData() || appSettings_$.isStaleData(),
            summary: globalSummary_$.summary(),
        });
    }

    showGroup(group) {
        this.props.history.push('/');
        appTranslations.changeGroup(group);
    }

    getEntry(stat) {
        return {
            group: stat.hasOwnProperty('group') ? stat.group : null,
            deleted: stat.hasOwnProperty('deleted') ? stat.deleted : null,
            missing: stat.hasOwnProperty('missing') ? stat.missing : null,
            changed: stat.hasOwnProperty('changed') ? stat.changed : null,
            cached: stat.hasOwnProperty('cached') ? stat.cached : null,
        };
    }

    reload() {
        globalSummary.load();
    }

    render() {
        const { t } = this.props;
        const { error, isLoaded, summary, } = this.state;

        if (this.noShow()) return null;

        let body;
        if (error) {
            body = <div>Error: {error.message}</div>;
        } else if (!isLoaded) {
            body = <tr>
                <td colSpan='5' width='100%' className='text-center'><div className='show-loading'/></td>
            </tr>;
        } else {
            body = summary.map((item, index) => {
                const stat = this.getEntry(item);
                return (
                    <tr key={index}>
                        <StatColumn columnType='deleted' count={stat.deleted}/>
                        <StatColumn columnType='missing' count={stat.missing}/>
                        <StatColumn columnType='changed' count={stat.changed}/>
                        <StatColumn columnType='cached' count={stat.cached}/>
                        <GroupColumn stat={stat} callBack={this.showGroup}/>
                    </tr>
                )
            });
        }

        return (
            <Dashboard headerChildren={t('messages.stats')} maxHeight='300px'
                {...this.getDashboardProps()}
            >
                <table className="table table-sm table-hover table-striped table-bordered translation-stats">
                    <thead className='thead-light'>
                    <tr>
                        <th className="deleted" width="16%">{t('messages.deleted')}</th>
                        <th className="missing" width="16%">{t('messages.missing')}</th>
                        <th className="changed" width="16%">{t('messages.changed')}</th>
                        <th className="cached" width="16%">{t('messages.cached')}</th>
                        <th className="group" width="36%">{t('messages.group')}</th>
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

SummaryDashboard.propTypes = {
    routeSettings: PropTypes.string, // settings prefix for show/collapse
    showDashboard: PropTypes.bool,
    noHide: PropTypes.bool,
};

export default compose(translate(), connect())(withRouter(SummaryDashboard));
