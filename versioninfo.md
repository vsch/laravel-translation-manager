### Version Notes

The 1.x.x versions are for Laravel 4.2, 2.x.x versions are for Laravel 5.1

#### x.0.32

- fix move keys would fail if destination had existing key. Now conflicting destination keys are deleted and replaced by the moved keys.
- fix translation edit pop-up to caps now lower cases the affected text then proper caps it.
- add translation edit pop-up button to cap just the first character of the translation and the rest lowercase.
- add auto lower case all except the first character of translations only if translation text contains only letters, digits or white space, optionally terminated by a period.
- add mark visible translations as deleted, and un-mark visible translations as deleted.
- add persistence of translation filter selection between page refreshes.
- change 'Show Unpublished' to only show changed or deleted translations. Used to also show missing translations.
- add 'Need Attention' filter option to show missing, changed or deleted translations. This used to be what 'Show Unpublished' displayed.
- fix importing with 'delete all then import' was not preserving usage information.

#### x.0.31

- fix error reporting for wild card key operations would show empty alert.
- fix discrepancy between changed status in dashboard view and show changed radio button translation results.
- add unpublished radio button to show translations that need attention or will be modified on next publish. These are: deleted, changed or missing.
- add text box for regex to show translations whose keys match regex in the text box. Applied on top of other radio button filtering.
- add filtered key stats display in the translations table key column. Shows total displayed after regex filtering vs total from radio buttons vs total keys.
- add `show_locales` config option to limit the locales to ones contained in the option. If empty or not provided then all locales from the database will be shown. Only affects locales shown. All locales are imported and exported regardless of this setting.

#### 1.0.30 - 2.0.30

- fix import of locale sub-directories. Note that in L4 you access translations in sub-directories by using the `.` as separator in L5 this is `/`. In translation manager the group will have a period in all Laravel Versions. For example for directory structure like the following:

    ```text
    lang
    └── en
        └── test
            ├── sub-dir
            │ └── sub-sub-test.php
            └── subtest.php
    ```

    You would access translations:

    - in L5 as `@lang('test/subtest.translation-key')` for `subtest.php` and as `@lang('test/sub-dir/sub-sub-test.translation-key')` for `sub-sub-test.php`.

    - in L4 as `@lang('test.subtest.translation-key')` for `subtest.php` and as `@lang('test.sub-dir.sub-sub-test.translation-key')` for `sub-sub-test.php`.

    In both cases the group in translation manager will show as `test.subtest` and `test.sub-dir.sub-sub-test`. It was too big a pain to support `/` in group names because these are passed in the URL as parameters and having slashes messes things up. URL encoding is no help because Laravel cannot resolve the path.

#### x.0.29

- fix formSubmit() was not properly processing translation result for inPlaceEdit() mode
- fix moved csrf meta from index.blade.php to layouts.master.blade.php so that all pages that extend layouts.master can use in-place-edit mode.
- move most of the details from the readme to the wiki.
- fix runtime exception if workbench projects are present but the config has an empty include for workbench config.
- fix replace deprecated \Route::after()
- add key usage logging. Similar to missing keys except it logs keys that were accessed.
- add alternate database connection to config and to web interface. Can manage production translations from local dev environment.
- fix keyOps to handle group names for wbn: and vnd: prefixed names. Now can move/copy keys from normal groups to these ones.
- fix translations page now redirects to 'no group selected' url if the requested group no longer exists.
- change locales that are part of the working set are moved to the front of the lists to ease their selection.
- add batch insert mode for new translations. Uses insert into with a list of values instead of invoking db connection for each one. Speeds up imports of new translations and initial import significantly, especially for remote database connections.
- add now missing translation keys for packages from vendor and workbench directories will have these keys added to the appropriate vendor/workbench group if there are no translations for the package in the project. Makes working on code in workbench and vendor directories that references a new translation easier. Previously the new key would be created under the package name but in the project space instead of the vendor/workbench (ie. vnd: or wbn: prefixed group) name space.
- add buttons to filter translation rows: show all, show non-empty, show empty, show changed, show deleted
- fix indatabase_publish was not working since usage tracking was added.
- add auto translate buttons to all locale column headers not just the translating locale.
- fix exception when 'log_missing_keys' is disabled in configuration and translation manager in-place-edit mode is used. Issue: #10
- add `locales` config entry for additional locales that are to be included in the locales list even if these don't have translation files or translations in the database. That way you can add locales by adding translations for them in translation manager without having to create the directories with a single group file and a single translation key to get locales added to your translations.
