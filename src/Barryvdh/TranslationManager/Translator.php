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

    /* @var $manager Manager  */
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
                list($namespace, $group, $item) = $this->parseKey($key);

                if ($this->manager && $namespace === '*' && $group && $item && !$this->manager->excludedPageEditGroup($group))
                {
                    $t = $this->manager->missingKey($namespace, $group, $item);
                    if ($t)
                    {
                        if (is_null($t->value)) $t->value = ''; //$t->value = parent::get($key, $replace, $locale);

                        $result = '<a href="#edit" class="editable status-' . ($t ? $t->status : 0) . ' locale-' . $t->locale . '" data-locale="' . $t->locale . '"
                        data-name="' . $t->locale . '|' . $t->key . '" id="username" data-type="textarea" data-pk="' . ($t ? $t->id : 0) . '"
                        data-url="' . URL::action('Barryvdh\TranslationManager\Controller@postEdit', array($t->group)) . '"
                        data-inputclass="editable-input"
                        data-title="' . parent::trans('laravel-translation-manager::translations.enter-translation') . ': [' . $t->locale . '] ' . $key . '">'
                            . ($t ? htmlentities($t->value, ENT_QUOTES, 'UTF-8', false) : '') . '</a> '//. (!$t ? '' : ($t->saved_value === $t->value ? '' : ' [' . \Barryvdh\TranslationManager\Controller::mb_renderDiffHtml($t->saved_value, $t->value) . ']'));
                        ;
                        return $result;
                    }
                }
            }
        }

        $result = parent::get($key, $replace, $locale);
        if ($result === $key)
        {
            $this->notifyMissingKey($key);
        }
        return $result;
    }

    public
    function setTranslationManager(Manager $manager)
    {
        $this->manager = $manager;
    }

    protected
    function notifyMissingKey($key)
    {
        list($namespace, $group, $item) = $this->parseKey($key);
        if ($this->manager && $namespace === '*' && $group && $item)
        {
            // KLUDGE: find an independent way to hook in role validation on users
            $this->manager->missingKey($namespace, $group, $item, !Auth::check() || Auth::user()->useTranslatorMissingKeysLottery());
        }
    }
}
