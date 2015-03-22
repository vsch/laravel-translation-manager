<?php
use Illuminate\Support\Facades\URL;

?>

<h4>Results found: <?= $numTranslations ?></h4>
<table class="table">
    <thead>
        <tr>
            <th width="15%"><?= trans('laravel-translation-manager::translations.group') ?></th>
            <th width="20%"><?= trans('laravel-translation-manager::translations.key') ?></th>
            <th width=" 5%"><?= trans('laravel-translation-manager::translations.locale') ?></th>
            <th width="60%"><?= trans('laravel-translation-manager::translations.translation') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($translations as $t): ?>
            <?php $groupUrl = action('Barryvdh\TranslationManager\Controller@getIndex', $t->group); ?>
            <tr>
                <td><a href="<?= $groupUrl ?>#<?= $t->key ?>"><?= $t->group ?></a></td>
                <td><?= $t->key ?></td>
                <td><?= $t->locale ?></td>
                <td>
                    <a href="#edit" class="editable status-<?= $t ? $t->status : 0 ?> locale-<?= $t->locale ?>" data-locale="<?= $t->locale ?>"
                        data-name="<?= $t->locale . "|" . $t->key ?>" id="username" data-type="textarea" data-pk="<?= $t ? $t->id : 0 ?>" data-url="<?= URL::action('Barryvdh\TranslationManager\Controller@postEdit', array($t->group)) ?>"
                        data-inputclass="editable-input"
                        data-title="<?=trans('laravel-translation-manager::translations.enter-translation')?>"><?= $t ? htmlentities($t->value, ENT_QUOTES, 'UTF-8', false) : '' ?></a> <?= !$t ? '' : ($t->saved_value === $t->value ? '' : ' [' . \Barryvdh\TranslationManager\Controller::mb_renderDiffHtml($t->saved_value, $t->value) . ']') ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

