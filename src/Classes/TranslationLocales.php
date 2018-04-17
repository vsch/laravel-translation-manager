<?php
namespace Vsch\TranslationManager\Classes;

use Vsch\TranslationManager\Manager;

class TranslationLocales
{
    private $manager;
    
    public $appLocale;
    public $currentLocale;
    public $primaryLocale;
    public $translatingLocale;
    public $displayLocales;
    public $userLocales;
    public $locales;
    public $allLocales;

    /**
     * TranslationLocales constructor.
     * @param $manager
     */
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public static function packLocales($locales)
    {
        $packedLocales = implode(',', $locales);
        return $packedLocales ? ',' . $packedLocales . ',' : '';
    }

    private static function nat_sort($arr)
    {
        natsort($arr);
        return $arr;
    }
    
    private static function firstOf($arg) {
        if ($arg) {
            $json = '';
            foreach ($arg as $key => $value) {
                if ($value !== 'json') {
                    return $value;
                }
            }
            return $json;
        }
        return '';
    }

    public function normalize() {
        $appLocale = $this->appLocale;
        $allLocales = $this->allLocales;
        $currentLocale = $this->currentLocale;
        $primaryLocale = $this->primaryLocale;
        $translatingLocale = $this->translatingLocale;
        $displayLocales = $this->displayLocales;

        // get all locales in the translation table
        // limit the locale list to what is in the config
        $configShowLocales = $this->manager->config(Manager::SHOW_LOCALES_KEY, []);
        if ($configShowLocales) {
            if (!is_array($configShowLocales)) $configShowLocales = array($configShowLocales);
        }

        $addConfigLocales = $this->manager->config(Manager::ADDITIONAL_LOCALES_KEY, []);
        if (!is_array($addConfigLocales)) $addConfigLocales = array($addConfigLocales);

        // always add the current locale as reported by the application
        $addConfigLocales[] = $appLocale;

        // trim to show locales and add additional locales
        $allShowLocales = $configShowLocales ? array_intersect($allLocales, $configShowLocales) : $allLocales;
        $locales = array_unique(array_merge($allShowLocales, $addConfigLocales));
        
        $userLocales = $this->userLocales ?: $locales;
        $userLocales = array_values(array_unique(array_intersect($userLocales, $locales)));

        // now make sure primary, translating and current locale are part of the $locale list
        if (array_search($currentLocale, $locales) === false) $currentLocale = $appLocale;
        if (array_search($primaryLocale, $locales) === false) $primaryLocale = $appLocale;

        if ($translatingLocale === $primaryLocale
            || array_search($translatingLocale, $locales) === false
            || array_search($translatingLocale, $userLocales) === false) {
            $translatingLocale = null;
        }

        if (!$translatingLocale) {
            $userTranslatableLocales = self::nat_sort(array_diff($userLocales, array($primaryLocale)));
            $firstLocale = self::firstOf($userTranslatableLocales);
            $translatingLocale = $firstLocale  ?: $primaryLocale;
        }

        // now need to create displayLocales
        if (!$displayLocales || count($displayLocales) === 0) $displayLocales = [$primaryLocale, $translatingLocale]; 
        $displayLocales = array_intersect($displayLocales, $locales);

        // add primary, translating to list
        $firstLocales = array($primaryLocale);
        if ($translatingLocale !== $primaryLocale) $firstLocales[] = $translatingLocale;
        $displayLocales = self::nat_sort(array_diff($displayLocales, $firstLocales));

        // display has primary, translating then the rest of displayed locales
        $displayLocales = array_values(array_merge($firstLocales, $displayLocales));
        // locales has displayed locales then the rest displayable ones
        $locales = array_values(array_merge($displayLocales, self::nat_sort(array_diff($locales, $displayLocales))));

        $this->currentLocale = $currentLocale;
        $this->primaryLocale = $primaryLocale;
        $this->translatingLocale = $translatingLocale;
        $this->userLocales = $userLocales;
        $this->displayLocales = $displayLocales;
        $this->locales = $locales;
    }
}
