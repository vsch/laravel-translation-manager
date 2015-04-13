<?php namespace Barryvdh\TranslationManager;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
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

    protected $useDB;
    protected $inPlaceEditing;

    /**
     * Translator constructor.
     */
    public
    function __construct(LoaderInterface $loader, $locale)
    {
        parent::__construct($loader, $locale);
        $this->suspendInPlaceEdit = 0;
        $this->inPlaceEditing = 0;
        $this->useDB = 1;  // fill in missing keys from DB by default

        if (Session::has('laravel-translation-manager::lang_inplaceedit'))
        {
            $this->inPlaceEditing(Session::get('laravel-translation-manager::lang_inplaceedit'));
        }
    }

    public
    function inPlaceEditing($inPlaceEditing = null)
    {
        if ($inPlaceEditing !== null)
        {
            $this->inPlaceEditing = $inPlaceEditing;
            Session::put('laravel-translation-manager::lang_inplaceedit', $this->inPlaceEditing);
        }
        return $this->inPlaceEditing;
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
    function inPlaceEditLink($t, $withDiff = false, $key = null, $locale = null, $useDB = null)
    {
        $diff = '';
        if (!$t && $key)
        {
            if ($useDB === null) $useDB = $this->useDB;

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

            if ($t->value === null) $t->value = ''; //$t->value = parent::get($key, $replace, $locale);
            $result = '<a href="#edit" class="editable status-' . ($t->status ?: 0) . ' locale-' . $t->locale . '" data-locale="' . $t->locale . '" '
                . 'data-name="' . $t->locale . '|' . $t->key . '" id="' . $t->locale . "-" . str_replace('.', '-', $t->key) . '"  data-type="textarea" data-pk="' . ($t->id ?: 0) . '" '
                . 'data-url="' . URL::action('Barryvdh\TranslationManager\Controller@postEdit', array($t->group)) . '" '
                . 'data-inputclass="editable-input" data-saved_value="' . htmlentities($t->saved_value, ENT_QUOTES, 'UTF-8', false) . '" '
                . 'data-title="' . $title . ': [' . $t->locale . '] ' . $t->group . '.' . $t->key . '">'
                . ($t ? htmlentities($t->value, ENT_QUOTES, 'UTF-8', false) : '') . '</a> '
                . $diff;
            return $result;
        }

        return '';
    }

    public
    function getInPlaceEditLink($key, array $replace = array(), $locale = null, $withDiff = null, $useDB = null)
    {
        return $this->inPlaceEditLink(null, $withDiff, $key, $locale, $useDB);
        //list($namespace, $group, $item) = $this->parseKey($key);
        //
        //if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group))
        //{
        //    $t = $this->manager->missingKey($namespace, $group, $item, $locale);
        //}
        //return '';
    }

    protected
    function processResult($line, $replace)
    {
        if (is_string($line))
        {
            return $this->makeReplacements($line, $replace);
        }
        return $line;
    }

    /**
     * Get the translation for the given key.
     *
     * @param  string $key
     * @param  array  $replace
     * @param  string $locale
     * @param  int    $useDB null - check usedb field which is set to 1 by default,
     *                       0 - don't use,
     *                       1 - only if key is missing in files or saved in the translator cache, use saved_value fallback on $key,
     *                       2 - always use value from db, (unpublished value) not cached.
     *
     * @return string
     */
    public
    function get($key, array $replace = array(), $locale = null, $useDB = null)
    {
        if (!$this->suspendInPlaceEdit && $this->inPlaceEditing())
        {
            return $this->inPlaceEditLink(null, true, $key, $locale);
        }

        $cacheKey = null;
        if ($useDB === null) $useDB = $this->useDB;

        if ($useDB && $useDB !== 2)
        {
            $result = $this->manager->cachedTranslation($key, $locale ?: $this->locale());
            if ($result)
            {
                return $this->processResult($result, $replace);
            }
        }

        if ($useDB == 2)
        {
            list($namespace, $group, $item) = $this->parseKey($key);
            if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group))
            {
                $t = $this->manager->missingKey($namespace, $group, $item, $locale, false, true);
                $result = $t->value ?: $key;
                if ($t->isDirty()) $t->save();
                return $this->processResult($result, $replace);
            }
        }

        $result = parent::get($key, $replace, $locale);
        if ($result === $key)
        {
            if ($useDB === 1)
            {
                list($namespace, $group, $item) = $this->parseKey($key);
                if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group))
                {
                    $t = $this->manager->missingKey($namespace, $group, $item, $locale, false, true);
                    $result = $t->saved_value ?: $key;
                    if ($t->isDirty()) $t->save();

                    if ($result !== $key)
                    {
                        $this->manager->cacheTranslation($key, $result, $locale ?: $this->getLocale());
                        return $this->processResult($result, $replace);
                    }
                    return $result;
                }
            }

            $this->notifyMissingKey($key, $locale);
        }
        return $result;
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param  string $id
     * @param  int    $number
     * @param  array  $parameters
     * @param  string $domain
     * @param  string $locale
     *
     * @return string
     */
    public
    function transChoice($id, $number, array $parameters = array(), $domain = 'messages', $locale = null, $useDB = null)
    {
        return $this->choice($id, $number, $parameters, $locale, $useDB);
    }

    /**
     * Get the translation for a given key.
     *
     * @param  string $id
     * @param  array  $parameters
     * @param  string $domain
     * @param  string $locale
     *
     * @return string
     */
    public
    function trans($id, array $parameters = array(), $domain = 'messages', $locale = null, $useDB = null)
    {
        return $this->get($id, $parameters, $locale, $useDB);
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param  string $key
     * @param  int    $number
     * @param  array  $replace
     * @param  string $locale
     *
     * @return string
     */
    public
    function choice($key, $number, array $replace = array(), $locale = null, $useDB = null)
    {
        if (!$this->suspendInPlaceEdit && $this->inPlaceEditing())
        {
            return $this->get($key, $replace, $locale, $useDB);
        }
        else
        {
            if ($useDB !== null)
            {
                $oldUseDB = $this->useDB;
                $this->useDB = $useDB;
                $retVal = parent::choice($key, $number, $replace, $locale);
                $this->useDB = $oldUseDB;
                return $retVal;
            }
            else
            {
                return parent::choice($key, $number, $replace, $locale);
            }
        }
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
        if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group))
        {
            // KLUDGE: find an independent way to hook in role validation on users
            $this->manager->missingKey($namespace, $group, $item, $locale, !Auth::check() || Auth::user()->useTranslatorMissingKeysLottery());
        }
    }
}
