import React from "react";
import { withRouter } from "react-router-dom";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import TransXEditable from "./TransXEditable";
import appTranslations from "../helpers/GlobalTranslations";
import appEvents from '../helpers/AppEvents';
import globalMismatches from '../helpers/GlobalMismatches';

export class TranslationMismatches extends React.Component {
    constructor(props) {
        super(props);

        this.showGroup = this.showGroup.bind(this);

        // this.state = this.getState();
        // this.usesSettings = [globalMismatches, appSettings];
    }

    // getState() {
    //     return this.adjustState({
    //         error: null,
    //         isLoaded: globalMismatches_$.isLoaded() && appSettings_$.isLoaded(),
    //         isLoading: globalMismatches_$.isLoading(),
    //         isStaleData: globalMismatches_$.isStaleData() || appSettings_$.isStaleData(),
    //         translatingLocale: appSettings_$.translatingLocale(),
    //         primaryLocale: appSettings_$.primaryLocale(),
    //         userLocales: appSettings_$.userLocales(),
    //         mismatches: globalMismatches_$.mismatches(),
    //     });
    // }

    componentDidMount() {
        this.invalidateTranslations = appEvents.subscribe('invalidate.translations', (group) => {
            globalMismatches.staleData();
        });
    }

    componentWillUnmount() {
        if (this.invalidateTranslations) this.invalidateTranslations();
    }

    showGroup(e, group) {
        e.preventDefault();

        this.props.history.push('/');
        appTranslations.changeGroup(group);
    }

    getEntry(stat) {
        return {
            group: stat.hasOwnProperty('group') ? stat.group : null,
            key: stat.hasOwnProperty('key') ? stat.key : null,
            tr: stat.hasOwnProperty('tr') ? stat.tr : null,
            tr_value: stat.hasOwnProperty('tr_value') ? stat.tr_value : null,
            pr: stat.hasOwnProperty('pr') ? stat.pr : null,
            pr_value: stat.hasOwnProperty('pr_value') ? stat.pr_value : null,
            status: stat.hasOwnProperty('status') ? stat.status : null,
        };
    }

    render() {
        const { t } = this.props;
        const { error, isLoaded, mismatches, translatingLocale, primaryLocale, userLocales } = this.props;

        let body;
        if (error) {
            body = <div>Error: {error.message}</div>;
        } else if (!isLoaded) {
            body = (
                <tr>
                    <td colSpan='5' width='100%' className='text-center'>
                        <div className='show-loading'/>
                    </td>
                </tr>
            );
        } else if (!mismatches.length) {
            body = (
                <tr>
                    <td colSpan='5' width='100%' className='text-center'>{t('messages.no-mismatches')}</td>
                </tr>
            );
        } else {
            let $key = '', $keyText, $link;
            let $locale = translatingLocale;
            let $isLocaleEnabled = userLocales.indexOf($locale) > -1;

            body = mismatches.map((item, index) => {
                const $mismatch = this.getEntry(item);
                // this is an in-app url
                const url = "group/" + $mismatch.group;
                let $borderTop = 'no-border-top';

                if ($key !== $mismatch.key) {
                    if ($key !== '') {
                        $borderTop = 'border-top';
                    }
                    $key = $mismatch.key;
                    $keyText = $mismatch.key;
                }

                $link = url + '/' + $mismatch.group + '#' + $mismatch.key;
                $mismatch.value = $mismatch.tr_value;
                $mismatch.locale = $locale;

                return (
                    <tr key={index + '.' + $mismatch.group + '.' + $mismatch.key} className={$borderTop}>
                        <td className="missing">{$keyText}</td>
                        <td className={
                            ($mismatch.status == '1' ? ' has-unpublished-translation' : '') +
                            ($mismatch.status == '2' ? ' has-cached-translation' : '')}>{$isLocaleEnabled ? TransXEditable.transXEditLink($mismatch.group, $mismatch.key, $mismatch.locale, $mismatch, false) : $mismatch.value}</td>
                        <td className="missing" dangerouslySetInnerHTML={{ __html: $mismatch.tr }}/>
                        <td className="missing" dangerouslySetInnerHTML={{ __html: $mismatch.pr }}/>
                        <td className="group missing"><a href='#' onClick={(e) => this.showGroup(e, $mismatch.group)}>{$mismatch.group}</a></td>
                    </tr>
                );
            });

        }

        return (
            <table className="table table-sm table-hover table-striped table-bordered translation-stats table-translations">
                <thead className='thead-light'>
                <tr>
                    <th width='20%' className="key">{t('messages.key')}</th>
                    <th width='40%' colSpan="2">{translatingLocale}</th>
                    <th width='20%'>{primaryLocale}</th>
                    <th width='20%' className="group">{t('messages.group')}</th>
                </tr>
                </thead>
                <tbody>
                {body}
                </tbody>
            </table>
        );
    }
}

export default compose(translate(), connect())(withRouter(TranslationMismatches));
