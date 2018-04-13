import React from "react";
import { isArray, isString } from "../helpers/helpers";

// import 'bootstrap-editable';

class XEditable extends React.Component {
    constructor(props) {
        super(props);
    }

    componentDidMount() {
        this.$el = $(this.el);
        let defaults = this.props['defaultOptions'] || {};
        this.$el.editable(defaults);

        if (defaults.hasOwnProperty('editableform') && defaults.editableform.hasOwnProperty('template')) {
            $.fn.editableform.template = defaults.editableform.template;
        }
    }

    componentDidUpdate() {
        let text = this.$el.editable('getValue', true);
        // let propText = this.props['data-value'];
        //
        // if (text !== propText) {
        //     // if updating table from empty to non-empty, need to 
        //     // update editable value otherwise it will think the text is empty
        //     // this.$el.editable('setValue', propText, true);
        // }

        // when it is empty, need to reattach the editable so it sets the .editable-empty class
        // otherwise when empty is published it turns non-empty color. 
        if (isArray(text) && text.join('') === '' || isString(text) && text === '') {
            this.$el.editable('destroy');
        }
        this.$el = $(this.el);
        let defaults = this.props['defaultOptions'] || {};
        this.$el.editable(defaults);

        if (defaults.hasOwnProperty('editableform') && defaults.editableform.hasOwnProperty('template')) {
            $.fn.editableform.template = defaults.editableform.template;
        }
        // }
    }

    componentWillUnmount() {
        this.$el.editable('destroy');
    }

    render() {
        const opts = Object.assign({}, this.props);
        delete opts['defaultOptions'];

        return (
            <div>
                <a href='#' ref={el => this.el = el} {...opts}>{this.props.children || ''}</a>
            </div>
        );
    }
}

export default XEditable;
