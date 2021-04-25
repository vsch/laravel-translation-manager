# Laravel Translation Manager

[![GitQ](https://gitq.com/badge.svg)](https://gitq.com/vsch/laravel-translation-manager)

This package is used to comfortably manage, view, edit and translate Laravel language files with translation assistance
through the Yandex Translation API. It augments the Laravel Translator system with a ton of practical
functionality. [Features]

:warning: Only **MySQL** and **PostgreSQL** Database connections are supported. Adding another database only requires
additional repository interface implementations following the examples of
[MysqlTranslatorRepository.php] or [PostgresTranslatorRepository.php].

**Detailed information is now in the [wiki].**

#### Support Laravel 8:

#### Add to composer:

> * Require this package in your composer.json and run composer update

```bash
  "require": {
    ..
    "vsch/laravel-translation-manager": "dev-laravel-8"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/codetec-info/laravel-translation-manager"
    }
  ]
```

[Installation][]  
[Configuration][]

:warning: You can use Yandex or mymemory for auto translation. To use mymemory, just leave the yandex_translator_key in
laravel-translation-manager as xx, else add the Yandex api. MyMemory tracks it usage in words. This means that it
doesn't matter how many requests you submit to consult the archive, but the weight of each request.

By default, Free mymemory, anonymous usage is limited to 1000 words/day.
Provide a valid email ('de' parameter), where we can reach you in case of troubles, 
and enjoy 10000 words/day. (append url '&de=your_email_address' in the translation.js in line 95)

### Per Locale User Access Control

Implementation changed from the last release since using a closure in config file is not supported by the framework. Now
using abilities to do the same. See
[Enabling per locale user access control]

By default this option is turned off and any user who does not have `ltm-admin-translations`
ability can modify any locale. With `user_locales_enabled` option enabled you can control which locales a user is
allowed to modify. Default for all users is all locales, unless you specifically change that through the web UI,
see [User Admin] or by populating the
`ltm_user_locales` table appropriately.

### Screen Shot

![Translation Manager Screenshot]

***

\* This package was originally based on Barry vd. Heuvel's excellent [barryvdh] package.

[barryvdh]: https://github.com/barryvdh/laravel-translation-manager

[Configuration]: https://github.com/vsch/laravel-translation-manager/wiki/Configuration

[Enabling per locale user access control]: ../../wiki/Configuration#enabling-per-locale-user-access-control

[Features]: ../../wiki/#features

[Installation]: https://github.com/vsch/laravel-translation-manager/wiki/Installation

[Installation: Publishing And Running Migrations]: ../../wiki/Installation#publishing-and-running-migrations

[MysqlTranslatorRepository.php]: https://github.com/vsch/laravel-translation-manager/blob/master/src/Repositories/MysqlTranslatorRepository.php

[PostgresTranslatorRepository.php]: https://github.com/vsch/laravel-translation-manager/blob/master/src/Repositories/PostgresTranslatorRepository.php

[Removing dependency on UserPrivilegeMapper from facade alias array]: ../../wiki/Installation#removing-dependency-on-userprivilegemapper-from-facade-alias-array

[Removing dependency on UserPrivilegeMapper from service providers array]: ../../wiki/Installation#removing-dependency-on-userprivilegemapper-from-service-providers-array

[Screen Shot Show Source Refs]: https://raw.githubusercontent.com/wiki/vsch/laravel-translation-manager/images/ScreenShot_ShowSourceRefs.png

[Setting up user authorization]: ../../wiki/Installation#setting-up-user-authorization

[Translation Manager Screenshot]: https://raw.githubusercontent.com/wiki/vsch/laravel-translation-manager/images/ScreenShot_main.png

[User Admin]: ../../wiki/Web-Interface#user-admin

[Version Notes]: versioninfo.md

[Web Interface: Source References]: ../../wiki/Web-Interface#source-references

[wiki]: https://github.com/vsch/laravel-translation-manager/wiki

