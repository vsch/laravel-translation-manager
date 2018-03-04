<?php

namespace Vsch\TranslationManager\Repositories\Interfaces;

interface ITranslatorRepository
{
    public function updateIsDeletedByIds($rowId);

    public function setNotUsedForAllTranslations();

    public function updateStatusForTranslations($status, $updated_at, $translationIds);

    public function getInsertTranslationsElement($translation, $timeStamp);

    public function deleteTranslationsForIds($translationIds);

    public function updateValuesByStatus();

    public function updateUsedTranslationsForGroup($keys, $group);

    public function updateIsDeletedByGroupAndKey($group, $key, $value);

    public function updateGroupKeyStatusById($group, $key, $id);

    public function selectTranslationsByLocaleAndGroup($locale, $db_group);

    public function selectSourceByGroupAndKey($group, $key);

    public function insertTranslations($values);

    public function deleteTranslationWhereIsDeleted($group = null);

    public function deleteTranslationByGroup($group);
    
    public function deleteTranslationByGroupLocale($group, $locale);

    public function updateValueInGroup($group);

    public function searchByRequest($q, $displayWhere);

    public function allTranslations($group, $displayLocales);

    public function stats($displayLocales);

    public function findMismatches($displayLocales, $primaryLocale, $translatingLocale);

    public function selectToDeleteTranslations($group, $key, $locale, $rowIds);

    public function selectKeys($src, $dst, $userLocales, $srcgrp, $srckey, $dstkey, $dstgrp);

    public function copyKeys($dstgrp, $dstkey, $rowId);

    public function findFilledGroups();
}
