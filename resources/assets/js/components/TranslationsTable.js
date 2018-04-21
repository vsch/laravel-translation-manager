import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import TransXEditable from "./TransXEditable";
import TransTableFilter from "./TransTableFilter";
import { absoluteUrlPrefix, apiURL, POST_DELETE_TRANSLATION, POST_UNDELETE_TRANSLATION, URL_DELETE_GROUP, URL_FIND_REFERENCES, URL_IMPORT_GROUP, URL_PUBLISH_GROUP, URL_SHOW_KEY_REFERENCES, URL_ZIP_TRANSLATIONS } from "../helpers/ApiRoutes";
import axios from "axios";
import appSettings, { appSettings_$ } from "../helpers/AppSettings";
import appTranslations, { appTranslations_$ } from "../helpers/GlobalTranslations";
import Dashboard from "./Dashboard";
import PropTypes from "prop-types";
import DashboardComponent from "./DashboardComponent";
import UrlButton from "./UrlButton";
import appModal from '../helpers/AppModal';

const DELETE_GROUP = 'confirmDeleteGroup';
const IMPORT_GROUP = 'confirmImportGroup';
const IMPORT_GROUP_REPLACE = 'confirmImportGroupReplace';
const IMPORT_GROUP_DELETE = 'confirmImportGroupDelete';
const PUBLISH_GROUP = 'confirmPublishGroup';
const IMPORT_ALL_GROUPS = 'confirmImportAllGroups';
const IMPORT_ALL_GROUPS_REPLACE = 'confirmImportAllGroupsReplace';
const IMPORT_ALL_GROUPS_DELETE = 'confirmImportAllGroupsDelete';
const ADD_REFERENCES = 'confirmAddReferences';
const PUBLISH_ALL_GROUPS = 'confirmPublishAllGroups';

