<?php

return array(

    /**
     * Enable management of translations beyond just editing and command line manipulations
     *
     * @type boolean
     */
    'admin_enabled' => true,

    /**
     * Enable mismatch dashboard
     *
     * @type boolean
     */
    'mismatch_enabled' => false,

    /**
     * Exclude specific groups from Laravel Translation Manager.
     * This is useful if, for example, you want to avoid editing the official Laravel language files.
     *
     * @type array
     */
    'exclude_groups' => array(
        //'pagination',
        //'reminders',
        //'validation',
    ),

    /**
     * Exclude specific groups from Laravel Translation Manager in page edit mode.
     * This is useful for groups that are used exclusively for non-display strings like page titles and emails
     *
     * @type array
     */
    'exclude_page_edit_groups' => array(
        //'page-titles',
        //'reminders',
        //'validation',
    ),

    /**
     * determines whether missing keys are logged
     * @type boolean
     */
    'log_missing_keys' => false,

    /**
     * determines one out of how many user sessions will have a chance to log missing keys
     * since the operation hits the database for every missing key you can limit this by setting a
     * higher number depending on the traffic load to your site.
     *
     * @type int
     *
     * 1 - means every user
     * 10 - means 1 in 10 users
     * 100 - 1 in a 100 users
     * 1000 ....
     *
     */
    'missing_keys_lottery' => 100, // 1 in 100 of users will have the missing translation keys logged.

    /**
     * used to cache db translations that are used in the application.
     *
     * @type string     key used to cache saved values for keys that don't match translation files
     *                  format [prefix]["locale:key"]
     *
     */
    'cache_prefix' => 'translation-manager',

    /**
     * @type int        0 - as usual, write out files and set status for translations to SAVED,
     *
     *                  1 - on publish will only copy value to saved_value in the database and set the status to SAVED_CACHED
     *                  and add the changed keys to the translator cache so that the correct translation will be used. Used to
     *                  publish translations on production cluster or where write access to lang directory is not available.
     *
     *                  2 - write out files but act as if doing in database publish only, this setting is useful for accessing
     *                  translations from a local dev server to a production database for the purpose of updating translation files
     *                  for deployment. Lets you create the translation files but leaves the translations in the database in a state
     *                  where they will continue to be served up with the latest published version, not the outdated file versions.
     *
     *                  to be used by clustered systems where the translation files are determined at deployment and publishing
     *                  on one system does no good to the rest of the cluster.
     */
    'indatabase_publish' => 1,

    /**
     * used to provide the Yandex key for use in automatic Yandex translations
     *
     * @type string     Yandex translation key
     *
     * This key is free to obtain and use but is required to enable Yandex translations.
     *
     */

    'yandex_translator_key' => '',
);
