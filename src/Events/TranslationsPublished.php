<?php
/**
 * Created by PhpStorm.
 * User: vlad
 * Date: 2017-05-16
 * Time: 12:11 PM
 */

namespace Vsch\TranslationManager\Events;

use Illuminate\Queue\SerializesModels;

class TranslationsPublished
{
    use SerializesModels;

    public $groups;
    public $errors;

    /**
     * Create a new event instance.
     *
     * @param  string $groups group parameter, * if all groups, else group name
     * @param  array  $errors array of errors encountered during publishing of translations
     */
    public function __construct($groups, $errors)
    {
        $this->groups = $groups;
        $this->errors = $errors;
    }
}
