<?php namespace Vsch\TranslationManager;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Translation\Translator as LaravelTranslator;

class Translator extends LaravelTranslator
{
    protected $useLottery;

    /** @var  Dispatcher */
    protected $dispatchesEvents;

    /* @var $manager Manager */
    protected $manager;

    protected $suspendInPlaceEdit;
    protected $suspendUsageLogging;

    protected $useDB;
    protected $inPlaceEditing;
    protected $package;
    protected $packagePrefix;
    protected $cookiePrefix;
    protected $useCookies;
    protected $cookiesLoaded;

    // Storage used for used translation keys
    protected $usedKeys = array();
    
    protected $customPostProcessor = null;

    /**
     * Translator constructor.
     *
     * @param \Illuminate\Foundation\Application $app
     * @param \Illuminate\Contracts\Translation\Loader $loader
     * @param                                          $locale
     */
    public function __construct(Application $app, Loader $loader, $locale)
    {
        $this->useLottery = null;
        parent::__construct($loader, $locale);
        $this->suspendInPlaceEdit = 0;
        $this->suspendUsageLogging = 0;
        $this->inPlaceEditing = null;
        $this->useDB = 1;  // fill in missing keys from DB by default
        $this->app = $app;
    }

    /**
     * @return Manager
     */
    public function getManager()
    {
        return $this->manager;
    }

    protected function isUseLottery()
    {
        if ($this->useLottery === null) {
            $this->useLottery = !Gate::allows(Manager::ABILITY_BYPASS_LOTTERY);
        }
        return $this->useLottery;
    }

    public function inPlaceEditing($inPlaceEditing = null)
    {
        if ($inPlaceEditing !== null) {
            $this->inPlaceEditing = $inPlaceEditing;
            if ($this->useCookies) {
                Cookie::queue($this->cookiePrefix . 'lang_inplaceedit', $this->inPlaceEditing);
            } else {
                $session = $this->app->make('session');
                if ($session->all()) {
                    // only put a value if session has already has some value set, meaning its been loaded
                    $session->put($this->cookiePrefix . 'lang_inplaceedit', $this->inPlaceEditing);
                }
            }
        }

        if ($this->inPlaceEditing === null) {
            if ($this->useCookies) {
                if (Cookie::has($this->cookiePrefix . 'lang_inplaceedit')) {
                    $this->inPlaceEditing = Cookie::get($this->cookiePrefix . 'lang_inplaceedit', 0);
                }
            } else {
                $session = $this->app->make('session');
                $this->inPlaceEditing = $session->get($this->cookiePrefix . 'lang_inplaceedit', 0);
            }
        }

        // reset in place edit mode if not logged in
        if ($this->inPlaceEditing != 0 && !Auth::check()) {
            $this->inPlaceEditing = 0;
        }
        return $this->inPlaceEditing;
    }

    public function isInPlaceEditing($inPlaceEditing = null)
    {
        return $this->inPlaceEditing() && ($inPlaceEditing == null || $this->getInPlaceEditingMode() == $inPlaceEditing);
    }

    /**
     * Get the default locale being used.
     *
     * @return string
     */
    public function getLocale()
    {
        if (!$this->cookiesLoaded) {
            $key = $this->cookiePrefix . 'lang_locale';
            $queuedCookieLocale = \Cookie::queued($key, null);
            $locale = getSupportedLocale($queuedCookieLocale != null ? $queuedCookieLocale->getValue() : \Cookie::get($key, ''));
            parent::setLocale($locale);
            
            // load unpublished translation flag at the same time
            $this->getShowUnpublished();
            $this->cookiesLoaded = true;
        }
        return parent::getLocale();
    }

    /**
     * Set the default locale.
     *
     * @param  string $locale
     *
     * @return void
     */
    public function setLocale($locale)
    {
        if ($this->useCookies) {
            Cookie::queue($this->cookiePrefix . 'lang_locale', $locale);
        }
        parent::setLocale($locale);
    }

    /**
     * Get the default showUnpublished being used.
     *
     * @return boolean
     */
    public function getShowUnpublished()
    {
        if (!$this->cookiesLoaded) {
            $key = $this->cookiePrefix . 'show_unpublished';
            $queuedCookie = \Cookie::queued($key, null);
            $showUnpublished = $queuedCookie != null ? $queuedCookie->getValue() : \Cookie::get($key, false);
            $this->useDB = $showUnpublished ? 2 : 1;
            $this->cookiesLoaded = true;
        }
        return $this->useDB === 2;
    }