function getConnectionNameParam() {
    return { connectionName: appSettings.getState().connectionName };
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
        const group = appSettings_$.uiSettings.group();
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
            group: group,

            translationsGroup: appTranslations_$.group(),
            displayLocales: appTranslations_$.displayLocales(),
            yandexKey: appTranslations_$.yandexKey(),
            translations: appTranslations_$.translations(),
            importReplace: appSettings_$.uiSettings.importReplace() || '0',
        }, isAdminEnabled ? 'collapsePublishButtons' : null);
    }

    reload() {
        appTranslations.update({ isLoaded: false });
        appTranslations.load();
    }

    deleteTransFlag(e, group, key, url, flag) {
        e.preventDefault();
        e.stopPropagation();
        axios.post(url)
            .then((result) => {
                appTranslations.changeTranslations(group, (transKey) => {
                    return transKey === key;
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
        const { error, isStaleData, isLoaded, isLoading, collapsePublishButtons, translationsGroup, showPublishButtons, importReplace, isAdminEnabled, groups, group, translations, translatingLocale, primaryLocale, userLocales, displayLocales, showUsage, yandexKey } = this.state;

        if (this.noShow()) return null;

        let body;
        let headings;
        if (error) {
            headings = <th width="100%">&nbsp;</th>;
            body = <div>Error: {error.message}</div>;
        } else if (!isLoaded || !group || group !== translationsGroup) {
            headings = <th width="100%">&nbsp;</th>;
            body = group ? (
                <tr>
                    <td width='100%' className='text-center'>
                        <div className='show-loading'/>
                    </td>
                </tr>
            ) : (
                <tr>
                    <td width='100%' className='text-center'>
                        <div>{t('messages.choose-group-text')}</div>
                    </td>
                </tr>
            );
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
                    </th>,
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
                </th>,
            );

            let iMax = $locales.length;
            for (let i = 0; i < iMax; i++) {
                const $locale = $locales[i];
                const $jsonAdjustedLocale = $locale === 'json' ? t('messages.json-key') : $locale;

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
                            </th>,
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
                            </th>,
                        );
                    } else {
                        headings.push(
                            <th key={headings.length} width={$mainWidth + "%"}>{$jsonAdjustedLocale}</th>,
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
                        </th>,
                    );
                } else {
                    headings.push(
                        <th key={headings.length}>{$jsonAdjustedLocale}</th>,
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
                        </td>,
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

                for (let i in $locales) {
                    if (!$locales.hasOwnProperty(i)) continue;
                    let $locale = $locales[i];

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
                            <UrlButton asLink
                                dataUrl={URL_SHOW_KEY_REFERENCES(group, $key, connectionName)}
                                className="float-right" 
                                title={t("messages.show-source-refs")}
                                onSuccess={(result)=>{
                                    const keyName = result.data.key_name;
                                    const modalTitle = <span>{t('messages.source-refs-header')}<code> '{keyName}'</code></span>;
                                    const sources = result.data.result.join('\n');
                                    const modal = {
                                        modalProps: {
                                            modalTitle: modalTitle,
                                            modalType: 'modal',
                                            modalDialogType:  'modal-dialog modal-lg modal-dialog-centered',
                                            backdrop: true,
                                        },
                                        modalBody: <pre>{sources}</pre>,
                                    };
                                    appModal.showModal(modal);
                                }}
                            ><span className={"fa " + ($is_auto_added ? 'fa-question-circle' : 'fa-info-circle')}/></UrlButton>
                        )}
                    </td>,
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
                            {$isLocaleEnabled ? TransXEditable.transXEditLink($group, $key, $locale, !$t ? null : $t, true) : $t ? $t.value : ''}
                        </td>,
                    );
                }

                body.push(
                    <tr key={body.length} id={$key.replace('.', '-')} className={($is_deleted ? ' deleted-translation' : '') +
                    ($has_empty ? ' has-empty-translation' : '') + ($has_nonempty ? ' has-nonempty-translation' : '') + ($has_changes ? ' has-changed-translation' : '') + ($has_used ? ' has-used-translation' : '')}>
                        {columns}
                    </tr>,
                );
            }
        }

        const connectionName = appSettings.getState().connectionName;

        const zipGroupURL = (group) => URL_ZIP_TRANSLATIONS(group || '*', connectionName);
        const publishGroupURL = (group) => URL_PUBLISH_GROUP(group || '*', connectionName);
        const deleteGroupURL = (group) => URL_DELETE_GROUP(group, connectionName);
        const importGroupURL = (group) => URL_IMPORT_GROUP(group || '*', importReplace, connectionName);
        const findReferencesURL = () => URL_FIND_REFERENCES(connectionName);

        const publish = [];
        const buttons = [];
        const replaceTextColor = importReplace == 0 ? 'text-safe' : importReplace == 1 ? 'text-attention' : 'text-caution';
        const importButtonType = importReplace == 0 ? 'border-light btn-success' : importReplace == 1 ? 'border-light btn-info' : 'border-light btn-warning';
        const importAllConfirm = importReplace == 0 ? IMPORT_ALL_GROUPS : importReplace == 1 ? IMPORT_ALL_GROUPS_REPLACE : IMPORT_ALL_GROUPS_DELETE;
        const importGroupConfirm = importReplace == 0 ? IMPORT_GROUP : importReplace == 1 ? IMPORT_GROUP_REPLACE : IMPORT_GROUP_DELETE;
        const groupReplaceOption = importReplace == 0 ? '' : importReplace == 1 ? '-replace' : '-delete';

        if (isAdminEnabled) {
            if (showPublishButtons) {
                if (!collapsePublishButtons) {
                    publish.push(
                        <div key={publish.length} className='row'>
                            <div className="col col-sm-6">
                                <div className="input-group input-group-sm mb-2" onClick={(e) => e.stopPropagation()}>
                                    <select name="replace" className={`form-control ${replaceTextColor}` + (!isStaleData || isLoading ? " bg-primary" : " bg-secondary")}
                                        value={importReplace || '0'} onChange={this.handleImportReplace}>
                                        <option value="0">{t('messages.import-add')}</option>
                                        <option value="1">{t('messages.import-replace')}</option>
                                        <option value="2">{t('messages.import-fresh')}</option>
                                    </select>
                                </div>
                            </div>
                            <div className="col col-sm-6">
                                <div className="row">
                                    <div className='col col-sm-5'>
                                        <div className="input-group input-group-sm" onClick={(e) => e.stopPropagation()}>
                                            <UrlButton
                                                className="btn border-light btn-sm btn-warning ml-3"
                                                dataUrl={publishGroupURL()}
                                                invalidateGroup='*'
                                                confirmationKey={PUBLISH_ALL_GROUPS}
                                                disableWith={t('messages.publishing')}
                                            >{t('messages.publish-all-groups')}</UrlButton>
                                            <UrlButton
                                                className="btn border-light btn-sm btn-primary ml-1"
                                                plainLink
                                                dataUrl={zipGroupURL()}
                                            >{t('messages.zip-all')}</UrlButton>
                                        </div>
                                    </div>
                                    <div className='col col-sm-3'>
                                        <div className="row justify-content-md-center">
                                            <div className="col-md-auto">
                                                <UrlButton
                                                    className={"mx-auto btn btn-sm " + importButtonType}
                                                    dataUrl={importGroupURL()}
                                                    reloadGroups
                                                    confirmationKey={importAllConfirm}
                                                    disableWith={t('messages.loading')}
                                                    onSuccess={(result)=>{
                                                        appModal.messageBox(
                                                            <div dangerouslySetInnerHTML={{__html: t('messages.api-import-groups-done-title'), }}/>,
                                                            <div dangerouslySetInnerHTML={{__html: t('messages.api-import-groups-done-message').replace(':count', result.data.counter)}}/>
                                                        );
                                                    }}
                                                >{t(`messages.import-all-groups${groupReplaceOption}`)}</UrlButton>
                                            </div>
                                        </div>
                                    </div>
                                    <div className='col col-sm-4'>
                                        <UrlButton
                                            className="float-right btn border-light btn-sm btn-danger ml-1"
                                            dataUrl={findReferencesURL()}
                                            reloadGroups
                                            confirmationKey={ADD_REFERENCES}
                                            disableWith={t('messages.searching')}
                                            onSuccess={(result)=>{
                                                appModal.messageBox(
                                                    <div dangerouslySetInnerHTML={{__html: t('messages.api-add-references-done-title'), }}/>,
                                                    <div dangerouslySetInnerHTML={{__html: t('messages.api-add-references-done-message').replace(':count', result.data.counter)}}/>
                                                );
                                            }}
                                        >{t('messages.find-in-files')}</UrlButton>
                                    </div>
                                </div>
                            </div>
                        </div>,
                    );
                }
            }
        }

        buttons.push(
            <div key={'select group' + publish.length} className='row'>
                <div className="col col-sm-6">
                    <div className="input-group input-group-sm" onClick={(e) => e.stopPropagation()}>
                        <select className={"form-control text-white" + (!isStaleData || isLoading ? " bg-primary" : " bg-secondary")}
                            value={group || ''} onChange={this.loadGroup}>
                            <option key="" value="">{t('messages.choose-group')}</option>
                            {groups.map(item => (
                                <option key={item} value={item}>{item}</option>
                            ))}
                        </select>
                    </div>
                </div>
                <div className="col col-sm-6">
                    {!!group && isAdminEnabled &&
                    <div className="row">
                        <div className='col col-sm-5'>
                            <UrlButton
                                className="btn border-light btn-sm btn-info ml-3"
                                dataUrl={publishGroupURL(group)}
                                invalidateGroup={group}
                                confirmationKey={PUBLISH_GROUP}
                                disableWith={t('messages.publishing')}
                            >{t('messages.publish-group')}</UrlButton>
                            <UrlButton
                                className="btn border-light btn-sm btn-primary ml-1"
                                plainLink
                                dataUrl={zipGroupURL(group)}
                            >{t('messages.zip')}</UrlButton>
                        </div>
                        <div className='col col-sm-3'>
                            <div className="row justify-content-md-center">
                                <div className="col-md-auto">
                                    <UrlButton
                                        className={"mx-auto btn btn-sm " + importButtonType}
                                        dataUrl={importGroupURL(group)}
                                        invalidateGroup={group}
                                        confirmationKey={importGroupConfirm}
                                        disableWith={t('messages.loading')}
                                        onSuccess={(result)=>{
                                            appModal.messageBox(
                                                <div dangerouslySetInnerHTML={{__html: t('messages.api-import-group-done-title').replace(':group', group), }}/>,
                                                <div dangerouslySetInnerHTML={{__html: t('messages.api-import-group-done-message').replace(':group', group).replace(':count', result.data.counter)}}/>
                                            );
                                        }}
                                    >{t(`messages.import-group${groupReplaceOption}`)}</UrlButton>
                                </div>
                            </div>
                        </div>
                        <div className='col col-sm-4'>
                            <div className='float-right'>
                                <UrlButton
                                    className="btn border-light btn-sm btn-danger ml-1"
                                    dataUrl={deleteGroupURL(group)}
                                    reloadGroups
                                    confirmationKey={DELETE_GROUP}
                                    disableWith={t('messages.deleting')}
                                >{t('messages.delete')}</UrlButton>
                            </div>
                        </div>
                    </div>
                    }
                </div>
            </div>,
        );
        return (
            <Dashboard headerChildren=
                {
                    <div>
                        {publish}
                        {buttons}
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






