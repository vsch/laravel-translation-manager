
# Laravel 5.1 Translation Manager

This package is used to comfortably manage, view, edit and translate 
Laravel language files with translation assistance through the Yandex 
Translation API. It augments the Laravel Translator system with a ton of 
practical functionality. [Features] 

**Detailed information is now in the [wiki].**

[Installation](../../wiki/Installation)  
[Configuration](../../wiki/Configuration)  
[Version Notes](versioninfo.md)  

> - For Laravel 5.x use the master branch, or require: 
>  `"vsch/laravel-translation-manager": "~2.0"` 
>
> #### Laravel version 4.2 is no longer supported. 
> You can still get access to the last updated version. Use the 
> `laravel4` branch, or require: `"vsch/laravel-translation-manager": 
> "~1.0"` 
>
> #### Initial Localizations Added
> If you have made correction to the auto-translated localization and 
> would like to share them with others please do so. It will be greatly 
> appreciated. 

### Per Locale User Access Control

Per locale user access control was added to version 2.0.41. By default it is turned off and any
user who does not have `UserCan::admin_translate()` return true can modify any locale. With this
option enabled you can control which locales a user is allowed to modify. Default for all users
is all locales, unless you specifically change that through the web UI, see
[User Admin](../../wiki/Web-Interface#user-admin) or by populating the `ltm_user_locales` table
appropriately. See
[Enabling per locale user access control](../../wiki/Configuration#enabling-per-locale-user-access-control)

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





