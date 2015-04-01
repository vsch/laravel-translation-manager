@extends('layouts.master')

@section('head')
    <!--<link href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet">-->
    <link href="//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
@stop

@section('content')
    {{--<div style="width: 80%; margin: auto;">--}}
    <div class="col-sm-11 col-sm-offset-1">
        <h1>@lang('laravel-translation-manager::translations.translation-manager')</h1>

        <p>@lang('laravel-translation-manager::translations.export-warning-text')</p>

        <div class="alert alert-success success-import" style="display:none;">
            <p>Done importing, processed <strong class="counter">N</strong> items! Reload this page to refresh the groups! </p>
        </div>
        <div class="alert alert-success success-find" style="display:none;">
            <p>Done searching for translations, found <strong class="counter">N</strong> items!</p>
        </div>
        <div class="alert alert-success success-publish" style="display:none;">
            <p>Done publishing the translations for group '<?= $group ?>'!</p>
        </div>

        <?php if(Session::has('successPublish')) : ?>
        <div class="alert alert-info">
            <?php echo Session::get('successPublish'); ?>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-sm-8">
                @if($deleteEnabled = (Auth::check() && Auth::user()->is_admin))
                    <div class="row">
                        <?php if(!isset($group)) : ?>
                        <div class="col col-sm-12">
                            <form id="form-import" class="form-inline form-import" method="POST" action="<?= action('Barryvdh\TranslationManager\Controller@postImport') ?>"
                                    data-remote="true" role="form">
                                <select name="replace" class="form-control">
                                    <option value="0">Append new translations</option>
                                    <option value="1">Replace existing translations</option>
                                </select>

                                <div class="btn-group">
                                    <button type="submit" form="form-import" class="btn btn-success" data-disable-with="Loading..">Import groups
                                    </button>
                                    <button type="submit" form="form-publish" class="btn btn-warning" data-disable-with="Publishing..">Publish All
                                    </button>
                                    <button type="submit" form="form-find" class="btn btn-info" data-disable-with="Searching..">Find translations in files
                                    </button>
                                </div>
                            </form>
                        </div>
                        <form id="form-publish" class="form-inline form-publish" method="POST" action="<?= action('Barryvdh\TranslationManager\Controller@postPublish', '*') ?>"
                                data-remote="true" role="form"></form>
                        <form id="form-find" class="form-inline form-find" method="POST" action="<?= action('Barryvdh\TranslationManager\Controller@postFind') ?>"
                                data-remote="true" role="form"
                                data-confirm="Are you sure you want to scan you app folder? All found translation keys will be added to the database."></form>
                        <?php endif; ?>
                    </div>

                    <?php if(isset($group)) : ?>
                    <div class="row">
                        <div class="col-sm-12">
                            <form class="form-inline form-publish" method="POST" action="<?= action('Barryvdh\TranslationManager\Controller@postPublish', $group) ?>"
                                    data-remote="true" role="form">
                                <button type="submit" class="btn btn-info" data-disable-with="Publishing..">Publish translations
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                @endif
            </div>
        </div>
        <br>

        <div class="row">
            <div class="col-sm-8">
                @if(!empty($mismatches))
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">@lang('laravel-translation-manager::translations.mismatches')</h3>
                        </div>
                        <div class="panel-body">
                            <table class="table table-condensed translation-stats" style="max-height: 300px; margin-bottom: 0; overflow: auto; display: block;">
                                <thead>
                                    <tr>
                                        <th class="key" width="20%">@lang('laravel-translation-manager::translations.key')</th>
                                        <th class="missing" width="20%" colspan="2">ru</th>
                                        <th class="missing" width="20%">en</th>
                                        <th class="group" width="20%">@lang('laravel-translation-manager::translations.group')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $key = '';
                                    $mismatches[] = null;
                                    $locale = 'ru';
                                    foreach($mismatches as $mismatch)
                                    {
                                    if ($mismatch === null) break;

                                    $borderTop = ' class="no-border-top"';
                                    $keyText = '';
                                    if ($key != $mismatch->key)
                                    {
                                        if ($key !== '')
                                        {
                                            $borderTop = ' class="border-top"';
                                            $keyText = $key;
                                        }
                                        $key = $mismatch->key;
                                    }
                                    $link = action('Barryvdh\TranslationManager\Controller@getIndex', $mismatch->group) . '#' . $mismatch->key;
                                    ?>
                                    <tr{{$borderTop}}>
                                        <td width="20%" class="missing">{{$keyText}}</td>
                                        <td>
                                            <a href="#edit" class="editable status-0 locale-<?= $locale ?>" data-locale="<?= $locale ?>" data-name="<?= $locale . "|" . $key ?>"
                                                    id="username" data-type="textarea" data-pk="<?= $mismatch->id ?>"
                                                    data-url="<?= URL::action('Barryvdh\TranslationManager\Controller@postEdit', array($mismatch->group)) ?> ?>"
                                                    data-inputclass="editable-input"
                                                    data-title="@lang('laravel-translation-manager::translations.enter-translation')"><?= htmlentities($mismatch->ru_value, ENT_QUOTES, 'UTF-8', false) ?></a>
                                        </td>
                                        <td width="20%" class="missing">{{$mismatch->ru}}</td>
                                        <td width="20%" class="missing">{{$mismatch->en}}</td>
                                        <td width="20%" class="group missing"><a href="{{$link}}">{{$mismatch->group}}</a></td>
                                    </tr>
                                    <?php
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
                <form role="form">
                    <div class="form-group">
                        <?php $groups[''] = trans('laravel-translation-manager::translations.choose-group'); ?>
                        <?= Form::select('group', $groups, $group, array('class' => 'form-control group-select')) ?>
                    </div>
                </form>
                <?php if(!$group): ?>
                <div class="col-sm-9">
                    <p>@lang('laravel-translation-manager::translations.choose-group-text')</p>
                </div>
                <div class="col-sm-3">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#searchModal" style="float:right; display:inline">
                        @lang('laravel-translation-manager::translations.search')
                    </button>
                </div>
                <?php endif; ?>

                <?php if($group): ?>
                <form action="<?= action('Barryvdh\TranslationManager\Controller@postAdd', array($group)) ?>" method="POST" role="form">
                    <textarea class="form-control" rows="3" name="keys" placeholder="@lang('laravel-translation-manager::translations.addkeys-placeholder')"></textarea> <br> <input type="submit"
                            value="@lang('laravel-translation-manager::translations.addkeys')" class="btn btn-primary">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#searchModal" style="float:right; display:inline">
                        @lang('laravel-translation-manager::translations.search')
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="col-sm-4">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title">@lang('laravel-translation-manager::translations.stats')</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-condensed translation-stats">
                            <thead>
                                <tr>
                                    <th class="missing" width="35%">@lang('laravel-translation-manager::translations.missing')</th>
                                    <th class="changed" width="25%">@lang('laravel-translation-manager::translations.changed')</th>
                                    <th class="group">@lang('laravel-translation-manager::translations.group')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stats as $stat)
                                    <tr>
                                        <td class="missing">{{$stat->missing ?: '&nbsp;'}}</td>
                                        <td class="changed">{{$stat->changed ?: '&nbsp;'}}</td>
                                        @if ($stat->missing)
                                            <td class="group missing">
                                                <a href="{{action('Barryvdh\TranslationManager\Controller@getIndex', $stat->group)}}">{{$stat->group ?: '&nbsp;'}}</a>
                                            </td>
                                        @elseif ($stat->changed)
                                            <td class="group changed">
                                                <a href="{{action('Barryvdh\TranslationManager\Controller@getIndex', $stat->group)}}">{{$stat->group ?: '&nbsp;'}}</a>
                                            </td>
                                        @else
                                            <td class="group">
                                                <a href="{{action('Barryvdh\TranslationManager\Controller@getIndex', $stat->group)}}">{{$stat->group ?: '&nbsp;'}}</a>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php if($group): ?>
        <div class="row">
            <div class="col-sm-12 ">
                <br>
                <table class="table table-condensed">
                    <thead>
                        <tr>
                            <th width="20%">@lang('laravel-translation-manager::translations.key')</th>
                            <?php foreach($locales as $locale): ?>
                            <th width="40%"><?= $locale ?></th>
                            <?php endforeach; ?>
                            <?php if($deleteEnabled): ?>
                            <th width="40%">&nbsp;</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($translations as $key => $translation): ?>
                        <tr id="<?= $key ?>">
                            <td><?= $key ?></td>
                            <?php foreach($locales as $locale): ?>
                            <?php $t = isset($translation[$locale]) ? $translation[$locale] : null?>

                            <td>
                                <a href="#edit" class="editable status-<?= $t ? $t->status : 0 ?> locale-<?= $locale ?>" data-locale="<?= $locale ?>"
                                        data-name="<?= $locale . "|" . $key ?>" id="username" data-type="textarea" data-pk="<?= $t ? $t->id : 0 ?>" data-url="<?= $editUrl ?>"
                                        data-inputclass="editable-input"
                                        data-title="@lang('laravel-translation-manager::translations.enter-translation')"><?= $t ? htmlentities($t->value, ENT_QUOTES, 'UTF-8', false) : '' ?></a> <?= !$t ? '' : ($t->saved_value === $t->value ? '' : ' [' . \Barryvdh\TranslationManager\Controller::mb_renderDiffHtml($t->saved_value, $t->value) . ']') ?>
                            </td>
                            <?php endforeach; ?>
                            <?php if($deleteEnabled): ?>
                            <td>
                                <a href="<?= action('Barryvdh\TranslationManager\Controller@postDelete', [
                                        $group,
                                        $key
                                ]) ?>" class="delete-key" data-method="POST" data-remote="true">
                                    <span class="glyphicon glyphicon-trash"></span>
                                </a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <!-- Search Modal -->
        <div class="modal fade" id="searchModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel">@lang('laravel-translation-manager::translations.search-translations')</h4>
                    </div>
                    <div class="modal-body">
                        <form id="search-form" method="GET" action="<?= $searchUrl ?>" data-remote="true">
                            <div class="form-group">
                                <div class="input-group">
                                    <input id="search-form-text" type="search" name="q" class="form-control"><span class="input-group-btn">
                                        <button class="btn btn-default" type="submit">@lang('laravel-translation-manager::translations.search')</button>
                                    </span>
                                </div>
                            </div>
                        </form>
                        <div class="results"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">@lang('laravel-translation-manager::translations.close')</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@stop

