<?php

namespace Vsch\TranslationManager\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Translation model
 *
 * @property integer        $id
 * @property integer        $status
 * @property string         $locale
 * @property string         $group
 * @property string         $key
 * @property string         $value
 * @property string         $saved_value   // misnomer, it should really be published_value, we don't know what is saved in the file unless status is SAVED.
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Translation extends Model
{
    const STATUS_SAVED = 0;                 // means the translation was published and saved to the translation file on the server
    const STATUS_CHANGED = 1;               // means the translation is not published, ie. value !== saved_value
    const STATUS_SAVED_CACHED = 2;          // means the translation was published but not saved to the translation file on the server (remove db ops, or locked down local file system)

    protected $table = 'ltm_translations';
    protected $guarded = array('id', 'created_at', 'updated_at');
    
    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection(config('laravel-translation-manager.default_connection'));
    }
}
