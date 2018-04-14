import React from "react";
import PropTypes from 'prop-types';
import { boxedImmutable, firstDefined, isFunction, isString } from "../helpers/helpers";
import appSettings, { appSettingChecks, appSettingForcedChecks, appSettings_$, dashboardConfig } from "../helpers/AppSettings";
import BoxedStateComponent from "./BoxedStateComponent";
import ConfirmationButtons from "./ConfirmationButtons";
import $ from "jquery";
import appTranslations, { appTranslations_$ } from "../helpers/GlobalTranslations";
import axios from "axios/index";
import languageSynchronizer from "../helpers/LanguageSynchronizer";

class DashboardComponent extends BoxedStateComponent {
    constructor(props, dashboardName, usesOldScripts) {
        super(props);

        if (!dashboardConfig.dashboards[dashboardName]) throw `IllegalArgument, ${dashboardName} is not found in dashboardConfig`;

        this.usesOldScripts = !!usesOldScripts;

        this.dashboardName = dashboardName;
        this.reload = this.reload.bind(this);
        this.handleReload = this.handleReload.bind(this);
        this.handleCollapse = this.handleCollapse.bind(this);
        this.handleHide = this.handleHide.bind(this);
        this.handleExtras = this.handleExtras.bind(this);
        this.handleButtonClick = this.handleButtonClick.bind(this);

        this.inButtonOp = false;

        this.state = {};
        this.onReload = this.reload;
    }

    noShow() {
        return this.state.showDashboard !== undefined && !this.state.showDashboard && this.props.noHide !== true;
    }

    adjustState(state, onExtras) {
        function getSetting(onToggle, defaultState, override) {
            return override ? defaultState : isString(onToggle) ? appSettings_$.uiSettings.$_path(onToggle) : defaultState;
        }

        // TODO: rework this component to not use state for props, instead compute this in getDashboardProps()
        const props = this.props;
        const noHide = props.noHide;
        state.dashboardProps = {};
        if (noHide) {
            state.dashboardProps.onHide = undefined;
            state.dashboardProps.onCollapse = undefined;
        } else {
            const dashboardName = this.dashboardName;
            if (dashboardName) {
                const routeSettings = props.routeSettings || '';
                const sep = routeSettings ? '.' : '';

                state.dashboardProps.onHide = routeSettings + sep + dashboardConfig.dashboards[dashboardName].showState;
                state.dashboardProps.onCollapse = routeSettings + sep + dashboardConfig.dashboards[dashboardName].collapseState;
            } else {
                state.dashboardProps.onHide = this.handleHide;
                state.dashboardProps.onCollapse = this.handleCollapse;
            }
        }

        state.showDashboard = getSetting(state.dashboardProps.onHide, true, noHide);
        state.isCollapsed = getSetting(state.dashboardProps.onCollapse, false);

        if (onExtras) {
            state.dashboardProps.onExtras = onExtras;
            state.isAltExtras = getSetting(state.dashboardProps.onExtras, false);
        }

        // if (this.dashboardName === 'summary') {
        //     console.debug("Dashboard State, collapseState", state.onCollapse, state);
        // }

        // modal state information
        state = boxedImmutable.util.mergeDefaultProperties(state, {
            showModal: false,
            modalProps: this.state.modalProps || {},
            modalBody: null,
        }, 1, true);

        // let state be adjusted for pending updates
        return BoxedStateComponent.prototype.adjustState.call(this, state);
    }

    getDashboardProps() {
        return {
            isAltExtras: this.state.isAltExtras,
            isCollapsed: this.state.isCollapsed,
            isLoaded: firstDefined(this.state.isLoaded, true),
            isLoading: firstDefined(this.state.isLoading, false),
            isStaleData: firstDefined(this.state.isStaleData, false),
            onCollapse: this.state.dashboardProps.onCollapse ? this.handleCollapse : null,
            onExtras: this.state.dashboardProps.onExtras ? this.handleExtras : null,
            onHide: this.state.dashboardProps.onHide ? this.handleHide : null,
            onReload: this.onReload ? this.handleReload : null,
            showDashboard: this.state.showDashboard,
            hookOldScripts: !!this.usesOldScripts,
        };
    }

    handleToggle(onToggle, newState) {
        // IMPORTANT: we need to change our state so component renders and changes props for Dashboard component
        if (isString(onToggle)) {
            // we toggle the appSettings_$.uiSettings.
            appSettings_$.uiSettings.path_$(onToggle).$_value = newState;
            appSettings_$.save();
        } else if (isFunction(onToggle)) {
            onToggle(newState);
        }
    }

    handleCollapse(newState) {
        this.state_$.isCollapsed = newState;
        this.state_$.save();

        this.handleToggle(this.state.dashboardProps.onCollapse, newState)
    }

    handleHide(e) {
        this.handleToggle(this.state.dashboardProps.onHide, false)
    }

    handleExtras(e) {
        this.handleToggle(this.state.dashboardProps.onExtras, !this.state.isAltExtras)
    }

