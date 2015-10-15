
# Laravel 5.1 Translation Manager

This package is used to comfortably manage, view, edit and translate Laravel language files with translation assistance through the Yandex Translation API. It augments the Laravel Translator system with a ton of practical functionality. [Features](../../wiki/#features)

**Detailed information is now in the [wiki](../../wiki).**

[Installation](../../wiki/Installation)  
[Configuration](../../wiki/Configuration)  
[Version Notes](versioninfo.md)  

> Master branch is now for Laravel version 5.1
>
> - For Laravel 4.2 use the Laravel4 branch, or require: `"vsch/laravel-translation-manager": "~1.0"`
>   **I would like to stop maintaining Laravel 4.2 version of this package. If you are still using it please leave a comment on [Issue #14](../../issues/14) to let me know that it is still worth maintaining.**
>
> - For Laravel 5.1 use the master branch, or require: `"vsch/laravel-translation-manager": "~2.0"`
>
> New file layout configuration can handle non-standard location and layout of translation files. The main motivator for the change was to eliminate differences in code between the two Laravel versions to ease maintenance, the added benefit is that now Translation Manager can import and publish translations located anywhere in the project tree and is configured to handle vendor and workbench subdirectories. This does require publishing of the new configuration files to your project and manually applying any changes you have made to them. You should rename your current configuration file before publishing a new one so you can merge your changes into the new file. [publishing configuration](../../wiki/Installation#publish-config)

> **Initial Localizations Added**
> Only en and ru locales were manually verified. All others are there as a starter set and were automatically generated  via Yandex by using the new Auto Translate feature in the web interface.
> Any help in cleaning them up would be greatly appreciated.

#### Screenshot

![Translation Manager Screenshot](../../wiki/images/ScreenShot_main.png)

***

\* This package was originally based on Barry vd. Heuvel's excellent [barryvdh/laravel-translation-manager](https://github.com/barryvdh/laravel-translation-manager) package.
