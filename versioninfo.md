#### 1.0.29

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
