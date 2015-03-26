<?php namespace Barryvdh\TranslationManager;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Translation\LoaderInterface;
use Illuminate\Translation\Translator as LaravelTranslator;
use Illuminate\Events\Dispatcher;

class Translator extends LaravelTranslator
{

    /** @var  Dispatcher */
    protected $events;

    /* @var $manager Manager */
    protected $manager;

    protected $suspendInPlaceEdit;

    /**
     * Translator constructor.
     */
    public
    function __construct(LoaderInterface $loader, $locale)
    {
        parent::__construct($loader, $locale);
        $this->suspendInPlaceEdit = 0;
    }

    public
    function inPlaceEditing()
    {
        return $this->getFallback() === 'dbg' && $this->getLocale() !== 'dbg';
    }

    public
    function suspendInPlaceEditing()
    {
        return $this->suspendInPlaceEdit++;
    }

    public
    function resumeInPlaceEditing()
    {
        return $this->suspendInPlaceEdit ? --$this->suspendInPlaceEdit : 0;
    }

    public
    function inPlaceEditLink($t, $withDiff = false, $key = null, $locale = null)
    {
        $diff = '';
        if (!$t && $key)
        {
            list($namespace, $group, $item) = $this->parseKey($key);
            if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group))
            {
                $t = $this->manager->missingKey($namespace, $group, $item, $locale, false, true);
                if ((!$t->exists || $t->value == '') && $namespace != '*')
                { // get the package definition, we don't have an override
                    $t->saved_value = parent::get($key, [], $locale);
                    $t->status = 0;
                    if ($withDiff)
                    {
                        $diff = ' [' . $t->saved_value . ']';
                    }
                }
            }
        }
        if ($t)
        {
            if ($withDiff && $diff === '')
            {
                $diff = ($t->saved_value == $t->value ? '' : ($t->saved_value === $t->value ? '' : ' [' . \Barryvdh\TranslationManager\Controller::mb_renderDiffHtml($t->saved_value, $t->value) . ']'));
            }
            $title = parent::get('laravel-translation-manager::messages.enter-translation');

            if (is_null($t->value)) $t->value = ''; //$t->value = parent::get($key, $replace, $locale);
            $result = '<a href="#edit" class="editable status-' . ($t->status ?: 0) . ' locale-' . $t->locale . '" data-locale="' . $t->locale . '"'
                . 'data-name="' . $t->locale . '|' . $t->key . '" id="' . $t->locale . "-" . $t->key . '"  data-type="textarea" data-pk="' . ($t->id ?: 0) . '"'
                . 'data-url="' . URL::action('Barryvdh\TranslationManager\Controller@postEdit', array($t->group)) . '"'
                . 'data-inputclass="editable-input" data-saved_value="' . htmlentities($t->saved_value, ENT_QUOTES, 'UTF-8', false) . '"'
                . 'data-title="' . $title . ': [' . $t->locale . '] ' . $t->group . '.' . $t->key . '">'
                . ($t ? htmlentities($t->value, ENT_QUOTES, 'UTF-8', false) : '') . '</a> '
                . $diff;
            return $result;
        }

        return '';
    }

    public
    function getInPlaceEditLink($key, array $replace = array(), $locale = null, $withDiff = null)
    {
        return $this->inPlaceEditLink(null, $withDiff, $key, $locale);
        //list($namespace, $group, $item) = $this->parseKey($key);
        //
        //if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group))
        //{
        //    $t = $this->manager->missingKey($namespace, $group, $item, $locale);
        //}
        //return '';
    }

    /**
     * Get the translation for the given key.
     *
     * @param  string $key
     * @param  array  $replace
     * @param  string $locale
     *
     * @return string
     */
    public
    function get($key, array $replace = array(), $locale = null)
    {
        if (!$this->suspendInPlaceEdit)
        {
            $thisLocale = $this->parseLocale($locale);

            if ($thisLocale[0] !== 'dbg' && $thisLocale[1] === 'dbg')
            {
                return $this->inPlaceEditLink(null, true, $key, $locale);
                //list($namespace, $group, $item) = $this->parseKey($key);
                //
                //if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group))
                //{
                //    $t = $this->manager->missingKey($namespace, $group, $item, $locale, false, true);
                //    if (!$t->exists && $namespace != '*')
                //    { // get the package definition, we don't have an override
                //        $t->value = $t->saved_value = parent::get($key, $replace, $locale);
                //        $t->status = 0;
                //    }
                //}
            }
        }

        $result = parent::get($key, $replace, $locale);
        if ($result === $key)
        {
            $this->notifyMissingKey($key, $locale);
        }
        return $result;
    }

    public
    function setTranslationManager(Manager $manager)
    {
        $this->manager = $manager;
    }

    protected
    function notifyMissingKey($key, $locale = null)
    {
        list($namespace, $group, $item) = $this->parseKey($key);
        if ($this->manager && $namespace === '*' && $group && $item)
        {
            // KLUDGE: find an independent way to hook in role validation on users
            $this->manager->missingKey($namespace, $group, $item, $locale, !Auth::check() || Auth::user()->useTranslatorMissingKeysLottery());
        }
    }
}
