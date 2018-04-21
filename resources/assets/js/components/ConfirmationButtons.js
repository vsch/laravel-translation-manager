import React from 'react';
import PropTypes from "prop-types";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import { appSettings_$ } from "../helpers/AppSettings";
import { isFunction, isString } from "../helpers/helpers";
import $ from "jquery";

class ModalDialog extends React.Component {
    constructor(props) {
        super(props);

        this.handleClose = this.handleClose.bind(this);
        this.handleOk = this.handleOk.bind(this);
        this.handleNeverShow = this.handleNeverShow.bind(this);
    }

    handleClose(e) {
        e.preventDefault();
        e.stopPropagation();
        let onClose = this.props.onClose;
        if (isFunction(onClose)) onClose(e, false);
        $(e.target).closest('.modal').modal('hide');
    }

    handleOk(e) {
        e.preventDefault();
        e.stopPropagation();
        let onClose = this.props.onClose;
        if (isFunction(onClose)) onClose(e, true);
        $(e.target).closest('.modal').modal('hide');
    }

    handleNeverShow(e) {
        e.preventDefault();
        e.stopPropagation();
        let onNeverShow = this.props.onNeverShow;
        if (onNeverShow) {
            if (isFunction(onNeverShow)) onNeverShow(e);
            else if (isString(onNeverShow)) {
                // must be confirmation appSetting key
                appSettings_$.uiSettings[onNeverShow] = false;
                appSettings_$.save();
            } else {
                // TODO: fix prop types to allow function or string only
                console.error("Invalid onNeverShow property for modal", onNeverShow);
            }
        }
        this.handleOk(e);
    }

    render() {      
        const { t, cancelText, okText, onNeverShow, neverShowText, } = this.props;

        return (
            <div className="modal-footer">
                {onNeverShow && <button type='button' className={this.props.neverShowButtonType || 'btn btn-sm btn-warning'} onClick={this.handleNeverShow}>{neverShowText ? neverShowText : t('messages.modal-button-never-show')}</button>}
                {!this.props.hideCancel && <button type="button" className={this.props.cancelButtonType || 'btn btn-sm btn-outline-secondary'} onClick={this.handleClose}>{cancelText ? cancelText : t('messages.modal-button-close')}</button>}
                <button type="button" className={this.props.okButtonType || 'btn btn-sm btn-secondary'} onClick={this.handleOk}>{okText ? okText : t('messages.modal-button-ok')}</button>
            </div>
        );
    }
}

ModalDialog.propTypes = {
    onNeverShow: PropTypes.any,
    hideCancel: PropTypes.bool,
    onClose: PropTypes.any,
    neverShowText: PropTypes.string,
    neverShowButtonType: PropTypes.string,
    okText: PropTypes.string,
    okButtonType: PropTypes.string,
    cancelText: PropTypes.string,
    cancelButtonType: PropTypes.string,
};

export default compose(translate(), connect())(ModalDialog);
