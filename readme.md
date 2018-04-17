# Laravel 5 Translation Manager

[![GitQ](https://gitq.com/badge.svg)](https://gitq.com/vsch/laravel-translation-manager)

This package is used to comfortably manage, view, edit and translate Laravel language files with
translation assistance through the Yandex Translation API. It augments the Laravel Translator
system with a ton of practical functionality. [Features]

:warning: Only **MySQL** and **PostgreSQL** Database connections are supported. Adding another
database only requires additional repository interface implementations following the examples of
[MysqlTranslatorRepository.php] or [PostgresTranslatorRepository.php].

#### :warning: **Version 2.6.10 has a new migration** 

When upgrading from earlier versions run:

```bash
$ php artisan vendor:publish --provider="Vsch\TranslationManager\ManagerServiceProvider" --tag=public --force
$ php artisan vendor:publish --provider="Vsch\TranslationManager\ManagerServiceProvider" --tag=migrations
$ php artisan migrate
```

**Detailed information is now in the [wiki].**

[Installation][]  
[Configuration][]  
[Version Notes][]

#### 2.6.16 Adds React App UI as an alternative to WebUI 

![React_UI](../../wiki/images/React_UI.png)

> * For Laravel 5.6 require: `"vsch/laravel-translation-manager": "~2.6"`
>
> * For Laravel 5.5 require: `"vsch/laravel-translation-manager": "~2.5"`
>
> * For Laravel 5.4 require: `"vsch/laravel-translation-manager": "~2.4"`
>
> * For Laravel 5.3 require: `"vsch/laravel-translation-manager": "~2.3"`
>   
>   [Upgrading from LTM 2.0 or 2.1 to 2.3](../../wiki/Upgrade-2.0-to-2.3)
>
> * For Laravel 5.2 require: `"vsch/laravel-translation-manager": "~2.1"`
>
> #### Laravel version 4.2 is no longer supported.
>
> You can still get access to the last updated version. Use the `laravel4` branch, or require:
> `"vsch/laravel-translation-manager": "~1.0"`
> 
> #### Initial Localizations Added
>
> :exclamation: If you have made correction to the auto-translated localization and would like
> to share them with others please do so. It will be greatly appreciated.

### Version 2.6.10 released

React UI added as an option to WebUI.

Code updated for Laravel 5.6 compatibility

Support for JSON translation files added. [Versioninfo.md](versioninfo.md#264) 

### Version 2.5.6 released

Support for JSON translation files added. [Versioninfo.md](versioninfo.md#256)

Code updated for Laravel 5.5 compatibility

### Version 2.4.36 released

Support for JSON translation files added.  [Versioninfo.md](versioninfo.md#2436)

Important LTM Translator method changes to restore compatibility with Laravel 5.4 API. These
changes affect the order of arguments to the LTM Translator implementation. If you were using
these methods based on previous LTM implementation then you will need to make changes in your
code:

From `transChoice($id, $number, array $parameters = array(), $domain = 'messages', $locale =
null, $useDB = null)` to `transChoice($id, $number, array $parameters = array(), $locale = null,
$domain = 'messages', $useDB = null)`

From `trans($id, array $parameters = array(), $domain = 'messages', $locale = null, $useDB =
null)` to `trans($id, array $parameters = array(), $locale = null, $domain = 'messages', $useDB
= null)`

From `get($key, array $replace = array(), $locale = null, $useDB = null)` to `get($key, array
$replace = array(), $locale = null, $fallback = true, $useDB = null)`

### Version 2.4.0 released

Laravel 5.4 compatible release. No API changes only internal implementation changes.

### Version 2.3.3 released

Laravel 5.3 compatible release. For upgrade instructions see
[Upgrading 2.0, 2.1 to 2.3](../../wiki/Upgrade-2.0-to-2.3)

Now using Laravel 5 authorization API to handle all LTM related authorizations.

Find Translations now update source references for translation keys and add new keys with
cleanup of dynamic keys. Need to publish and run migrations for this update
[Installation: Publishing And Running Migrations]

Now you can view source file and line number references for translations. See
[Web Interface: Source References]

![Screen Shot Show Source Refs]

**If you are upgrading from version 2.0.x of LTM** you need to:

1. Remove the dependency to `UserPrivilegeMapper` from your application:
   [Removing dependency on UserPrivilegeMapper from service providers array] and
   [Removing dependency on UserPrivilegeMapper from facade alias array]
2. Define the abilities used by LTM: [Setting up user authorization]

### Per Locale User Access Control

Implementation changed from the last release since using a closure in config file is not
supported by the framework. Now using abilities to do the same. See
[Enabling per locale user access control]

By default this option is turned off and any user who does not have `ltm-admin-translations`
ability can modify any locale. With `user_locales_enabled` option enabled you can control which
locales a user is allowed to modify. Default for all users is all locales, unless you
specifically change that through the web UI, see [User Admin] or by populating the
`ltm_user_locales` table appropriately.

### Screen Shot

![Translation Manager Screenshot]

***

\* This package was originally based on Barry vd. Heuvel's excellent [barryvdh] package.

[barryvdh]: https://github.com/barryvdh/laravel-translation-manager
[Configuration]: ../../wiki/Configuration
[Enabling per locale user access control]: ../../wiki/Configuration#enabling-per-locale-user-access-control
[Features]: ../../wiki/#features
[Installation]: ../../wiki/Installation
[Installation: Publishing And Running Migrations]: ../../wiki/Installation#publishing-and-running-migrations
[Removing dependency on UserPrivilegeMapper from facade alias array]: ../../wiki/Installation#removing-dependency-on-userprivilegemapper-from-facade-alias-array
[Removing dependency on UserPrivilegeMapper from service providers array]: ../../wiki/Installation#removing-dependency-on-userprivilegemapper-from-service-providers-array
[Screen Shot Show Source Refs]: https://raw.githubusercontent.com/wiki/vsch/laravel-translation-manager/images/ScreenShot_ShowSourceRefs.png
[Setting up user authorization]: ../../wiki/Installation#setting-up-user-authorization
[Translation Manager Screenshot]: https://raw.githubusercontent.com/wiki/vsch/laravel-translation-manager/images/ScreenShot_main.png
[User Admin]: ../../wiki/Web-Interface#user-admin
[Version Notes]: versioninfo.md
[Web Interface: Source References]: ../../wiki/Web-Interface#source-references
[wiki]: ../../wiki
[MysqlTranslatorRepository.php]: https://github.com/vsch/laravel-translation-manager/blob/master/src/Repositories/MysqlTranslatorRepository.php
[PostgresTranslatorRepository.php]: https://github.com/vsch/laravel-translation-manager/blob/master/src/Repositories/PostgresTranslatorRepository.php

