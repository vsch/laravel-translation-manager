<?php namespace Vsch\TranslationManager;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
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

    // Storage used for used translation keys
    protected $usedKeys = array();

    /**
     * Translator constructor.
     *
     * @param \Illuminate\Foundation\Application       $app
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
            $this->useLottery = !\Gate::allows(Manager::ABILITY_BYPASS_LOTTERY);
        }
        return $this->useLottery;
    }

    public function inPlaceEditing($inPlaceEditing = null)
    {
        if ($inPlaceEditing !== null) {
            $this->inPlaceEditing = $inPlaceEditing;
            if ($this->useCookies) {
                \Cookie::queue($this->cookiePrefix . 'lang_inplaceedit', $this->inPlaceEditing);
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
                if (\Cookie::has($this->cookiePrefix . 'lang_inplaceedit')) {
                    $this->inPlaceEditing = \Cookie::get($this->cookiePrefix . 'lang_inplaceedit', 0);
                }
            } else {
                $session = $this->app->make('session');
                $this->inPlaceEditing = $session->get($this->cookiePrefix . 'lang_inplaceedit', 0);
            }
        }

        // reset in place edit mode if not logged in
        if ($this->inPlaceEditing != 0 && !\Auth::check()) {
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
        if ($this->useCookies) {
            $locale = \Cookie::get($this->cookiePrefix . 'lang_locale', parent::getLocale());
            if ($locale != parent::getLocale()) {
                parent::setLocale($locale);
            }
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
            \Cookie::queue($this->cookiePrefix . 'lang_locale', $locale);
        }
        $this->locale = $locale;
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

    public function inPlaceEditLink($t, $withDiff = false, $key = null, $locale = null, $useDB = null, $group = null)
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

                $action = \URL::action(ManagerServiceProvider::CONTROLLER_PREFIX . 'Vsch\TranslationManager\Controller@postEdit', array($t->group));

                $result = '<a href="#edit" class="vsch_editable status-' . ($t->status ?: 0)
                    . ' locale-' . $t->locale
                    . '" data-locale="' . $t->locale . '" '
                    . 'data-name="' . $t->locale . '|' . $t->key
                    . '" id="' . $t->locale . "-" . str_replace('.', '-', $t->key)
                    . '"  data-type="textarea" data-pk="' . ($t->id ?: 0) . '" '
                    . 'data-url="' . $action
                    . '" ' . 'data-inputclass="editable-input" data-saved_value="' . htmlentities($t->saved_value, ENT_QUOTES, 'UTF-8', false) . '" '
                    . 'data-title="' . $title . ': [' . $t->locale . '] ' . $t->group . '.' . $t->key . '">' . ($t ? htmlentities($t->value, ENT_QUOTES, 'UTF-8', false) : '') . '</a> ' . $diff;
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
     * @param bool    $fallback
     * @param  int    $useDB null - check usedb field which is set to 1 by default,
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
            $this->notifyUsingKey($key, $locale);
            return $this->inPlaceEditLink(null, true, $key, $locale);
        }

        $cacheKey = null;
        if ($useDB === null) $useDB = $this->useDB;

        if ($useDB && $useDB !== 2) {
            $result = $this->manager->cachedTranslation($namespace, $group, $item,$locale ?: $this->locale());
            if ($result) {
                $this->notifyUsingKey($key, $locale);
                return $this->processResult($result, $replace);
            }
        }

        if ($useDB == 2) {
            if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                $t = $this->manager->missingKey($namespace, $group, $item, $locale, $this->isUseLottery(), true);
                if ($t) {
                    $result = $t->value ?: $key;
                    if ($t->isDirty()) $t->save();
                    $this->notifyUsingKey($key, $locale);
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
                        if ($t->isDirty()) $t->save();

                        // save in cache even if it has no value to prevent hitting the database every time just to figure it out
                        if (true || $result !== $key) {
                            // save in cache
                            $group = Manager::fixGroup($group);
                            $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;
                            $this->manager->cacheTranslation($group, $item, $result, $locale ?: $this->getLocale());
                            return $this->processResult($result, $replace);
                        }
                        $this->notifyUsingKey($key, $locale);
                        return $result;
                    }
                }
            }

            $this->notifyMissingKey($key, $locale);
            $this->notifyUsingKey($key, $locale);
        } else {
            $this->notifyUsingKey($key, $locale);
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
     * @param  int    $number
     * @param  array  $parameters
     * @param  string $locale
     * @param  string $domain
     * @param null    $useDB
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
     * @param  array  $parameters
     * @param  string $locale
     * @param  string $domain
     * @param null    $useDB
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
     * @param  int    $number
     * @param  array  $replace
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
    }

    protected function notifyMissingKey($key, $locale = null)
    {
        list($namespace, $group, $item) = $this->parseKey($key);
        if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
            $this->manager->missingKey($namespace, $group, $item, $locale, $this->isUseLottery(), false);
        }
    }

    protected function notifyUsingKey($key, $locale = null)
    {
        if (!$this->suspendUsageLogging) {
            list($namespace, $group, $item) = $this->parseKey($key);
            if ($this->manager && $group && $item && !$this->manager->excludedPageEditGroup($group)) {
                $this->manager->usingKey($namespace, $group, $item, $locale, $this->isUseLottery());
            }
        }
    }

    public static function routes()
    {
        Controller::routes();
    }

    public function getLocales()
    {
        //Set the default locale as the first one.
        $currentLocale = \Config::get('app.locale');
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
