<?php namespace Vsch\TranslationManager;

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\URL;
use Illuminate\Translation\LoaderInterface;
use Illuminate\Translation\Translator as LaravelTranslator;
use Vsch\UserPrivilegeMapper\Facade\Privilege as UserCan;

class Translator extends LaravelTranslator
{

    /** @var  Dispatcher */
    protected $events;

    /* @var $manager Manager */
    protected $manager;

    protected $suspendInPlaceEdit;
    protected $suspendUsageLogging;

    protected $useDB;
    protected $inPlaceEditing;
    protected $package;
    protected $packagePrefix;
    protected $cookiePrefix;

    /**
     * Translator constructor.
     */
    public
    function __construct(Application $app, LoaderInterface $loader, $locale)
    {
        parent::__construct($loader, $locale);
        $this->suspendInPlaceEdit = 0;
        $this->suspendUsageLogging = 0;
        $this->inPlaceEditing = null;
        $this->useDB = 1;  // fill in missing keys from DB by default
        $this->app = $app;
    }

    public
    function inPlaceEditing($inPlaceEditing = null)
    {
        if ($inPlaceEditing !== null) {
            $this->inPlaceEditing = $inPlaceEditing;
            $session = $this->app->make('session');
            $session->put($this->cookiePrefix . 'lang_inplaceedit', $this->inPlaceEditing);
        }

        if ($this->inPlaceEditing === null) {
            $session = $this->app->make('session');
            $this->inPlaceEditing = $session->get($this->cookiePrefix . 'lang_inplaceedit', 0);
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
    function suspendUsageLogging()
    {
        return $this->suspendUsageLogging++;
    }

    public
    function resumeUsageLogging()
    {
        return $this->suspendUsageLogging ? --$this->suspendUsageLogging : 0;
    }

    public static
    function isLaravelNamespace($namespace)
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $namespace);
    }

    public
    function inPlaceEditLink($t, $withDiff = false, $key = null, $locale = null, $useDB = null, $group = null)
    {
        try {
            $this->suspendUsageLogging();

            $diff = '';
            if (!$t && $key) {
                if ($useDB === null) $useDB = $this->useDB;

                list($namespace, $parsed_group, $item) = $this->parseKey($key);
                if ($group === null) $group = $parsed_group;
                else {
                    $item = substr($key, strlen("$group."));
                    if ($namespace && $namespace !== '*') $group = substr($group, strlen("$namespace::"));
                }

                if ($this->manager && $group && $item && (!$this->manager->excludedPageEditGroup($group) || $withDiff)) {
                    if ($locale == null) $locale = $this->locale();

                    $t = $this->manager->missingKey($namespace, $group, $item, $locale, false, true);
                    if ($t && (!$t->exists || $t->value == '') && $namespace != '*') {
                        if (static::isLaravelNamespace($namespace)) {
                            // get the package definition, we don't have an override
                            $t->saved_value = parent::get($key, [], $locale);
                            $t->status = 0;
                            if ($withDiff) {
                                $diff = ' [' . $t->saved_value . ']';
                            }
                        }
                    }
                }
            }

            if ($t) {
                if ($withDiff && $diff === '') {
                    $diff = ($t->saved_value == $t->value ? '' : ($t->saved_value === $t->value ? '' : ' [' . mb_renderDiffHtml($t->saved_value, $t->value) . ']'));
                }
                $title = parent::get($this->packagePrefix . 'messages.enter-translation');

                if ($t->value === null) $t->value = ''; //$t->value = parent::get($key, $replace, $locale);
                $result = '<a href="#edit" class="vsch_editable status-' . ($t->status ?: 0) . ' locale-' . $t->locale . '" data-locale="' . $t->locale . '" ' . 'data-name="' . $t->locale . '|' . $t->key . '" id="' . $t->locale . "-" . str_replace('.', '-', $t->key) . '"  data-type="textarea" data-pk="' . ($t->id ?: 0) . '" ' . 'data-url="' . URL::action('\Vsch\TranslationManager\Controller@postEdit', array($t->group)) . '" ' . 'data-inputclass="editable-input" data-saved_value="' . htmlentities($t->saved_value, ENT_QUOTES, 'UTF-8', false) . '" ' . 'data-title="' . $title . ': [' . $t->locale . '] ' . $t->group . '.' . $t->key . '">' . ($t ? htmlentities($t->value, ENT_QUOTES, 'UTF-8', false) : '') . '</a> ' . $diff;
                return $result;
            }

            return '';
        } finally {
            $this->resumeUsageLogging();
        }
    }

