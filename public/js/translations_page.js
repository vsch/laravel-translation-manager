/*jshint browser: true, jquery: true*/
/**
 * Created by vlad on 15-02-10.
 */
var CLIP_TEXT; // we store translation copy/paste here
var YANDEX_TRANSLATOR_KEY;
var URL_YANDEX_TRANSLATOR_KEY;
var PRIMARY_LOCALE;
var CURRENT_LOCALE;
var TRANSLATING_LOCALE;
var URL_TRANSLATOR_GROUP;
var URL_TRANSLATOR_ALL;
var xtranslateText;
var xtranslateService;

jQuery(document).ready(function ($) {
    $.ajaxPrefilter(function (options) {
        if (!options.crossDomain) {
            options.headers = {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            };
            //window.console.log('Injected CSRF: ' + $('meta[name="csrf-token"]').attr('content'));
        }
    });

    $('.group-select').on('change', function () {
        var group = $(this).val();
        if (group) {
            window.location.href = URL_TRANSLATOR_GROUP + $(this).val();
        } else {
            window.location.href = URL_TRANSLATOR_ALL;
        }
    });

    $.ajax({
        type: 'POST',
        url: URL_YANDEX_TRANSLATOR_KEY,
        data: {},
        success: function (json) {
            if (json.status === 'ok') {
                YANDEX_TRANSLATOR_KEY = json.yandex_key;
            }
        },
        encode: true
    });

    $('a.delete-key').on('ajax:success', function (e, data) {
        var row = $(this).closest('tr');
        row.addClass("deleted-translation");

        $(this).addClass("hidden");
        row.find("a.undelete-key").first().removeClass("hidden");
    });

    $('a.undelete-key').on('ajax:success', function (e, data) {
        var row = $(this).closest('tr');
        row.removeClass("deleted-translation");

        $(this).addClass("hidden");
        row.find("a.delete-key").first().removeClass("hidden");
    });

    var successReporter = function (suffix) {
        var elem = $('div.success-' + suffix);
        var originalHtml = elem.html();
        $('.form-' + suffix).on('ajax:success', function (e, data) {
            elem.html(originalHtml.replace(/:count\b/, data.counter));
            elem.closest('div.alert').slideDown();
        });
    };

    successReporter('import-group');
    //$('.form-import-group').on('ajax:success', function (e, data) {
    //    var elem = $('div.success-import-group');
    //    elem.html(elem.html().replace(/:count\b/, data.counter));
    //    elem.closest('div.alert').slideDown();
    //});

    successReporter('import-all');
    //$('.form-import-all').on('ajax:success', function (e, data) {
    //    var elem = $('div.success-import-all');
    //    elem.html(elem.html().replace(/:count\b/, data.counter));
    //    elem.closest('div.alert').slideDown();
    //});

    successReporter('find');
    //$('.form-find').on('ajax:success', function (e, data) {
    //    var elem = $('div.success-find');
    //    elem.html(elem.html().replace(/:count\b/, data.counter));
    //    elem.closest('div.alert').slideDown();
    //});

    $('.form-publish-group').on('ajax:success', function (e, data) {
        if (data.status === 'errors') {
            var elem = $('div.errors-alert'),
                errors = data.errors;
            elem.html("<p>"+errors.join("</p>\n<p>") + "</p>\n");
            elem.closest('div.alert').slideDown();
        }
        else {
            $('div.success-publish').closest('div.alert').slideDown();
            $('tr.deleted-translation').remove();
        }
    });

    $('.form-publish-all').on('ajax:success', function (e, data) {
        if (data.status === 'errors') {
            var elem = $('div.errors-alert'),
                errors = data.errors;
            elem.html("<p>"+errors.join("</p>\n<p>") + "</p>\n");
            elem.closest('div.alert').slideDown();
        }
        else {
            $('div.success-publish-all').closest('div.alert').slideDown();
            $('tr.deleted-translation').remove();
        }
    });

    $('#form-keyops').on('ajax:success', function (e, data) {
        //                var elemModal = $('#keyOpModal').first();
        //                var elem = elemModal.find('.results');
        var elem = $('#wildcard-keyops-results').first();
        elem.html(data);
        //                elemModal.modal({show: true, keyboard: true/*, backdrop: 'static'*/});
        elem.find('.vsch_editable').vsch_editable();
    });

    var elemModal = $('#searchModal').first();
    elemModal.on('shown.bs.modal', function (event, data, status, xhr) {
        elemModal.find('#search-form-text').first().focus();
    });

    elemModal.on('ajax:success', function (event, data, status, xhr) {
        var elem = elemModal.find('.results');
        elem.html(data);
        elem.find('.vsch_editable').vsch_editable();
    });

    $('#translate-current-primary').on('click', function () {
        var elemFrom = $('#current-text').first(),
            fromText = elemFrom[0].value;
        xtranslateService(TRANSLATING_LOCALE, fromText, PRIMARY_LOCALE, function (text) {
            var elem = $('#primary-text').first();
            if (elem.length) {
                elem.val(text);
            }
        });
    });

    $('#translate-primary-current').on('click', function () {
        var elemFrom = $('#primary-text').first(),
            fromText = elemFrom[0].value;
        xtranslateService(PRIMARY_LOCALE, fromText, TRANSLATING_LOCALE, function (text) {
            var elem = $('#current-text').first();
            if (elem.length) {
                elem.val(text);
            }
        });
    });

    $('#db-connection').on('change', function () {
        $('#form-interface-locale')[0].submit();
    });

    $('#interface-locale').on('change', function () {
        $('#form-interface-locale')[0].submit();
    });

    $('#translating-locale').on('change', function () {
        $('#form-interface-locale')[0].submit();
    });

    $('#primary-locale').on('change', function () {
        $('#form-interface-locale')[0].submit();
    });

    $('#display-locale-all').on('click', function (e) {
        e.preventDefault();
        $('.display-locale').prop('checked', true);
    });

    $('#display-locale-none').on('click', function (e) {
        e.preventDefault();
        $('.display-locale').prop('checked', false);
    });

    $('#show-all').on('click', function (e) {
        //e.preventDefault();
        var table = $('#translations');
        table.find('tr').removeClass('hidden');
    });

    $('#show-empty').on('click', function (e) {
        //e.preventDefault();
        var table = $('#translations').find('tbody').first();
        table.find('tr').addClass('hidden');
        table.find('tr.has-empty-translation').removeClass('hidden');
    });

    $('#show-nonempty').on('click', function (e) {
        //e.preventDefault();
        var table = $('#translations').find('tbody').first();
        table.find('tr').addClass('hidden');
        table.find('tr.has-nonempty-translation').removeClass('hidden');
    });

    $('#show-deleted').on('click', function (e) {
        //e.preventDefault();
        var table = $('#translations').find('tbody').first();
        table.find('tr').addClass('hidden');
        table.find('tr.deleted-translation').removeClass('hidden');
    });

    $('#show-changed').on('click', function (e) {
        //e.preventDefault();
        var table = $('#translations').find('tbody').first();
        table.find('tr').addClass('hidden');
        table.find('tr.has-changed-translation').removeClass('hidden');
    });

    $('div.alert-hideable').each(function () {
        var elem = $(this), btn = elem.find('button.close').first();
        if (btn.length) {
            btn.on('click', function () {
                elem.slideUp();
            });
        }
    });

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
                        data: {'name': t.dataName, 'value': text},
                        success: function (json) {
                            if (json.status === 'ok') {
                                // now can update the element and fire off the next translation
                                t.dstElem.removeClass('editable-empty status-0');
                                t.dstElem.addClass('status-1');
                                t.dstElem.text(text);
                                t.dstElem.editable('setValue', text, false);
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

    var elemAutoTrans = $('#auto-translate');
    elemAutoTrans.on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var autoTranslate = [];

        // step through all the definitions in the second column and auto translate empty ones
        // here we make a log of assumptons about where the data is.
        // we assume that the source is the child element immediately preceeding this one and it is a <td> containing
        // <a> containing the source text
        $(".auto-translatable").each(function () {
            var row = $(this).parent().find('.vsch_editable');
            if (row.length > 1) {
                var srcElem = $(row[0]),
                    dstElem = $(row[1]);

                if (dstElem.length && srcElem.length) {
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

        (function (fromLoc, toLoc, elemButton) {
            postTranslationValues(autoTranslate, elemButton, function (text, storeText) {
                xtranslateText(xtranslateService, fromLoc, text, toLoc, storeText);
            });
        })(PRIMARY_LOCALE, TRANSLATING_LOCALE, elemAutoTrans);
    });

    var elemAutoFill = $('#auto-fill');
    elemAutoFill.on('click', function (e) {
        var autoFill = [];

        e.preventDefault();
        e.stopPropagation();

        // step through all the definitions in the second column and auto translate empty ones
        // here we make a log of assumptons about where the data is.
        // we assume that the source is the child element immediately preceeding this one and it is a <td> containing
        // <a> containing the source text
        $(".auto-fillable").each(function () {
            var dstElem = $(this).find("a.vsch_editable.editable-empty");

            if (dstElem.length) {
                autoFill.push({
                    srcText: dstElem.data('name').substr(PRIMARY_LOCALE.length + 1),
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

    function textareaTandemResize(src, dst, liveupdate) {
        return function () {
            var srcTimeout = {id: null},
                dstTimeout = {id: null};

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

    var elem = $("#form-addkeys").first();
    textareaTandemResize(elem.find("textarea[name=keys]"), elem.find("textarea[name=suffixes]"), true)();
    textareaTandemResize($("#primary-text"), $("#current-text"), true)();
    textareaTandemResize($("#srckeys"), $("#dstkeys"), true)();
});
