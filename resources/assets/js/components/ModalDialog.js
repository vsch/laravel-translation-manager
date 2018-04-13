import React from 'react';
import PropTypes from "prop-types";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import $ from "jquery";
import languageSynchronizer from "../helpers/LanguageSynchronizer";
import { isFunction } from "../helpers/helpers";
import BoxedStateComponent from "./BoxedStateComponent";

class ModalDialog extends BoxedStateComponent {
    constructor(props) {
        super(props);

        this.handleClose = this.handleClose.bind(this);
        this.handleExtras = this.handleExtras.bind(this);
    }

    componentDidMount() {
        this.$el = $(this.el);
        this.updateModal();
    }

    componentWillUnmount() {
        this.$el.modal('dispose');
    }

    updateModal() {
        if (this.props.showModal) {
            this.$el.modal({
                backdrop: this.props.backdrop === null || this.props.backdrop === undefined ? 'static' : !!this.props.backdrop,
                show: this.props.showModal,
            });
        } else {
            if (isFunction(this.props.onNotShown) && this.props.onNotShown()) {
                this.$el.modal('hide');
            }
        }
    }

    componentDidUpdate() {
        this.updateModal();
    }

    handleClose(e) {
        e.preventDefault();
        e.stopPropagation();
        let onClose = this.props.onClose;
        if (isFunction(onClose)) onClose(e, false);
        this.$el.modal('hide');
    }

    handleExtras(e) {
        e.preventDefault();
        e.stopPropagation();
        let onExtras = this.props.onExtras;
        if (onExtras && typeof onExtras === "function") onExtras(e);
        this.$el.modal('hide');
    }

    render() {
        const { t } = this.props;

        return (
            <div ref={el => this.el = el} className={this.props.modalType || "modal fade"} tabIndex="-1" role="dialog" aria-labelledby="modalTitle" aria-hidden="true">
                <div className={this.props.modalDialogType || "modal-dialog modal-dialog-centered"} role="document">
                    <div className="modal-content">
                        <div className="modal-header">
                            <h5 className="modal-title" id="modalTitle">{this.props.modalTitle || 'Missing Title'}</h5>
                            <button type='button' className="close" onClick={this.handleClose}><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div className="modal-body">
                            {this.props.children}
                        </div>
                        {this.props.footer}
                    </div>
                </div>
            </div>
        );
    }
}

ModalDialog.propTypes = {
    modalTitle: PropTypes.string,
    modalType: PropTypes.string,
    modalDialogType: PropTypes.string,
    showModal: PropTypes.bool,
    onClose: PropTypes.func,
    onExtras: PropTypes.func,
    hookOldScripts: PropTypes.bool,
    backdrop: PropTypes.any,
    footer: PropTypes.node,
    onNotShown: PropTypes.func,
};

export default compose(translate(), connect())(ModalDialog);
