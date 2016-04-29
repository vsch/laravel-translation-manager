
# Laravel 5.2 Translation Manager

This package is used to comfortably manage, view, edit and translate Laravel language files with
translation assistance through the Yandex Translation API. It augments the Laravel Translator
system with a ton of practical functionality. [Features]

**Detailed information is now in the [wiki].**

[Installation](../../wiki/Installation)  
[Configuration](../../wiki/Configuration)  
[Version Notes](versioninfo.md)  

> - For Laravel 5.2 use the master branch, or require: `"vsch/laravel-translation-manager":
>   "~2.1"`
>
> #### Laravel version 4.2 is no longer supported. 
> You can still get access to the last updated version. Use the `laravel4` branch, or require:
> `"vsch/laravel-translation-manager": "~1.0"`
>
> #### Initial Localizations Added
> If you have made correction to the auto-translated localization and would like to share them
> with others please do so. It will be greatly appreciated.


### Version 2.1.0 released

Now using Laravel 5 authorization provisions to handle all LTM related authorizations.

**If you are upgrading from version 2.0.x of LTM** you need to: 
 
1. Remove the dependency to `UserPrivilegeMapper` from your application: service providers.
   [Installation step 2](../../wiki/Installation#step2) and facade alias array
   [Installation step 3](../../wiki/Installation#step3)
2. Define the abilities used by LTM: [Installation step 8](../../wiki/Installation#step8)

### Per Locale User Access Control

Implementation changed from the last release since using a closure in config file is not
supported by the framework. Now using abilities to do the same. See
[Enabling per locale user access control](../../wiki/Configuration#enabling-per-locale-user-access-control)
    
By default this option is turned off and any user who does not have `ltm-admin-translations`
ability can modify any locale. With `user_locales_enabled` option enabled you can control which
locales a user is allowed to modify. Default for all users is all locales, unless you
specifically change that through the web UI, see
[User Admin](../../wiki/Web-Interface#user-admin) or by populating the `ltm_user_locales` table
appropriately.

#### Screen Shot

![Translation Manager Screenshot]

***

\* This package was originally based on Barry vd. Heuvel's excellent 
[barryvdh] package. 

[wiki]: ../../wiki

[Translation Manager Screenshot]: ../../wiki/images/ScreenShot_main.png
[Features]: ../../wiki/#features
[barryvdh]: https://github.com/barryvdh/laravel-translation-manager
[issue #14]: ../../issues/14
[publishing configuration]: ../../wiki/Installation#publish-config





