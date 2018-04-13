<?php
?>
<h4><?php use Vsch\TranslationManager\Manager;

    trans($package . '::messages.search-header', ['count' => $numTranslations]) ?></h4>
<table class="table table-translations">
  <thead>
    <tr>
      <th width="15%"><?= trans($package . '::messages.group') ?></th>
      <th width="20%"><?= trans($package . '::messages.key') ?></th>
      <th width=" 5%"><?= trans($package . '::messages.locale') ?></th>
      <th width="60%"><?= trans($package . '::messages.translation') ?></th>
    </tr>
  </thead>
  <tbody>
      <?php 
      $translator = App::make('translator'); 
      foreach ($translations as $t):
          $groupUrl = action($controller . '@getView', $t->group); 
          $isLocaleEnabled = str_contains($userLocales, ',' . $t->locale . ',');
          if ($t->group === Manager::JSON_GROUP && $t->locale === 'json' && $t->value === null || $t->value === '') {
              $t->value = $t->key;
          }
      ?>
        <tr id='<?=str_replace('.','-', $t->key)?>'>
          <td>
            <a href="<?= $groupUrl ?>#<?= $t->key ?>"><?= $t->group ?></a>
          </td>
          <td><?= $t->key ?></td>
          <td><?= $t->locale ?></td>
          <td>
              <?= $isLocaleEnabled ? $translator->inPlaceEditLink($t) : $t->value ?>
          </td>
        </tr>
      <?php endforeach; ?>
  </tbody>
</table>

