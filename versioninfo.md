### Version Notes

The 1.x.x versions are for Laravel 4.2, 2.1.x versions are for Laravel 5.1+, 2.3.x for Laravel
5.3, 2.4.x for Laravel 5.4, 2.5.x for Laravel 5.5 and 2.6.x for Laravel 5.6 compatibility.

#### 2.6.34

* Fix: #121, improve Dutch translation, thanks to [@sebsel](https://github.com/sebsel)

#### 2.6.32

* Fix: #119, Call to undefined function Vsch\TranslationManager\getSupportedLocale(). 

#### 2.6.30

* Fix: unpublished mode shows groups not in LTM database as undefined. 
* Fix: unpublished mode showing translations marked deleted instead of as undefined
* Fix: add missing keys to the cache to not thrash the database on every access to their
  translations

#### 2.6.28

* Add: #101, is there a way to preview the changes before publishing, added button to WebUI to
  toggle and cookie setting to show site with unpublished translations. Also added
  `Translator::getShowUnpublished()` and `Translator::setShowUnpublished($showUnpublished)` to
  get/set mode and cookie if `useCookies` is enabled.
* Fix: view route missing optional group param, caused incorrect URL for group links in search,
  overview and mismatched translations
* Fix: editing translation in mismatches or search would update the current translations
  regardless of group. The server data was correct only react UI would show incorrect changes
  until translations table was refreshed from the server.
* Fix: use status in translation mismatches to highlight entry same as in translation table.
* Fix: remove base path prefix from translation reference paths.
* Fix: plural translations handling in pop-up, now removes the `:count ` from individual parts
  before sending for translation. Prefixes results with `:count ` if it was present in the
  original. This prevents Yandex translate from getting confused and results in better plural
  forms, especially for Russian which has 3.
* Change: Now `|:` generates plural forms with `:count ` prefix and toggles the prefix once
  the plurals are generated. Toggles in the pattern: plurals only prefixed, all prefixed,
  none-prefixed.

#### 2.6.26

* Add: PR merge from @vesper8 for customizing regex for reference search through the config. 
* Fix: clean out old recursive publish group code
* Fix: zipping translations used to inadvertently publish the translations
* Fix: JSON export now fills in any empty json -> ltm mapping keys (the translation values of
  the `json` locale) with the key value. If the translation was not imported and not changed by
  the user then the default value depends on the setting: `new-json-keys-primary-locale`. see
  below.
* Add: config options for customizing JSON translation key generation:

      /**
       * Set to true to have newly created JSON group entries get primary locale translation string as their key
       * false for having new keys default on export to ltm key. true by default
       *
       * @type boolean
       */
      'new-json-keys-primary-locale' => true,
      /**
       * What character to use for separating words in json generated keys. Only first char is used.
       *
       * @type string 
       */
      'new-json-keys-separator' => '-',

#### 2.6.24

* Fix: if `json` locale is part of the work set and current group is not JSON then any ui
  updates which are persisted on the server cause translation table to be reloaded.
* Fix: un-initialized session failing on compute display locales

#### 2.6.22     

* Fix: when configured for in database publish, delete import would only replace import import.
* Fix: locale working set would not include added locales if they were not part of any
  translations in the database
* Fix: ReferenceError when a missing translation entry appeared in non-editable locale
* Fix: show source information

#### 2.6.20          

* Fix: moved line caused all buttons to have undefined urls. I blame it on the cat.

#### 2.6.18

* Fix: server exception on new ltm session and switching translating locale before setting
  display locales.

#### 2.6.16

* Add: instructions for react manifest mods react ui files are found by mix
* Add: instructions for config options for react ui: disable ui, disable link
* Fix: use ltm translation files for React UI translations if not available in the database
* Fix: cached translations not being used if namespace was not '' or '*'
* Fix: modal to be easier to use.
* Fix: translation mods did not always reflect changes in the translation table unless
  refreshed.

#### 2.6.14