    public
    function getInPlaceEditLink($key, array $replace = array(), $locale = null, $withDiff = null, $useDB = null)
    {
        return $this->inPlaceEditLink(null, $withDiff, $key, $locale, $useDB);
    }

    protected
    function processResult($line, $replace)
    {
        if (is_string($line)) {
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
     *                       1 - only if key is missing in files or saved in the translator cache, use saved_value
     *                       fallback on $key,
     *                       2 - always use value from db, (unpublished value) not cached.
     *
     * @return string
     */
    public
    function get($key, array $replace = array(), $locale = null, $useDB = null)
    {
        if (!$this->suspendInPlaceEdit && $this->inPlaceEditing()) {
            $this->notifyUsingKey($key, $locale);
            return $this->inPlaceEditLink(null, true, $key, $locale);
        }

        $cacheKey = null;
        if ($useDB === null) $useDB = $this->useDB;

        if ($useDB && $useDB !== 2) {
            $result = $this->manager->cachedTranslation($key, $locale ?: $this->locale());
            if ($result) {
                $this->notifyUsingKey($key, $locale);
                return $this->processResult($result, $replace);
            }
        }

        if ($useDB == 2) {
            list($namespace, $group, $item) = $this->parseKey($key);
            if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                $t = $this->manager->missingKey($namespace, $group, $item, $locale, false, true);
                if ($t) {
                    $result = $t->value ?: $key;
                    if ($t->isDirty()) $t->save();
                    $this->notifyUsingKey($key, $locale);
                    return $this->processResult($result, $replace);
                }
            }
        }

        $result = parent::get($key, $replace, $locale);
        if ($result === $key) {
            if ($useDB === 1) {
                list($namespace, $group, $item) = $this->parseKey($key);
                if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                    $t = $this->manager->missingKey($namespace, $group, $item, $locale, false, true);
                    if ($t) {
                        $result = $t->saved_value ?: $key;
                        if ($t->isDirty()) $t->save();

                        // save in cache even if it has no value to prevent hitting the database every time just to figure it out
                        if (true || $result !== $key) {
                            // save in cache
                            $this->manager->cacheTranslation($key, $result, $locale ?: $this->getLocale());
                            return $this->processResult($result, $replace);
                        }
                        $this->notifyUsingKey($key, $locale);
                        return $result;
                    }
                }
            }

            $this->notifyMissingKey($key, $locale);
            $this->notifyUsingKey($key, $locale);
        }
        else {
            $this->notifyUsingKey($key, $locale);
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
        if (!$this->suspendInPlaceEdit && $this->inPlaceEditing()) {
            return $this->get($key, $replace, $locale, $useDB);
        }
        else {
            if ($useDB !== null) {
                $oldUseDB = $this->useDB;
                $this->useDB = $useDB;
                $retVal = parent::choice($key, $number, $replace, $locale);
                $this->useDB = $oldUseDB;
                return $retVal;
            }
            else {
                return parent::choice($key, $number, $replace, $locale);
            }
        }
    }

    public
    function setTranslationManager(Manager $manager)
    {
        $this->manager = $manager;
        $this->package = \Vsch\TranslationManager\ManagerServiceProvider::PACKAGE;
        $this->packagePrefix = $this->package . '::';
        $this->cookiePrefix = $this->manager->config('persistent_prefix', $this->packagePrefix);
    }

    protected
    function notifyMissingKey($key, $locale = null)
    {
        list($namespace, $group, $item) = $this->parseKey($key);
        if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
            $this->manager->missingKey($namespace, $group, $item, $locale, !UserCan::bypass_translations_lottery());
        }
    }

    protected
    function notifyUsingKey($key, $locale = null)
    {
        if (!$this->suspendUsageLogging) {
            list($namespace, $group, $item) = $this->parseKey($key);
            if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                $this->manager->usingKey($namespace, $group, $item, $locale, !UserCan::bypass_translations_lottery());
            }
        }
    }
}
