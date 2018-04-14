import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import TransXEditable from "./TransXEditable";
import TransTableFilter from "./TransTableFilter";
import { apiURL, POST_DELETE_TRANSLATION, POST_IMPORT_GROUP_URL, POST_PUBLISH_GROUP_URL, POST_UNDELETE_TRANSLATION, URL_DELETE_GROUP, URL_FIND_REFERENCES, URL_IMPORT_GROUP, URL_PUBLISH_GROUP, URL_SHOW_KEY_REFERENCES, URL_ZIP_TRANSLATIONS } from "../helpers/ApiRoutes";
import axios from "axios";
import appSettings, { appSettings_$ } from "../helpers/AppSettings";
import appTranslations, { appTranslations_$ } from "../helpers/GlobalTranslations";
import Dashboard from "./Dashboard";
import ModalDialog from "./ModalDialog";
import PropTypes from "prop-types";
import DashboardComponent from "./DashboardComponent";

const DELETE_GROUP = 'confirmDeleteGroup';
const IMPORT_GROUP = 'confirmImportGroup';
const PUBLISH_GROUP = 'confirmPublishGroup';
const IMPORT_ALL_GROUPS = 'confirmImportAllGroups';
const ADD_REFERENCES = 'confirmAddReferences';
const PUBLISH_ALL_GROUPS = 'confirmPublishAllGroups';

function getConnectionNameParam() {
    return { connectionName: appSettings.getState().connectionName, };
}

class TranslationsTable extends DashboardComponent {
    constructor(props) {
        super(props, 'translations', true);

        // to apply defaults, with transformations and save them before component is mounted. 
        // fill in the rest of the state information
        this.state = this.getState();

        this.loadGroup = this.loadGroup.bind(this);
        this.handleImportReplace = this.handleImportReplace.bind(this);
        this.isDoneLoading = this.isDoneLoading.bind(this);
        this.usesSettings = [appSettings, appTranslations];
    }

    getState() {
        const isAdminEnabled = appSettings_$.isAdminEnabled();
        const group = appTranslations_$.group();
        return this.adjustState({
            error: null,
            isLoaded: appTranslations_$.isLoaded() && appSettings_$.isLoaded(),
            isLoading: appTranslations_$.isLoading()/* || appSettings_$.isLoading()*/,
            isStaleData: appTranslations_$.isStaleData() || appSettings_$.isStaleData(),
            isAdminEnabled: isAdminEnabled,
            primaryLocale: appSettings_$.primaryLocale(),
            showUsage: appSettings_$.showUsage(),
            translatingLocale: appSettings_$.translatingLocale(),
            showPublishButtons: appSettings_$.uiSettings.showPublishButtons(),
            collapsePublishButtons: appSettings_$.uiSettings.collapsePublishButtons(),
            userLocales: appSettings_$.userLocales(),
            groups: appSettings_$.groups(),

            displayLocales: appTranslations_$.displayLocales(),
            group: group,
            yandexKey: appTranslations_$.yandexKey(),
            translations: appTranslations_$.translations(),
            importReplace: appSettings_$.uiSettings.importReplace() || 0,
            getConnectionNameParam: getConnectionNameParam,
            replaceFields: () => URL_IMPORT_GROUP(group, this.state.importReplace, appSettings.getState().connectionName).data,
        }, isAdminEnabled ? 'collapsePublishButtons' : null);
    }

    reload() {
        appTranslations.update({ isLoaded: false, });
        appTranslations.load();
    }

    deleteTransFlag(e, group, key, url, flag) {
        e.preventDefault();
        e.stopPropagation();
        axios.post(url)
            .then((result) => {
                appTranslations.changeTranslations(group, (transKey) => {
                    return transKey === key
                }, (locale, trans) => {
                    trans.is_deleted = flag;
                });
            });
    }

    loadGroup(e) {
        appTranslations.changeGroup(e.target.value);
    }

    handleImportReplace(e) {
        appSettings_$.uiSettings.importReplace = e.target.value;
        appSettings_$.save();
    }

    isDoneLoading() {
        return this.state.isLoaded && !this.state.isLoading;
    }

