<?php
use Illuminate\Support\Facades\URL;

?>

<h4><?php trans('laravel-translation-manager::messages.search-header', ['count'=>$numTranslations])?></h4>
<table class="table">
    <thead>
    <tr>
        <th width="15%"><?= trans('group') ?></th>
        <th width="20%"><?= trans('key') ?></th>
        <th width=" 5%"><?= trans('locale') ?></th>
        <th width="60%"><?= trans('translation') ?></th>
    </tr>
    </thead>
    <tbody>
    <?php $translator = App::make('translator') ?>
    <?php foreach($translations as $t): ?>
        <?php $groupUrl = action('Vsch\TranslationManager\Controller@getIndex', $t->group); ?>
        <tr>
            <td><a href="<?= $groupUrl ?>#<?= $t->key ?>"><?= $t->group ?></a></td>
            <td><?= $t->key ?></td>
            <td><?= $t->locale ?></td>
            <td>
                <?= $translator->inPlaceEditLink($t) ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