* Fix: erroneous inclusion of appDebug() instead of using config `app.debug`.

#### 2.6.12

* Fix: broken alternate db connections were not working properly and depending on which instance
  of connection was used then some used default connection while others the right connection.

  The standard for ALL connections is the Translation instance from TranslatorRepository and
  used by the Manager. One instance used for queries and setting/getting the connection name.

#### 2.6.10

**Need to run migrations**

```bash
$ php artisan vendor:publish --provider="Vsch\TranslationManager\ManagerServiceProvider" --tag=public --force
$ php artisan vendor:publish --provider="Vsch\TranslationManager\ManagerServiceProvider" --tag=migrations
$ php artisan migrate
```

and

* Add: React UI for LTM
* Add: `ui_settings` to `ltm_user_locales` table to store the user's react ui app settings for
  persistence. Sessions are too short and too much data for one cookie and splitting is a pain.
* Fix: change `ltm_user_locales` index on user_id to unique
* Fix: JSON json locale, used for key mapping was never saved to the database on publishing of
  the JSON group.
* Fix: creating new keys in the JSON group caused the `json` locale keys to stay empty instead
  of defaulting to key name.

#### Next: 2.6.6

* Fix: Pass x-edit popup title translations to JS

#### 2.6.6

* Fix: #113, Error with PHP 7.2

#### 2.6.4

* Add: JSON translation file handling
  * Stored in the LTM table under `JSON` group. JSON translation keys to LTM translation keys
    are stored in the same group under the `json` locale.
  * On import LTM keys are generated from Alphanumeric characters with _ between runs of
    Alphanumeric up to a maximum of 120 or `'json_dbkey_length'`, whichever is smaller.
  * On export the JSON to LTM key map is exported to `json.json` file in `resources/lang`
    directory. It is needed for efficient conversion of JSON to LTM keys
  * All features of LTM translations are supported for JSON translations: import, export, zip,
    in-database publishing, display database value via additional `useDB` argument added to
    `getFromJson()`
* Fix: usage information was not being set

#### 2.6.2