    reload() {

    }

    handleReload() {
        this.handleCollapse(false);
        this.reload();
    }

    handleButtonClick(e) {
        e.preventDefault();
        e.stopPropagation();
        if (this.inButtonOp) return;

        this.inButtonOp = true;

        const $el = $(e.target);

        const reloadGroups = $el.data('reload-groups');
        const disableWith = $el.data('disable-with');
        const confirmationKey = $el.data('confirmation-key');
        const confirmationExtra = this.state.confirmationExtra || null;
        const invalidateGroup = $el.data('invalidate-group');
        const postUrl = $el.data('post-url');
        const extraFieldsState = $el.data('extra-fields');
        const extraFieldsParams = $el.data('extra-params');
        const extraFieldValue = extraFieldsState ? this.state_$.$_path(extraFieldsState) || {} : {};
        const extraFields = isFunction(extraFieldValue) ? extraFieldValue(extraFieldsParams) : extraFieldValue;

        const doUpdate = (function () {
            let restoreText;
            if (disableWith) {
                restoreText = $el.text();
                $el.text(disableWith);
            }
            // $el.attr('disabled', true);
            $el.addClass('busy');
            axios.post(postUrl, extraFields)
                .then(result => {
                    if (restoreText) $el.text(restoreText);
                    $el.removeAttr('disabled');
                    $el.removeClass('busy');
                    restoreText = null;
                    this.inButtonOp = false;

                    if (invalidateGroup) {
                        if (this.state.group === invalidateGroup) {
                            appTranslations.staleData(appSettings_$.uiSettings.autoUpdateTranslationTable());
                        }
                    }
                    if (reloadGroups) {
                        // group deleted
                        appSettings_$.uiSettings.group = null;
                        appSettings_$.save();
                        appTranslations_$.group = null;
                        appTranslations_$.save();
                        appSettings.load();
                    }
                    console.log("Operation complete", postUrl, result.data);
                })
                .catch(() => {
                    if (restoreText) $el.text(restoreText);
                    $el.removeAttr('disabled');
                    $el.removeClass('busy');
                    this.inButtonOp = false;

                    // TODO: post error message
                })
            ;
        }).bind(this);

        if (confirmationKey && appSettings_$.uiSettings[confirmationKey]()) {
            const { t } = this.props;

            const confirmationDashCase = confirmationKey ? appSettingChecks[confirmationKey] : null;
            const forcedConfirmationCheck = confirmationKey ? appSettingForcedChecks[confirmationKey] : null;
            if (!confirmationDashCase) {
                console.error(confirmationKey + " missing dashCase entry in appSettings appSettingChecks", confirmationDashCase);
            }

            const classes = $el.attr('class').split(' ');
            let okBtnType = 'btn-outline-primary';
            let cancelBtnType = 'btn-outline-secondary';
            let btnSize = "";

            classes.forEach(cls => {
                switch (cls) {
                    case 'btn-sm' :
                        btnSize = 'btn-sm';
                        break;

                    case 'btn' :
                        break;

                    case 'btn-lg' :
                        btnSize = cls;
                        break;

                    default:
                        if (cls.match(/^btn-outline-[a-z]+$/)) {
                            okBtnType = cls;
                        } else if (cls.match(/^btn-[a-z]+$/)) {
                            okBtnType = 'btn-outline-' + cls.substr(4);
                        }
                        break;
                }

            });

            const okBtnClass = `btn ${btnSize} ${okBtnType}`;
            const cancelBtnClass = `btn ${btnSize} ${cancelBtnType}`;

            const onClose = function onClose(e, ok) {
                console.log("Modal closed", ok);
                this.state_$.showModal = false;
                this.state_$.save();
                if (ok) {
                    doUpdate();
                } else {
                    this.inButtonOp = false;
                }
            }.bind(this);

            this.state_$.showModal = true;
            this.state_$.modalProps = {
                modalTitle: t('messages.' + confirmationDashCase + "-title"),
                modalType: '',
                onClose: onClose,
                footer: (
                    <ConfirmationButtons
                        onNeverShow={forcedConfirmationCheck === undefined ? confirmationKey : null}
                        okText={t('messages.' + confirmationDashCase.substring("confirm-".length))}
                        okButtonType={okBtnClass}
                        neverShowButtonType={okBtnClass}
                        cancelButtonType={cancelBtnClass}
                        onClose={onClose}
                    />
                ),
            };
            this.state_$.modalBody = (
                <div>
                    <p>{t('messages.' + confirmationDashCase + "-message")}</p>
                    {confirmationExtra}
                </div>
            );

            this.state_$.save();
        } else {
            doUpdate();
        }
    }
}

DashboardComponent.propTypes = {
    dashboardName: PropTypes.string,  // get config from dashboards config
    routeSettings: PropTypes.string, // get settings from url specific settings 
    noHide: PropTypes.bool,         // if set close is not shown
};

export default DashboardComponent;