    /**
     * Get the default showUnpublished being used.
     *
     * @return boolean
     */
    public function getShowCached()
    {
        return $this->useDB === 1;
    }

    /**
     * Set the default showUnpublished.
     *
     * @param  string $showUnpublished
     *
     * @return void
     */
    public function setShowUnpublished($showUnpublished)
    {
        if ($this->useCookies) {
            Cookie::queue($this->cookiePrefix . 'show_unpublished', $showUnpublished);
        }
        $this->useDB = $showUnpublished ? 2 : 1;
    }

    public function getTranslations($namespace, $group, $locale)
    {
        $this->load($namespace, $group, $locale);

        return $this->loaded[$namespace][$group][$locale];
    }

    /**
     * Get the fallback locale being used.
     *
     * @return string
     */
    public function getFallback()
    {
        //return $this->fallback;
        return parent::getFallback();
    }

    /**
     * Set the fallback locale being used.
     *
     * @param  string $fallback
     *
     * @return void
     */
    public function setFallback($fallback)
    {
        //$this->fallback = $fallback;
        parent::setFallback($fallback);
    }

    public function getInPlaceEditingMode()
    {
        return $this->manager->config('inplace_edit_mode');
    }

    public function suspendInPlaceEditing()
    {
        return $this->suspendInPlaceEdit++;
    }

    public function resumeInPlaceEditing()
    {
        return $this->suspendInPlaceEdit ? --$this->suspendInPlaceEdit : 0;
    }

    public function suspendUsageLogging()
    {
        return $this->suspendUsageLogging++;
    }

    public function resumeUsageLogging()
    {
        return $this->suspendUsageLogging ? --$this->suspendUsageLogging : 0;
    }

