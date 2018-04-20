import GlobalSetting, { UPDATE_STORE } from './GlobalSetting';
import boxedImmutable from "boxed-immutable";
import { isUndefined } from './helpers';

const util = boxedImmutable.util;
const _$ = boxedImmutable.box;
const isArray = util.isArray;
const eachProp = util.eachProp;
const isFunction = util.isFunction;
const isObject = util.isObjectLike;
const UNDEFINED = util.UNDEFINED;

const MODAL_HIDDEN = 0;
const MODAL_HIDING = 1;
const MODAL_SHOWING = 2;
const MODAL_SHOWN = 3;

export class AppModal extends GlobalSetting {
    constructor() {
        super("appModal");

        let defaults = ({
            // default settings
            modalBody: null,
            modalProps: {},
            showModal: false,
            hideModal: false,
        });

        const updateSettingsType = {
            showModal: UPDATE_STORE, // store only, 
            hideModal: UPDATE_STORE, // store only, 
            modalProps: UPDATE_STORE, // store only, 
            modalBody: UPDATE_STORE, // store only, 
        };

        let transforms_$ = _$({
            showModal: _$.transform.toDefault(false),
            hideModal: _$.transform.toDefault(false),
            modalProps: _$.transform.toDefault({}),
            modalBody: _$.transform.toDefault(null),
        });

        const defaultSettings = defaults;
        const transforms = transforms_$.$_value;
        this.setStateTransforms(transforms);
        this.setDefaultSettings(defaultSettings, updateSettingsType);

        this.modalState = MODAL_HIDDEN;
        this.shakeModal = false;

        this.onShow = null; // current modal callbacks, information only, cannot change modal
        this.onShown = null; // current modal callbacks, information only, cannot change modal
        this.onHide = null; // current modal callbacks, information only, cannot change modal or prevent closing 
        this.onHidden = null; // current modal callbacks, information only, cannot change modal
        
        // special allows validation and can prevent closing
        this.onClose = null; // current modal callback

        this._onModalShow = this._onModalShow.bind(this);
        this._onModalShown = this._onModalShown.bind(this);
        this._onModalHide = this._onModalHide.bind(this);
        this._onModalHidden = this._onModalHidden.bind(this);
        this._onModalClose = this._onModalClose.bind(this);
        
        this.inButtonOp = false;
    }

    // implement to test if can request settings from server
    serverCanLoad() {
        return false;
    }
    
    _onModalShow() {
        this.modalState = MODAL_SHOWING;
        const callback = this.onShow;
        this.onShow = null;
        callback && callback();
    }

    _onModalShown() {
        this.modalState = MODAL_SHOWN;
        const callback = this.onShown;
        this.onShown = null;
        callback && callback();
    }

    _onModalClose(e) {
        let preventClose = null;
        const callback = this.onClose;
        
        if (callback) { 
            preventClose = callback(e);
        }
        
        if (!preventClose) {
            this.onClose = null;
            return true;
        } else {
            if (isObject(preventClose)) { 
                // new options to display
                this._showModal(preventClose);
            }
            return false;
        }
    }

    _onModalHide(e) {
        this.modalState = MODAL_HIDING;
        const callback = this.onHide;
        this.onHide = null;
        if (callback) {
            callback(e);
        }
    }

    _onModalHidden() {
        this.modalState = MODAL_HIDDEN;

        const callback = this.onHidden;
        this.onHidden = null;
        callback && callback();

        if (this.modalState === MODAL_HIDDEN) { 
            // no other dialog started showing, we can turn off dialog display
            this.update(this.defaultSettings);
        }
    }

    _showModal(options) {
        this.onShow = options.onShow;         
        this.onShown = options.onShown;       
        this.onHide = options.onHide; 
        this.onClose = options.onClose;
        this.onHidden = options.onHidden; 

        const props = Object.assign({ modalTitle: 'Missing Modal Title'}, options.modalProps, {
            onHide: this._onModalHide,
            onHidden: this._onModalHidden,
            onShow: this._onModalShow,
            onShown: this._onModalShown,
            onClose: this._onModalClose,
        });

        this.update({
            showModal: true,
            modalBody: options.modalBody || 'Missing Modal Body',
            modalProps: props,
        });
    }
    
    hideModal() {
        switch (this.modalState) {
            case MODAL_HIDDEN:
                break;

            case MODAL_HIDING:
                break;

            case MODAL_SHOWING:
                this.update({hideModal: true, });
                break;

            case MODAL_SHOWN:
                this.update({hideModal: true, });
                break;

            default:
                window.console.error("Unknown modal state: " + this.modalState);
                break;
        }

    }

    showModal(options) {
        const state = this.getState();
        switch (this.modalState) {
            case MODAL_HIDDEN:
                this.modalState = MODAL_SHOWING;
                this._showModal(options);
                break;
                
            case MODAL_HIDING:
                // this means that close triggered an action that needs to show dialog,
                // it will display after the current one enters hidden state
                const onHidden = this.onHidden;
                this.onHidden = () => {
                    if (onHidden) { 
                        onHidden();
                    }
                    this._showModal(options);
                };
                break;
                
            case MODAL_SHOWING:
                window.console.error("showModal called while previous is still in show transition: ", options);
                break;
                
            case MODAL_SHOWN:
                window.console.error("showModal called while previous is still shown: ", options);
                break;

            default:
                window.console.error("Unknown modal state: " + this.modalState);
                break;
        }
    }

    isModalShowing() {
        return this.modalState !== MODAL_HIDDEN;
    }
}

const appModal = new AppModal();

export default appModal;