@section('body-bottom')
    <!--<script src="//code.jquery.com/jquery-1.11.0.min.js"></script>-->
    <!--<script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>-->
    <script src="//cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/js/bootstrap-editable.min.js"></script>
    <script src="@asset('/js/rails.min.js')"></script>
    {{-- @formatter:off --}}
    <script>
    </script>
    {{-- @formatter:on --}}
    <style>
        a.status-1 {
            font-weight: bold;
        }

        del {
            color: #C80010;
        }

        ins {
            color: #108030;
        }
    </style>
    <script>
        jQuery(document).ready(function ($) {
//                        $.fn.editable.defaults.mode = 'inline';
            $.fn.editableform.template = '' +
            '<form class="form-inline editableform">' +
            '<div class="control-group">' +
            '<div><div class="editable-buttons"></div><br><br><div class="editable-input"></div></div>' +
            '<div class="editable-error-block"></div>' +
            '</div>' +
            '</form>';

            $.fn.editableform.buttons = '' +
            '<button type="submit" class="editable-submit btn btn-sm btn-success"><i class="glyphicon glyphicon-ok"></i></button>' +
            '&nbsp;<button type="button" class="editable-cancel btn btn-sm btn-danger"><i class="glyphicon glyphicon-remove"></i></button>';

            $('.editable').editable().on('hidden', function (e, reason) {
                var locale = $(this).data('locale');
                if (reason === 'save') {
                    $(this).removeClass('status-0').addClass('status-1');
                }
                if (reason === 'save' || reason === 'nochange') {
//          var $next = $(this).closest('tr').next().find('.editable.locale-' + locale);
//          setTimeout(function () {
//            $next.editable('show');
//          }, 300);
                }
            });

            $('.group-select').on('change', function () {
                window.location.href = '<?= action('Barryvdh\TranslationManager\Controller@getIndex') ?>/' + $(this).val();
            });

            $('a.delete-key').on('ajax:success', function (e, data) {
                $(this).closest('tr').remove();
            });

            $('.form-import').on('ajax:success', function (e, data) {
                $('div.success-import strong.counter').text(data.counter);
                $('div.success-import').slideDown();
            });

            $('.form-find').on('ajax:success', function (e, data) {
                $('div.success-find strong.counter').text(data.counter);
                $('div.success-find').slideDown();
            });

            $('.form-publish').on('ajax:success', function (e, data) {
                $('div.success-publish').slideDown();
            });

            $('#searchModal').on('shown.bs.modal', function (e) {
                // do something...
                var elem = $('#search-form-text').first();
                if (elem.length) {
                    elem[0].focus();
                    elem[0].select();
                }
            })

        })
    </script>

    <script>
        $('#searchModal').on('ajax:success', function (event, data, status, xhr) {
            $('#searchModal .results').html(data);
            $('#searchModal .results .editable').editable().on('hidden', function (e, reason) {
                var locale = $(this).data('locale');
                if (reason === 'save') {
                    $(this).removeClass('status-0').addClass('status-1');
                }
                if (reason === 'save' || reason === 'nochange') {
//          var $next = $(this).closest('tr').next().find('.editable.locale-' + locale);
//          setTimeout(function () {
//            $next.editable('show');
//          }, 300);
                }
            });

        });
    </script>
@stop

