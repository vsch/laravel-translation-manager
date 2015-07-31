<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Routes group config
    |--------------------------------------------------------------------------
    |
    | The default group settings for the elFinder routes.
    |
    */
    'route' => [
        'prefix' => 'translations',
        'middleware' => 'auth',
    ],

    /**
     * Specify the locale that is used for creating the initial translation strings. This locale is considered
     * to be the driver of all other translations.
     *
     * @type string
     */
    'primary_locale' => 'en',

    /**
     * Specify the prefix used for all cookies, session data and cache persistence.
     *
     * @type string
     */
    'persistent_prefix' => 'g2Lu2pyz8QcVrxhL32eN',

    /**
     * Enable management of translations beyond just editing and command line manipulations
     *
     * @type boolean
     */
    'admin_enabled' => true,

    /**
     * Specify export formatting options:
     *
     * PRESERVE_EMPTY_ARRAYS - preserve first level translations that are empty arrays
     * USE_QUOTES - use " instead of ' for wrapping strings
     * USE_HEREDOC - use <<<'TEXT' for wrapping string that contain \n
     * USE_SHORT_ARRAY - use [] instead of array() for arrays
     * SORT_KEYS - alphabetically sort keys withing an array
     *
     * @type string | array
     */
    'export_format' => array(
        'PRESERVE_EMPTY_ARRAYS',
        //'USE_QUOTES',
        'USE_HEREDOC',
        'USE_SHORT_ARRAY',
        'SORT_KEYS',
    ),

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
    'indatabase_publish' => 0,

    /**
     * used to provide the Yandex key for use in automatic Yandex translations
     *
     * @type string     Yandex translation key
     *
     * This key is free to obtain and use but is required to enable Yandex translations. Visit: https://tech.yandex.com/translate/
     *
     */

    'yandex_translator_key' => '',

    /**
     * used to provide configuration on where the translation files are stored and where to write them out.
     *
     * This configuration provides the difference in layout between Laravel 4 & 5.
     * It also can be used to inlcude language files from vendor directories without having to export them to the
     * standard lang/ directory for those cases where these files are modified directly so that a git commit can
     * be done in-place.
     *
     * It can also be used to include lang/ files from projects in the workbench subdirectory for Laravel 4.2
     * or Laravel 5 even though Laravel 5 does not support workbench subdirectory the same way it does in
     * version 4.2
     *
     *
     * keys:            - for all keys / means root of the application directory.
     *
     *                  Placeholder variables can be included as path parts in 'group' definitions. The values for
     *                  these place holders are taken from the path definition during import and the group string
     *                  value in the database during export. The first matching pattern will be used so make sure that
     *                  your definitions have a uniquifying string for different as used in the defaults for the
     *                  'vendor' and 'workbench' definitions.
     *
     *                  {package} - the name of the package, (laravel-translation-manager), it will also be used
     *                  as the namespace prefix for the group, with :: appended as a suffix to the package name, {package}::{group}
     *
     *                  {vendor} - the vendor part of the package, (vsch)
     *
     *                  {locale} - the subdirectories for the locales
     *
     *                  {group} - will be taken from the *.php file name found in the directory. Do not include
     *                  this placeholder in the path spec, it is assumed and handled differently than other parts.
     *                  Mainly, any sub-directories between
     *                  the 'path' spec and the file name, whether in lang, packages, workbench or any other path will
     *                  be included as . separated prefixes to the group. So you are free to organize your {locale}
     *                  sub-directories with other subdirectories. All will be recognized and managed by the translation
     *                  manager.
     *
     * values:          if the value is a string then it is assumed to be a 'path' spec, and the default spec for the
     *                  'db_group' definition, which provides an encoding template for the value stored for the group
     *                  in the database, will be provided as follows:
     *
     *                  lang        - 'db_group' => '{group}'
     *                        (4.2) - 'path' => '/app/lang/{locale}'
     *                        (5.x) - 'path' => '/resources/lang/{locale}'
     *
     *                  packages    - 'db_group' => '{package}::{group}'
     *                        (4.2) - 'path' => '/app/lang/packages/{locale}/{package}'
     *                        (5.x) - 'path' => '/resources/lang/vendor/{package}/{locale}'
     *
     *                  workbench   - 'db_group' => 'workbench.{vendor}.{package}::{group}'
     *                        (4.2) - 'path' => '/workbench/{vendor}/{package}/src/lang/{locale}'
     *                        (5.x) - 'path' => '/workbench/{vendor}/{package}/resources/lang/{locale}'
     *
     *                  vendor      - 'db_group' => 'vendor.{vendor}.{package}::{group}'
     *                        (4.2) - 'path' => '/vendor/{vendor}/{package}/src/lang/{locale}'
     *                        (5.x) - 'path' => '/vendor/{vendor}/{package}/resources/lang/{locale}'
     *
     *
     *                  for any other types you need to use the array value definition and specify path and group
     *                  templates explicitly.
     *
     *                  array values can have the following:
     *                  'path' - the path spec for the element, see above
     *
     *                  'group' - the group spec template for this element
     *
     *                  'include' - valid for all types other than 'lang' and 'packages', it is string or an array
     *                  defining package combinations to include, each
     *                  element in the array is assumed to be {vendor}/{package}, if either {vendor} or {package} is
     *                  missing or * then all such sub-directories will be included. Example:
     *
     *                  'vsch/'  - will include all packages under vsch/
     *
     *                  '/laravel-translation-manager' - all packages of that name regardless of the vendor will be
     *                  included.
     *
     *                  '/' or '' or if the 'include' array is ommited. - will include all vendors and packages.
     *
     *                  NOTE: if the array is empty then no vendors will be included. This is the default for
     *                  'vendors' and no definition is the default for 'workbench' which will include language files
     *                  for all workbench packages.
     *
     * array keys:
     *
     * 'path'           - the root path for this element's language definitions, Any placeholder values placeholder
     *                  will take the name from the part inside {} and the value from the actual path found on the disk.
     *
     *
     * 'lang'           - defines the location of the application's standard language files relative to the root
     *                  of the application. Laravel 4.2 '/app/lang', 5.x '/resources/lang'.
     *
     * 'packages'        - defines the location of the package override directory
     *
     * 'workbench'       - defines the location of the packages you are including in the project which are also
     *                  the source of the package that you are developing.
     *
     * @type array
     *
     * Please read above before changing.
     */
    'language_dirs' => array(
        'lang' => '/resources/lang/{locale}',
        'packages' => '/resources/lang/vendor/{package}/{locale}',
        'workbench' => '/workbench/{vendor}/{package}/resources/lang/{locale}',
        'vendor' => array(
            'path' => '/vendor/{vendor}/{package}/resources/lang/{locale}',
            'include' => array(),
        ),
    ),
    /**
     *
     * Provide the prefix for the root of the zip file
     * if a path from language_dirs does not start with this prefix then language files exported
     * for that part will include the full path. Therefore define the most common root path
     * / means application root.
     *
     */
    'zip_root' => '/resources',

);
