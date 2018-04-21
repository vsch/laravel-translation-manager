import React from 'react';
import PropTypes from "prop-types";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import $ from "jquery";
import axios from "axios";
import appEvents from '../helpers/AppEvents';
import appModal, { normalizeProp, runDelayed, setButtonBusy } from '../helpers/AppModal';

const boxedImmutable = require('boxed-immutable');

const util = boxedImmutable.util;

class UrlButton extends React.Component {
    constructor(props) {
        super(props);
        this.handleClick = this.handleClick.bind(this);
    }

    handleClick(e) {
        const { t } = this.props;
        e.preventDefault();
        e.stopPropagation();

        if (appModal.inButtonOp || normalizeProp(this.props.disabled)) return;
        appModal.inButtonOp = true;

        const target = e.target;
        const $el = $(target);

        const normalizedUrlData = normalizeProp(this.props.dataUrl);
        const dataUrl = util.isObjectLike(normalizedUrlData) ? normalizedUrlData : { url: normalizedUrlData };
        const reloadGroups = this.props.reloadGroups;
        const disableWith = this.props.disableWith;
        const confirmationKey = this.props.confirmationKey;
        const confirmationBody = this.props.confirmationBody || null;
        const invalidateGroup = this.props.invalidateGroup;
        const onConfirmed = this.props.onConfirmed;
        const onSuccess = this.props.onSuccess;
        const onFailure = this.props.onFailure;

        const doUpdate = (function (ok) {
            if (!ok) {
                appModal.inButtonOp = false;
            } else {
                if (util.isFunction(onConfirmed)) {
                    try {
                        onConfirmed();
                    } finally {
                        appModal.inButtonOp = false;
                    }
                } else {
                    let restoreButton = setButtonBusy(target, disableWith);

                    (dataUrl.type && dataUrl.type.toLowerCase() === 'get' ? axios.get : axios.post)(dataUrl.url, dataUrl.data)
                        .then(result => {
                            restoreButton();

                            if (result.data.status === 'ok') {
                                if (invalidateGroup) {
                                    appEvents.fireEvent('invalidate.translations', normalizeProp(invalidateGroup));
                                    appEvents.fireEvent('invalidate.group');
                                }

                                if (normalizeProp(reloadGroups)) appEvents.fireEvent('invalidate.groups');
                                runDelayed(onSuccess, result);
                            } else {
                                // must be an error
                                if (onFailure) {
                                    runDelayed(onFailure, null, result);
                                } else {
                                    const errors = util.isArray(result.data.error) ?
                                        <ul>{result.data.error.map((message, index) =>
                                            <li key={index}
                                                dangerouslySetInnerHTML={{ __html: message }}/>,
                                        )}</ul> : <p>{result.data.error}</p>;
                                        
                                    appModal.messageBox(t('messages.server-error-title'), (
                                        <div>
                                            <p>{t('messages.server-error-response')}</p>
                                            {errors}
                                        </div>
                                    ), {
                                        headerType: 'bg-danger text-white',
                                    });
                                }
                            }

                            console.log("Operation complete", dataUrl, result.data);
                        })
                        .catch((e) => {
                            restoreButton();

                            if (onFailure) {
                                runDelayed(onFailure, e);
                            } else {
                                appModal.messageBox(t('messages.server-error-title'), (
                                    <div>
                                        <p>{t('messages.server-error-message')}</p>
                                        {e.message}
                                    </div>
                                ), {
                                    headerType: 'bg-danger text-white',
                                });
                            }
                        });
                }
            }
        }).bind(this);

        if (confirmationKey) {
            appModal.confirmationModal(confirmationKey, confirmationBody, doUpdate, {
                //cancelBtnClass: `btn ${btnSize} ${cancelBtnType}`
                //okBtnClass: `btn ${btnSize} ${okBtnType}`,
                btnClasses: $el.attr('class'),
                //okBtnType: 'btn-outline-primary',
                //cancelBtnType: 'btn-outline-secondary',
                //btnSize: "",
            });
        } else {
            doUpdate(true);
        }
    }

    render() {
        const { t } = this.props;

        return !this.props.plainLink ? (
            !this.props.asLink ? (
                <button ref={el => this.el = el}
                    type={this.props.type || "button"}
                    onClick={this.handleClick}
                    className={this.props.className}
                >{this.props.children}</button>
            ) : (
                <a ref={el => this.el = el}
                    href='#'
                    title={this.props.title || ''}
                    onClick={this.handleClick}
                    className={this.props.className}
                >{this.props.children}</a>
            )
        ) : (
            <a
                role='button'
                className={this.props.className}
                href={normalizeProp(this.props.dataUrl.url)}
                {...normalizeProp(this.props.disabled) ? { disabled: true } : {}}
            >{this.props.children}</a>
        );
    }
}

UrlButton.propTypes = {
    plainLink: PropTypes.bool,
    asLink: PropTypes.bool,
    disable: PropTypes.any,
    dataUrl: PropTypes.any,
    onConfirmed: PropTypes.func,
    onSuccess: PropTypes.func,
    onFailure: PropTypes.func,
    reloadGroups: PropTypes.any,
    invalidateGroup: PropTypes.any,
    confirmationKey: PropTypes.any,
    confirmationBody: PropTypes.any,
    disableWith: PropTypes.any,
};

export default compose(translate(), connect())(UrlButton);
