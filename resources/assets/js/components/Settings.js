import React from "react";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import TranslationSettings from "./TranslationSettings";
import AppSettings from "./AppSettings";
import appSettings from "../helpers/AppSettings";
import { Subscriber } from "../helpers/Subscriptions";
import BoxedStateComponent from "./BoxedStateComponent";

class Settings extends BoxedStateComponent {
    constructor(props) {
        super(props);

        this.state = this.getState();
    }

    // noinspection JSMethodCanBeStatic
    getState() {
        return {
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
    
    render() {
        return (
            <div>
                <AppSettings noHide />
                <TranslationSettings noHide />
            </div>
        );
    }
}

Settings.propTypes = {
};

export default compose(
    translate(),
    connect()
)(Settings);
