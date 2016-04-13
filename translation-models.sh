#!/usr/bin/env bash
# UserLocales
#php artisan --verbose generate:scaffold licenseAgent --overwrite --fields="name:string[64]:unique:keyindex(1,1), launched_at:date"
OVERWRITE_OPT='--overwrite'
OVERWRITE=$OVERWRITE_OPT

php artisan --verbose generate:scaffold ltmUserLocales $OVERWRITE --fields="\
user_id:int:unsigned:index(1,1) \
, locale:string[5]:index(1,2) \
"

php artisan optimize
php artisan ide-helper:models --reset --write