    public static function isLaravelNamespace($namespace)
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $namespace);
    }

    /**
     * @param $t
     * @param $withDiff
     * @param $key
     * @param $locale
     * @param $useDB
     * @param $group
     * @return $t resolved or adjusted for details needed for edit link generation
     */
    public function getTranslationForEditLink($t, $withDiff, $key, $locale, $useDB, $group)
    {
        try {
            $this->suspendUsageLogging();

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
                                $t->diff = ''; // ' [' . $t->saved_value . ']';
                            }
                        }
                    }
                }
            }

            if ($t) {
                if ($t->value === null) $t->value = '';

                if ($withDiff && (!isset($t->diff) || $t->diff === '')) {
                    $t->diff = ($t->saved_value == $t->value || !$t->save_value || !$t->value ? '' : ($t->saved_value === $t->value ? '' : mb_renderDiffHtml($t->saved_value, $t->value)));
                } else {
                    $t->diff = '';
                }
            }
            return $t;
        } finally {
            $this->resumeUsageLogging();
        }
    }

    public function inPlaceEditLink($t, $withDiff = false, $key = null, $locale = null, $useDB = null, $group = null)
    {
        try {
            $this->suspendUsageLogging();

            $t = $this->getTranslationForEditLink($t, $withDiff, $key, $locale, $useDB, $group);

            if ($t) {
                $title = parent::get($this->packagePrefix . 'messages.enter-translation');

                $action = URL::action(ManagerServiceProvider::CONTROLLER_PREFIX . 'Vsch\TranslationManager\Controller@postEdit', array($t->group));

                $result = '<a href="#edit" class="vsch_editable status-' . ($t->status ?: 0)
                    . ' locale-' . $t->locale
                    . '" data-locale="' . $t->locale . '" '
                    . 'data-name="' . $t->locale . '|' . $t->key
                    . '" id="' . $t->locale . "-" . str_replace('.', '-', $t->key)
                    . '"  data-type="textarea" data-pk="' . ($t->id ?: 0) . '" '
                    . 'data-url="' . $action
                    . '" ' . 'data-inputclass="editable-input" data-saved_value="' . htmlentities($t->saved_value, ENT_QUOTES, 'UTF-8', false) . '" '
                    . 'data-title="' . $title . ': [' . $t->locale . '] ' . $t->group . '.' . $t->key . '">' . (htmlentities($t->value, ENT_QUOTES, 'UTF-8', false)) . '</a> ' . ($t->diff ? " [$t->diff]" : '');
                return $result;
            }

            return '';
        } finally {
            $this->resumeUsageLogging();
        }
    }

    public function getInPlaceEditLink($key, array $replace = array(), $locale = null, $withDiff = null, $useDB = null)
    {
        return $this->inPlaceEditLink(null, $withDiff, $key, $locale, $useDB);
    }

    protected function processResult($line, $replace)
    {
        if (is_string($line)) {
            if ($this->customPostProcessor) {
                $replaced = $this->makeReplacements($line, $replace);
                return call_user_func($this->customPostProcessor, $replaced);
            } else {
                return $this->makeReplacements($line, $replace);
            } 
        }
        return $line;
    }

    /**
     * @return null
     */
    public function getCustomPostProcessor()
    {
        return $this->customPostProcessor;
    }

    /**
     * @param callable $customPostProcessor custom translation processor taking a string argument
     */
    public function setCustomPostProcessor($customPostProcessor): void
    {
        $this->customPostProcessor = $customPostProcessor;
    }

    /**
     * Get the translation for a given key from the JSON translation files.
     *
     * @param  string $key
     * @param  array $replace
     * @param  string $locale
     * @param  int $useDB null - check usedb field which is set to 1 by default,
     *                       0 - don't use,
     *                       1 - only if key is missing in files or saved in the translator cache, use saved_value
     *                       fallback on $key,
     *                       2 - always use value from db, (unpublished value) not cached.
     *
     * @return string
     */
    public function getFromJson($key, array $replace = [], $locale = null, $useDB = null)
    {
        // see if json key and can be translated to ltm key
        $this->load('*', '*', 'json');
        // see if have it in the cache
        $item = $this->manager->cachedTranslation('', 'JSON', $key, 'json');
        if ($item === null) {
            if (array_key_exists($key, $this->loaded['*']['*']['json'])) {
                $item = $this->loaded['*']['*']['json'][$key];
            }
        }
        if ($item == null) return $this->get($key, $replace, $locale, true, $useDB);   // not a json key

        $locale = $locale ?: $this->locale;
        if ($useDB === null) $useDB = $this->useDB;
        $group = 'JSON';
        $namespace = '';

        if ($useDB && $useDB !== 2) {
            $result = $this->manager->cachedTranslation($namespace, $group, $item, $locale ?: $this->locale());
            if ($result) {
                $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
                return $this->processResult($result, $replace);
            }
        }

        if ($useDB == 2) {
            if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                $t = $this->manager->missingKey($namespace, $group, $item, $locale, $this->isUseLottery(), true);
                if ($t) {
                    $result = $t->value ?: $key;
                    if ($t->isDirty()) $t->save();
                    $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
                    return $this->processResult($result, $replace);
                }
            }
        }

        // get the JSON translation
        $this->load('*', '*', $locale);
        if (array_key_exists($key, $this->loaded['*']['*'][$locale])) {
            $result = $this->loaded['*']['*'][$locale][$key];
        } else {
            unset($result);
        }

        // If we can't find a translation for the JSON key, we will attempt to translate it
        // using the typical translation file. This way developers can always just use a
        // helper such as __ instead of having to pick between trans or __ with views.
        if (!isset($result)) {
            if ($useDB === 1) {
                if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                    $t = $this->manager->missingKey($namespace, $group, $item, $locale, $this->isUseLottery(), true);
                    if ($t) {
                        $result = $t->saved_value ?: $key;
                        if ($t->isDirty()) $t->save();

                        // save in cache even if it has no value to prevent hitting the database every time just to figure it out
                        $this->manager->cacheTranslation($namespace, $group, $item, $result, $locale ?: $this->getLocale());
                        return $this->processResult($result, $replace);
                    }
                }
            }

            $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
            $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
        } else {
            $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
            return $this->processResult($result, $replace);
        }
        return $result;
    }

    /**
     * Get the translation for the given key.
     *
     * @param  string $key
     * @param  array $replace
     * @param  string $locale
     * @param bool $fallback
     * @param  int $useDB null - check usedb field which is set to 1 by default,
     *                       0 - don't use,
     *                       1 - only if key is missing in files or saved in the translator cache, use saved_value
     *                       fallback on $key,
     *                       2 - always use value from db, (unpublished value) not cached.
     *
     * @return string
     */
    public function get($key, array $replace = array(), $locale = null, $fallback = true, $useDB = null)
    {
        $inplaceEditMode = $this->manager->config('inplace_edit_mode');
        list($namespace, $group, $item) = $this->parseKey($key);

        if ($this->inPlaceEditing() && $inplaceEditMode == 2) {
            if (!in_array($key, $this->usedKeys)) {
                $this->usedKeys[] = $key;
            }
        }

        if (!$this->suspendInPlaceEdit && $this->inPlaceEditing() && $inplaceEditMode == 1) {
            $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
            return $this->inPlaceEditLink(null, true, $key, $locale);
        }

        if ($useDB === null) $useDB = $this->useDB;

        if ($useDB && $useDB !== 2) {
            $augmentedGroup = $this->manager->getAugmentedGroup($namespace, $group);
            $result = $this->manager->cachedTranslation('', $augmentedGroup, $item, $locale ?: $this->locale());
            if ($result) {
                $this->notifyUsingGroupItem('', $augmentedGroup, $item, $locale);
                return $this->processResult($result, $replace);
            }
        }

        if ($useDB == 2) {
            if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                $t = $this->manager->missingKey($namespace, $group, $item, $locale, $this->isUseLottery(), true);
                if ($t) {
                    $result = $t->value ?: $key;
                    if ($t->isDirty()) {
                        unset($t->diff); // remove value added by inplaceedit link
                        $t->save();
                    }
                    $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
                    return $this->processResult($result, $replace);
                }
            }
        }

        $result = parent::get($key, $replace, $locale, $fallback);
        if ($result === $key) {
            if ($useDB === 1) {
                if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                    $t = $this->manager->missingKey($namespace, $group, $item, $locale, $this->isUseLottery(), true);
                    if ($t) {
                        $result = $t->saved_value ?: $key;
                        if ($t->isDirty()) {
                            unset($t->diff); // remove value added by inplaceedit link
                            $t->save();
                        }

                        // save in cache even if it has no value to prevent hitting the database every time just to figure it out
                        if (true || $result !== $key) {
                            // save in cache
                            $this->manager->cacheTranslation($namespace, $group, $item, $result, $locale ?: $this->getLocale());
                            return $this->processResult($result, $replace);
                        }
                        $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
                        return $result;
                    }
                }
            }

            $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
            $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
        } else {
            $this->notifyUsingGroupItem($namespace, $group, $item, $locale);
            return $this->processResult($result, $replace);
        }
        return $result;
    }

    /**
     * Make the translation popup from used keys when rendering a page
     *
     * @return string
     */
    public function getEditableLinksOnly()
    {
        $inplaceEditMode = $this->manager->config('inplace_edit_mode');
        if ($this->inPlaceEditing() && $inplaceEditMode == 2) {
            $keyDiv = '<div id="keys" style="padding:5px; padding-top:0; white-space: nowrap;">' . PHP_EOL . '<b>Keys</b><br>' . PHP_EOL;
            $textDiv = '<div id="keytexts" style="padding:5px; padding-top:0; white-space: nowrap;">' . PHP_EOL . '<b>Translations</b><br>' . PHP_EOL;

            $sorted = $this->usedKeys;
            sort($sorted);
            foreach ($sorted as $key) {
                $keyDiv .= $key . '<br>' . PHP_EOL;
                $textDiv .= $this->getInPlaceEditLink($key, [], $this->locale, $this->useDB) . '<br>' . PHP_EOL;
            }

            $keyDiv .= '</div>' . PHP_EOL;
            $textDiv .= '</div>' . PHP_EOL;

            // Top right corner button
            $translateButton = '<a href="#"><i class="fa fa-language" style="position: fixed; right: 5px; top: 5px; z-index:99999;"' . ' onclick="document.getElementById(\'transcontainer\').style.display = \'flex\';"></i></a>' . PHP_EOL;

            // Buttons
            $buttons = '<div style="display:flex; justify-content: flex-end;">' . PHP_EOL;
            $buttons .= '<div style="margin: 5px;"><a href="#" style="text-decoration: none;" onclick="window.location.reload(true);">' . '<i class="fa fa-btn fa-refresh" style="margin-right: 4px;"></i>' . $this->trans($this->package . '::messages.reload-page') . '</a></div>' . PHP_EOL;
            $buttons .= '<div style="margin: 5px;"><a href="#" style="text-decoration: none;"' . ' onClick="document.getElementById(\'transcontainer\').style.display = \'none\';"><i class="fa fa-btn fa-times" style="margin-right: 4px;"></i>' . $this->trans($this->package . '::messages.close') . '</a></div>' . PHP_EOL;
            $buttons .= '</div>' . PHP_EOL;

            // Translations
            $translations = '<div style="display:flex;">' . PHP_EOL;
            $translations .= $keyDiv . $textDiv;
            $translations .= '</div>' . PHP_EOL;

            $result = '<div id="transcontainer" style="display: none; position:fixed; top:0; height: 100%; width: 100%; align-items: center; justify-content:center; overflow: auto; z-index: 5" ><div id="transkeylist" class="transpopup">' . PHP_EOL . $buttons . $translations . '</div>' . PHP_EOL . '</div>' . PHP_EOL;
            return $result;
        }
        return null;
    }

    /**
     * Output translation strings for WebUI used by JS
     *
     * @return string
     */
    public function getWebUITranslations()
    {
        $TITLE_SAVE_CHANGES = $this->get($this->package . '::messages.title-save-changes');
        $TITLE_CANCEL_CHANGES = $this->get($this->package . '::messages.title-cancel-changes');
        $TITLE_TRANSLATE = $this->get($this->package . '::messages.title-translate');
        $TITLE_CONVERT_KEY = $this->get($this->package . '::messages.title-convert-key');
        $TITLE_GENERATE_PLURALS = $this->get($this->package . '::messages.title-generate-plurals');
        $TITLE_CLEAN_HTML_MARKDOWN = $this->get($this->package . '::messages.title-clean-html-markdown');
        $TITLE_CAPITALIZE = $this->get($this->package . '::messages.title-capitalize');
        $TITLE_LOWERCASE = $this->get($this->package . '::messages.title-lowercase');
        $TITLE_CAPITALIZE_FIRST_WORD = $this->get($this->package . '::messages.title-capitalize-first-word');
        $TITLE_SIMULATED_COPY = $this->get($this->package . '::messages.title-simulated-copy');
        $TITLE_SIMULATED_PASTE = $this->get($this->package . '::messages.title-simulated-paste');
        $TITLE_RESET_EDITOR = $this->get($this->package . '::messages.title-reset-editor');
        $TITLE_LOAD_LAST = $this->get($this->package . '::messages.title-load-last');

        return <<<HTML
<script>
var TITLE_SAVE_CHANGES = "$TITLE_SAVE_CHANGES";
var TITLE_CANCEL_CHANGES = "$TITLE_CANCEL_CHANGES";
var TITLE_TRANSLATE = "$TITLE_TRANSLATE";
var TITLE_CONVERT_KEY = "$TITLE_CONVERT_KEY";
var TITLE_GENERATE_PLURALS = "$TITLE_GENERATE_PLURALS";
var TITLE_CLEAN_HTML_MARKDOWN = "$TITLE_CLEAN_HTML_MARKDOWN";
var TITLE_CAPITALIZE = "$TITLE_CAPITALIZE";
var TITLE_LOWERCASE = "$TITLE_LOWERCASE";
var TITLE_CAPITALIZE_FIRST_WORD = "$TITLE_CAPITALIZE_FIRST_WORD";
var TITLE_SIMULATED_COPY = "$TITLE_SIMULATED_COPY";
var TITLE_SIMULATED_PASTE = "$TITLE_SIMULATED_PASTE";
var TITLE_RESET_EDITOR = "$TITLE_RESET_EDITOR";
var TITLE_LOAD_LAST = "$TITLE_LOAD_LAST";
</script>
HTML;
    }

    /**
     * Make the translation popup from used keys when rendering a page
     *
     * @return string
     */
    public function getEditableLinks()
    {
        $inplaceEditMode = $this->manager->config('inplace_edit_mode');
        if ($this->inPlaceEditing() && $inplaceEditMode == 2) {
            return getEditableTranslationsButton() . $this->getEditableLinksOnly();
        }
        return null;
    }

    public function getEditableTranslationsButton($style = null)
    {
        if ($style === null) {
            $style = 'style="position: fixed; right: 5px; top: 5px; z-index:99999;"';
        }

        $inplaceEditMode = $this->manager->config('inplace_edit_mode');
        if ($this->inPlaceEditing() && $inplaceEditMode == 2) {
            // Top right corner button
            $translateButton = '<a href="#"><i class="fa fa-language" ' . $style . ' onclick="document.getElementById(\'transcontainer\').style.display = \'flex\';"></i></a>' . PHP_EOL;

            return $translateButton;
        }
        return null;
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param  string $id
     * @param  int $number
     * @param  array $parameters
     * @param  string $locale
     * @param  string $domain
     * @param null $useDB
     *
     * @return string
     */
    public function transChoice($id, $number, array $parameters = array(), $locale = null, $domain = 'messages', $useDB = null)
    {
        return $this->choice($id, $number, $parameters, $locale, $useDB);
    }

    /**
     * Get the translation for a given key.
     *
     * @param  string $id
     * @param  array $parameters
     * @param  string $locale
     * @param  string $domain
     * @param null $useDB
     *
     * @return string
     */
    public function trans($id, array $parameters = array(), $locale = null, $domain = 'messages', $useDB = null)
    {
        return $this->get($id, $parameters, $locale, true, $useDB);
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param  string $key
     * @param  int $number
     * @param  array $replace
     * @param  string $locale
     *
     * @return string
     */
    public function choice($key, $number, array $replace = array(), $locale = null, $useDB = null)
    {
        $inplaceEditMode = $this->manager->config('inplace_edit_mode');
        if ($this->inPlaceEditing() && $inplaceEditMode == 2) {
            if (!in_array($key, $this->usedKeys)) {
                $this->usedKeys[] = $key;
            }
        }
        if (!$this->suspendInPlaceEdit && $this->inPlaceEditing() && $inplaceEditMode == 1) {
            return $this->get($key, $replace, $locale, true, $useDB);
        } else {
            if ($useDB !== null) {
                $oldUseDB = $this->useDB;
                $this->useDB = $useDB;
                $retVal = parent::choice($key, $number, $replace, $locale);
                $this->useDB = $oldUseDB;
                return $retVal;
            } else {
                return parent::choice($key, $number, $replace, $locale);
            }
        }
    }

    public function setTranslationManager(Manager $manager)
    {
        $this->manager = $manager;
        $this->package = \Vsch\TranslationManager\ManagerServiceProvider::PACKAGE;
        $this->packagePrefix = $this->package . '::';
        $this->cookiePrefix = $this->manager->config('persistent_prefix', $this->packagePrefix);
        $this->useCookies = $this->manager->config('use_cookies', true);
        $this->cookiesLoaded = !$this->useCookies;
    }

    protected function notifyMissingGroupItem($namespace, $group, $item, $locale = null)
    {
        if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
            $this->manager->missingKey($namespace, $group, $item, $locale, $this->isUseLottery(), false);
        }
    }

    protected function notifyUsingGroupItem($namespace, $group, $item, $locale = null)
    {
        if (!$this->suspendUsageLogging) {
            if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                $augmentedGroup = $this->manager->getAugmentedGroup($namespace, $group);
                $this->manager->usingKey('', $augmentedGroup, $item, $locale, $this->isUseLottery());
            }
        }
    }

    public static function routes()
    {
        $config = App::get('config')[\Vsch\TranslationManager\ManagerServiceProvider::PACKAGE];
        $key = \Vsch\TranslationManager\Manager::DISABLE_REACT_UI;

        $disableReactUI = array_key_exists($key, $config) ? $config[$key] : false;
        Controller::routes($disableReactUI);
    }

    public static function webRoutes()
    {
        Controller::webRoutes();
    }

    public static function apiRoutes()
    {
        $config = App::get('config')[\Vsch\TranslationManager\ManagerServiceProvider::PACKAGE];
        $key = \Vsch\TranslationManager\Manager::DISABLE_REACT_UI;

        $disableReactUI = array_key_exists($key, $config) ? $config[$key] : false;
        Controller::apiRoutes($disableReactUI);
    }

    public function getLocales()
    {
        //Set the default locale as the first one.
        $currentLocale = Config::get('app.locale');
        $locales = ManagerServiceProvider::getLists($this->manager->getTranslation()->groupBy('locale')->pluck('locale')) ?: [];

        // limit the locale list to what is in the config
        $configShowLocales = $this->manager->config(Manager::SHOW_LOCALES_KEY, []);
        if ($configShowLocales) {
            if (!is_array($configShowLocales)) $configShowLocales = array($configShowLocales);
            $locales = array_intersect($locales, $configShowLocales);
        }

        $configLocales = $this->manager->config(Manager::ADDITIONAL_LOCALES_KEY, []);
        if (!is_array($configLocales)) $configLocales = array($configLocales);

        $locales = array_merge(array($currentLocale), $configLocales, $locales);
        return array_flatten(array_unique($locales));
    }
}
