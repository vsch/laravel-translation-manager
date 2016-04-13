<?php namespace Vsch\TranslationManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Vsch\TranslationManager\Manager;

/**
 * Translation model
 *
 * @property integer        $id
 * @property integer        $status
 * @property string         $locale
 * @property string         $group
 * @property string         $key
 * @property string         $value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Translation extends Model
{
    const STATUS_SAVED = 0;
    const STATUS_CHANGED = 1;
    const STATUS_SAVED_CACHED = 2;

    protected $table = 'ltm_translations';
    protected $guarded = array('id', 'created_at', 'updated_at');
}
