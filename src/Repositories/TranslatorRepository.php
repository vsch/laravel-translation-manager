<?php

namespace Vsch\TranslationManager\Repositories;

use Vsch\TranslationManager\Repositories\Interfaces\ITranslatorRepository;
use Vsch\TranslationManager\Models\Translation;

class TranslatorRepository implements ITranslatorRepository
{
    protected $translation;
    protected $connection;
    protected $tableName;
    protected $tableRenameNeeded;

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
    protected function adjustTranslationTable($sql)
    {
        $adjustedSql = $this->tableRenameNeeded ? str_replace(' ltm_translations ', ' ' . $this->tableName . ' ', $sql) : $sql;
        return $adjustedSql;
    }

    public function updateIsDeletedByIds($rowIds)
    {
        $this->connection->update($this->adjustTranslationTable("UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND id IN ($rowIds)"));
    }

    public function setNotUsedForAllTranslations()
    {
        $this->connection->affectingStatement($this->adjustTranslationTable(<<<SQL
            UPDATE ltm_translations SET was_used = 0 WHERE was_used <> 0
SQL
        ));
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

    public function deleteTranslationsForIds($translationIds)
    {
        $this->connection->unprepared($this->adjustTranslationTable(<<<SQL
          DELETE FROM ltm_translations WHERE id IN ($translationIds)
SQL
        ));
    }

    public function updateValuesByStatus()
    {

        $this->connection->affectingStatement($this->adjustTranslationTable(<<<SQL
UPDATE ltm_translations SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?)
SQL
        ), [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED]);
    }
}