    render() {
        const { t } = this.props;
        const { error, isStaleData, isLoaded, isLoading, collapsePublishButtons, showPublishButtons, importReplace, isAdminEnabled, groups, group, translations, translatingLocale, primaryLocale, userLocales, displayLocales, showUsage, yandexKey, } = this.state;

        if (this.noShow()) return null;

        let body;
        let headings;
        if (error) {
            headings = <th width="100%">&nbsp;</th>;
            body = <div>Error: {error.message}</div>;
        } else if (!isLoaded) {
            headings = <th width="100%">&nbsp;</th>;
            body = <tr>
                <td width='100%' className='text-center'>
                    <div className='show-loading'/>
                </td>
            </tr>;
        } else if (!group) {
            if (!isAdminEnabled) {
                return;
            }
        } else {
            // TODO: set urls for actions
            if (group !== 'JSON') {
                const index = displayLocales.indexOf('json');
                if (index !== -1) {
                    displayLocales.splice(index, 1);
                }
            }

            const allLocalesList = displayLocales;
            const userLocaleList = [];
            let i = 1;
            for (let userLocale in allLocalesList) {
                if (!allLocalesList.hasOwnProperty(userLocale)) continue;
                const localeName = allLocalesList[userLocale];
                userLocaleList.push({ value: localeName, text: localeName });
            }

            let $displayLocales = displayLocales;
            headings = [];

            if (isAdminEnabled) {
                headings.push(
                    <th key={headings.length} width="1%">
                        <a href="#" className="auto-delete-key">
                            <span className="fa fa-trash-alt"/>
                        </a>&nbsp;
                        <a href="#" className="auto-undelete-key">
                            <span className="fa fa-thumbs-up"/>
                        </a>
                    </th>
                );
            }

            let $setWidth = $displayLocales.length;
            let $mainWidth;
            if ($setWidth > 3) {
                $mainWidth = 25;
            } else if ($setWidth === 3) {
                $mainWidth = 28;
            } else {
                $mainWidth = 42;
            }

            const $translations = translations;
            const $locales = displayLocales;
            const $userLocales = ',' + userLocales.join(',') + ',';
            const $group = group;

            let $col = 0;
            let $translationRows = $translations.length;

            headings.push(
                <th key={headings.length} width="15%">{t("messages.key")}
                    <span className="key-filter" id="key-filter">{$translationRows}</span>
                </th>
            );

            let iMax = $locales.length;
            for (let i = 0; i < iMax; i++) {
                const $locale = $locales[i];

                let $isLocaleEnabled = $userLocales.indexOf(',' + $locale + ',') > -1;
                if (!$displayLocales.indexOf($locale) < 0) continue;
                if ($col < 3) {
                    if ($col === 0) {
                        headings.push(
                            <th key={headings.length} width={$mainWidth + "%"}>{$locale}&nbsp;
                                <a className="btn btn-sm btn-light btn-outline-secondary" id="auto-fill" role="button"
                                    disabled={!$isLocaleEnabled}
                                    data-disable-with={t('messages.auto-fill-disabled')}
                                    href="#">{t('messages.auto-fill')}</a>
                            </th>
                        );
                    } else if (yandexKey && $isLocaleEnabled && $locale !== 'json') {
                        headings.push(
                            <th key={headings.length} width={$mainWidth + "%"}>{$locale}&nbsp;
                                <a key={1} className="btn btn-sm btn-light btn-outline-secondary auto-translate"
                                    role="button" data-trans={$col} data-locale={$locale} disabled={!$isLocaleEnabled}
                                    data-disable-with={t("messages.auto-translate-disabled")}
                                    href="#">{t("messages.auto-translate")}</a>
                                <a key={2} className="btn btn-sm btn-light btn-outline-secondary auto-prop-case ml-1"
                                    role="button" data-trans={$col} data-locale={$locale} disabled={!$isLocaleEnabled}
                                    data-disable-with={t("messages.auto-prop-case-disabled")}
                                    href="#">Ab Ab <i className="fa fa-share"/> Ab ab
                                </a>
                            </th>
                        );
                    } else {
                        headings.push(
                            <th key={headings.length} width={$mainWidth + "%"}>{$locale}</th>
                        );
                    }
                } else if (yandexKey && $isLocaleEnabled && $locale !== 'json') {
                    headings.push(
                        <th key={headings.length}>{$locale}
                            <a key={1} className="btn btn-sm btn-light btn-outline-secondary auto-translate" role="button" data-trans={$col} data-locale={$locale}
                                data-disable-with={t("messages.auto-translate-disabled")}
                                href="#">{t("messages.auto-translate")}</a>
                            <a key={2} className="btn btn-sm btn-light btn-outline-secondary auto-prop-case ml-1" role="button" data-trans={$col} data-locale={$locale}
                                data-disable-with={t("messages.auto-prop-case-disabled")}
                                href="#">Ab Ab <i className="fa fa-share"/> Ab ab
                            </a>
                        </th>
                    );
                } else {
                    headings.push(
                        <th key={headings.length}>{$locale}</th>
                    );
                }
                $col++;
            }

            body = [];
            for (let $key in $translations) {
                if (!$translations.hasOwnProperty($key)) continue;

                const $translation = $translations[$key];

                // add value for the locales
                // options.value = valueList;
                // options.source = userLocaleList;
                const columns = [];

                let $is_deleted = false;
                let $has_empty = false;
                let $has_nonempty = false;
                let $has_changes = false;
                let $has_changed = {};
                let $has_changes_cached = {};
                let $has_used = false;

                for (let i = 0; i < iMax; i++) {
                    const $locale = $locales[i];
                    if (!displayLocales.indexOf($locale) < 0) continue;

                    $has_changed[$locale] = false;
                    $has_changes_cached[$locale] = false;

                    if ($translation.hasOwnProperty($locale)) {
                        let $trans = $translation[$locale];
                        if ($trans.is_deleted) $is_deleted = true;
                        if ($trans.was_used) $has_used = true;
                        if ($trans.value !== '') {
                            $has_nonempty = true;
                            if ($trans.status !== 0 || ($trans.value || '') !== ($trans.saved_value || '')) {
                                $has_changes = true;
                            }
                        } else $has_empty = true;

                        if ($trans.status !== 0) {
                            if ($trans.status === 1 || ($trans.value || '') !== ($trans.saved_value || '')) $has_changed[$locale] = true;
                            else $has_changes_cached[$locale] = $trans.value !== '' && $trans.status === 2;
                        }
                    }
                }

                if (isAdminEnabled) {
                    const unDeleteKeyUrl = apiURL(POST_UNDELETE_TRANSLATION, encodeURI($group), encodeURI($key));
                    const deleteKeyUrl = apiURL(POST_DELETE_TRANSLATION, encodeURI($group), encodeURI($key));
                    columns.push(
                        <td key={columns.length + $group + "." + $key + ":x"}>
                            <a key={"1"} href="#"
                                className={"undelete-key" + ($is_deleted ? "" : " hidden")}
                                onClick={(e) => this.deleteTransFlag(e, $group, $key, unDeleteKeyUrl + apiURL(), 0)}>
                                <span className="fa fa-thumbs-up"/>
                            </a>
                            <a key={"2"} href="#"
                                className={"delete-key" + (!$is_deleted ? "" : " hidden")}
                                onClick={(e) => this.deleteTransFlag(e, $group, $key, deleteKeyUrl + apiURL(), 1)}>
                                <span className="fa fa-trash-alt"/>
                            </a>
                        </td>
                    );
                }

                let $was_used = true;
                let $has_source = false;
                let $is_auto_added = false;
                let $t;

                if (showUsage) {
                    $was_used = false;
                    for (let i = 0; i < iMax; i++) {
                        const $locale = $locales[i];

                        $t = $translation.hasOwnProperty($locale) ? $translation[$locale] : null;
                        if ($t != null && $t.was_used) {
                            $was_used = true;
                            break;
                        }
                    }
                }

                for (let $locale in $locales) {
                    if (!$locales.hasOwnProperty($locale)) continue;

                    $t = $translation.hasOwnProperty($locale) ? $translation[$locale] : null;
                    if ($t != null && $t.has_source) {
                        $has_source = true;
                        $is_auto_added = $t.is_auto_added;
                        break;
                    }
                }

                columns.push(
                    <td key={columns.length + $group + "." + $key + ":"} className={"key" + ($was_used ? ' used-key' : ' unused-key')}>{$key}
                        {$has_source && (
                            <a style="float: right;" href={apiURL(absoluteUrlPrefix(), URL_SHOW_KEY_REFERENCES(group, $key).url)}
                                className="show-source-refs" data-method="POST" data-remote="true" title={t("messages.show-source-refs")}>
                                <span className={"fa" + ($is_auto_added ? 'fa-question-sign' : 'fa-info-sign')}/>
                            </a>
                        )}
                    </td>
                );

                for (let i = 0; i < iMax; i++) {
                    const $locale = $locales[i];

                    let $isLocaleEnabled = $userLocales.indexOf(',' + $locale + ',') >= 0;
                    if (!displayLocales.indexOf($locale) < 0) continue;
                    $t = $translation.hasOwnProperty($locale) ? $translation[$locale] : null;

                    columns.push(
                        <td key={columns.length + $group + "." + $key + ":" + $locale} className={
                            ($locale !== primaryLocale ? 'auto-translatable-' + $locale : 'auto-fillable') +
                            ($has_changed[$locale] ? ' has-unpublished-translation' : '') +
                            ($has_changes_cached[$locale] ? ' has-cached-translation' : '')}>
                            {$isLocaleEnabled ? TransXEditable.transXEditLink($group, $key, $locale, !$t ? null : $t, true) : $t.value}
                        </td>
                    );
                }

                body.push(
                    <tr key={body.length} id={$key.replace('.', '-')} className={($is_deleted ? ' deleted-translation' : '') +
                    ($has_empty ? ' has-empty-translation' : '') + ($has_nonempty ? ' has-nonempty-translation' : '') + ($has_changes ? ' has-changed-translation' : '') + ($has_used ? ' has-used-translation' : '')}>
                        {columns}
                    </tr>
                );
            }
        }

        const connectionName = appSettings.getState().connectionName;

        const zipGroupURL = (group) => URL_ZIP_TRANSLATIONS(group || '*', connectionName).url;
        const publishGroupURL = (group) => URL_PUBLISH_GROUP(group || '*', connectionName).url;
        const deleteGroupURL = (group) => URL_DELETE_GROUP(group, connectionName).url;
        const importGroupURL = (group) => URL_IMPORT_GROUP(group || '*', 0, connectionName).url;
        const findReferencesURL = () => URL_FIND_REFERENCES(connectionName).url;

        const publish = [];
        const buttons = [];

        if (isAdminEnabled) {
            if (showPublishButtons) {
                if (!collapsePublishButtons) {
                    publish.push(
                        <div key={publish.length} className='row'>
                            <div className="col col-sm-6">
                                <div className="input-group input-group-sm mb-2" onClick={(e) => e.stopPropagation()}>
                                    <select name="replace" className={"form-control text-white" + (!isStaleData || isLoading ? " bg-primary" : " bg-secondary")}
                                        value={importReplace || '0'} onChange={this.handleImportReplace}>
                                        <option value="0">{t('messages.import-add')}</option>
                                        <option value="1">{t('messages.import-replace')}</option>
                                        <option value="2">{t('messages.import-fresh')}</option>
                                    </select>
                                </div>
                            </div>
                            <div className="col col-sm-6">
                                <div className="mx-auto input-group input-group-sm" onClick={(e) => e.stopPropagation()}>
                                    <div className='float-left'>
                                        <button type="button"
                                            onClick={this.handleButtonClick}
                                            className="btn border-light btn-sm btn-warning ml-3"
                                            data-post-url={publishGroupURL()}
                                            data-extra-fields={'getConnectionNameParam'}
                                            data-invalidate-group='*'
                                            data-confirmation-key={PUBLISH_ALL_GROUPS}
                                            data-disable-with={t('messages.publishing')}
                                        >{t('messages.publish-all-groups')}</button>
                                        <a role="button"
                                            className="btn border-light btn-sm btn-primary ml-1"
                                            href={zipGroupURL()}
                                        >{t('messages.zip-all')}</a>
                                    </div>
                                    <div className='mx-auto'>
                                        <button type="button"
                                            onClick={this.handleButtonClick}
                                            className="btn border-light btn-sm btn-success ml-1"
                                            data-post-url={importGroupURL()}
                                            data-extra-fields={'replaceFields'}
                                            data-invalidate-group='*'
                                            data-confirmation-key={IMPORT_ALL_GROUPS}
                                            data-disable-with={t('messages.loading')}
                                        >{t('messages.import-groups')}</button>
                                    </div>
                                    <div className='float-right'>
                                        <button type="button"
                                            onClick={this.handleButtonClick}
                                            className="btn border-light btn-sm btn-danger ml-1"
                                            data-post-url={findReferencesURL()}
                                            data-invalidate-group='*'
                                            data-confirmation-key={ADD_REFERENCES}
                                            data-extra-fields={'getConnectionNameParam'}
                                            data-disable-with={t('messages.searching')}
                                        >{t('messages.find-in-files')}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                }

                if (group) {
                    buttons.push(
                        <div key={buttons.length} className="mx-auto input-group input-group-sm" onClick={(e) => e.stopPropagation()}>
                            <div className='float-left'>
                                <button type="button"
                                    onClick={this.handleButtonClick}
                                    className="btn border-light btn-sm btn-info ml-3"
                                    data-post-url={publishGroupURL(group)}
                                    data-invalidate-group={group}
                                    data-confirmation-key={PUBLISH_GROUP}
                                    data-extra-fields={'getConnectionNameParam'}
                                    data-disable-with={t('messages.publishing')}
                                >{t('messages.publish-group')}</button>
                                <a role="button"
                                    className="btn border-light btn-sm btn-primary ml-1"
                                    href={zipGroupURL(group)}
                                >{t('messages.zip')}</a>
                            </div>
                            <div className='mx-auto'>
                                <button type="button"
                                    onClick={this.handleButtonClick}
                                    className="btn border-light btn-sm btn-success ml-1"
                                    data-post-url={importGroupURL(group)}
                                    data-extra-fields={'replaceFields'}
                                    data-invalidate-group={group}
                                    data-confirmation-key={IMPORT_GROUP}
                                    data-disable-with={t('messages.loading')}
                                >{t('messages.import-group')}</button>
                            </div>
                            <div className='float-right'>
                                <button type="button"
                                    onClick={this.handleButtonClick}
                                    className="btn border-light btn-sm btn-danger ml-1"
                                    data-post-url={deleteGroupURL(group)}
                                    data-extra-fields={'getConnectionNameParam'}
                                    data-reload-groups
                                    data-confirmation-key={DELETE_GROUP}
                                    data-disable-with={t('messages.deleting')}
                                >{t('messages.delete')}</button>
                            </div>
                        </div>
                    );
                }
            }
        }

        return (
            <Dashboard headerChildren=
                {
                    <div>
                        {publish}
                        <div className='row'>
                            <div className="col col-sm-6">
                                <div className="input-group input-group-sm" onClick={(e) => e.stopPropagation()}>
                                    <select name="primaryLocale" id="primary-locale" className={"form-control text-white" + (!isStaleData || isLoading ? " bg-primary" : " bg-secondary")}
                                        value={group || ''} onChange={this.loadGroup}>
                                        {groups.map(item => (
                                            <option key={item} value={item}>{item}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="col col-sm-6">
                                <div className="input-group input-group-sm" onClick={(e) => e.stopPropagation()}>
                                    {buttons}
                                </div>
                            </div>
                        </div>
                    </div>
                }
                extrasContent={isAdminEnabled ? <i className="fas fa fa-minus-square" title={t('messages.hide-actions')}/> : null}
                extrasAltContent={isAdminEnabled ? <i className="fas fa fa-cogs" title={t('messages.show-actions')}/> : null}
                {...this.getDashboardProps()}
            >
                <TransTableFilter/>
                <div className="row">
                    <div className="col-sm-12 ">
                        <table id='translations' className="table table-sm table-hover table-striped table-bordered table-translations">
                            <thead className='thead-light'>
                            <tr key={"heading"}>
                                {headings}
                            </tr>
                            </thead>
                            <tbody>
                            {body}
                            </tbody>
                        </table>
                    </div>
                </div>

                <ModalDialog {...this.state.modalProps} showModal={this.state.showModal}>
                    {this.state.modalBody}
                </ModalDialog>
            </Dashboard>
        );
    }
}

TranslationsTable.propTypes = {
    routeSettings: PropTypes.string, // settings prefix for show/collapse
    showDashboard: PropTypes.bool,
    noHide: PropTypes.bool,
};

export default compose(translate(), connect())(TranslationsTable);






