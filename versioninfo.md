### Version Notes

The 1.x.x versions are for Laravel 4.2, 2.1.x versions are for Laravel 5.1+m 2.2.x for Laravel
5.3 compatibility.

#### 2.3.0

- Change: Laravel 5.3 compatibility
- Add: routes entry for translations. Old routes handling no longer works, now need to add a
  call to `Translator::routes()` wrapped in appropriate middleware and prefix grouping:
   
        \Route::group(['middleware' => 'web', 'prefix' => 'translations'], function () {
            Translator::routes();
        });

    to be added to routes/web.php to create translator routes
- Fix: search to list columns explicitly
- Fix: Controller cookie values initialization moved out of constructor, cookies were not
  decrypted otherwise
- Fix: if non-default db connection is set and it causes exception then connection is reset
      to default. Otherwise, you have to delete the cookie from the browser.
- Fix: wild card key operation for copy was failing due to extra column in insert statement.
- Add: Powered by Yandex.Translate as required by their new terms.
- Fix: Yandex documentation url.
- Fix: Update yandex supported languages.

#### 2.1.4

- Fix: #22, find function isn't supported in laravel 5
- Add: show references for keys that have source reference information. Need to run `Add
  References` to update source references and add new groups/keys from source files and views.
  Currently app/ and all view paths are checked. Command line can give specific path to search.
  need to run migrations for this update: [Installation: Publishing And Running Migrations]

#### 2.1.3

- Fix: #31, translations:import is not working for windows based paths

#### 2.1.2

- Fix: #32, `user_list_connection` in `db_connections` configuration was ignored instead of
  being used to retrieve the user list for the given connection.
- Fix: change default config sample from `caouecs/laravel4-lang` to `caouecs/laravel-lang` as
  needed by this package for Laravel 5.
- Fix: #33, Laravel 5.2 - "symfony/finder": "2.8.*|3.0.*" dependency mismatch

#### 2.1.1

- Fix: #30, mismatch in class names
- Add: config option `markdown_key_suffix` and code to handle markdown to html conversion of
  translations for keys ending in this suffix. Converted HTML is stored in a key with the suffix
  removed. For now this is an experimental feature.

#### 2.1.0

- Change: upgrade to Laravel 5.2 (.31 to be exact)

