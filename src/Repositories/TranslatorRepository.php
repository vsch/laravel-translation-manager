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
}