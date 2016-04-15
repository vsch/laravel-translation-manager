<?php

namespace Vsch\TranslationManager\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * app\UserLocale
 *
 * @property-read \app\User $user
 * @mixin \Eloquent
 */
class UserLocales extends Eloquent
{
    protected $table = 'ltm_user_locales';
}
