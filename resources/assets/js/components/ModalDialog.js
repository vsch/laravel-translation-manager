import React from 'react';
import PropTypes from "prop-types";
import { connect } from "react-redux";
import { translate } from 'react-i18next';
import { compose } from "redux";
import $ from "jquery";
import BoxedStateComponent from "./BoxedStateComponent";
const boxedImmutable = require('boxed-immutable');

const util = boxedImmutable.util;

class ModalDialog extends BoxedStateComponent {
    constructor(props) {
        super(props);

        this.handleClose = this.handleClose.bind(this);
        this.onHidden = this.onHidden.bind(this);
        this.onHide = this.onHide.bind(this);
        this.onShow = this.onShow.bind(this);
        this.onShown = this.onShown.bind(this);
        
        this.isHidden = true;
        this.isShown = false;
    }

    componentDidMount() {
        this.attachModal();
    }
    
    attachModal() {
        this.$el = $(this.el);
        this.$el.on('hidden.bs.modal', this.onHidden);
        this.$el.on('hide.bs.modal', this.onHide);
        this.$el.on('show.bs.modal', this.onShow);
        this.$el.on('shown.bs.modal', this.onShown);
    }

    componentWillUnmount() {
        this.detachModal();
    }

    detachModal() {
        this.$el.modal('dispose');
    }

    onHidden() {
        this.detachModal();
        this.isHidden = true;
        util.isFunction(this.props.onHidden) && this.props.onHidden();
    }

    onHide() {
        util.isFunction(this.props.onHide) && this.props.onHide();
    }

    onShow() {
        util.isFunction(this.props.onShow) && this.props.onShow();
    }

    onShown() {
        this.isShown = true;
        util.isFunction(this.props.onShown) && this.props.onShown();
    }

    componentDidUpdate() {
        if (this.isShown && this.props.hideModal) { 
            // requesting hide modal 
            this.handleClose();
        }
        if (this.isHidden && this.props.showModal) { 
            // requesting show modal 
            this.detachModal();
            this.attachModal();
            
            this.isHidden = false;
            const option = {
                backdrop: this.props.backdrop === null || this.props.backdrop === undefined || this.props.backdrop === 'static' ? 'static' : this.props.backdrop,
                keyboard: this.props.keyboard === null || this.props.keyboard === undefined ? true : !!this.props.keyboard,
                focus: this.props.focus === null || this.props.focus === undefined ? true : !!this.props.focus,
            };
            this.$el.modal(option);
        }
    }

    handleClose(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const onClose = this.props.onClose;
        
        let canClose = true;
        if (util.isFunction(onClose)) {
            canClose = onClose(e, false);
        }
        
        if (canClose === undefined || canClose) {
            this.isShown = false;
            this.$el.modal('hide');
        }
    }

    render() {
        const { t } = this.props;

        return (
            <div ref={el => this.el = el} className={(this.props.modalType || "modal fade")} tabIndex="-1" role="dialog" aria-labelledby="modalTitle" aria-hidden="true">
                <div className={(this.props.modalDialogType || "modal-dialog modal-dialog-centered")} role="document">
                    <div className="modal-content">
                        <div className={"modal-header " + this.props.headerType}>
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
    onHidden: PropTypes.func,
    onHide: PropTypes.func,
    onShow: PropTypes.func,
    onShown: PropTypes.func,
    onClose: PropTypes.func,
    
    showModal: PropTypes.bool,
    hideModal: PropTypes.bool,     // set to true when modal is shown to start hiding it
    modalTitle: PropTypes.any,
    modalType: PropTypes.string,
    modalDialogType: PropTypes.string,
    headerType: PropTypes.string,
    backdrop: PropTypes.any,
    footer: PropTypes.node,
};

export default compose(translate(), connect())(ModalDialog);
