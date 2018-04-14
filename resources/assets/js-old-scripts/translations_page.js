/*jshint browser: true, jquery: true*/

let onUnhookFuncts = [];

function unhookTranslationPage() {
    const iMax = onUnhookFuncts.length;
    for (let i = 0; i < iMax; i++) {
        try {
            onUnhookFuncts[i].call();
        } catch (e) {
            window.console.error('unhookTranslationPage for ', onUnhookFuncts[i], e)
        }
    }
    onUnhookFuncts = [];
}

(function ($) {
    $.fn.OldScriptHooks.UNHOOK_TRANSLATION_PAGE_EVENTS = unhookTranslationPage;

    $.fn.OldScriptHooks.HOOKUP_TRANSLATION_PAGE_EVENTS = function () {
        const vars = $.fn.OldScriptHooks;
        const UPDATE_MATCHING_DELAY = 100; // delay before applying filter to translation table rows
        const UPDATE_MATCHING_REGEX_ERROR_DELAY = 1000;  // delay before displaying regex error, give more time to finish typing
        const TRANSLATION_FILTERS_SAVE_DELAY = 500; // delay before sending filter settings to server

        unhookTranslationPage();

        // $('.group-select').on('change', function () {
        //     var group = $(this).val();
        //     if (group) {
        //         window.location.href = vars.URL_TRANSLATOR_GROUP + $(this).val();
        //     } else {
        //         window.location.href = vars.URL_TRANSLATOR_ALL;
        //     }
        // });

        let $a_delete_key = $('a.delete-key');
        onUnhookFuncts.push(() => $a_delete_key.off('ajax:success'));
        $a_delete_key.on('ajax:success', function (e, data) {
            var row = $(this).closest('tr');
            row.addClass("deleted-translation");

            $(this).addClass("hidden");
            row.find("a.undelete-key").first().removeClass("hidden");
        });

        let $a_undelete_key = $('a.undelete-key');
        onUnhookFuncts.push(() => $a_undelete_key.off('ajax:success'));
        $a_undelete_key.on('ajax:success', function (e, data) {
            var row = $(this).closest('tr');
            row.removeClass("deleted-translation");

            $(this).addClass("hidden");
            row.find("a.delete-key").first().removeClass("hidden");
        });

        let $form_keyops = $('#form-keyops');
        onUnhookFuncts.push(() => $form_keyops.off('ajax:success'));
        $form_keyops.on('ajax:success', function (e, data) {
            var elem = $('#wildcard-keyops-results').first();
            elem.html(data);
            elem.find('.vsch_editable').vsch_editable();
        });

        let $translate_current_primary = $('#translate-current-primary');
        onUnhookFuncts.push(() => $translate_current_primary.off('click'));
        $translate_current_primary.on('click', function () {
            var elemFrom = $('#current-text').first(),
                fromText = elemFrom[0].value;
            vars.xtranslateService(vars.TRANSLATING_LOCALE, fromText, vars.PRIMARY_LOCALE, function (text) {
                var elem = $('#primary-text').first();
                if (elem.length) {
                    elem.val(text);
                    // save it
                    if (vars.GLOBAL_SETTINGS_CHANGED) {
                        vars.GLOBAL_SETTINGS_CHANGED({ uiSettings: { yandexPrimaryText: text } });
                    }
                }
            });
        });

        let $translate_primary_current = $('#translate-primary-current');
        onUnhookFuncts.push(() => $translate_primary_current.off('click'));
        $translate_primary_current.on('click', function () {
            var elemFrom = $('#primary-text').first(),
                fromText = elemFrom[0].value;
            vars.xtranslateService(vars.PRIMARY_LOCALE, fromText, vars.TRANSLATING_LOCALE, function (text) {
                var elem = $('#current-text').first();
                if (elem.length) {
                    elem.val(text);
                    // save it
                    if (vars.GLOBAL_SETTINGS_CHANGED) {
                        vars.GLOBAL_SETTINGS_CHANGED({ uiSettings: { yandexTranslatingText: text } });
                    }
                }
            });
        });

        function showMatched(table, matched) {
            table.find('tr').each(function () {
                if (!$(this).hasClass('hidden')) {
                    var key = $(this).find('td.key').first();
                    if (key.length > 0) {
                        var text = key[0].innerText;
                        if (!matched.exec(text)) {
                            $(this).addClass('hidden');
                        }
                    }
                }
            });
        }

        function showAll(table) {
            table.find('tr').removeClass('hidden');
        }

        var updateTranslationList = showAll;
        var updateTranslationFilter = 'show-all';
        var filterUpdateTimer = null;
        var filterRegexErrorTimer = null;

        onUnhookFuncts.push(() => {
            filterUpdateTimer && window.clearTimeout(filterUpdateTimer);
            filterRegexErrorTimer && window.clearTimeout(filterRegexErrorTimer);
        });

        function saveTransFilters(data) {
            if (filterUpdateTimer) {
                window.clearTimeout(filterUpdateTimer);
                filterUpdateTimer = null;
            }

            vars.TRANS_FILTERS = data;

            filterUpdateTimer = window.setTimeout(() => {
                filterUpdateTimer = null;
                // CANNOT DO THIS, it causes popup to close. Only use when want to change settings that affect the react UI.
                // if (vars.GLOBAL_SETTINGS_CHANGED) {
                //     vars.TRANS_FILTERS = data;
                //     vars.GLOBAL_SETTINGS_CHANGED({transFilters: data});
                // } else {
                var jqxhr = $.ajax({
                    type: 'POST',
                    url: vars.URL_TRANSLATOR_FILTERS,
                    data: data,

                    success: function (json) {
                        if (json.status === 'ok') {
                        }
                    },
                    encode: true
                });
                // }
            }, TRANSLATION_FILTERS_SAVE_DELAY);
        }

        function updateMatching(elem) {
            var table = $('#translations').find('tbody').first(),
                matchedText = $('#show-matching-text'),
                matchedTextLabel = $('#show-matching-text-label'),
                matched, totalKeys, filteredKeys, matchedKeys, keyFilterSpan, pattern = '', elemName;

            elem = $(elem);
            if (elem.length > 0) {
                updateTranslationFilter = elem.prop('id');
            }

            totalKeys = table.find('tr').length;
            updateTranslationList(table);
            matchedKeys = filteredKeys = totalKeys - table.find('tr.hidden').length;

            if (elem.length > 0) {
                var elemDiv = elem.closest('div');

                $('.translation-filter').removeClass('has-success has-error has-feedback has-highlight');
                if (updateTranslationFilter !== 'show-all') {
                    if (filteredKeys > 0) {
                        // turn the filter green
                        if (filteredKeys === totalKeys) {
                            elemDiv.addClass('has-feedback');
                        } else {
                            elemDiv.addClass('has-highlight');
                        }
                    } else {
                        // turn the filter red
                        elemDiv.addClass('has-error');
                    }
                }
            }

            if (matchedText.length === 0) {
                matchedTextLabel.html("&nbsp;");
                matchedTextLabel.addClass("hidden");
            } else {
                pattern = matchedText[0].value.trim();
                var regexError = false;

                if (filterRegexErrorTimer) {
                    window.clearTimeout(filterRegexErrorTimer);
                    filterRegexErrorTimer = null;
                }

                try {
                    matched = new RegExp(pattern, 'i');
                    matchedTextLabel.html("&nbsp;");
                    matchedTextLabel.addClass("hidden");
                }
                catch (e) {
                    matched = new RegExp("");
                    matchedTextLabel.text(e.message);
                    // matchedTextLabel.removeClass('hidden');
                    regexError = true;
                }

                showMatched(table, matched);

                matchedKeys = totalKeys - table.find('tr.hidden').length;
                var matchedTextParent = matchedText.parent('div');

                matchedTextParent.removeClass('has-error has-success has-feedback has-highlight');
                matchedText.removeClass('bg-danger bg-success bg-highlight regex-error');
                if (regexError) {
                    matchedTextParent.addClass('has-error');
                    matchedText.addClass('regex-error');
                    filterRegexErrorTimer = window.setTimeout(() => {
                        filterRegexErrorTimer = null;
                        matchedTextLabel.removeClass("hidden");
                    }, UPDATE_MATCHING_REGEX_ERROR_DELAY);
                } else {
                    if (pattern.length > 0 && filteredKeys > 0) {
                        if (matchedKeys === 0) {
                            matchedTextParent.addClass('has-error');
                            matchedText.addClass('bg-danger');
                        } else {
                            if (matchedKeys < filteredKeys) {
                                matchedTextParent.addClass('has-highlight');
                                matchedText.addClass('bg-highlight');
                            }
                        }
                    }
                }
            }

            const data = { 'filter': updateTranslationFilter, 'regex': pattern };
            saveTransFilters(data);

            keyFilterSpan = $('#key-filter').first();
            if (keyFilterSpan.length > 0) {
                var html = "";
                if (matchedKeys !== filteredKeys) {
                    html += matchedKeys + "/";
                }
                if (filteredKeys !== totalKeys) {
                    html += filteredKeys + "/";
                }

                html += totalKeys;

                keyFilterSpan.removeClass('have-filtered');
                if (matchedKeys !== totalKeys) {
                    keyFilterSpan.addClass('have-filtered');
                }
                keyFilterSpan[0].innerHTML = html;
            }
        }

        let $show_all = $('#show-all');
        onUnhookFuncts.push(() => $show_all.off('click'));
        $show_all.on('click', function (e) {
            //e.preventDefault();
            updateTranslationList = showAll;
            updateMatching(this);
        });

        let $show_new = $('#show-new');
        onUnhookFuncts.push(() => $show_new.off('click'));
        $show_new.on('click', function (e) {
            //e.preventDefault();
            updateTranslationList = function (table) {
                table.find('tr').removeClass('hidden');
                table.find('tr.has-nonempty-translation').addClass('hidden');
            };
            updateMatching(this);
        });

        let $show_need_attention = $('#show-need-attention');
        onUnhookFuncts.push(() => $show_need_attention.off('click'));
        $show_need_attention.on('click', function (e) {
            //e.preventDefault();
            updateTranslationList = function (table) {
                table.find('tr').addClass('hidden');
                table.find('tr.has-empty-translation').removeClass('hidden');
                table.find('tr.deleted-translation').removeClass('hidden');
                table.find('tr.has-changed-translation').removeClass('hidden');
            };
            updateMatching(this);
        });

        let $show_unpublished = $('#show-unpublished');
        onUnhookFuncts.push(() => $show_unpublished.off('click'));
        $show_unpublished.on('click', function (e) {
            //e.preventDefault();
            updateTranslationList = function (table) {
                table.find('tr').addClass('hidden');
                table.find('tr.deleted-translation').removeClass('hidden');
                table.find('tr.has-changed-translation').removeClass('hidden');
            };
            updateMatching(this);
        });

        let $show_empty = $('#show-empty');
        onUnhookFuncts.push(() => $show_empty.off('click'));
        $show_empty.on('click', function (e) {
            //e.preventDefault();
            updateTranslationList = function (table) {
                table.find('tr').addClass('hidden');
                table.find('tr.has-empty-translation').removeClass('hidden');
            };
            updateMatching(this);
        });

        let $show_nonempty = $('#show-nonempty');
        onUnhookFuncts.push(() => $show_nonempty.off('click'));
        $show_nonempty.on('click', function (e) {
            //e.preventDefault();
            updateTranslationList = function (table) {
                table.find('tr').addClass('hidden');
                table.find('tr.has-nonempty-translation').removeClass('hidden');
            };
            updateMatching(this);
        });

        let $show_used = $('#show-used');
        onUnhookFuncts.push(() => $show_used.off('click'));
        $show_used.on('click', function (e) {
            //e.preventDefault();
            updateTranslationList = function (table) {
                table.find('tr').addClass('hidden');
                table.find('tr.has-used-translation').removeClass('hidden');
            };
            updateMatching(this);
        });

        let $show_deleted = $('#show-deleted');
        onUnhookFuncts.push(() => $show_deleted.off('click'));
        $show_deleted.on('click', function (e) {
            //e.preventDefault();
            updateTranslationList = function (table) {
                table.find('tr').addClass('hidden');
                table.find('tr.deleted-translation').removeClass('hidden');
            };
            updateMatching(this);
        });

        let $show_changed = $('#show-changed');
        onUnhookFuncts.push(() => $show_changed.off('click'));
        $show_changed.on('click', function (e) {
            //e.preventDefault();
            updateTranslationList = function (table) {
                table.find('tr').addClass('hidden');
                table.find('tr.has-changed-translation').removeClass('hidden');
            };
            updateMatching(this);
        });

        var updateMatchingTimer = null;
        onUnhookFuncts.push(() => updateMatchingTimer && window.clearTimeout(updateMatchingTimer));

        let $matchingText = $('#show-matching-text');
        onUnhookFuncts.push(() => $matchingText.off('keyup change'));
        $matchingText.on('keyup change', function () {
            if (updateMatchingTimer) {
                window.clearTimeout(updateMatchingTimer);
                updateMatchingTimer = null;
            }

            updateMatchingTimer = window.setTimeout(function () {
                updateMatchingTimer = null;
                updateMatching();
            }, UPDATE_MATCHING_DELAY);
        });

        let $show_matching_clear = $('#show-matching-clear');
        onUnhookFuncts.push(() => $show_matching_clear.off('click'));
        $show_matching_clear.on('click', function () {
            if ($matchingText.length) {
                $matchingText[0].value = '';
                $matchingText.focus();
                updateMatching();
            }
        });

        // $('div.alert-dismissible').each(function () {
        //     var elem = $(this), btn = elem.find('button.close').first();
        //     if (btn.length) {
        //         onUnhookFuncts.push(() => btn.off('click'));
        //         btn.on('click', function () {
        //             elem.slideUp();
        //         });
        //     }
        // });

        function postTranslationValues(translations, elemButton, processText) {
            if (!translations.length) {
                return;
            }

            var progTotal, progCurrent, btnText = elemButton.text(), btnAlt = elemButton.data('disable-with');
            progTotal = translations.length;
            progCurrent = 0;

            // we could process all the keys in parallel but we will do it a few at a time
            var fireTranslate, translateNext = function (t) {
                processText(t.srcText, function (text, trans) {
                    if (text !== "") {
                        var jqxhr = $.ajax({
                            type: 'POST',
                            url: t.dataUrl,
                            data: { 'name': t.dataName, 'value': text },
                            success: function (json) {
                                if (json.status === 'ok') {
                                    // now can update the element and fire off the next translation
                                    t.dstElem.removeClass('editable-empty status-0');
                                    t.dstElem.text(text);
                                    t.dstElem.editable('setValue', text, false);
                                    let res = t.dstElem.editable('getValue', true);
                                    // let the proper function handle this
                                    $.fn.vsch_editable.updateTranslationFromXEditable.call(t.dstElem);

                                    // t.dstElem.addClass('status-1');
                                    // t.dstElem.closest('tr').addClass('has-changed-translation');
                                }
                                else {
                                    elemButton.removeAttr('disabled');
                                    elemButton.text(btnText);
                                }
                            },
                            encode: true
                        });

                        jqxhr.done(function () {
                            fireTranslate();
                        });

                        jqxhr.fail(function () {
                            elemButton.removeAttr('disabled');
                            elemButton.text(btnText);
                        });
                    }
                    else {
                        elemButton.removeAttr('disabled');
                        elemButton.text(btnText);
                    }
                });

            };

            fireTranslate = function () {
                if (translations.length) {
                    progCurrent++;
                    translateNext(translations.pop());
                    elemButton.attr('disabled', 'disabled');
                    elemButton.text(btnAlt + ' ' + progCurrent + ' / ' + progTotal);
                }
                else {
                    elemButton.removeAttr('disabled');
                    elemButton.text(btnText);
                }
            };

            for (var i = 0; i < 5; i++) {
                fireTranslate();
            }
        }

        var elemAutoTrans = $('.btn.auto-translate');
        elemAutoTrans.each(function () {
            var colNum = $(this).data('trans');
            var dstLoc = $(this).data('locale');

            var btnElem = $(this);
            onUnhookFuncts.push(() => btnElem.off('click'));
            btnElem.on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var autoTranslate = [];

                // step through all the definitions in the second column and auto translate empty ones
                // here we make a log of assumptions about where the data is.
                // we assume that the source is the child element immediately preceding this one and it is a <td> containing
                // <a> containing the source text
                $(".auto-translatable-" + dstLoc).each(function () {
                    var row = $(this).parent().find('.vsch_editable');
                    if (row.length > 1) {
                        var srcElem = $(row[0]),
                            dstElem = $(row[colNum]),
                            tr = row.closest('tr');

                        if (!tr.hasClass('hidden') && dstElem.length && srcElem.length) {
                            if (dstElem.hasClass('editable-empty') && !srcElem.hasClass('editable-empty')) {
                                autoTranslate.push({
                                    srcText: srcElem.text(),
                                    dataUrl: dstElem.data('url'),
                                    dataName: dstElem.data('name'),
                                    dstElem: dstElem
                                });
                            }
                        }
                    }
                });

                (function (fromLoc, toLoc, btnElem) {
                    postTranslationValues(autoTranslate, btnElem, function (text, storeText) {
                        vars.xtranslateText(vars.xtranslateService, fromLoc, text, toLoc, function (text, trans) {
                            storeText(text, trans);
                        });
                    });
                })(vars.PRIMARY_LOCALE, dstLoc, btnElem);
            });
        });

        var elemAutoFill = $('#auto-fill');
        onUnhookFuncts.push(() => elemAutoFill.off('click'));
        elemAutoFill.on('click', function (e) {
            var autoFill = [];

            e.preventDefault();
            e.stopPropagation();

            // step through all the definitions in the second column and auto translate empty ones
            // here we make a log of assumptons about where the data is.
            // we assume that the source is the child element immediately preceeding this one and it is a <td> containing
            // <a> containing the source text
            $(".auto-fillable").each(function () {
                var dstElem = $(this).find("a.vsch_editable.editable-empty"),
                    tr = dstElem.closest('tr');

                if (dstElem.length && !tr.hasClass('hidden')) {
                    autoFill.push({
                        srcText: dstElem.data('name').substr(vars.PRIMARY_LOCALE.length + 1),
                        dataUrl: dstElem.data('url'),
                        dataName: dstElem.data('name'),
                        dstElem: dstElem
                    });
                }
            });

            (function (elemButton) {
                postTranslationValues(autoFill, elemButton, function (text, storeText) {
                    var regexnodash = /^.*\.|-|_/g,
                        value = text.replace(regexnodash, ' ').toCapitalCase().trim();

                    storeText(value, '');
                });
            })(elemAutoFill);
        });

        function simulateClick($el) {
            if ($el.length) {
                var event = new MouseEvent('click', {
                    view: window,
                    bubbles: true,
                    cancelable: true
                });
                var cancelled = !$el[0].dispatchEvent(event);
                // if (cancelled) {
                //     // A handler called preventDefault.
                //     alert("cancelled");
                // } else {
                //     // None of the handlers called preventDefault.
                //     alert("not cancelled");
                // }
            }
        }

        let $auto_delete_key = $('.auto-delete-key');
        onUnhookFuncts.push(() => $auto_delete_key.off('click'));
        $auto_delete_key.on('click', function (e) {
            e.preventDefault();

            var table = $('#translations').find('tbody').first(),
                keys = table.find('.delete-key');

            keys.each(function () {
                var row = $(this).closest('tr');
                if (!row.hasClass('hidden') && !$(this).hasClass('hidden')) {
                    // $(this).trigger('click');
                    simulateClick($(this));
                }
            });
        });

        let $auto_undelete_key = $('.auto-undelete-key');
        onUnhookFuncts.push(() => $auto_undelete_key.off('click'));
        $auto_undelete_key.on('click', function (e) {
            e.preventDefault();

            var table = $('#translations').find('tbody').first(),
                keys = table.find('.undelete-key');

            keys.each(function () {
                var row = $(this).closest('tr');
                if (!row.hasClass('hidden') && !$(this).hasClass('hidden')) {
                    // $(this).trigger('click');
                    simulateClick($(this));
                }
            });
        });

        let $a_show_source_refs = $('a.show-source-refs');
        onUnhookFuncts.push(() => $a_show_source_refs.off('ajax:success'));
        $a_show_source_refs.on('ajax:success', function (e, data) {
            var elemModal = $('#sourceRefsModal').first();
            var elemHeader = elemModal.find('#key-name').first();
            elemHeader.text(data.key_name);
            var elemResults = elemModal.find('.results').first();
            var result = data.result.join("<br>");
            elemResults.html(result);
            elemModal.modal('show');
        });

        var elemAutoPropCase = $('.btn.auto-prop-case');
        elemAutoPropCase.each(function () {
            var colNum = $(this).data('trans');
            var dstLoc = $(this).data('locale');

            var btnElem = $(this);
            onUnhookFuncts.push(() => btnElem.off('click'));
            btnElem.on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var autoPropCase = [],
                    regex = XRegExp("^(\\p{Alphabetic}|\\p{Number})+(\\p{Space_Separator}+(\\p{Alphabetic}|\\p{Number})+)+\\.?$");

                // step through all the definitions in the second column and auto translate empty ones
                // here we make a log of assumptions about where the data is.
                // we assume that the source is the child element immediately preceding this one and it is a <td> containing
                // <a> containing the source text
                $(".auto-translatable-" + dstLoc).each(function () {
                    var row = $(this).closest('tr');
                    if (!row.hasClass('hidden')) {
                        var editable = $(this).parent().find('.vsch_editable');
                        // exclude filtered out rows, give a bit of control over what will change
                        if (editable.length > 1) {
                            var srcElem = $(editable[0]),
                                dstElem = $(editable[colNum]);

                            if (dstElem.length) {
                                if (!dstElem.hasClass('editable-empty')) {
                                    var text = dstElem.text();
                                    var simpleWords = regex.test(text);
                                    window.console.log(text + " simple " + simpleWords);
                                    if (text !== text.toLocaleProperCaseOrLowerCase() && simpleWords) {
                                        autoPropCase.push({
                                            srcText: dstElem.text(),
                                            dataUrl: dstElem.data('url'),
                                            dataName: dstElem.data('name'),
                                            dstElem: dstElem
                                        });
                                    }
                                }
                            }
                        }
                    }
                });

                if (autoPropCase.length > 0) {
                    (function (fromLoc, toLoc, btnElem) {
                        postTranslationValues(autoPropCase, btnElem, function (text, storeText) {
                            storeText(text.toLocaleProperCaseOrLowerCase(), '');
                        });
                    })(dstLoc, dstLoc, btnElem);
                }
            });
        });

        function textareaTandemResize(src, dst, liveupdate) {
            return function () {
                var srcTimeout = { id: null },
                    dstTimeout = { id: null };

                // the handler function
                var resizeEvent = function (src, dst, timeout) {
                    return function () {
                        if (dst.css("resize") === 'both') {
                            dst.outerWidth(src.outerWidth());
                            dst.outerHeight(src.outerHeight());
                        }
                        else {
                            if (dst.css("resize") === 'horizontal') {
                                dst.outerWidth(src.outerWidth());
                            }
                            else {
                                if (dst.css("resize") === 'vertical') {
                                    dst.outerHeight(src.outerHeight());
                                }
                            }
                        }

                        if (timeout.id) {
                            clearTimeout(timeout.id);
                            timeout.id = null;
                        }
                    };
                };

                // This provides a "real-time" (actually 15 fps)
                // event, while resizing.
                // Unfortunately, mousedown is not fired on Chrome when
                // clicking on the resize area, so the real-time effect
                // does not work under Chrome.
                if (liveupdate) {
                    onUnhookFuncts.push(() => $(window).off("mousemove"));
                    $(window).on("mousemove", function (e) {
                        if (e.target === src[0]) {
                            if (srcTimeout.id) {
                                clearTimeout(srcTimeout.id);
                                srcTimeout.id = null;
                            }
                            srcTimeout.id = setTimeout(resizeEvent(src, dst, srcTimeout), 1000 / 30);
                        }
                        else {
                            if (e.target === dst[0]) {
                                if (dstTimeout.id) {
                                    clearTimeout(dstTimeout.id);
                                    dstTimeout.id = null;
                                }
                                dstTimeout.id = setTimeout(resizeEvent(dst, src, dstTimeout), 1000 / 30);
                            }
                        }
                    });
                }

                // The mouseup event stops the interval,
                // then call the resize event one last time.
                // We listen for the whole window because in some cases,
                // the mouse pointer may be on the outside of the textarea.
                onUnhookFuncts.push(() => $(window).off("mouseup"));
                $(window).on("mouseup", function (e) {
                    if (srcTimeout.id !== null) {
                        clearTimeout(srcTimeout.id);
                        srcTimeout.id = null;
                    }
                    if (e.target === src[0]) {
                        resizeEvent(src, dst, srcTimeout)();
                    }
                    else {
                        if (e.target === dst[0]) {
                            resizeEvent(dst, src, dstTimeout)();
                        }
                    }
                });
            };
        }

        textareaTandemResize($("#keyop-keys"), $("#keyop-suffixes"), true)();
        textareaTandemResize($("#primary-text"), $("#current-text"), true)();
        textareaTandemResize($("#srckeys"), $("#dstkeys"), true)();

        if (vars.TRANS_FILTERS && vars.CURRENT_GROUP !== '') {
            var filter = vars.TRANS_FILTERS.filter;
            var regex = vars.TRANS_FILTERS.regex;
            var elemRadio = $('#' + filter);
            elemRadio.prop('checked', true);

            if ($matchingText.length > 0) {
                // if (filter !== 'show-all' || $matchingText[0].value !== regex) {
                $matchingText[0].value = regex;
                simulateClick(elemRadio);
                // elemRadio.trigger('click');
                // }
            }
        }
    };
}(window.jQuery));
