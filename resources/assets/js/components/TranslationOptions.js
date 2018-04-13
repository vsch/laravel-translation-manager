import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import appSettings, { appSettings_$ } from '../helpers/AppSettings';
import { Subscriber } from "../helpers/Subscriptions";
import BoxedStateComponent from "./BoxedStateComponent";

class TranslationOptions extends BoxedStateComponent {
    constructor(props) {
        super(props);

        this.state = this.getState();

        this.onCurrentLocaleChange = this.onCurrentLocaleChange.bind(this);
        this.onPrimaryLocaleChange = this.onPrimaryLocaleChange.bind(this);
        this.onTranslatingLocaleChange = this.onTranslatingLocaleChange.bind(this);
        this.onConnectionNameChange = this.onConnectionNameChange.bind(this);

        // this.setStateTransforms({
        //     isCollapsed: _$.transform.toBoolean,
        // });
    }

    // noinspection JSMethodCanBeStatic
    getState() {
        return {
            isLoaded: appSettings_$.isLoaded(),
            isLoading: appSettings_$.isLoading(),
            isAdminEnabled: appSettings_$.isAdminEnabled(),
            translatingLocale: appSettings_$.translatingLocale(),
            currentLocale: appSettings_$.currentLocale(),
            primaryLocale: appSettings_$.primaryLocale(),
            connectionList: appSettings_$.connectionList(),
            connectionName: appSettings_$.connectionName(),
            userLocales: appSettings_$.userLocales(),
            locales: appSettings_$.locales(),
        };
    }

    componentDidMount() {
        this.subscriber = new Subscriber(appSettings, () => {
            this.state_$.cancel();
            this.setState(this.getState());
        });
    }

    componentWillUnmount() {
        this.subscriber.unsubscribe();
    }

    onCurrentLocaleChange(e) {
        e.preventDefault();
        appSettings_$.currentLocale = e.target.value;
        appSettings_$.save();
    }

    // noinspection JSMethodCanBeStatic
    onPrimaryLocaleChange(e) {
        e.preventDefault();
        appSettings_$.primaryLocale = e.target.value;
        appSettings_$.save();
    }

    // noinspection JSMethodCanBeStatic
    onTranslatingLocaleChange(e) {
        e.preventDefault();
        appSettings_$.translatingLocale = e.target.value;
        appSettings_$.save();
    }

    // noinspection JSMethodCanBeStatic
    onConnectionNameChange(e) {
        e.preventDefault();
        appSettings_$.connectionName = e.target.value;
        appSettings_$.save();
    }

    render() {
        const { t, i18n } = this.props;
        const { isLoaded, isAdminEnabled, userLocales, translatingLocale, currentLocale, primaryLocale, connectionList, connectionName, locales } = this.state;
        let connections = [];
        for (let connection in connectionList) {
            if (connectionList.hasOwnProperty(connection)) {
                connections.push(connection);
            }
        }

        if (!isLoaded) {
            return <div>Loading...</div>;
        } else {
            return (
                <div className="row">
                    <div className=" col-sm-3">
                        {isAdminEnabled && connections.length > 1 && (
                            <div className="input-group-sm">
                                <label htmlFor="db-connection">{t('messages.db-connection')}:</label>
                                <br/>
                                <select name="connectionName" id="db-connection" className="form-control"
                                    value={connectionName} onChange={this.onConnectionNameChange}>
                                    {connections.map((connection) => (
                                        <option key={connection}
                                            value={connection}>{connectionList[connection]}</option>
                                    ))}
                                </select>
                            </div>
                        )}
                        {(!isAdminEnabled || connections.length === 0) && (
                            <span>&nbsp;</span>
                        )}
                    </div>

                    <div className="col-sm-3">
                        <div className="input-group-sm">
                            <label>{t('messages.interface-locale')}:</label>
                            <br/>
                            <select name="locale" id="interface-locale" className="form-control" value={currentLocale}
                                onChange={this.onCurrentLocaleChange}>
                                {locales.map(locale => (
                                    <option key={locale} value={locale}>{locale}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="col-sm-3">
                        <div className="input-group-sm">
                            <label>{t('messages.translating-locale')}:</label>
                            <br/>
                            <select name="translatingLocale" id="translating-locale" className="form-control"
                                value={translatingLocale} onChange={this.onTranslatingLocaleChange}>
                                {locales.map(locale => (userLocales.indexOf(locale) > -1 &&
                                    <option key={locale} value={locale}>{locale}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="col-sm-3">
                        <div className="input-group-sm">
                            <label>{t('messages.primary-locale')}:</label>
                            <br/>
                            <select name="primaryLocale" id="primary-locale" className="form-control"
                                value={primaryLocale} onChange={this.onPrimaryLocaleChange}>
                                {locales.map(locale => (
                                    <option key={locale} value={locale}>{locale}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    {/*
                        <div className="col-sm-3">
                            <?php if(str_contains($userLocales, ',' . $currentLocale . ',')): ?>
                                <div className="input-group input-group-sm" style="float:right; display:inline">
                                    <?= ifEditTrans($package . '::messages.in-place-edit') ?>
                                    <label htmlFor="edit-in-place">&nbsp;</label>
                                    <br>
                                        <a className="btn btn-sm btn-primary" role="button" id="edit-in-place"
                                           href="<?= action($controller . '@getToggleInPlaceEdit') ?>">
                                            <?= noEditTrans($package . '::messages.in-place-edit') ?>
                                        </a>
                                </div>
                                <?php endif ?>
                            </div>
                        </div>
*/}
                </div>
            );
        }
    }
}

export default compose(
    translate(),
    connect(),
)(TranslationOptions);
