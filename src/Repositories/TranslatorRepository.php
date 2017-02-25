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
     * @return mixed
     */
    public function getConnection()
    {
        return $this->connection;
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

        /**
         * Update for keys
         */
        $this->getConnection()->affectingStatement(<<<SQL
            UPDATE $this->tableName SET was_used = $value WHERE was_used <> $value AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?) AND `key` IN ($keys)
SQL
            , [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
    }

    public function updateIsDeletedByGroupAndKey($group, $key, $value)
    {
        $whereClause = $value == 1 ? 0 : 1;

        return $this->getConnection()->update(<<<SQL
UPDATE $this->tableName SET is_deleted = $value WHERE is_deleted = $whereClause AND `group` = ? AND `key` = ?
SQL
            , [$group, $key]);
    }

    public function updateIsDeletedByIds($rowIds)
    {
        $this->getConnection()->update("UPDATE $this->tableName SET is_deleted = 1 WHERE is_deleted = 0 AND id IN ($rowIds)");
    }

    public function updateGroupKeyStatusById($dstgrp, $dstkey, $id)
    {
        $this->getConnection()->update("UPDATE $this->tableName SET `group` = ?, `key` = ?, status = 1 WHERE id = ?"
            , [$dstgrp, $dstkey, $id]);
    }
    
    public function setNotUsedForAllTranslations()
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

    public function selectSourceByGroupAndKey($group, $key)
    {
        return $this->getConnection()->select(<<<SQL
SELECT source FROM $this->tableName WHERE `group` = ? AND `key` = ?
SQL
            , [$group, $key]);
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

    public function deleteTranslationWhereIsDeleted($group = null) {
        if (! $group) {
            $this->getConnection()->affectingStatement("DELETE FROM $this->tableName WHERE is_deleted = 1");
        } else {
            $this->getConnection()->affectingStatement("DELETE FROM $this->tableName WHERE is_deleted = 1 AND `group` = ?", [$group]);
        }
    }

    public function deleteTranslationsForIds($translationIds)
    {
        $this->getConnection()->unprepared(<<<SQL
          DELETE FROM $this->tableName WHERE id IN ($translationIds)
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

    public function searchByRequest($q, $displayWhere)
    {
        return $this->getConnection()->select(
            <<<SQL
                SELECT  
                    id, status, locale, `group`, `key`, value, created_at, updated_at, source, saved_value, is_deleted, was_used
                    FROM $this->tableName rt WHERE (`key` LIKE ? OR value LIKE ?) $displayWhere
                UNION ALL
                SELECT NULL id, 0 status, lt.locale, kt.`group`, kt.`key`, NULL value, NULL created_at, NULL updated_at, NULL source, NULL saved_value, NULL is_deleted, NULL was_used
                FROM (SELECT DISTINCT locale FROM $this->tableName  WHERE 1=1 $displayWhere) lt
                    CROSS JOIN (SELECT DISTINCT `key`, `group` FROM $this->tableName  WHERE 1=1 $displayWhere) kt
                WHERE NOT exists(SELECT * FROM $this->tableName  tr WHERE tr.`key` = kt.`key` AND tr.`group` = kt.`group` AND tr.locale = lt.locale)
                      AND `key` LIKE ?
                ORDER BY `key`, `group`, locale
SQL
            , [$q, $q, $q,]);
    }
    
    public function allTranslations($group, $displayWhere)
    {
    return  $this->getTranslation()->fromQuery($sql = <<<SQL
        SELECT  
            ltm.id,
            ltm.status,
            ltm.locale,
            ltm.`group`,
            ltm.`key`,
            ltm.value,
            ltm.created_at,
            ltm.updated_at,
            ltm.saved_value,
            ltm.is_deleted,
            ltm.was_used,
            ltm.source <> '' has_source,
            ltm.is_auto_added
        FROM $this->tableName ltm WHERE `group` = ? $displayWhere
        UNION ALL
        SELECT DISTINCT
            NULL id,
            NULL status,
            locale,
            `group`,
            `key`,
            NULL value,
            NULL created_at,
            NULL updated_at,
            NULL saved_value,
            NULL is_deleted,
            NULL was_used,
            NULL has_source,
            NULL is_auto_added
        FROM
        (SELECT * FROM (SELECT DISTINCT locale FROM $this->tableName WHERE 1=1 $displayWhere) lcs
            CROSS JOIN (SELECT DISTINCT `group`, `key` FROM $this->tableName WHERE `group` = ? $displayWhere) grp) m
        WHERE NOT EXISTS(SELECT * FROM $this->tableName t WHERE t.locale = m.locale AND t.`group` = m.`group` AND t.`key` = m.`key`)
        ORDER BY `key` ASC
SQL
            , [$group, $group], $this->getTranslation()->getConnectionName());
    }

    public function stats($displayWhere)
    {
        return $this->getConnection()->select(<<<SQL
            SELECT (mx.total_keys - lcs.total) missing, lcs.changed, lcs.cached, lcs.deleted, lcs.locale, lcs.`group`
            FROM
                (SELECT sum(total) total, sum(changed) changed, sum(cached) cached, sum(deleted) deleted, `group`, locale
                 FROM
                     (SELECT count(value) total,
                      sum(CASE WHEN status = 1 THEN 1 ELSE 0 END) changed,
                      sum(CASE WHEN status = 2 AND value IS NOT NULL THEN 1 ELSE 0 END) cached,
                     sum(is_deleted) deleted,
                     `group`, locale
                            FROM $this->tableName lt WHERE 1=1 $displayWhere GROUP BY `group`, locale
                      UNION ALL
                      SELECT DISTINCT 0, 0, 0, 0, `group`, locale FROM (SELECT DISTINCT locale FROM $this->tableName WHERE 1=1 $displayWhere) lc
                          CROSS JOIN (SELECT DISTINCT `group` FROM $this->tableName) lg) a
                 GROUP BY `group`, locale) lcs
                JOIN (SELECT count(DISTINCT `key`) total_keys, `group` FROM $this->tableName WHERE 1=1 $displayWhere GROUP BY `group`) mx
                    ON lcs.`group` = mx.`group`
            WHERE lcs.total < mx.total_keys OR lcs.changed > 0 OR lcs.cached > 0 OR lcs.deleted > 0
SQL
        );
    }
    
    public function findMismatches($displayWhere, $primaryLocale, $translatingLocale)
    {
        return $this->getConnection()->select(<<<SQL
            SELECT DISTINCT lt.*, ft.ru, ft.en
            FROM (SELECT * FROM $this->tableName WHERE 1=1 $displayWhere) lt
                JOIN
                (SELECT DISTINCT mt.`key`, BINARY mt.ru ru, BINARY mt.en en
                 FROM (SELECT lt.`group`, lt.`key`, group_concat(CASE lt.locale WHEN '$primaryLocale' THEN VALUE ELSE NULL END) en, group_concat(CASE lt.locale WHEN '$translatingLocale' THEN VALUE ELSE NULL END) ru
                       FROM (SELECT value, `group`, `key`, locale FROM $this->tableName WHERE 1=1 $displayWhere
                             UNION ALL
                             SELECT NULL, `group`, `key`, locale FROM ((SELECT DISTINCT locale FROM $this->tableName WHERE 1=1 $displayWhere) lc
                                 CROSS JOIN (SELECT DISTINCT `group`, `key` FROM $this->tableName WHERE 1=1 $displayWhere) lg)
                            ) lt
                       GROUP BY `group`, `key`) mt
                     JOIN (SELECT lt.`group`, lt.`key`, group_concat(CASE lt.locale WHEN '$primaryLocale' THEN VALUE ELSE NULL END) en, group_concat(CASE lt.locale WHEN '$translatingLocale' THEN VALUE ELSE NULL END) ru
                           FROM (SELECT value, `group`, `key`, locale FROM $this->tableName WHERE 1=1 $displayWhere
                                 UNION ALL
                                 SELECT NULL, `group`, `key`, locale FROM ((SELECT DISTINCT locale FROM $this->tableName WHERE 1=1 $displayWhere) lc
                                     CROSS JOIN (SELECT DISTINCT `group`, `key` FROM $this->tableName WHERE 1=1 $displayWhere) lg)
                                ) lt
                           GROUP BY `group`, `key`) ht ON mt.`key` = ht.`key`
                 WHERE (mt.ru NOT LIKE BINARY ht.ru AND mt.en LIKE BINARY ht.en) OR (mt.ru LIKE BINARY ht.ru AND mt.en NOT LIKE BINARY ht.en)
                ) ft
                    ON (lt.locale = '$translatingLocale' AND lt.value LIKE BINARY ft.ru) AND lt.`key` = ft.key
            ORDER BY `key`, `group`
SQL
        );
    }


    public function selectToDeleteTranslations($dstgrp, $dstkey, $locale, $rowIds)
    {
        return $this->getConnection()->select(<<<SQL
SELECT GROUP_CONCAT(id SEPARATOR ',') ids FROM $this->tableName tr
WHERE `group` = ? AND `key` = ? AND locale = ? AND id NOT IN ($rowIds)

SQL
            , [$dstgrp, $dstkey, $locale]);
    }
    
    
    
    
    

}