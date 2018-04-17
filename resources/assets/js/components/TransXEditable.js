import React from "react";
import $ from "jquery";
import { absoluteUrlPrefix, apiURL, POST_EDIT_TRANSLATION } from "../helpers/ApiRoutes";

class TransXEditable extends React.Component {
    constructor(props) {
        super(props);
    }

    componentDidMount() {
        this.$el = $(this.el);
        let defaults = this.props['defaultOptions'] || {};
        this.$el.vsch_editable(defaults);

        if (defaults.hasOwnProperty('editableform') && defaults.editableform.hasOwnProperty('template')) {
            $.fn.editableform.template = defaults.editableform.template;
        }
    }

    componentWillUnmount() {
        this.$el.vsch_editable('destroy');
    }

    componentDidUpdate() {
        let text = this.$el.editable('getValue', true);
        let propText = this.props.children ? this.props.children.trim() : '';

        if (text !== propText) {
            // if updating table from empty to non-empty, need to 
            // update editable value otherwise it will think the text is empty
            this.$el.editable('setValue', propText, true);
        }

        // when it is empty, need to reattach the editable so it sets the .editable-empty class
        // otherwise when empty is published it turns non-empty color. 
        if (propText === '') {
            this.$el.vsch_editable('destroy');
            this.$el = $(this.el);
            let defaults = this.props['defaultOptions'] || {};
            this.$el.vsch_editable(defaults);
        }
    }

    render() {
        const opts = { ...this.props };
        delete opts['defaultOptions'];

        if (this.props.id === 'ru-new-key-json') {
            const tmp = 0;
        }

        return (
            <a href='#' ref={el => this.el = el} {...opts}>{this.props.children}</a>
        );
    }

    static htmlEncode(value) {
        return $('<div/>').text(value).html();
    }

    static transXEditLink(group, key, locale, $t, $withDiff) {
        // TODO: convert to proper attributes for translation edit popup
        let url = apiURL(absoluteUrlPrefix(), POST_EDIT_TRANSLATION, group);

        if (group === 'JSON' && key === 'new-key-json' && locale === 'ru') {
            const tmp = 0;
        }

        if ($t) {
            let diff = $t.diff ? <span style={{ display: "inline" }} dangerouslySetInnerHTML={{ __html: " [" + $t.diff + "]" }}/> : null;
            return (
                <span>
            <TransXEditable
                className={"vsch_editable status-" + ($t.status || 0) + ' locale-' + $t.locale/* + ($t.value === null ? ' editable-empty':'')*/}
                // data-placement='bottom'
                data-type="textarea"
                data-pk={$t.id || 0}
                data-url={url}
                data-locale={$t.locale}
                data-name={$t.locale + '|' + $t.key}
                id={$t.locale + "-" + $t.key}
                data-inputclass="editable-input"
                data-saved_value={$t.saved_value}
                data-title={'[' + $t.locale + '] ' + $t.group + '.' + $t.key}
            >
                {$t.value || ''}
            </TransXEditable>
                    {diff}    
            </span>
            );
        } else {
            return (
                <span>
            <TransXEditable
                className={"vsch_editable editable-empty status-" + (0) + ' locale-' + locale}
                // data-placement='bottom'
                data-type="textarea"
                data-pk={0}
                data-url={url}
                data-locale={locale}
                data-name={locale + '|' + key}
                id={locale + "-" + key}
                data-inputclass="editable-input"
                data-saved_value={''}
                data-title={'[' + locale + '] ' + group + '.' + key}
            />
            </span>
            );
        }
    }
}

export default TransXEditable;

