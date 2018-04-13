import React from "react";
import $ from "jquery";
import i18n from '../helpers/I18n';

class TransTableFilter extends React.Component {
    constructor(props) {
        super(props);
    }

    componentDidMount() {
        this.$el = $(this.el);
    }

    componentWillUnmount() {

    }

    render() {
        return (
            <div ref={el => this.el = el}>
                <div className="row">
                    <div className="col-sm-12 ">
                        <label id="show-matching-text-label" className="regex-error hidden"/>
                    </div>
                </div>
                <div className="row">
                    <div className="col-sm-12 ">
                        <div className="form-inline mb-3">
                            <div className="input-group input-group-sm">
                                <input className="form-control form-control-sm" style={{ width: "200px" }} id="show-matching-text" type="text" placeholder={i18n.t('messages.show-matching-text')}/>
                                <div className="input-group-append">
                                    <button type="button" className="btn btn-outline-secondary" id="show-matching-clear">&times;</button>
                                </div>
                            </div>
                            <div className="translation-filter form-control-sm form-check form-check-inline ml-2">
                                <label className="form-check-label"><input id="show-all" className='form-check-input' type="radio" name="show-options" value="show-all"/>{i18n.t("messages.show-all")}</label>
                            </div>
                            <div className="translation-filter form-control-sm form-check form-check-inline">
                                <label className="form-check-label"><input id="show-new" className='form-check-input' type="radio" name="show-options" value="show-new"/>{i18n.t("messages.show-new")}</label>
                            </div>
                            <div className="translation-filter form-control-sm form-check form-check-inline">
                                <label className="form-check-label"><input id="show-need-attention" className='form-check-input' type="radio" name="show-options" value="show-need-attention"/>{i18n.t("messages.show-need-attention")}</label>
                            </div>
                            <div className="translation-filter form-control-sm form-check form-check-inline">
                                <label className="form-check-label"><input id="show-nonempty" className='form-check-input' type="radio" name="show-options" value="show-nonempty"/>{i18n.t("messages.show-nonempty")}</label>
                            </div>
                            <div className="translation-filter form-control-sm form-check form-check-inline">
                                <label className="form-check-label"><input id="show-used" className='form-check-input' type="radio" name="show-options" value="show-used"/>{i18n.t("messages.show-used")}</label>
                            </div>
                            <div className="translation-filter form-control-sm form-check form-check-inline">
                                <label className="form-check-label"><input id="show-unpublished" className='form-check-input' type="radio" name="show-options" value="show-unpublished"/>{i18n.t("messages.show-unpublished")}</label>
                            </div>
                            <div className="translation-filter form-control-sm form-check form-check-inline">
                                <label className="form-check-label"><input id="show-empty" className='form-check-input' type="radio" name="show-options" value="show-empty"/>{i18n.t("messages.show-empty")}</label>
                            </div>
                            <div className="translation-filter form-control-sm form-check form-check-inline">
                                <label className="form-check-label"><input id="show-changed" className='form-check-input' type="radio" name="show-options" value="show-changed"/>{i18n.t("messages.show-changed")}</label>
                            </div>
                            <div className="translation-filter form-control-sm form-check form-check-inline">
                                <label className="form-check-label"><input id="show-deleted" className='form-check-input' type="radio" name="show-options" value="show-deleted"/>{i18n.t("messages.show-deleted")}</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }
}

export default TransTableFilter;
