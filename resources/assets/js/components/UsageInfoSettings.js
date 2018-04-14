import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import appSettings, { appSettings_$ } from '../helpers/AppSettings';
import { Subscriber } from "../helpers/Subscriptions";
import BoxedStateComponent from "./BoxedStateComponent";

class UsageInfoSettings extends BoxedStateComponent {
    constructor(props) {
        super(props);

        this.state = this.getState();

        this.handleShowUsageInfoChange = this.handleShowUsageInfoChange.bind(this);
        this.handleResetUsageInfoChange = this.handleResetUsageInfoChange.bind(this);
        this.updateUsageInfo = this.updateUsageInfo.bind(this);
    }

    // noinspection JSMethodCanBeStatic
    getState() {
        return {
            isLoaded: appSettings_$.isLoaded(),
            isLoading: appSettings_$.isLoading(),
            isStaleData: appSettings_$.isStaleData(),
            isAdminEnabled: appSettings_$.isAdminEnabled(),
            showUsage: appSettings_$.showUsage(),
            resetUsage: appSettings_$.resetUsage(),
            usageInfoEnabled: appSettings_$.usageInfoEnabled(),
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

    updateUsageInfo() {
        appSettings_$.showUsage = this.state.showUsage;
        appSettings_$.resetUsage = this.state.resetUsage;
        appSettings_$.save();
    }

    handleShowUsageInfoChange(e) {
        this.state_$.showUsage = e.target.checked;
        this.state_$.save();
    }

    handleResetUsageInfoChange(e) {
        this.state_$.resetUsage = e.target.checked;
        this.state_$.save();
    }

    render() {
        const { t } = this.props;
        const { isLoaded, isAdminEnabled, showUsage, resetUsage, usageInfoEnabled } = this.state;
        if (!isLoaded) {
            return <div>Loading...</div>;
        } else {
            return usageInfoEnabled && (
                <div className="row">
                    <div className="col-sm-12">
                        <div className="row">
                            <div className=" col-sm-3">
                                <button className='btn btn-sm btn-primary' onClick={this.updateUsageInfo}>
                                    {t("messages.set-usage-info")}
                                </button>
                            </div>
                            <div className=" col-sm-4">
                                <label>
                                    <input id="show-usage-info" name="show-usage-info" type="checkbox"
                                        checked={showUsage} 
                                        onChange={this.handleShowUsageInfoChange}/>
                                    {t('messages.show-usage-info')}
                                </label>
                            </div>
                            <div className=" col-sm-4">
                                <label>
                                    <input id="reset-usage-info" name="reset-usage-info" type="checkbox"
                                        checked={resetUsage}
                                        onChange={this.handleResetUsageInfoChange}/>
                                    {t('messages.reset-usage-info')}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }
    }
}

export default compose(
    translate(),
    connect()
)(UsageInfoSettings);
