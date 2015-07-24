<?php
use Illuminate\Support\Facades\URL;

?>

<div class="alert alert-default" role="alert" style="padding-top: 0; padding-bottom: 0; margin: 0;">
    <div class="row">
        <div class="panel panel-default">
            <div class="panel-heading">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <?= ifEditTrans('laravel-translation-manager::messages.keyop-header-'.$op, ['group' => $group]) ?>
                <h4><?= \Lang::get('laravel-translation-manager::messages.keyop-header-' . $op, ['group' => $group]) ?></h4>
            </div>
            <div class="panel-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?= $err ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif ?>
                <?php if (!empty($keymap)): ?>
                    <table class="table table-condensed" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th width="15%"><?= trans('laravel-translation-manager::messages.srckey') ?></th>
                                <th width="15%"><?= trans('laravel-translation-manager::messages.dstkey') ?></th>
                                <th width="70%">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($keymap as $src => $map): ?>
                                <tr>
                                    <?php if (array_key_exists('errors', $map)): ?>
                                        <td><?= $src ?></td>
                                        <td><?= $map['dst'] ?></td>
                                        <td>
                                            <?php foreach ($map['errors'] as $err): ?>
                                                <div class="alert alert-danger">
                                                    <ul>
                                                        <?php foreach ($errors as $err): ?>
                                                            <li><?= $err ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                    <?php else: ?>
                                        <td><?= $src ?></td>
                                        <td><?= $map['dst'] ?></td>
                                        <?php if (!empty($map['rows'])): ?>
                                            <td>
                                                <table class="table table-striped table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th width="5%"><?= trans('laravel-translation-manager::messages.locale') ?></th>
                                                            <th width="25%"><?= trans('laravel-translation-manager::messages.src-preview') ?></th>
                                                            <th width="5%">&nbsp;</th>
                                                            <th width="25%"><?= trans('laravel-translation-manager::messages.dst-preview') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($map['rows'] as $row): ?>
                                                            <tr>
                                                                <td><?= $row->locale ?></td>
                                                                <td><?= $row->group . '.' . $row->key ?></td>
                                                                <td><?= $row->dst === null ? '✘' : '➽' ?></td>
                                                                <td><?= $row->dst === null ? '' : $row->dstgrp . '.' . $row->dst ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </td>
                                        <?php else: ?>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                        <?php endif ?>
                                    <?php endif ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

