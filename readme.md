# Laravel 5.2 Translation Manager

This package is used to comfortably manage, view, edit and translate Laravel language files with
translation assistance through the Yandex Translation API. It augments the Laravel Translator
system with a ton of practical functionality. [Features]

**Detailed information is now in the [wiki].**

[Installation][]  
[Configuration][]  
[Version Notes][]  

> - For Laravel 5.3 require: `"vsch/laravel-translation-manager": "~2.3"`
>     
>     [Upgrading from LTM 2.0 or 2.1 to 2.3](../../wiki/Upgrade-2.0-to-2.3) 
>     
> - For Laravel 5.2 require: `"vsch/laravel-translation-manager": "~2.1"`
>     
> #### Laravel version 4.2 is no longer supported. 
> 
> You can still get access to the last updated version. Use the `laravel4` branch, or require:
> `"vsch/laravel-translation-manager": "~1.0"`
> 
> #### Initial Localizations Added
> 
> :exclamation: If you have made correction to the auto-translated localization and would like to share
> them with others please do so. It will be greatly appreciated.

### Version 2.3.3 released

Laravel 5.3 compatible release. For upgrade instructions see
[Upgrading 2.0, 2.1 to 2.3](../../wiki/Upgrade-2.0-to-2.3)

Now using Laravel 5 authorization API to handle all LTM related authorizations.

Find Translations now update source references for translation keys and add new keys with
cleanup of dynamic keys. Need to publish and run migrations for this update
[Installation: Publishing And Running Migrations]

Now you can view source file and line number references for translations. See
[Web Interface: Source References]

![Screen Shot Show Source Refs](../../wiki/images/ScreenShot_ShowSourceRefs.png)

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

[wiki]: ../../wiki

[Features]: ../../wiki/#features
[barryvdh]: https://github.com/barryvdh/laravel-translation-manager
[Translation Manager Screenshot]: ../../wiki/images/ScreenShot_main.png
[Setting up user authorization]: ../../wiki/Installation#setting-up-user-authorization
[Removing dependency on UserPrivilegeMapper from service providers array]: ../../wiki/Installation#removing-dependency-on-userprivilegemapper-from-service-providers-array
[Enabling per locale user access control]: ../../wiki/Configuration#enabling-per-locale-user-access-control
[User Admin]: ../../wiki/Web-Interface#user-admin
[Installation]: ../../wiki/Installation
[Configuration]: ../../wiki/Configuration
[Version Notes]: versioninfo.md
[Removing dependency on UserPrivilegeMapper from facade alias array]: ../../wiki/Installation#removing-dependency-on-userprivilegemapper-from-facade-alias-array
[Installation: Publishing And Running Migrations]: ../../wiki/Installation#publishing-and-running-migrations
[Web Interface: Source References]: ../../wiki/Web-Interface#source-references
