<?php

namespace Vsch\TranslationManager\Repositories\Interfaces;

use Vsch\TranslationManager\Models\Translation;

interface ITranslatorRepository
{
    /**
     * Return the translation used for database access.
     * 
     * All connection changes will be done on this instance
     * Its connection MUST BE USED FOR ALL OPERATIONS
     * 
     * Otherwise alternate connections in the UI will not work properly
     * DO NOT CACHE ITS CONNECTION except in local variable of a function for the 
     * duration of the function.
     * 
     * @return Translation
     */
    public function getTranslation();

    public function updateIsDeletedByIds($rowId);

    public function setNotUsedForAllTranslations();

    public function updateStatusForTranslations($status, $updated_at, $translationIds);

    public function getInsertTranslationsElement($translation, $timeStamp);

    public function deleteTranslationsForIds($translationIds);

    public function updateUsedTranslationsForGroup($keys, $group);

    public function updateIsDeletedByGroupAndKey($group, $key, $value);

    public function updateGroupKeyStatusById($group, $key, $id);

    public function selectTranslationsByLocaleAndGroup($locale, $db_group);

    public function selectSourceByGroupAndKey($group, $key);

    public function insertTranslations($values);

    public function deleteTranslationWhereIsDeleted($group = null);

    public function deleteTranslationByGroup($group);
    
    public function deleteTranslationByGroupLocale($group, $locale);

    public function updatePublishTranslations($newStatus, $group = null, $locale = null);

    public function searchByRequest($q, $displayWhere, $limit);

    public function allTranslations($group, $displayLocales);

    public function stats($displayLocales);

    public function findMismatches($displayLocales, $primaryLocale, $translatingLocale);

    public function selectToDeleteTranslations($group, $key, $locale, $rowIds);

    public function selectKeys($src, $dst, $locales, $srcgrp, $srckey, $dstkey, $dstgrp);

    public function copyKeys($dstgrp, $dstkey, $rowId);

    public function findFilledGroups();
}
