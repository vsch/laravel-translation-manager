import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import appSettings, { appSettings_$ } from '../helpers/AppSettings';
import appTranslations from "../helpers/GlobalTranslations";
import { Subscriber } from "../helpers/Subscriptions";
import BoxedStateComponent from "./BoxedStateComponent";
import { _$ } from "../helpers/helpers";

class WorkingSet extends BoxedStateComponent {
    constructor(props) {
        super(props);

        this.state = this.getState();

        this.onChangeHandler = this.onChangeHandler.bind(this);
        this.setAllLanguagesTrue = this.setAllLanguagesTrue.bind(this);
        this.setAllLanguagesFalse = this.setAllLanguagesFalse.bind(this);
        this.updateWorkSet = this.updateWorkSet.bind(this);
        // this.setUpdateSettings = this.setUpdateSettings.bind(this);
    }

    // noinspection JSMethodCanBeStatic
    getState() {
        let settings = appSettings_$;
        return {
            isLoaded: settings.isLoaded(),
            isLoading: settings.isLoading(),
            isStaleData: settings.isStaleData(),
            currentLocale: settings.currentLocale(),
            primaryLocale: settings.primaryLocale(),
            translatingLocale: settings.translatingLocale(),
            locales: settings.locales(),
            userLocales: settings.userLocales(),
            displayLocales: settings.displayLocales(),
        };
    }

    componentDidMount() {
        this.subscriber = new Subscriber(appSettings, appTranslations, () => {
            this.state_$.cancel();
            this.setState(this.getState());
        });
    }

    componentWillUnmount() {
        this.subscriber.unsubscribe();
    }

    onChangeHandler(e) {
        // need to make a copy or state_$ will not see any changes 
        if (e.target.checked) {
            // add to display locales
            // newLocales.push(e.target.name);
            this.state_$.displayLocales.$_.push(e.target.name);
            this.state_$.save();
        } else {
            // remove locale
            this.state_$.displayLocales.$_if($_ => $_.splice($_.indexOf(e.target.name), 1));
            this.state_$.save();
        }
    }

    updateWorkSet() {
        appSettings_$.displayLocales = this.state.displayLocales;
        appSettings_$.save();
    }

    setAllLanguagesTrue() {
        this.state_$.displayLocales = this.state.locales.slice(0);
        this.state_$.save();
    }

    setAllLanguagesFalse() {
        this.state_$.displayLocales = [this.state.primaryLocale, this.state.translatingLocale];
        this.state_$.save();
    }

    render() {
        const {t} = this.props;
        const {isLoaded, primaryLocale, translatingLocale, locales, displayLocales} = this.state;

        if (!isLoaded) {
            return <div>Loading...</div>;
        } else {
            return (
                <div className="row">
                    {/*{changeLanguage(this.state.currentLocale)}*/}
                    <div className="col-sm-3">
                        <div className="row">
                            <div className="col-sm-12">
                                <button type="button" className='btn btn-sm btn-primary' onClick={this.updateWorkSet}>
                                    {t("messages.display-locales")}
                                </button>
                                &nbsp;&nbsp;
                            </div>
                        </div>
                        <div className="row">
                            <div className=" col-sm-12">
                                <div style={{minHeight: "10px"}}/>
                                <button id="display-locale-all" type="button"
                                    className="btn btn-sm btn-outline-secondary"
                                    onClick={this.setAllLanguagesTrue}>{t("messages.check-all")}</button>
                                <button id="display-locale-none" type="button"
                                    className="btn btn-sm btn-outline-secondary ml-1"
                                    onClick={this.setAllLanguagesFalse}>{t("messages.check-none")}</button>
                            </div>
                        </div>
                    </div>
                    <div className="col-sm-9">
                        <div className="input-group-sm">
                            {locales.map((locale, index) => (
                                <label key={index + ":" + locale}>
                                    <input
                                        className={locale !== primaryLocale && locale !== translatingLocale ? 'display-locale' : ''}
                                        name={locale} type="checkbox" value={locale}
                                        checked={locale === primaryLocale || locale === translatingLocale || displayLocales.indexOf(locale) !== -1}
                                        disabled={locale === primaryLocale || locale === translatingLocale}
                                        onChange={this.onChangeHandler}
                                    />
                                    {locale}
                                </label>
                            ))}
                        </div>
                    </div>
                </div>
            );
        }
    }
}

export default compose(
    translate(),
    connect(),
)(WorkingSet);
