import React from "react";
import { NavLink, Route, Switch, withRouter } from "react-router-dom";
import Settings from "./Settings";
import UserManagementDashboard from "./UserManagementDashboard";
import GroupManagementDashboard from "./GroupManagementDashboard";
import SummaryDashboard from "./SummaryDashboard";
import MismatchDashboard from "./MismatchDashboard";
import SearchDashboard from "./SearchDashboard";
import TranslationsTable from "./TranslationsTable";
import KeyOperationsDashboard from "./KeyOperationsDashboard";
import { translate } from 'react-i18next';
import appSettings, { appSettings_$ } from '../helpers/AppSettings';
import appTranslations, { appTranslations_$ } from '../helpers/GlobalTranslations';
import store from '../helpers/CreateAppStore';
import YandexTranslationDashboard from "./YandexTranslationDashboard";
import { _$, isFunction } from "../helpers/helpers";
import AppSettingsComponent from "./AppSettings";
import TranslationSettings from "./TranslationSettings";
import BoxedStateComponent from "./BoxedStateComponent";
import ModalDialog from "./ModalDialog";
import appModal from "../helpers/AppModal";

const dashboardComponents = {
    summary: SummaryDashboard,
    mismatches: MismatchDashboard,
    userAdmin: UserManagementDashboard,
    translationSettings: TranslationSettings,
    appSettings: AppSettingsComponent,
    search: SearchDashboard,
    translations: TranslationsTable,
    groups: GroupManagementDashboard,
    yandex: YandexTranslationDashboard,
    suffixedKeyOps: KeyOperationsDashboard,
};

class App extends BoxedStateComponent {
    constructor(props) {
        super(props);

        this.state = this.getState();

        // this.loginHandler = this.loginHandler.bind(this);
        this.Home = this.Home.bind(this);
        this.Settings = this.Settings.bind(this);
        this.Topics = this.Topics.bind(this);
        this.Search = this.Search.bind(this);
        this.UserAdmin = this.UserAdmin.bind(this);
        this.GroupAdmin = this.GroupAdmin.bind(this);
        this.loadGroup = this.loadGroup.bind(this);
        this.toggleDashboard = this.toggleDashboard.bind(this);
        this.dashboardLinks = this.dashboardLinks.bind(this);
        this.dashboardComponents = this.dashboardComponents.bind(this);
        this.collectDashboards = this.collectDashboards.bind(this);
        this.sideMenuClick = this.sideMenuClick.bind(this);
        this.Yandex = this.Yandex.bind(this);
    }

    componentDidMount() {
        let router = this.context.router;

        this.unsubscribe = store.subscribe(() => {
            this.setState(this.getState());
        });
    }

    componentWillUnmount() {
        this.unsubscribe();
    }

    // noinspection JSMethodCanBeStatic
    getState() {
        const state = {
            loggedIn: true,
            isAdminEnabled: appSettings_$.isAdminEnabled(),
            yandexKey: appSettings_$.yandexKey(),
            groups: appSettings_$.groups(),
            group: appTranslations_$.group(),
            modalBody: appModal.getState().modalBody,
            modalProps: appModal.getState().modalProps,
            showModal: appModal.getState().showModal,
            hideModal: appModal.getState().hideModal,
        };
        return state;
    }

    toggleDashboard(e, showState) {
        e.preventDefault();
        appSettings_$.uiSettings.$_path(showState, _$.transform.toBooleanNot);
        appSettings_$.save();
    }

    sideMenuClick(e) {
        e.preventDefault();
        let $el = $(e.target);
        let $a = $el.children('a:first-child');
        let url = $a.attr('href');
        if (url && url !== '#') {
            let baseURL = this.props.history.createHref({ path: '/', search: '', hash: '' });
            let newURL = url.substr(baseURL.length - 1);
            this.props.history.push(newURL);
        }
    }

    nullIfEmpty(arr) {
        return !arr || arr.length === 0 ? null : arr;
    }

    collectDashboards(dashboardRoute, func) {
        let dashboardList = appSettings.getRouteDashboard(dashboardRoute);
        if (dashboardList) {
            let list = dashboardList.map((dashboard, index) => func(dashboard, index)).filter(item => item);
            return list;
        }
        return null;
    }

