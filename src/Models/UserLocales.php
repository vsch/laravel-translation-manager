<?php

namespace Vsch\TranslationManager\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * App\UserLocale
 *
 * @property-read \App\User $user
 * @mixin \Eloquent
 */
class UserLocales extends Eloquent
{
    protected $table = 'ltm_user_locales';
}