* Fix: replace aliases with facades, merged PR from **[aiankile](https://github.com/aiankile)**

#### 2.6.0

* Fix: update for Laravel 5.6, merged PR from **[aiankile](https://github.com/aiankile)**

#### 2.5.6

* Add: JSON translation file handling
  * Stored in the LTM table under `JSON` group. JSON translation keys to LTM translation keys
    are stored in the same group under the `json` locale.
  * On import LTM keys are generated from Alphanumeric characters with _ between runs of
    Alphanumeric up to a maximum of 120 or `'json_dbkey_length'`, whichever is smaller.
  * On export the JSON to LTM key map is exported to `json.json` file in `resources/lang`
    directory. It is needed for efficient conversion of JSON to LTM keys
  * All features of LTM translations are supported for JSON translations: import, export, zip,
    in-database publishing, display database value via additional `useDB` argument added to
    `getFromJson()`
* Fix: usage information was not being set

#### 2.5.4

* Fix: #99, Regex doesnt clear on Php7.1

#### 2.5.2

* Fix: #98, indatabase_publish not working as intended

#### 2.4.36

* [ ] Fix: #106, Working with arrays

* [ ] Fix: #91, Cookies generating wrong locales

* [ ] Add: preview mode for editors/admins, fix for #101, Is there a way to preview the changes
      before publishing

#### 2.4.34

* Fix: LoaderInterface to FileLoader

#### 2.4.32

* Fix: #98, indatabase_publish not working as intended

#### 2.3.8

* Fix: #98, indatabase_publish not working as intended

#### 2.5.0

* Fix: update for Laravel 5.5

#### 2.4.30

* Fix: #96, laravel 5.5 support?, remove the <5.5 restriction from laravel version

#### 2.4.28

* Fix: additional Gate access for `Manager::ABILITY_BYPASS_LOTTERY` in `Translator`

#### 2.4.26

* Fix: #94, Laravel Gate, possible performance impact of logging missing keys

#### 2.4.24

* Fix: #93, laravel 5.4 default_connection setting in `Translation` and `UserLocales` models.

#### 2.4.22

* Fix: #92, Translation files can not be loaded

#### 2.4.20

* Fix: #90, Incompatibilities with mcamara/laravel-localization. This is an API breaking fix
  because order of arguments was changed to match the contract and/or the parent methods:

  From `transChoice($id, $number, array $parameters = array(), $domain = 'messages', $locale =
  null, $useDB = null)` to `transChoice($id, $number, array $parameters = array(), $locale =
  null, $domain = 'messages', $useDB = null)`

  From `trans($id, array $parameters = array(), $domain = 'messages', $locale = null, $useDB =
  null)` to `trans($id, array $parameters = array(), $locale = null, $domain = 'messages',
  $useDB = null)`

  From `get($key, array $replace = array(), $locale = null, $useDB = null)` to `get($key, array
  $replace = array(), $locale = null, $fallback = true, $useDB = null)`

#### 2.4.14

* Fix: #88, Import fails without feedback, file translations being deleted on publishing

#### 2.4.12

* Add: `Vsch\\TranslationManager\\Events\\TranslationsPublished` event class with two
  attributes:
  * `groups`, string of the groups parameter, either `*` if all or group name
  * `errors`, array of errors resulting from the publishing.

  Event is generated when publish is invoked through the Web UI or export from the command line.

* Fix: #86, After publish file takes another array format. Added an error message when incorrect
  translation key dot convention usage results in a translation being lost because its value is
  replaced with an array of its children.

#### 2.4.11

* Fix: #85, fix migrations rollback

#### 2.4.10

* Fix: #83, PHP7 fixes merged.

#### 2.4.8

* Fix: #81, Update database layer, add postgres support

#### 2.4.6

* Fix: #80, Move query to repository

* Fix: #77, log_missing_keys working even if set to false

#### 2.4.5

* Fix: #69, fix overflow of edit in place container

* Fix: refactor all database access out to `TranslatorRepository`, thanks to
  [Alex Mokrenko](https://github.com/al0mie)

* Fix: #71, Trying to get property of non-object exception

* Fix: #72, $locales array elements need to be filtered before passing to view

#### 2.4.4

* Change: merge RU and UK locale translations thanks to
  [Alex Mokrenko](https://github.com/al0mie)

* Fix: disable in place edit mode if not logged in

* Fix: reformat code to PSR-2

* Fix: #66, Add a note that only MySQL connection is supported

#### 2.4.3

* roll back changes in 2.4.2 because it only applies to Laravel 5.3

#### 2.4.2

* Fix: #63, Setting PDO Fetch mode to FETCH_CLASS, this now is supported but config setting
  `pdo_fetch_mode_enabled` must be set to `true`.

#### 2.4.1

* Fix: #61, Search in __ functions

#### 2.4.0

* Fix: #60, 5.4 compatibility

#### 2.3.7

* Fix: #63, Setting PDO Fetch mode to FETCH_CLASS, this now is supported if `setFetchMode`
  method is available on `connection` instance.

#### 2.3.6

* Add: #41, Editor button tooltips, translation passed from PHP to JS for tooltips for pop-up
  editor button titles based on selected interface language.

  Requires adding `{!! getWebUITranslations() !!}` to layout master before including
  `translator.js`, otherwise english defaults will be used.

  * title-save-changes: default "Save changes"
  * title-cancel-changes: default "Cancel changes"
  * title-translate: default "Translate"
  * title-convert-key: default "Convert translation key to text"
  * title-generate-plurals: default "Generate plural forms"
  * title-clean-html-markdown: default "Clean HTML markdown"
  * title-capitalize: default "Capitalize text"
  * title-lowercase: default "Lowercase text"
  * title-capitalize-first-word: default "Capitalize first word"
  * title-simulated-copy: default "Copy text to simulated clipboard (page refresh clears
    contents)"
  * title-simulated-paste: default "Paste text from simulated clipboard"
  * title-reset-editor: default "Reset editor contents"
  * title-load-last: default "Load last published/imported value"

#### 2.3.5

* Fix: #55, Missing `use_cookies` configuration. Fix typo on default value from translation
  manager package to `true`.

#### 2.3.4

* Fix: #52, Error when publishing translations with openbasedir active. Thanks to @lltyre.

#### 2.3.3

* Fix: #51, Completely overhaul German translations. Thanks to @pille1842.

#### 2.3.2

* Fix: #48, Can't install this package for Laravel 5.3, updated composer dependency versions

#### 2.3.1

* Fix: use `controller->middleware()` closure to handle controller constructor initialization
  requiring middleware to be running.

#### 2.3.0

* Change: Laravel 5.3 compatibility
* Add: routes entry for translations. Old routes handling no longer works, now need to add a
  call to `Translator::routes()` wrapped in appropriate middleware and prefix grouping:

      \Route::group(['middleware' => 'web', 'prefix' => 'translations'], function () {
          Translator::routes();
      });

  to be added to routes/web.php to create translator routes
* Fix: search to list columns explicitly
* Fix: Controller cookie values initialization moved out of constructor, cookies were not
  decrypted otherwise
* Fix: if non-default db connection is set and it causes exception then connection is reset to
  default. Otherwise, you have to delete the cookie from the browser.
* Fix: wild card key operation for copy was failing due to extra column in insert statement.
* Add: Powered by Yandex.Translate as required by their new terms.
* Fix: Yandex documentation url.
* Fix: Update yandex supported languages.
* Add: Merged [yurtesen](https://github.com/yurtesen) PR for in place edit mode requiring
  minimal view modifications. It is now the default in place edit mode.
* Fix: Update `laravelcollective/html` version to 5.3

#### 2.1.4

* Fix: #22, find function isn't supported in laravel 5
* Add: show references for keys that have source reference information. Need to run `Add
  References` to update source references and add new groups/keys from source files and views.
  Currently app/ and all view paths are checked. Command line can give specific path to search.
  need to run migrations for this update: [Installation: Publishing And Running Migrations]

#### 2.1.3

* Fix: #31, translations:import is not working for windows based paths

#### 2.1.2

* Fix: #32, `user_list_connection` in `db_connections` configuration was ignored instead of
  being used to retrieve the user list for the given connection.
* Fix: change default config sample from `caouecs/laravel4-lang` to `caouecs/laravel-lang` as
  needed by this package for Laravel 5.
* Fix: #33, Laravel 5.2 - `"symfony/finder": "2.8.*|3.0.*"` dependency mismatch

#### 2.1.1

* Fix: #30, mismatch in class names
* Add: config option `markdown_key_suffix` and code to handle markdown to html conversion of
  translations for keys ending in this suffix. Converted HTML is stored in a key with the suffix
  removed. For now this is an experimental feature.

#### 2.1.0

* Change: upgrade to Laravel 5.2 (.31 to be exact)

* Change: using abilities to handle all LTM related authorization and providing a list of
  translation editors. Abilities:
  * `ltm-admin-translations` true/false for users that can administer LTM through web UI

  * `ltm-bypass-lottery` true/false for users that bypass the missing key lottery. For these
    users all sessions track missing keys.

  * `ltm-list-editors` true/false

  Takes a reference argument in which to return an array of objects with `id`, `email` and
  optional `name` fields used for managing per locale access. `connection` parameter is the
  current connection name that can used to modify how the list is generated.

  See
  [Enabling per locale user access control](../../wiki/Configuration#enabling-per-locale-user-access-control)

* Change: remove dependency on UserPrivilegeManager package. It was only needed for Laravel 4.

* Add: Middleware to handle equivalent of `listen('route.after'...)` event so that translation
  manager's cached translations can be persisted to a cache.

* Fix: #29, Closure in config file breaks config:cache, removed the config closure. Per locale
  access control implemented using LtmPolicy with all abilities in version 2.1.0. LTM provides
  an empty user list for locale management by default.

* Add: color highlight for key regex text box and radio button translation filters to visually
  signal when key list is incomplete and which filter is responsible:
  * no filter: normal
  * filter with matched keys: blue
  * filter with no matched keys: red

#### 2.0.41

* Fix: #24, Translation Work Orders: Allow users to access only some languages. Basic user per
  locale access management available to users for whom `UserCan::admin_translations()` return
  true. See
  [Enabling per locale user access control](../../wiki/Configuration#enabling-per-locale-user-access-control)
* Fix: access control for non-admin users so that they cannot inadvertently delete keys or
  modify locales to which they have no access.

#### 2.0.40

* Fix: #28, name of DB connection should be definable in config file - feature request. Now can
  add `database_name` to package config which will override the database name defined for the
  connection. Similarly, alternate connections can have their own database name settings, use
  the globally defined one in package config file or use the connection's database name.

#### 2.0.39

#### 2.0.38

* merge #26, handling of numeric translation keys

#### 2.0.37

* fix#25, Delete button not working for some keys, solved by double url encoding translation key
  to get around Laravel url decoding before applying the routing logic to the URL.

#### 2.0.36

* fix workbench language files were not saved and caused a server exception, suspect this was
  introduced with upgrade to PHP 7.0 which handles array_replace_recursive differently than PHP
  5.6. It appears that 7.0 does a copy by reference not by value if there is only one reference
  to an item in an array.
* fix improper test for existence of language file was not testing if the path was a directory.
* change laravelcollective/html dependency to 5.2 to make it Laravel 5.2 compatible
* change all Input:: to Request:: for Laravel 5.2 compatibility
* add 'web' to middleware in config otherwise Laravel 5.2 does not initialize session object

#### x.0.35

* add database table prefix handling in hand rolled queries

#### x.0.34

* fix cached entries were filtered out by query so they never showed in web UI
* add merge PR from @killtw for Laravel 5.2 support in composer.json

#### x.0.33

* add cached column to dashboard to show translations that were published to the cache, but have
  not been saved to server files
* fix a bunch of typos in translation.css attributes
* change translations that have changes published to the cached have table cell background hue
  matching cached stats colors in dashboard
* change deleted column in dashboard to hot-pink-purple and show stats in bold and color so they
  stand out
* change deleted unpublished translation rows in table to match hue of the dashboard color

#### x.0.32

* fix translations were not using selected remote connection for edit and default connection
  values were being displayed in the translation table for empty remote translations
* add cache translations for the group being edited for the session so that all translations for
  the page are loaded in one db query
* fix empty translations would show in the dashboard as unpublished when in-database-publish
  mode is used
* add table cell highlight of unpublished changed translations so that they are easier to locate
* add table cell highlight of cached published translations so that they stand out from actual
  unpublished changes
* fix dashboard no longer shows cached published translations as changed
* fix move keys would fail if destination had existing key. Now conflicting destination keys are
  deleted and replaced by the moved keys.
* fix translation edit pop-up to caps now lower cases the affected text then proper caps it.
* add translation edit pop-up button to cap just the first character of the translation and the
  rest lowercase.
* add auto lower case all except the first character of translations only if translation text
  contains only letters, digits or white space, optionally terminated by a period.
* add mark visible translations as deleted, and un-mark visible translations as deleted.
* add persistence of translation filter selection between page refreshes.
* change 'Show Unpublished' to only show changed or deleted translations. Used to also show
  missing translations.
* add 'Need Attention' filter option to show missing, changed or deleted translations. This used
  to be what 'Show Unpublished' displayed.
* add 'New' filter option to show only rows that have no translations.
* Change remove 'Show' prefix from all filter radio buttons to save real-estate.
* fix importing with 'delete all then import' was not preserving usage information.
* fix Auto Translate and Auto Fill to only affect rows that are visible, ie. not filtered out.

#### x.0.31

* fix error reporting for wild card key operations would show empty alert.
* fix discrepancy between changed status in dashboard view and show changed radio button
  translation results.
* add unpublished radio button to show translations that need attention or will be modified on
  next publish. These are: deleted, changed or missing.
* add text box for regex to show translations whose keys match regex in the text box. Applied on
  top of other radio button filtering.
* add filtered key stats display in the translations table key column. Shows total displayed
  after regex filtering vs total from radio buttons vs total keys.
* add `show_locales` config option to limit the locales to ones contained in the option. If
  empty or not provided then all locales from the database will be shown. Only affects locales
  shown. All locales are imported and exported regardless of this setting.

#### 1.0.30 - 2.0.30

* fix import of locale sub-directories. Note that in L4 you access translations in
  sub-directories by using the `.` as separator in L5 this is `/`. In translation manager the
  group will have a period in all Laravel Versions. For example for directory structure like the
  following:

```text
lang
    └── en
        └── test
            ├── sub-dir
            │ └── sub-sub-test.php
            └── subtest.php
```

You would access translations:

* in L5 as `@lang('test/subtest.translation-key')` for `subtest.php` and as
  `@lang('test/sub-dir/sub-sub-test.translation-key')` for `sub-sub-test.php`.

* in L4 as `@lang('test.subtest.translation-key')` for `subtest.php` and as
  `@lang('test.sub-dir.sub-sub-test.translation-key')` for `sub-sub-test.php`.

In both cases the group in translation manager will show as `test.subtest` and
`test.sub-dir.sub-sub-test`. It was too big a pain to support `/` in group names because these
are passed in the URL as parameters and having slashes messes things up. URL encoding is no help
because Laravel cannot resolve the path.

#### x.0.29

* fix formSubmit() was not properly processing translation result for inPlaceEdit() mode
* fix moved csrf meta from index.blade.php to layouts.master.blade.php so that all pages that
  extend layouts.master can use in-place-edit mode.
* move most of the details from the readme to the wiki.
* fix runtime exception if workbench projects are present but the config has an empty include
  for workbench config.
* fix replace deprecated \Route::after()
* add key usage logging. Similar to missing keys except it logs keys that were accessed.
* add alternate database connection to config and to web interface. Can manage production
  translations from local dev environment.
* fix keyOps to handle group names for wbn: and vnd: prefixed names. Now can move/copy keys from
  normal groups to these ones.
* fix translations page now redirects to 'no group selected' url if the requested group no
  longer exists.
* change locales that are part of the working set are moved to the front of the lists to ease
  their selection.
* add batch insert mode for new translations. Uses insert into with a list of values instead of
  invoking db connection for each one. Speeds up imports of new translations and initial import
  significantly, especially for remote database connections.
* add now missing translation keys for packages from vendor and workbench directories will have
  these keys added to the appropriate vendor/workbench group if there are no translations for
  the package in the project. Makes working on code in workbench and vendor directories that
  references a new translation easier. Previously the new key would be created under the package
  name but in the project space instead of the vendor/workbench (ie. vnd: or wbn: prefixed
  group) name space.
* add buttons to filter translation rows: show all, show non-empty, show empty, show changed,
  show deleted
* fix indatabase_publish was not working since usage tracking was added.
* add auto translate buttons to all locale column headers not just the translating locale.
* fix exception when 'log_missing_keys' is disabled in configuration and translation manager
  in-place-edit mode is used. Issue: #10
* add `locales` config entry for additional locales that are to be included in the locales list
  even if these don't have translation files or translations in the database. That way you can
  add locales by adding translations for them in translation manager without having to create
  the directories with a single group file and a single translation key to get locales added to
  your translations.

[Installation: Publishing And Running Migrations]: ../../wiki/Installation#publishing-and-running-migrations

