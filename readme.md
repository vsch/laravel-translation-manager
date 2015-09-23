# Laravel 5.1 Translation Manager
This package is used to comfortably manage, view, edit and translate Laravel language files with translation assistance through the Yandex Translation API. It augments the Laravel Translator system with a ton of practical functionality. [Features](https://github.com/vsch/laravel-translation-manager/wiki/#features)

**Detailed information is now in the [wiki](https://github.com/vsch/laravel-translation-manager/wiki).**

> Master branch is now for Laravel version 5.1
>
> - For Laravel 4.2 use the Laravel4 branch, or require: `"vsch/laravel-translation-manager": "~1.0"`
> - For Laravel 5.1 use the master branch, or require: `"vsch/laravel-translation-manager": "~2.0"`
>
> New file layout configuration can handle non-standard location and layout of translation files. The main motivator for the change was to eliminate differences in code between the two Laravel versions to ease maintenance, the added benefit is that now Translation Manager can import and publish translations located anywhere in the project tree and is configured to handle vendor and workbench subdirectories. This does require publishing of the new configuration files to your project and manually applying any changes you have made to them. You should rename your current configuration file before publishing a new one so you can merge your changes into the new file. [publishing configuration](https://github.com/vsch/laravel-translation-manager/wiki/Installation#publish-config)

> **Initial Localizations Added**
> Only en and ru locales were manually verified. All others are there as a starter set and were automatically generated  via Yandex by using the new Auto Translate feature in the web interface.
> Any help in cleaning them up would be greatly appreciated.

#### Screenshot

![Translation Manager Screenshot](https://github.com/vsch/laravel-translation-manager/wiki/images/ScreenShot_main.png)

***

\* This package was originally based on Barry vd. Heuvel's excellent [barryvdh/laravel-translation-manager](https://github.com/barryvdh/laravel-translation-manager) package.
