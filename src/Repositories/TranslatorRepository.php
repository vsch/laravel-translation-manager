<?php

namespace Vsch\TranslationManager\Repositories;

use Vsch\TranslationManager\Models\Translation;

class TranslatorRepository
{
    private $translation;
    private $connection;
    private $tableName;
    private $tableRenameNeeded;

    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
        $this->connection = $translation->getConnection();
        $this->tableName = $this->getTranslationsTableName();
        $this->tableRenameNeeded = $this->tableName != 'ltm_translations';
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
     * Return value to be used for concatenation as a database value
     *
     * @param         $value
     * @param  string $nullValue to be used if value is null
     *
     * @return string
     */
    public static function dbValue($value, $nullValue = 'NULL')
    {
        if ($value === null) {
            return $nullValue;
        }

        if (is_string($value)) {
            return '\'' . str_replace('\'', '\'\'', $value) . '\'';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value;
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
     * Replace translation table name, used to allow queries with standard table name so that PhpStorm SQL completions and refactoring could be used.
     *
     * @param string $sql SQL query where to replace every occurrence of " ltm_translations " with the actual table name
     *
     * @return string of the modified query
     */
    private function adjustTranslationTable($sql)
    {
        $adjustedSql = $this->tableRenameNeeded ? str_replace(' ltm_translations ', ' ' . $this->tableName . ' ', $sql) : $sql;
        return $adjustedSql;
    }

    /**
     * @param $keys
     * @param $group
     */
    public function updateUsedTranslationsForGroup($keys, $group)
    {
        $setKeys = "";
        $resetKeys = "";
        foreach ($keys as $key => $usage) {
            if ($usage) {
                if ($setKeys) $setKeys .= ',';
                $setKeys .= "'$key'";
            } else {
                if ($resetKeys) $resetKeys .= ',';
                $resetKeys .= "'$key'";
            }
        }

        if ($setKeys) {
            $this->connection->affectingStatement($this->adjustTranslationTable(<<<SQL
                UPDATE ltm_translations SET was_used = 1 WHERE was_used <> 0 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?)
SQL
            ), [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
        }

        if ($resetKeys) {
            $this->connection->affectingStatement($this->adjustTranslationTable(<<<SQL
            UPDATE ltm_translations SET was_used = 0 WHERE was_used <> 0 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?) AND `key` IN ($resetKeys)
SQL
            ), [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
        }
    }

    public function updateIsDeletedByGroupAndKey($group, $key, $value)
    {
        $whereClause = $value == 1 ? 0 : 1;

        return $this->connection->update($this->adjustTranslationTable(<<<SQL
UPDATE ltm_translations SET is_deleted = ? WHERE is_deleted = $whereClause AND `group` = ? AND `key` = ?
SQL
        ), [$value, $group, $key]);
    }

    public function updateIsDeletedByIds($rowIds)
    {
        $this->connection->update($this->adjustTranslationTable("UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND id IN ($rowIds)"));
    }

    public function updateGroupKeyStatusById($group, $key, $id)
    {
        $this->connection->update($this->adjustTranslationTable("UPDATE ltm_translations SET `group` = ?, `key` = ?, status = 1 WHERE id = ?"), [$group, $key, $id]);
    }

    public function setNotUsedForAllTranslations()
    {
        $this->connection->affectingStatement($this->adjustTranslationTable(<<<SQL
            UPDATE ltm_translations SET was_used = 0 WHERE was_used <> 0
SQL
        ));
    }

    public function selectTranslationsByLocaleAndGroup($locale, $db_group)
    {
        return $this->translation->fromQuery($this->adjustTranslationTable(<<<SQL
SELECT * FROM ltm_translations WHERE locale = ? AND `group` = ?
SQL
        ), [$locale, $db_group], $this->getTranslation()->getConnectionName());
    }

    public function selectSourceByGroupAndKey($group, $key)
    {
        return $this->connection->select($this->adjustTranslationTable(<<<SQL
SELECT source FROM ltm_translations WHERE `group` = ? AND `key` = ?
SQL
        ), [$group, $key]);
    }

    public function updateStatusForTranslations($status, $updated_at, $translationIds)
    {
        $this->connection->affectingStatement($this->adjustTranslationTable(<<<SQL
UPDATE ltm_translations SET status = ?, is_deleted = 0, updated_at = ? WHERE id IN ($translationIds)
SQL
        ), [$status, $updated_at]);
    }

    /**
     * return an element representing the translation for bulk insert by insertTranslations
     *
     * @param Translation $translation translation to prepare for bulk insert
     * @param string      $timeStamp   timestamp to be used for created and updated fields
     *
     * @return object|string element that represents the translation
     */
    public function getInsertTranslationsElement($translation, $timeStamp)
    {
        return '(' .
            self::dbValue($translation->status, Translation::STATUS_SAVED) . ',' .
            self::dbValue($translation->locale) . ',' .
            self::dbValue($translation->group) . ',' .
            self::dbValue($translation->key) . ',' .
            self::dbValue($translation->value) . ',' .
            self::dbValue($translation->created_at, $timeStamp) . ',' .
            self::dbValue($translation->updated_at, $timeStamp) . ',' .
            self::dbValue($translation->source) . ',' .
            self::dbValue($translation->saved_value) . ',' .
            self::dbValue($translation->is_deleted, 0) . ',' .
            self::dbValue($translation->was_used, 0) .
            ')';
    }

    /**
     * @param array $values element to be inserted, each element was created via getInsertTranslationsElement call for the translation
     */
    public function insertTranslations($values)
    {
        // $values is an 
        $sql = $this->adjustTranslationTable("INSERT INTO ltm_translations (status, locale, `group`, `key`, value, created_at, updated_at, source, saved_value, is_deleted, was_used) VALUES " . implode(",", $values));
        $this->connection->unprepared($sql);
    }

    public function deleteTranslationWhereIsDeleted($group = null)
    {
        if (!$group) {
            $this->connection->affectingStatement($this->adjustTranslationTable("DELETE FROM ltm_translations WHERE is_deleted = 1"));
        } else {
            $this->connection->affectingStatement($this->adjustTranslationTable("DELETE FROM ltm_translations WHERE is_deleted = 1 AND `group` = ?"), [$group]);
        }
    }

    public function deleteTranslationsForIds($translationIds)
    {
        $this->connection->unprepared($this->adjustTranslationTable(<<<SQL
          DELETE FROM ltm_translations WHERE id IN ($translationIds)
SQL
        ));
    }

    public function deleteTranslationByGroup($group)
    {
        $this->connection->affectingStatement($this->adjustTranslationTable("DELETE FROM ltm_translations WHERE `group` = ?"), [$group]);
    }

    public function updateValueInGroup($group)
    {
        $this->connection->affectingStatement($this->adjustTranslationTable(<<<SQL
UPDATE ltm_translations SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?) AND `group` = ?
SQL
        ), [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED, $group]);
    }

    public function updateValuesByStatus()
    {

        $this->connection->affectingStatement($this->adjustTranslationTable(<<<SQL
UPDATE ltm_translations SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?)
SQL
        ), [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED]);
    }

    public function searchByRequest($q, $displayWhere)
    {
        return $this->connection->select($this->adjustTranslationTable(<<<SQL
SELECT  
    id, status, locale, `group`, `key`, value, created_at, updated_at, source, saved_value, is_deleted, was_used
    FROM ltm_translations rt WHERE (`key` LIKE ? OR value LIKE ?) $displayWhere
UNION ALL
SELECT NULL id, 0 status, lt.locale, kt.`group`, kt.`key`, NULL value, NULL created_at, NULL updated_at, NULL source, NULL saved_value, NULL is_deleted, NULL was_used
FROM (SELECT DISTINCT locale FROM ltm_translations  WHERE 1=1 $displayWhere) lt
    CROSS JOIN (SELECT DISTINCT `key`, `group` FROM ltm_translations  WHERE 1=1 $displayWhere) kt
WHERE NOT exists(SELECT * FROM ltm_translations  tr WHERE tr.`key` = kt.`key` AND tr.`group` = kt.`group` AND tr.locale = lt.locale)
      AND `key` LIKE ?
ORDER BY `key`, `group`, locale
SQL
        ), [$q, $q, $q,]);
    }

    public function allTranslations($group, $displayLocales)
    {
        $displayWhere = $displayLocales ? ' AND locale IN (\'' . implode("','", explode(',', $displayLocales)) . "')" : '';

        return $this->getTranslation()->fromQuery($this->adjustTranslationTable(<<<SQL
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
FROM ltm_translations ltm WHERE `group` = ? $displayWhere
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
(SELECT * FROM (SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lcs
    CROSS JOIN (SELECT DISTINCT `group`, `key` FROM ltm_translations WHERE `group` = ? $displayWhere) grp) m
WHERE NOT EXISTS(SELECT * FROM ltm_translations t WHERE t.locale = m.locale AND t.`group` = m.`group` AND t.`key` = m.`key`)
ORDER BY `key` ASC
SQL
        ), [$group, $group], $this->getTranslation()->getConnectionName());
    }

    public function stats($displayLocales)
    {
        $displayWhere = $displayLocales ? ' AND locale IN (\'' . implode("','", explode(',', $displayLocales)) . "')" : '';

        return $this->connection->select($this->adjustTranslationTable(<<<SQL
SELECT (mx.total_keys - lcs.total) missing, lcs.changed, lcs.cached, lcs.deleted, lcs.locale, lcs.`group`
FROM
    (SELECT sum(total) total, sum(changed) changed, sum(cached) cached, sum(deleted) deleted, `group`, locale
     FROM
         (SELECT count(value) total,
          sum(CASE WHEN status = 1 THEN 1 ELSE 0 END) changed,
          sum(CASE WHEN status = 2 AND value IS NOT NULL THEN 1 ELSE 0 END) cached,
         sum(is_deleted) deleted,
         `group`, locale
                FROM ltm_translations lt WHERE 1=1 $displayWhere GROUP BY `group`, locale
          UNION ALL
          SELECT DISTINCT 0, 0, 0, 0, `group`, locale FROM (SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lc
              CROSS JOIN (SELECT DISTINCT `group` FROM ltm_translations) lg) a
     GROUP BY `group`, locale) lcs
    JOIN (SELECT count(DISTINCT `key`) total_keys, `group` FROM ltm_translations WHERE 1=1 $displayWhere GROUP BY `group`) mx
        ON lcs.`group` = mx.`group`
WHERE lcs.total < mx.total_keys OR lcs.changed > 0 OR lcs.cached > 0 OR lcs.deleted > 0
SQL
        ));
    }

    public function findMismatches($displayLocales, $primaryLocale, $translatingLocale)
    {
        $displayWhere = $displayLocales ? ' AND locale IN (\'' . implode("','", explode(',', $displayLocales)) . "')" : '';

        return $this->connection->select($this->adjustTranslationTable(<<<SQL
SELECT DISTINCT lt.*, ft.ru, ft.en
FROM (SELECT * FROM ltm_translations WHERE 1=1 $displayWhere) lt
    JOIN
    (SELECT DISTINCT mt.`key`, BINARY mt.ru ru, BINARY mt.en en
     FROM (SELECT lt.`group`, lt.`key`, group_concat(CASE lt.locale WHEN '$primaryLocale' THEN VALUE ELSE NULL END) en, group_concat(CASE lt.locale WHEN '$translatingLocale' THEN VALUE ELSE NULL END) ru
           FROM (SELECT value, `group`, `key`, locale FROM ltm_translations WHERE 1=1 $displayWhere
                 UNION ALL
                 SELECT NULL, `group`, `key`, locale FROM ((SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lc
                     CROSS JOIN (SELECT DISTINCT `group`, `key` FROM ltm_translations WHERE 1=1 $displayWhere) lg)
                ) lt
           GROUP BY `group`, `key`) mt
         JOIN (SELECT lt.`group`, lt.`key`, group_concat(CASE lt.locale WHEN '$primaryLocale' THEN VALUE ELSE NULL END) en, group_concat(CASE lt.locale WHEN '$translatingLocale' THEN VALUE ELSE NULL END) ru
               FROM (SELECT value, `group`, `key`, locale FROM ltm_translations WHERE 1=1 $displayWhere
                     UNION ALL
                     SELECT NULL, `group`, `key`, locale FROM ((SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lc
                         CROSS JOIN (SELECT DISTINCT `group`, `key` FROM ltm_translations WHERE 1=1 $displayWhere) lg)
                    ) lt
               GROUP BY `group`, `key`) ht ON mt.`key` = ht.`key`
     WHERE (mt.ru NOT LIKE BINARY ht.ru AND mt.en LIKE BINARY ht.en) OR (mt.ru LIKE BINARY ht.ru AND mt.en NOT LIKE BINARY ht.en)
    ) ft
        ON (lt.locale = '$translatingLocale' AND lt.value LIKE BINARY ft.ru) AND lt.`key` = ft.key
ORDER BY `key`, `group`
SQL
        ));
    }

    public function selectToDeleteTranslations($group, $key, $locale, $rowIds)
    {
        return $this->connection->select($this->adjustTranslationTable(<<<SQL
SELECT GROUP_CONCAT(id SEPARATOR ',') ids FROM ltm_translations tr
WHERE `group` = ? AND `key` = ? AND locale = ? AND id NOT IN ($rowIds)

SQL
        ), [$group, $key, $locale]);
    }

    public function selectKeys($src, $dst, $userLocales, $srcgrp, $srckey, $dstkey, $dstgrp)
    {
        $ltm_translations = $this->getTranslationsTableName();
        
        if ((substr($src, 0, 1) === '*')) {
            if ($dst === null) {
                $rows = $this->connection->select($this->adjustTranslationTable($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM $ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN ($userLocales)
ORDER BY locale, `key`

SQL
                ), [
                        $srcgrp,
                        '%' . mb_substr($srckey, 1),
                    ]);
            } else {
                $rows = $this->connection->select($this->adjustTranslationTable($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, CONCAT(SUBSTR(`key`, 1, CHAR_LENGTH(`key`)-?), ?) dst, ? dstgrp FROM $ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN ($userLocales)
AND NOT exists(SELECT * FROM $ltm_translations t2 WHERE t2.value IS NOT NULL AND t2.`group` = ? AND t1.locale = t2.locale
                AND t2.`key` LIKE BINARY CONCAT(SUBSTR(t1.`key`, 1, CHAR_LENGTH(t1.`key`)-?), ?))
ORDER BY locale, `key`

SQL
                ), [
                        mb_strlen($srckey) - 1,
                        mb_substr($dstkey, 1),
                        $dstgrp,
                        $srcgrp,
                        '%' . mb_substr($srckey, 1),
                        $dstgrp,
                        mb_strlen($srckey) - 1,
                        mb_substr($dstkey, 1)
                    ]);
            }
        } elseif ((substr($src, -1, 1) === '*')) {
            if ($dst === null) {
                $rows = $this->connection->select($this->adjustTranslationTable($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM $ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN ($userLocales)
ORDER BY locale, `key`

SQL
                ), [
                        $srcgrp,
                        mb_substr($srckey, 0, -1) . '%',
                    ]);
            } else {
                $rows = $this->connection->select($this->adjustTranslationTable($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, CONCAT(?, SUBSTR(`key`, ?+1, CHAR_LENGTH(`key`)-?)) dst, ? dstgrp FROM $ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN ($userLocales)
AND NOT exists(SELECT * FROM $ltm_translations t2 WHERE t2.value IS NOT NULL AND t2.`group` = ? AND t1.locale = t2.locale
                AND t2.`key` LIKE BINARY CONCAT(?, SUBSTR(t1.`key`, ?+1, CHAR_LENGTH(t1.`key`)-?)))
ORDER BY locale, `key`

SQL
                ), [
                        mb_substr($dstkey, 0, -1),
                        mb_strlen($srckey) - 1,
                        mb_strlen($srckey) - 1,
                        $dstgrp,
                        $srcgrp,
                        mb_substr($srckey, 0, -1) . '%',
                        $dstgrp,
                        mb_substr($dstkey, 0, -1),
                        mb_strlen($srckey) - 1,
                        mb_strlen($srckey) - 1
                    ]);
            }
        } else {
            if ($dst === null) {
                $rows = $this->connection->select($this->adjustTranslationTable($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM $ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN ($userLocales)
ORDER BY locale, `key`

SQL
                ), [
                        $srcgrp,
                        $srckey,
                    ]);
            } else {
                $rows = $this->connection->select($this->adjustTranslationTable($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, ? dst, ? dstgrp FROM $ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN ($userLocales)
AND NOT exists(SELECT * FROM $ltm_translations t2 WHERE t2.value IS NOT NULL AND t2.`group` = ? AND t1.locale = t2.locale AND t2.`key` LIKE BINARY ?)
ORDER BY locale, `key`

SQL
                ), [
                        $dstkey,
                        $dstgrp,
                        $srcgrp,
                        $srckey,
                        $dstgrp,
                        $dstkey,
                    ]);
            }
        }
 
        return $rows;
    }

    public function copyKeys($dstgrp, $dstkey, $rowId)
    {
        $ltm_translations = $this->getTranslationsTableName();

        return $this->connection->insert($this->adjustTranslationTable(<<<SQL
INSERT INTO $ltm_translations (status, locale, `group`, `key`, value, created_at, updated_at, source, saved_value, is_deleted, was_used) 
SELECT
    1 status,
    locale,
    ? `group`,
    ? `key`,
    value,
    sysdate() created_at,
    sysdate() updated_at,
    source,
    saved_value,
    is_deleted,
    was_used
FROM $ltm_translations t1
WHERE id = ?

SQL
        ), [$dstgrp, $dstkey, $rowId]);
    }
}