    dashboardLinks(dashboardRoute) {
        dashboardRoute = appSettings.dashboardRoute(dashboardRoute);
        const route = appSettings.getRouteSettingPrefix(dashboardRoute) || '';
        let links = this.collectDashboards(dashboardRoute, (dashboard, index) => {
            const showState = dashboard.showState;

            var disabledFunc = dashboard.isDisabled;
            const disabled = disabledFunc && (!isFunction(disabledFunc) || disabledFunc());
            const title = dashboard.title;
            return <li key={dashboardRoute + '.' + index}><NavLink to="#" className={"menu-checked" + (disabled ? " disabled" : "") + (!disabled && appSettings_$.uiSettings.$_path(showState) ? " activeSidebar item-checked" : "")} onClick={(e) => !disabled && this.toggleDashboard(e, showState)} aria-hidden="true">{this.props.t(title)}</NavLink></li>
        });
        return links;
    }

    dashboardComponents(dashboardRoute) {
        dashboardRoute = appSettings.dashboardRoute(dashboardRoute);
        const routeSettings = appSettings.getRouteSettingPrefix(dashboardRoute) || '';
        let dashboards = this.collectDashboards(dashboardRoute, (dashboard, index) => {
            const component = dashboardComponents[dashboard.dashboardName];
            const key = index;
            // console.debug("React.CreateElement: ", component, { key: dashboardRoute + '.' + key, routeSettings: routeSettings, }, dashboard);
            return React.createElement(component, { key: dashboardRoute + '.' + key, routeSettings: routeSettings, });
        });
        return dashboards;
    }

    loadGroup(group) {
        appTranslations.changeGroup(group);
    }

    Home({ match }) {
        return (
            <div>
                {this.dashboardComponents(appSettings.getRouteSettingPrefix(match))}
                <TranslationsTable noHide/>
            </div>
        );
    }

    Search({ match }) {
        return (
            <div>
                {this.dashboardComponents(appSettings.getRouteSettingPrefix(match))}
                <SearchDashboard noHide/>
            </div>
        );
    }

    Yandex({ match }) {
        return (
            <div>
                {this.dashboardComponents(appSettings.getRouteSettingPrefix(match))}
                <YandexTranslationDashboard noHide/>
            </div>
        );
    }

    // noinspection JSMethodCanBeStatic
    Settings({ match }) {
        return (
            <div>
                {this.dashboardComponents(appSettings.getRouteSettingPrefix(match))}
                <Settings noHide/>
            </div>
        );
    }

    UserAdmin({ match }) {
        return (
            <div>
                {this.dashboardComponents(appSettings.getRouteSettingPrefix(match))}
                <UserManagementDashboard noHide/>
            </div>
        );
    }

    GroupAdmin({ match }) {
        return (
            <div>
                {this.dashboardComponents(appSettings.getRouteSettingPrefix(match))}
                <GroupManagementDashboard noHide/>
            </div>
        );
    }

    static Topic({ match }) {
        return (
            <div>
                <h3>{match.params.topicId}</h3>
            </div>
        );
    }

    Topics({ match }) {
        return (
            <div>
                {this.dashboardComponents(appSettings.getRouteSettingPrefix(match))}
                <h2>Topics</h2>
                <Route path={`${match.url}/:topicId`} component={App.Topic}/>
                <Route exact path={match.url} render={() => <h3>Please select a topic.</h3>}/>
            </div>
        );
    }

