import React from "react";
import PropTypes from 'prop-types';
import $ from "jquery";
import languageSynchronizer from "../helpers/LanguageSynchronizer";
import { isFunction } from "../helpers/helpers";

class Dashboard extends React.Component {
    constructor(props) {
        super(props);

        this.state = {};
        this.handleReload = this.handleReload.bind(this);
        this.handleCollapse = this.handleCollapse.bind(this);
        this.handleHide = this.handleHide.bind(this);
        this.handleExtras = this.handleExtras.bind(this);
        this.collapseUpdateTimer = null;
        this.oldScriptHookerId = null;
    }

    onCollapseFire(collapsed) {
        isFunction(this.props.onCollapse) && this.props.onCollapse(collapsed);
    }

    componentDidMount() {
        this.$el = $(this.el);
        this.$btn = $(this.btn);
        this.$el.collapse({ toggle: false });

        this.$el.on('shown.bs.collapse', () => {
            this.onCollapseFire(false);
        });

        this.$el.on('hidden.bs.collapse', () => {
            this.onCollapseFire(true);
        });

        this.oldScriptHookerId = languageSynchronizer.hookOnComponentDidMount(this.oldScriptHookerId, this.usesOldScripts);
    }

    componentWillUnmount() {
        this.$el.collapse('dispose');

        this.oldScriptHookerId = languageSynchronizer.hookOnComponentWillUnmount(this.oldScriptHookerId, this.usesOldScripts);
    }

    componentDidUpdate() {
        this.oldScriptHookerId = languageSynchronizer.hookOnComponentDidUpdate(this.oldScriptHookerId, this.props.hookOldScripts);
    }

    handleReload(e) {
        e.preventDefault();
        e.stopPropagation();
        isFunction(this.props.onReload) && this.props.onReload(e);
    }

    handleHide(e) {
        e.preventDefault();
        e.stopPropagation();
        isFunction(this.props.onHide) && this.props.onHide(e);
    }

    handleExtras(e) {
        e.preventDefault();
        e.stopPropagation();
        isFunction(this.props.onExtras) && this.props.onExtras(e);
    }

    handleCollapse(e) {
        e.preventDefault();
        e.stopPropagation();

        if (!this.$el.hasClass('.collapsing')) {
            if (this.props.isCollapsed) {
                this.$btn.addClass('shown');
                this.$el.collapse('show');
            } else {
                this.$btn.removeClass('shown');
                this.$el.collapse('hide');
            }
        }
    }

    render() {
        const { t, isCollapsed, onCollapse, isAltExtras, onReload, onHide, onExtras, extrasContent, extrasAltContent, isLoading, isStaleData, maxHeight } = this.props;

        const cardBody = <div className={"card-body" + (isStaleData ? " stale-data" : "")}>
            <div style={maxHeight === undefined ? {} : { display: "block", width: "100%", maxHeight: maxHeight || "300px", marginBottom: 0, overflow: "auto", }}>
                {this.props.children}
            </div>
        </div>;
        let colorClass = (!isStaleData || isLoading ? "primary" : "secondary");
        let bgColorClass = " bg-" + colorClass;
        return (
            <div className="row">
                <div className="col col-sm-12">
                    <div className={"card border-" + colorClass}>
                        <div className={"card-header text-white" + bgColorClass} onClick={onCollapse ? this.handleCollapse : null}>
                            <div className='row'>
                                <div className="col col-sm-10">
                                    {this.props.headerChildren}
                                </div>
                                {(onReload || onCollapse || onHide || onExtras) && (
                                    <div className="col col-sm-2">
                                        <div className='input-group input-group-heading'>
                                            {onExtras ? <button type='button' className="btn-heading" onClick={this.handleExtras}>{isAltExtras && extrasAltContent ? extrasAltContent : extrasContent || <i className="fas fa fa-tasks"/>}</button> : null}
                                            {onHide ? <button type='button' className="btn-heading" onClick={this.handleHide}><i className="fas fa fa-times"/></button> : null}
                                            {onReload ? <button type='button' className={"btn-heading" + (isLoading ? " loading" : "") + (isStaleData ? " stale-data" : "")} onClick={this.handleReload}><i className="fas fa fa-sync-alt"/></button> : null}
                                            {onCollapse ? <button ref={btn => this.btn = btn} className={"btn-heading btn-collapse" + (isCollapsed ? "" : " shown")} type="button" aria-expanded="false" aria-controls="dashboard" onClick={this.handleCollapse}/> : null}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                        {onCollapse ? (
                                <div ref={el => this.el = el} className={"collapse" + (isCollapsed ? "" : " show")}>
                                    {cardBody}
                                </div>
                            ) :
                            cardBody
                        }
                    </div>
                </div>
            </div>
        );
    }
}

Dashboard.propTypes = {
    onReload: PropTypes.func,
    onHide: PropTypes.any,
    onExtras: PropTypes.any,
    isAltExtras: PropTypes.bool,
    extrasContent: PropTypes.element,
    extrasAltContent: PropTypes.element,
    onCollapse: PropTypes.any,
    isCollapsed: PropTypes.bool,
    hookOldScripts: PropTypes.bool,
    isLoading: PropTypes.bool,
    isStaleData: PropTypes.bool,
    headerChildren: PropTypes.any.isRequired,
    maxHeight: PropTypes.string,
};

export default Dashboard;

