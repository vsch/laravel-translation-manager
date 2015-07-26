<?php

return array(
    // add local config overrides to translator manager configuration here

    /**
     * Enable deletion of translations
     *
     * @type boolean
     */
    'admin_enabled' => true,
    /**
     * determines whether missing keys are logged
     * @type boolean
     */
    'log_missing_keys' => true,
    /**
     * @type int        0 - as usual, write out files and set status for translations to SAVED,
     *
     *                  1 - on publish will only copy value to saved_value and set the status to SAVED_CACHED
     *                  and add the changed keys to the translator cache so that the correct translation will be used
     *
     *                  2 - write out files but act as if doing in database publish only, this setting is useful for accessing
     *                  translations from a local dev server to a production database for the purpose of updating translation files
     *                  for deployment. Lets you create the translation files but leaves the translations in the database in a state
     *                  where they will continue to be served up with the latest published version, not the outdated file versions.
     *
     *                  to be used by clustered systems where the translation files are determined at deployment and publishing
     *                  on one system does no good to the rest of the cluster.
     */
    'indatabase_publish' => 0,
    /**
     * used to provide the Yandex key for use in automatic Yandex translations
     *
     * @type string     Yandex translation key
     *
     * This key is free to obtain and use but is required to enable Yandex translations.
     *
     */

    //'yandex_translator_key' => '',

);
