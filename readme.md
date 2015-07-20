## Laravel Translation Manager

Note that this package is originally based on Barry vd. Heuvel's <barryvdh@gmail.com> excellent **barryvdh/laravel-translation-manager** package but heavily reworked to add [New Features](#NewFeatures):

This is a package to manage Laravel translation files. It does not replace the Translation system but augments it with:

- import/export the php files to a database
- make translations editable through a web interface.
- allow in-database translations to override the ones in the language files. Used to update translations on server clusters where updating translation files is not possible (like AWS EC2) or would cause server code to be out of sync.

The workflow would be:

- Import translations: Read all translation files and save them in the database
- Find all translations in php/twig sources
- Optionally: Listen to missing translation with the custom Translator
- Translate all keys through the webinterface
- Export: Write all translations back to the translation files or cache if in production environment where writing of files is not an option.

This way, translations can be saved in git history and no overhead is introduced in production.

![Translator Page ](https://raw.githubusercontent.com/vsch/laravel-translation-manager/master/images/ScreenShot_main.png)

<a id="NewFeatures"></a>
#### New Features

- translation manager web-interface is localized. Current version has English and Russian.
- allows in-database translations to override the translations in files.
- publishing translations on production systems run where updating translation files would only update a single server, can be configured to use the cache for serving up the modified translations
- Translation service can be put into 'in place edit' mode that enables editing of translations where they appear on the page.
  - this eliminates the need to peruse code to find the translation group/key combination that is used for the resulting string and then looking for it in the translation files or in the web interface. Simply enable in-place editign mode and click on the string you wish to edit.
  - This may require some editing of view files to handle: string values that should not be links because they are not shown or are used for HTML attribute values, `<button>` contents that don't display links and other edge cases.
- changes to database translations that have not been published show a difference between previously published/imported translations and current unpublished changes.
- soft delete of translations that are not physically deleted from the database until translations are published.
- translation page has a dash board view showing all unpublished changes, missing translations and deleted translations.
- handling of translation files in nested directories under `lang/` and package translations under `lang/packages` to allow managing package translation overrides.
- missing translation key logging that can be used in a production environment by setting 1 of N sessions to actually log missing translations. Since checking for missing translation requires hitting the database for every translation, it can be a heavy load on the DB server. This configuration setting allows randomly selecting 1 of N user sessions to be marked as checking for missing translations allowing the benefit of finding missing translations while reducing the load burden on the server.
- in place edit mode inside the search dialog to allow editing of translation in the search result.
- extra buttons added to the bootstrap x-edit component for frequent operations:
  - change case of translation or selection within translation: lowercase, first cap
  - create plural forms for use in `choice()`, currently English is automatically created and Russian will do its best by using Yandex translator to derrive the plural forms.


## Installation

Require this package in your composer.json and run composer update (or run `composer require vsch/laravel-translation-manager:*` directly):

    "vsch/laravel-translation-manager": "0.1.x"

After updating composer, add the ServiceProvider to the providers array in app/config/app.php

    'Vsch\TranslationManager\ManagerServiceProvider',

You need to run the migrations for this package

    $ php artisan migrate --package="vsch/laravel-translation-manager"

You need to publish the config file for this package. This will add the file `app/config/packages/vsch/laravel-translation-manager/config.php`, where you can configure this package.

    $ php artisan config:publish vsch/laravel-translation-manager

You have to add the Controller to your routes.php, so you can set your own url/filters.

    Route::get('translations/keyop/{group}', 'Vsch\TranslationManager\Controller@getKeyop');
    Route::controller('translations', 'Vsch\TranslationManager\Controller');

This example will make the translation manager available at `http://yourdomain.com/translations`

## Usage

### Web interface

When you have imported your translation (via buttons or command), you can view them in the webinterface (on the url you defined the with the controller).
You can click on a translation and an edit field will popup. Just click save and it is saved :)
When a translation is not yet created in a different locale, you can also just edit it to create it.

Using the buttons on the webinterface, you can import/export the translations. For publishing translations, make sure your application can write to the language directory.

You can also use the commands below.

### Import command

The import command will search through app/lang and load all strings in the database, so you can easily manage them.

    $ php artisan translations:import
    
Note: By default, only new strings are added. Translations already in the DB are kept the same. If you want to replace all values with the ones from the files, 
add the `--replace` (or `-R`) option: `php artisan translations:import --replace`

### Find translations in source

The Find command/button will look search for all php/twig files in the app directory, to see if they contain translation functions, and will try to extract the group/item names.
The found keys will be added to the database, so they can be easily translated.
This can be done through the webinterface, or via an Artisan command.

    $ php artisan translations:find

### Export command

The export command will write the contents of the database back to app/lang php files.
This will overwrite existing translations and remove all comments, so make sure to backup your data before using.
Supply the group name to define which groups you want to publish.

    $ php artisan translations:export <group>

For example, `php artisan translations:export reminders` when you have 2 locales (en/nl), will write to `app/lang/en/reminders.php` and `app/lang/nl/reminders.php`

### Clean command

The clean command will search for all translation that are NULL and delete them, so your interface is a bit cleaner. Note: empty translations are never exported.

    $ php artisan translations:clean

### Reset command

The reset command simply clears all translation in the database, so you can start fresh (by a new import). Make sure to export your work if needed before doing this.

    $ php artisan translations:reset

### Detect missing translations, use in database translation ovrrides, enable in-place translation editing

Most translations can be found by using the Find command (see above), but in case you have dynamic keys (variables/automatic forms etc), it can be helpful to 'listen' to the missing translations.
To detect missing translations, we can swap the Laravel TranslationServicepProvider with a custom provider.
In your config/app.php, comment out the original TranslationServiceProvider and add the one from this package:

```php
    //'Illuminate\Translation\TranslationServiceProvider',
    'Vsch\TranslationManager\TranslationServiceProvider',
```

This will extend the Translator and will create a new database entry, whenever a key is not found, so you have to visit the pages that use them.
This way it shows up in the webinterface and can be edited and later exported.
You shouldn't use this in production, just in production to translate your views, then just switch back.

## TODO

This package is still very alpha. Few thinks that are on the todo-list:

- Add locales/groups via webinterface
- Improve webinterface (more selection/filtering, behavior of popup after save etc)
- Seed existing languages (https://github.com/caouecs/Laravel4-lang)
- Suggestions are welcome :)