    render() {
        const { t, i18n, match } = this.props;
        const { groups, isAdminEnabled, yandexKey, group: currentGroup, showSummaryDashboard, showMismatchDashboard } = this.state;

        let groupItems = [];
        const disabled = true;
        groupItems.push(
            <li key={"::new::"}><NavLink to={disabled ? "#" : "/groups"} className={(disabled ? "disabled " : "") + "menu-checked menu-action-item"} aria-hidden="true">{t('messages.manage-groups')}</NavLink></li>
        );
        groupItems.push(
            <li key={"::new-sep::"}>
                <NavLink to='#' className='menu-checked separator' aria-hidden="true"/>
            </li>
        );
        for (let i = 0; i < groups.length; i++) {
            let group = groups[i];
            groupItems.push(
                <li key={i + group}><NavLink to={"/"} aria-hidden="true" className={"menu-checked" + (group === currentGroup ? " activeSidebar item-checked" : "")} onClick={() => {this.loadGroup(group)}}>{/*i + ": " + */group}</NavLink></li>
            );
            // console.log("Group: '" + group + "'");
        }

        return (
            <div className='translation-manager'>
                <div className='sidebararound'>
                    <div className='sidebar list-group'>
                        <ul>
                            <li onClick={this.sideMenuClick}><NavLink exact to={"/"} activeClassName="activeSidebar" className="fa fa-bars fa-2x"/>Main</li>
                            <li onClick={this.sideMenuClick}><NavLink to={"/search"} className="fa fa-question-circle fa-2x" aria-hidden="true" activeClassName="activeSidebar"/>Search</li>
                            {isAdminEnabled &&
                            <li onClick={this.sideMenuClick}><NavLink exact to={"/users"} activeClassName="activeSidebar" className="fa fa-users fa-2x"/>User Admin</li>
                            }
                            <li onClick={this.sideMenuClick}><NavLink exact to={"/"} className="fa fa-bars fa-2x"/>Groups
                                <ul className='SubMenu'>
                                    {groupItems}
                                </ul>
                            </li>
                            <li onClick={this.sideMenuClick}><NavLink to="#" className={"fa fa-newspaper fa-2x"}/>{t('messages.dashboards')}
                                <Switch>
                                    <Route exact path={"/"}>
                                        <ul className='SubMenu'>{this.dashboardLinks("")}</ul>
                                    </Route>
                                    {isAdminEnabled ? <Route path={"/users"}>
                                        <ul className='SubMenu'>{this.dashboardLinks('users')}</ul>
                                    </Route> : null}
                                    {isAdminEnabled ? <Route path={"/groups"}>
                                        <ul className='SubMenu'>{this.dashboardLinks('groups')}</ul>
                                    </Route> : null}
                                    <Route path={"/topics"}>
                                        <ul className='SubMenu'>{this.dashboardLinks('topics')}</ul>
                                    </Route>
                                    <Route path={"/settings"}>
                                        <ul className='SubMenu'>{this.dashboardLinks('settings')}</ul>
                                    </Route>
                                    <Route path={"/search"}>
                                        <ul className='SubMenu'>{this.dashboardLinks('search')}</ul>
                                    </Route>
                                    {yandexKey ? <Route path={"/yandex"}>
                                        <ul className='SubMenu'>{this.dashboardLinks('yandex')}</ul>
                                    </Route> : null}
                                </Switch>
                            </li>
                        </ul>
                        <div className='bottomSideBar'>
                            <ul>
                                <li onClick={this.sideMenuClick}><NavLink to={"/settings"} activeClassName="activeSidebar" className="fa fa-cog fa-2x"/>Settings</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div className='content'>
                    <h1>{t('messages.translation-manager')}</h1>
                    <p dangerouslySetInnerHTML={{ __html: t('messages.export-warning-text') + "&nbsp;" + t('messages.powered-by-yandex') }}/>
                    <Route exact path={"/"} component={this.Home}/>
                    {isAdminEnabled && <Route path={"/users"} component={this.UserAdmin}/>}
                    {isAdminEnabled && <Route path={"/groups"} component={this.GroupAdmin}/>}
                    <Route path={"/topics"} component={this.Topics}/>
                    <Route path={"/settings"} component={this.Settings}/>
                    <Route path={"/search"} component={this.Search}/>
                    {yandexKey ? <Route path={"/yandex"} component={this.Yandex}/> : null}
                </div>
                
                <ModalDialog {...this.state.modalProps} showModal={this.state.showModal} hideModal={this.state.hideModal}>
                    {this.state.modalBody}
                </ModalDialog>
            </div>
        );
    }
}

export default translate()(withRouter(App));

