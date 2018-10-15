<?php

namespace Vsch\TranslationManager\Repositories;

use Vsch\TranslationManager\Models\Translation;

class MysqlTranslatorRepository extends TranslatorRepository
{
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

        $ltm_translations = $this->getTranslationsTableName();
        
        if ($setKeys) {
            $this->translation->getConnection()->affectingStatement($this->adjustTranslationTable(<<<SQL
                UPDATE $ltm_translations SET was_used = 1 WHERE was_used = 0 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?) AND `key` IN ($setKeys)
SQL
            ), [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
        }

        if ($resetKeys) {
            $this->translation->getConnection()->affectingStatement($this->adjustTranslationTable(<<<SQL
            UPDATE $ltm_translations SET was_used = 0 WHERE was_used <> 0 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?) AND `key` IN ($resetKeys)
SQL
            ), [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
        }
    }

    public function updateIsDeletedByGroupAndKey($group, $key, $value)
    {
        $whereClause = $value == 1 ? 0 : 1;
        $ltm_translations = $this->getTranslationsTableName();

        return $this->translation->getConnection()->update($this->adjustTranslationTable(<<<SQL
UPDATE $ltm_translations SET is_deleted = ? WHERE is_deleted = $whereClause AND `group` = ? AND `key` LIKE BINARY ?
SQL
        ), [$value, $group, $key]);
    }

    public function updateGroupKeyStatusById($group, $key, $id)
    {
        $ltm_translations = $this->getTranslationsTableName();
        $this->translation->getConnection()->update($this->adjustTranslationTable("UPDATE $ltm_translations SET `group` = ?, `key` LIKE BINARY ?, status = 1 WHERE id = ?"), [$group, $key, $id]);
    }

    public function selectTranslationsByLocaleAndGroup($locale, $db_group)
    {
        $ltm_translations = $this->getTranslationsTableName();
        return $this->translation->fromQuery($this->adjustTranslationTable(<<<SQL
SELECT * FROM $ltm_translations WHERE locale = ? AND `group` = ?
SQL
        ), [$locale, $db_group], $this->getTranslation()->getConnectionName());
    }

    public function selectSourceByGroupAndKey($group, $key)
    {
        $ltm_translations = $this->getTranslationsTableName();
        return $this->translation->getConnection()->select($this->adjustTranslationTable(<<<SQL
SELECT source FROM $ltm_translations WHERE `group` = ? AND `key` LIKE BINARY ?
SQL
        ), [$group, $key]);
    }

    /**
     * @param array $values element to be inserted, each element was created via getInsertTranslationsElement call for the translation
     */
    public function insertTranslations($values)
    {
        // $values is an
        $ltm_translations = $this->getTranslationsTableName();
        $sql = $this->adjustTranslationTable("INSERT INTO $ltm_translations (status, locale, `group`, `key`, value, created_at, updated_at, source, saved_value, is_deleted, was_used) VALUES " . implode(",", $values));
        $this->translation->getConnection()->unprepared($sql);
    }

    public function deleteTranslationWhereIsDeleted($group = null)
    {
        $ltm_translations = $this->getTranslationsTableName();
        if (!$group) {
            $this->translation->getConnection()->affectingStatement($this->adjustTranslationTable("DELETE FROM $ltm_translations WHERE is_deleted = 1"));
        } else {
            $this->translation->getConnection()->affectingStatement($this->adjustTranslationTable("DELETE FROM $ltm_translations WHERE is_deleted = 1 AND `group` = ?"), [$group]);
        }
    }

    public function deleteTranslationByGroup($group)
    {
        $ltm_translations = $this->getTranslationsTableName();
        $this->translation->getConnection()->affectingStatement($this->adjustTranslationTable("DELETE FROM $ltm_translations WHERE `group` = ?"), [$group]);
    }

    public function deleteTranslationByGroupLocale($group, $locale)
    {
        $ltm_translations = $this->getTranslationsTableName();
        $this->translation->getConnection()->affectingStatement($this->adjustTranslationTable("DELETE FROM $ltm_translations WHERE `group` = ? AND locale = ?"), [$group, $locale]);
    }

    public function updatePublishTranslations($newStatus, $group = null, $locale = null)
    {
        $ltm_translations = $this->getTranslationsTableName();
        if ($group) {
            if ($locale) {
                $this->translation->getConnection()->affectingStatement($this->adjustTranslationTable(<<<SQL
UPDATE $ltm_translations SET saved_value = value, status = ?, is_auto_added = 0 WHERE (ifnull(saved_value,'') <> ifnull(value,'') || (status <> ? and status <> ?)) AND `group` = ? AND locale = ?
SQL
                ), [$newStatus, $newStatus, Translation::STATUS_SAVED, $group, $locale]);
            } else {
                $this->translation->getConnection()->affectingStatement($this->adjustTranslationTable(<<<SQL
UPDATE $ltm_translations SET saved_value = value, status = ?, is_auto_added = 0 WHERE (ifnull(saved_value,'') <> ifnull(value,'') || (status <> ? and status <> ?)) AND `group` = ?
SQL
                ), [$newStatus, $newStatus, Translation::STATUS_SAVED, $group]);
            }
        } else {
            $this->translation->getConnection()->affectingStatement($this->adjustTranslationTable(<<<SQL
UPDATE $ltm_translations SET saved_value = value, status = ?, is_auto_added = 0 WHERE (ifnull(saved_value,'') <> ifnull(value,'') || (status <> ? and status <> ?))
SQL
            ), [$newStatus, $newStatus, Translation::STATUS_SAVED]);
        }
    }

    public function searchByRequest($q, $displayWhere, $limit)
    {
        $limitSQL = $limit > 0 ? 'LIMIT ' . $limit : '';
        $ltm_translations = $this->getTranslationsTableName();
        return $this->translation->getConnection()->select($this->adjustTranslationTable(<<<SQL
SELECT  
    id, status, locale, `group`, `key`, value, created_at, updated_at, source, saved_value, is_deleted, was_used
    FROM $ltm_translations rt WHERE (`key` LIKE ? OR value LIKE ?) $displayWhere
UNION ALL
SELECT NULL id, 0 status, lt.locale, kt.`group`, kt.`key`, NULL value, NULL created_at, NULL updated_at, NULL source, NULL saved_value, NULL is_deleted, NULL was_used
FROM (SELECT DISTINCT locale FROM $ltm_translations  WHERE 1=1 $displayWhere) lt
    CROSS JOIN (SELECT DISTINCT `key`, `group` FROM $ltm_translations  WHERE 1=1 $displayWhere) kt
WHERE NOT exists(SELECT * FROM $ltm_translations  tr WHERE tr.`key` LIKE BINARY kt.`key` AND tr.`group` = kt.`group` AND tr.locale = lt.locale)
      AND `key` LIKE ?
ORDER BY `key`, `group`, locale
$limitSQL
SQL
        ), [$q, $q, $q,]);
    }

    public function allTranslations($group, $displayLocales)
    {
        $displayWhere = $displayLocales ? " AND locale IN ('" . implode("','", $displayLocales) . "')" : '';
        $ltm_translations = $this->getTranslationsTableName();

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
FROM $ltm_translations ltm WHERE `group` = ? $displayWhere
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
(SELECT * FROM (SELECT DISTINCT locale FROM $ltm_translations WHERE 1=1 $displayWhere) lcs
    CROSS JOIN (SELECT DISTINCT `group`, `key` FROM $ltm_translations WHERE `group` = ? $displayWhere) grp) m
WHERE NOT EXISTS(SELECT * FROM $ltm_translations t WHERE t.locale = m.locale AND t.`group` = m.`group` AND t.`key` LIKE BINARY m.`key`)
ORDER BY `key` ASC
SQL
        ), [$group, $group], $this->getTranslation()->getConnectionName());
    }

    public function stats($displayLocales)
    {
        $displayWhere = $displayLocales ? " AND locale IN ('" . implode("','", $displayLocales) . "')" : '';
        $ltm_translations = $this->getTranslationsTableName();

        return $this->translation->getConnection()->select($this->adjustTranslationTable(<<<SQL
SELECT (mx.total_keys - lcs.total) missing, lcs.changed, lcs.cached, lcs.deleted, lcs.locale, lcs.`group`
FROM
    (SELECT sum(total) total, sum(changed) changed, sum(cached) cached, sum(deleted) deleted, `group`, locale
     FROM
         (SELECT count(value) total,
          sum(CASE WHEN status = 1 THEN 1 ELSE 0 END) changed,
          sum(CASE WHEN status = 2 AND value IS NOT NULL THEN 1 ELSE 0 END) cached,
          sum(is_deleted) deleted,
         `group`, locale
                FROM $ltm_translations lt WHERE 1=1 $displayWhere GROUP BY `group`, locale
          UNION ALL
          SELECT DISTINCT 0, 0, 0, 0, `group`, locale FROM (SELECT DISTINCT locale FROM $ltm_translations WHERE 1=1 $displayWhere) lc
              CROSS JOIN (SELECT DISTINCT `group` FROM $ltm_translations) lg) a
     GROUP BY `group`, locale) lcs
    JOIN (SELECT count(DISTINCT `key`) total_keys, `group` FROM $ltm_translations WHERE 1=1 $displayWhere GROUP BY `group`) mx
        ON lcs.`group` = mx.`group`
WHERE (lcs.total < mx.total_keys OR lcs.changed > 0 OR lcs.cached > 0 OR lcs.deleted > 0) AND locale <> 'json'
SQL
        ));
    }

    public function findMismatches($displayLocales, $primaryLocale, $translatingLocale)
    {
        $displayWhere = $displayLocales ? " AND locale IN ('" . implode("','", $displayLocales) . "')" : '';
        $ltm_translations = $this->getTranslationsTableName();

        return $this->translation->getConnection()->select($this->adjustTranslationTable(<<<SQL
SELECT DISTINCT lt.*, ft.ru, ft.en
FROM (SELECT * FROM $ltm_translations WHERE 1=1 $displayWhere) lt
    JOIN
    (SELECT DISTINCT mt.`key`, BINARY mt.ru ru, BINARY mt.en en
     FROM (SELECT lt.`group`, lt.`key`, group_concat(CASE lt.locale WHEN '$primaryLocale' THEN VALUE ELSE NULL END) en, group_concat(CASE lt.locale WHEN '$translatingLocale' THEN VALUE ELSE NULL END) ru
           FROM (SELECT value, `group`, `key`, locale FROM $ltm_translations WHERE 1=1 $displayWhere
                 UNION ALL
                 SELECT NULL, `group`, `key`, locale FROM ((SELECT DISTINCT locale FROM $ltm_translations WHERE 1=1 $displayWhere) lc
                     CROSS JOIN (SELECT DISTINCT `group`, `key` FROM $ltm_translations WHERE 1=1 $displayWhere) lg)
                ) lt
           GROUP BY `group`, `key`) mt
         JOIN (SELECT lt.`group`, lt.`key`, group_concat(CASE lt.locale WHEN '$primaryLocale' THEN VALUE ELSE NULL END) en, group_concat(CASE lt.locale WHEN '$translatingLocale' THEN VALUE ELSE NULL END) ru
               FROM (SELECT value, `group`, `key`, locale FROM $ltm_translations WHERE 1=1 $displayWhere
                     UNION ALL
                     SELECT NULL, `group`, `key`, locale FROM ((SELECT DISTINCT locale FROM $ltm_translations WHERE 1=1 $displayWhere) lc
                         CROSS JOIN (SELECT DISTINCT `group`, `key` FROM $ltm_translations WHERE 1=1 $displayWhere) lg)
                    ) lt
               GROUP BY `group`, `key`) ht ON mt.`key` LIKE BINARY ht.`key`
     WHERE (mt.ru NOT LIKE BINARY ht.ru AND mt.en LIKE BINARY ht.en) OR (mt.ru LIKE BINARY ht.ru AND mt.en NOT LIKE BINARY ht.en)
    ) ft
        ON (lt.locale = '$translatingLocale' AND lt.value LIKE BINARY ft.ru) AND lt.`key` LIKE BINARY ft.key
ORDER BY `key`, `group`
SQL
        ));
    }

    public function selectToDeleteTranslations($group, $key, $locale, $rowIds)
    {
        $ltm_translations = $this->getTranslationsTableName();
        return $this->translation->getConnection()->select($this->adjustTranslationTable(<<<SQL
SELECT GROUP_CONCAT(id SEPARATOR ',') ids FROM $ltm_translations tr
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN (?) AND id NOT IN ($rowIds)

SQL
        ), [$group, $key, $locale]);
    }

    public function selectKeys($src, $dst, $locales, $srcgrp, $srckey, $dstkey, $dstgrp)
    {
        $userLocales = "'" . implode("','", $locales) . "'";
        $ltm_translations = $this->getTranslationsTableName();

        if ((substr($src, 0, 1) === '*')) {
            if ($dst === null) {
                $rows = $this->translation->getConnection()->select($this->adjustTranslationTable($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM $ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN ($userLocales)
ORDER BY locale, `key`

SQL
                ), [
                    $srcgrp,
                    '%' . mb_substr($srckey, 1),
                ]);
            } else {
                $rows = $this->translation->getConnection()->select($this->adjustTranslationTable($sql = <<<SQL
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
                    mb_substr($dstkey, 1),
                ]);
            }
        } else if ((substr($src, -1, 1) === '*')) {
            if ($dst === null) {
                $rows = $this->translation->getConnection()->select($this->adjustTranslationTable($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM $ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN ($userLocales)
ORDER BY locale, `key`

SQL
                ), [
                    $srcgrp,
                    mb_substr($srckey, 0, -1) . '%',
                ]);
            } else {
                $rows = $this->translation->getConnection()->select($this->adjustTranslationTable($sql = <<<SQL
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
                    mb_strlen($srckey) - 1,
                ]);
            }
        } else {
            if ($dst === null) {
                $rows = $this->translation->getConnection()->select($this->adjustTranslationTable($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM $ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ? AND locale IN ($userLocales)
ORDER BY locale, `key`

SQL
                ), [
                    $srcgrp,
                    $srckey,
                ]);
            } else {
                $rows = $this->translation->getConnection()->select($this->adjustTranslationTable($sql = <<<SQL
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

        return $this->translation->getConnection()->insert($this->adjustTranslationTable(<<<SQL
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

    public function findFilledGroups()
    {
        return $this->translation->whereNotNull('value')->select(\DB::raw('DISTINCT `group`'))->get('group');
    }
}