- Change: using abilities to handle all LTM related authorization and providing a list of
  translation editors. Abilities:
    - `ltm-admin-translations` true/false for users that can administer LTM through web UI

    - `ltm-bypass-lottery` true/false for users that bypass the missing key lottery. For these
      users all sessions track missing keys.

    - `ltm-list-editors` true/false

    Takes a reference argument in which to return an array of objects with `id`, `email` and
    optional `name` fields used for managing per locale access. `connection` parameter is the
    current connection name that can used to modify how the list is generated.

    See
    [Enabling per locale user access control](../../wiki/Configuration#enabling-per-locale-user-access-control)

- Change: remove dependency on UserPrivilegeManager package. It was only needed for Laravel 4.

- Add: Middleware to handle equivalent of `listen('route.after'...)` event so that translation
  manager's cached translations can be persisted to a cache.

- Fix: #29, Closure in config file breaks config:cache, removed the config closure. Per locale
  access control implemented using LtmPolicy with all abilities in version 2.1.0. LTM provides
  an empty user list for locale management by default.

- Add: color highlight for key regex text box and radio button translation filters to visually
  signal when key list is incomplete and which filter is responsible:
    - no filter: normal
    - filter with matched keys: blue
    - filter with no matched keys: red

#### 2.0.41

- Fix: #24, Translation Work Orders: Allow users to access only some languages. Basic user per
  locale access management available to users for whom `UserCan::admin_translations()` return
  true. See
  [Enabling per locale user access control](../../wiki/Configuration#enabling-per-locale-user-access-control)
- Fix: access control for non-admin users so that they cannot inadvertently delete keys or
  modify locales to which they have no access.

#### 2.0.40

- Fix: #28, name of DB connection should be definable in config file - feature request. Now can
  add `database_name` to package config which will override the database name defined for the
  connection. Similarly, alternate connections can have their own database name settings, use
  the globally defined one in package config file or use the connection's database name.

#### 2.0.39

#### 2.0.38

- merge #26, handling of numeric translation keys

#### 2.0.37

- fix#25, Delete button not working for some keys, solved by double url encoding translation key
  to get around Laravel url decoding before applying the routing logic to the URL.

#### 2.0.36

- fix workbench language files were not saved and caused a server exception, suspect this was
  introduced with upgrade to PHP 7.0 which handles array_replace_recursive differently than PHP
  5.6. It appears that 7.0 does a copy by reference not by value if there is only one reference
  to an item in an array.
- fix improper test for existence of language file was not testing if the path was a directory.
- change laravelcollective/html dependency to 5.2 to make it Laravel 5.2 compatible
- change all Input:: to Request:: for Laravel 5.2 compatibility
- add 'web' to middleware in config otherwise Laravel 5.2 does not initialize session object

#### x.0.35

- add database table prefix handling in hand rolled queries

#### x.0.34

- fix cached entries were filtered out by query so they never showed in web UI
- add merge PR from @killtw for Laravel 5.2 support in composer.json

#### x.0.33

- add cached column to dashboard to show translations that were published to the cache, but have
  not been saved to server files
- fix a bunch of typos in translation.css attributes
- change translations that have changes published to the cached have table cell background hue
  matching cached stats colors in dashboard
- change deleted column in dashboard to hot-pink-purple and show stats in bold and color so they
  stand out
- change deleted unpublished translation rows in table to match hue of the dashboard color

#### x.0.32

- fix translations were not using selected remote connection for edit and default connection
  values were being displayed in the translation table for empty remote translations
- add cache translations for the group being edited for the session so that all translations for
  the page are loaded in one db query
- fix empty translations would show in the dashboard as unpublished when in-database-publish
  mode is used
- add table cell highlight of unpublished changed translations so that they are easier to locate
- add table cell highlight of cached published translations so that they stand out from actual
  unpublished changes
- fix dashboard no longer shows cached published translations as changed
- fix move keys would fail if destination had existing key. Now conflicting destination keys are
  deleted and replaced by the moved keys.
- fix translation edit pop-up to caps now lower cases the affected text then proper caps it.
- add translation edit pop-up button to cap just the first character of the translation and the
  rest lowercase.
- add auto lower case all except the first character of translations only if translation text
  contains only letters, digits or white space, optionally terminated by a period.
- add mark visible translations as deleted, and un-mark visible translations as deleted.
- add persistence of translation filter selection between page refreshes.
- change 'Show Unpublished' to only show changed or deleted translations. Used to also show
  missing translations.
- add 'Need Attention' filter option to show missing, changed or deleted translations. This used
  to be what 'Show Unpublished' displayed.
- add 'New' filter option to show only rows that have no translations.
- Change remove 'Show' prefix from all filter radio buttons to save real-estate.
- fix importing with 'delete all then import' was not preserving usage information.
- fix Auto Translate and Auto Fill to only affect rows that are visible, ie. not filtered out.

#### x.0.31

- fix error reporting for wild card key operations would show empty alert.
- fix discrepancy between changed status in dashboard view and show changed radio button
  translation results.
- add unpublished radio button to show translations that need attention or will be modified on
  next publish. These are: deleted, changed or missing.
- add text box for regex to show translations whose keys match regex in the text box. Applied on
  top of other radio button filtering.
- add filtered key stats display in the translations table key column. Shows total displayed
  after regex filtering vs total from radio buttons vs total keys.
- add `show_locales` config option to limit the locales to ones contained in the option. If
  empty or not provided then all locales from the database will be shown. Only affects locales
  shown. All locales are imported and exported regardless of this setting.

#### 1.0.30 - 2.0.30

- fix import of locale sub-directories. Note that in L4 you access translations in
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

    - in L5 as `@lang('test/subtest.translation-key')` for `subtest.php` and as
      `@lang('test/sub-dir/sub-sub-test.translation-key')` for `sub-sub-test.php`.

    - in L4 as `@lang('test.subtest.translation-key')` for `subtest.php` and as
      `@lang('test.sub-dir.sub-sub-test.translation-key')` for `sub-sub-test.php`.

    In both cases the group in translation manager will show as `test.subtest` and
    `test.sub-dir.sub-sub-test`. It was too big a pain to support `/` in group names because
    these are passed in the URL as parameters and having slashes messes things up. URL encoding
    is no help because Laravel cannot resolve the path.

#### x.0.29

- fix formSubmit() was not properly processing translation result for inPlaceEdit() mode
- fix moved csrf meta from index.blade.php to layouts.master.blade.php so that all pages that
  extend layouts.master can use in-place-edit mode.
- move most of the details from the readme to the wiki.
- fix runtime exception if workbench projects are present but the config has an empty include
  for workbench config.
- fix replace deprecated \Route::after()
- add key usage logging. Similar to missing keys except it logs keys that were accessed.
- add alternate database connection to config and to web interface. Can manage production
  translations from local dev environment.
- fix keyOps to handle group names for wbn: and vnd: prefixed names. Now can move/copy keys from
  normal groups to these ones.
- fix translations page now redirects to 'no group selected' url if the requested group no
  longer exists.
- change locales that are part of the working set are moved to the front of the lists to ease
  their selection.
- add batch insert mode for new translations. Uses insert into with a list of values instead of
  invoking db connection for each one. Speeds up imports of new translations and initial import
  significantly, especially for remote database connections.
- add now missing translation keys for packages from vendor and workbench directories will have
  these keys added to the appropriate vendor/workbench group if there are no translations for
  the package in the project. Makes working on code in workbench and vendor directories that
  references a new translation easier. Previously the new key would be created under the package
  name but in the project space instead of the vendor/workbench (ie. vnd: or wbn: prefixed
  group) name space.
- add buttons to filter translation rows: show all, show non-empty, show empty, show changed,
  show deleted
- fix indatabase_publish was not working since usage tracking was added.
- add auto translate buttons to all locale column headers not just the translating locale.
- fix exception when 'log_missing_keys' is disabled in configuration and translation manager
  in-place-edit mode is used. Issue: #10
- add `locales` config entry for additional locales that are to be included in the locales list
  even if these don't have translation files or translations in the database. That way you can
  add locales by adding translations for them in translation manager without having to create
  the directories with a single group file and a single translation key to get locales added to
  your translations.

[Installation: Publishing And Running Migrations]: ../../wiki/Installation#publishing-and-running-migrations

