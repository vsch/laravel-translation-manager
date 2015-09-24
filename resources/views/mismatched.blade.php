<?php
//$trans = App::make('translator');
//$trans->suspendUsageLogging();
?>
<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">@lang($package . '::messages.mismatches')</h3>
            </div>
            <div class="panel-body">
                <table class="table table-condensed translation-stats" style="max-height: 300px; margin-bottom: 0; overflow: auto; display: block;">
                    <thead>
                        <tr>
                            <th class="key" width="20%">@lang($package . '::messages.key')</th>
                            <th width="20%" colspan="2">ru</th>
                            <th width="20%">en</th>
                            <th class="group" width="20%">@lang($package . '::messages.group')</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $key = '';
                        $mismatches[] = null;
                        $locale = 'ru';
                        $translator = App::make('translator');
                        foreach($mismatches as $mismatch)
                        {
                        if ($mismatch === null) break;

                        $borderTop = ' class="no-border-top"';
                        if ($key != $mismatch->key)
                        {
                            if ($key !== '')
                            {
                                $borderTop = ' class="border-top"';
                            }
                            $key = $mismatch->key;
                            $keyText = $mismatch->key;
                        }
                        $link = action($controller . '@getView', $mismatch->group) . '#' . $mismatch->key;
                        $mismatch->value = $mismatch->ru_value;
                        $mismatch->locale = 'ru';
                        $mismatch->status = 'ru';
                        ?>
                        <tr<?= $borderTop?>>
                            <td width="20%" class="missing"><?= $keyText?></td>
                            <td>
                                <?= $translator->inPlaceEditLink($mismatch, false) ?>
                            </td>
                            <td width="20%" class="missing"><?= $mismatch->ru?></td>
                            <td width="20%" class="missing"><?= $mismatch->en?></td>
                            <td width="20%" class="group missing"><a href="<?= $link?>"><?= $mismatch->group?></a></td>
                        </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
//$trans->resumeUsageLogging();
?>
