<?php

namespace Vsch\TranslationManager\Repositories;

use Vsch\TranslationManager\Models\Translation;

class TranslatorRepository
{
    private $translation;
    private $connection;
    private $tableName;

    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
        $this->connection = $translation->getConnection();
        $this->tableName = $this->getTranslationsTableName();
    }

    public function getTranslationsTableName()
    {
        $prefix = $this->translation->getConnection()->getTablePrefix();
        return $prefix . $this->translation->getTable();
    }

    /**
     * @return Translation
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    /**
     * @param Translation $translation
     */
    public function setTranslation($translation)
    {
        $this->translation = $translation;
    }

    /**
     *
     * @param $keys
     * @param $group
     * @param $value
     */
    public function updateUsedTranslationsForGroup($keys, $group, $value)
    {
        /**
         * update without key
         */
        if (!$keys) {
            $this->getConnection()->affectingStatement(<<<SQL
                UPDATE $this->tableName SET was_used = $value WHERE was_used <> 0 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?)
SQL
                , [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
        }


        $this->getConnection()->affectingStatement(<<<SQL
            UPDATE $this->tableName SET was_used = $value WHERE was_used $value AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?) AND `key` IN ($keys)
SQL
            , [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
    }

    /**
     *
     */
    public function clearUsageCache()
    {

        $this->getConnection()->affectingStatement(<<<SQL
            UPDATE $this->tableName SET was_used = 0 WHERE was_used <> 0
SQL
        );
    }

    public function selectTranslationsByLocaleAndGroup($locale, $db_group, $connectionName)
    {
        return $this->translation->fromQuery(<<<SQL
SELECT * FROM $this->tableName WHERE locale = ? AND `group` = ?
SQL
            , [$locale, $db_group], $connectionName);
    }

    public function updateStatusForTranslations($status, $updated_at, $translationIds)
    {
        $this->getConnection()->affectingStatement(<<<SQL
UPDATE $this->tableName SET status = ?, is_deleted = 0, updated_at = ? WHERE id IN ($translationIds)
SQL
            , [$status, $updated_at]);
    }

    public function insertTranslations($values)
    {
        $sql = "INSERT INTO $this->tableName (status, locale, `group`, `key`, value, created_at, updated_at, source, saved_value, is_deleted, was_used) VALUES " . implode(",", $values);
        $this->getConnection()->unprepared($sql);
    }

    public function deleteTranslation($group = null) {
        if (! $group) {
            $this->getConnection()->affectingStatement("DELETE FROM $this->tableName WHERE is_deleted = 1");
        } else {
            $this->getConnection()->affectingStatement("DELETE FROM $this->tableName WHERE is_deleted = 1 AND `group` = ?", [$group]);
        }
    }

    public function deleteTranslations($keys)
    {
        $this->getConnection()->unprepared(<<<SQL
          DELETE FROM $this->tableName WHERE id IN ($keys)
SQL
        );
    }

    public function deleteTranslationByGroup($group)
    {
        $this->getConnection()->affectingStatement("DELETE FROM $this->tableName WHERE `group` = ?", [$group]);
    }

    public function updateValueInGroup($group)
    {
        $this->getConnection()->affectingStatement(<<<SQL
UPDATE $this->tableName SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?) AND `group` = ?
SQL
            , [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED, $group]);
    }

    public function updateValuesByStatus() {
        $this->getConnection()->affectingStatement(<<<SQL
UPDATE $this->tableName SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?)
SQL
            , [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED]);
    }

}