<?php

namespace Vsch\TranslationManager\Models;

use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * App\UserLocale
 *
 * @property integer        $id
 * @property integer        $user_id      // user id from User model
 * @property string         $locales      // list of comma separate locales the user is allowed to edit or NULL/blank if allowed to edit all
 * @property string         $ui_settings  // preference settings in the react ui app
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @mixin \Eloquent
 */
class UserLocales extends Eloquent
{
    protected $table = 'ltm_user_locales';

    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection(config('laravel-translation-manager.default_connection'));
    }
}

