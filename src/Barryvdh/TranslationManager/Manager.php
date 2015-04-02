<?php namespace Barryvdh\TranslationManager;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Events\Dispatcher;
use Barryvdh\TranslationManager\Models\Translation;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Symfony\Component\Finder\Finder;

/**
 * Class Manager
 * @package Barryvdh\TranslationManager
 */
class Manager
{

    /** @var \Illuminate\Foundation\Application */
    protected $app;
    /** @var \Illuminate\Filesystem\Filesystem */
    protected $files;
    /** @var \Illuminate\Events\Dispatcher */
    protected $events;

    protected $config;
    protected $imported;
    protected $translation;
    protected $cachePrefix;
    protected $cache;
    protected $cacheIsDirty;

    public
    function __construct(Application $app, Filesystem $files, Dispatcher $events, Translation $translation)
    {
        $this->app = $app;
        $this->files = $files;
        $this->events = $events;
        $this->translation = $translation;

        $this->cachePrefix = null;
        $this->cache = null;

        // when instantiated from the service provider, config info is not yet loaded, trying to get it here
        // causes a problem since none of the keys are defined.
        $this->config = null;

        $manager = $this;
        $app->after(function () use ($manager)
        {
            $manager->saveCache();
        });
    }

    public
    function config()
    {
        return $this->config ?: $this->config = $this->app['config']['laravel-translation-manager::config'];
    }

    public
    function cacheEnabled()
    {
        return $this->cachePrefix() !== '';
    }

    public
    function cachePrefix()
    {
        if ($this->cachePrefix === null)
        {
            if (in_array('cache_prefix', $this->config()))
            {
                $this->cachePrefix = $this->config()['cache_prefix'];
            }
            else
            {
                $this->cachePrefix = '';
            }
        }
        return $this->cachePrefix;
    }

    public
    function cache()
    {
        if ($this->cache === null)
        {
            $this->cache = $this->cachePrefix() !== '' && Cache::has($this->cachePrefix) ? Cache::get($this->cachePrefix) : [];
            $this->cacheIsDirty = $this->cachePrefix !== '' && !Cache::has($this->cachePrefix);
        }
        return $this->cache;
    }

    public
    function saveCache()
    {
        if ($this->cachePrefix && $this->cacheIsDirty)
        {
            Cache::put($this->cachePrefix, $this->cache, 60 * 24 * 365);
            $this->cacheIsDirty = false;
        }
    }

    public
    function clearCache($groups = null)
    {
        if (!$groups)
        {
            $this->cache = [];
            $this->cacheIsDirty = !!$this->cachePrefix;
        }
        elseif ($this->cache())
        {
            if (!is_array($groups)) $groups = [$groups];

            foreach ($groups as $group)
            {
                if (array_key_exists($group, $this->cache))
                {
                    unset($this->cache[$group]);
                    $this->cacheIsDirty = !!$this->cachePrefix;
                }
            }
        }
    }

    public
    function cacheKey($key, $locale)
    {
        return $locale . ':' . $key;
    }

    protected static
    function groupKeyList($key)
    {
        $key = explode('.', $key, 2);
        if (count($key) > 1)
        {
            $group = $key[0];
            $key = $key[1];
        }
        else
        {
            $group = '';
            $key = $key[0];
        }
        return [$group, $key];
    }

    public
    function cacheTranslation($key, $value, $locale)
    {
        list($group, $transKey) = self::groupKeyList($key);

        if ($group)
        {
            if (!array_key_exists($group, $this->cache())) $this->cache[$group] = [];
            $this->cache[$group][$locale . ':' . $transKey] = $value;
            $this->cacheIsDirty = $this->cachePrefix !== '';
        }
    }

    public
    function cachedTranslation($key, $locale)
    {
        list($group, $transKey) = self::groupKeyList($key);
        $value = $group && array_key_exists($group, $this->cache()) && array_key_exists($locale . ':' . $transKey, $this->cache[$group]) ? $this->cache[$group][$locale . ':' . $transKey] : null;
        return $value;
    }

    public
    function excludedPageEditGroup($group)
    {
        return in_array($group, $this->config()['exclude_page_edit_groups']);
    }

    /**
     * @param      $namespace string
     * @param      $group     string
     * @param      $key       string
     *
     * @param null $locale
     * @param bool $useLottery
     * @param bool $findOrNew
     *
     * @return \Barryvdh\TranslationManager\Models\Translation|null
     */
    public
    function missingKey($namespace, $group, $key, $locale = null, $useLottery = false, $findOrNew = false)
    {
        $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

        if (!in_array($group, $this->config()['exclude_groups']) && $this->config()['log_missing_keys'])
        {
            $lottery = 1;
            if ($useLottery && $this->config()['missing_keys_lottery'] !== 1)
            {
                $lottery = Session::get('laravel_translation_manager.lottery', '');
                if ($lottery === '')
                {
                    $lottery = rand(1, $this->config()['missing_keys_lottery']);
                    Session::put('laravel_translation_manager.lottery', $lottery);
                }
            }

            if ($lottery === 1)
            {
                $locale = $locale ?: $this->app['config']['app.locale'];
                if ($findOrNew)
                {
                    $translation = Translation::firstOrNew(array(
                        'locale' => $locale,
                        'group' => $group,
                        'key' => $key,
                    ));
                }
                else
                {
                    $translation = Translation::firstOrCreate(array(
                        'locale' => $locale,
                        'group' => $group,
                        'key' => $key,
                    ));
                }

                return $translation;
            }
        }
        return null;
    }

    public
    function importTranslationLocale($replace = false, $locale, $langPath, $namespace = null, $groups = null)
    {
        $files = $this->files->files($langPath);
        $package = $namespace ? $namespace . '::' : '';
        foreach ($files as $file)
        {
            $info = pathinfo($file);
            $group = $info['filename'];

            if (in_array($package . $group, $this->config()['exclude_groups']) || ($groups && !in_array($package . $group, $groups)))
            {
                continue;
            }

            $translations = array_dot(\Lang::getLoader()->load($locale, $group, $namespace));

            $dbTranslations = $this->translation->hydrateRaw(<<<SQL
SELECT * FROM ltm_translations WHERE locale = ? AND `group` = ?

SQL
                , [$locale, $package . $group]);

            $dbTransMap = [];
            $dbTranslations->each(function ($trans) use (&$dbTransMap)
            {
                $dbTransMap[$trans->key] = $trans;
            });

            foreach ($translations as $key => $value)
            {
                $value = (string)$value;

                if (array_key_exists($key, $dbTransMap))
                {
                    $translation = $dbTransMap[$key];
                }
                else
                {
                    $translation = new Translation(array(
                        'locale' => $locale,
                        'group' => $package . $group,
                        'key' => $key,
                    ));
                }

                // Importing from the source, status is always saved. When it is changed by the user, then it is changed.
                //$newStatus = ($translation->value === $value || !$translation->exists) ? Translation::STATUS_SAVED : Translation::STATUS_CHANGED;
                // Only replace when empty, or explicitly told so
                if ($replace || !$translation->value)
                {
                    $translation->value = $value;
                }

                $translation->saved_value = $value;

                $newStatus = ($translation->value === $translation->saved_value ? Translation::STATUS_SAVED
                    : ($translation->status === Translation::STATUS_SAVED ? Translation::STATUS_SAVED_CACHED : Translation::STATUS_CHANGED));

                if ($newStatus !== (int)$translation->status)
                {
                    $translation->status = $newStatus;
                }

                if (!$translation->exists || $translation->isDirty())
                {
                    $translation->save();
                }

                $this->imported++;
            }
        }
    }

    public
    function importTranslations($replace = false, $packages = false, $groups = null)
    {
        if (!$packages)
        {
            $this->imported = 0;

            $this->clearCache($groups);
        }

        $langdirs = $this->files->directories($this->app->make('path') . '/lang' . ($packages ? '/packages' : ''));
        foreach ($langdirs as $langdir)
        {
            $locale = basename($langdir);
            if ($locale === 'packages' && !$packages)
            {
                $this->importTranslations($replace, true, $groups);
            }
            else
            {
                if ($packages)
                {
                    $packdirs = $this->files->directories($langdir);
                    foreach ($packdirs as $packagedir)
                    {
                        $package = basename($packagedir);
                        $this->importTranslationLocale($replace, $locale, $packagedir, $package, $groups);
                    }
                }
                else
                {
                    $this->importTranslationLocale($replace, $locale, $langdir, null, $groups);
                }
            }
        }
        return $this->imported;
    }

    public
    function findTranslations($path = null)
    {
        $path = $path ?: $this->app['path'];
        $keys = array();
        $functions = array(
            'trans',
            'trans_choice',
            'Lang::get',
            'Lang::choice',
            'Lang::trans',
            'Lang::transChoice',
            '@lang',
            '@choice'
        );
        $pattern =                              // See http://regexr.com/392hu
            "(" . implode('|', $functions) . ")" .  // Must start with one of the functions
            "\(" .                               // Match opening parenthese
            "[\'\"]" .                           // Match " or '
            "(" .                                // Start a new group to match:
            "[a-zA-Z0-9_-]+" .               // Must start with group
            "([.][^\1)]+)+" .                // Be followed by one or more items/keys
            ")" .                                // Close group
            "[\'\"]" .                           // Closing quote
            "[\),]";                            // Close parentheses or new parameter

        // Find all PHP + Twig files in the app folder, except for storage
        $finder = new Finder();
        $finder->in($path)->exclude('storage')->name('*.php')->name('*.twig')->files();

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file)
        {
            // Search the current file for the pattern
            if (preg_match_all("/$pattern/siU", $file->getContents(), $matches))
            {
                // Get all matches
                foreach ($matches[2] as $key)
                {
                    $keys[] = $key;
                }
            }
        }
        // Remove duplicates
        $keys = array_unique($keys);

        // Add the translations to the database, if not existing.
        foreach ($keys as $key)
        {
            // Split the group and item
            list($group, $item) = explode('.', $key, 2);
            $this->missingKey('', $group, $item);
        }

        // Return the number of found translations
        return count($keys);
    }

    protected
    function formatForExport($trans, $indent = 0)
    {
        $ind = str_repeat(' ', $indent * 4);
        $text = '';
        if (is_array($trans))
        {
            $text .= "array(\n";
            $indT = $ind . str_repeat(' ', 4);
            $max = 0;
            foreach ($trans as $key => $val)
            {
                if (!is_int($key) && strlen($key) > $max) $max = strlen($key);
            }
            $max += (($max + 2) & 3) ? 4 - (($max + 2) & 3) : 0;

            foreach ($trans as $key => $val)
            {
                $val = $this->formatForExport($val, $indent + 1);
                if (is_int($key))
                {
                    $text .= $indT . $val . ",\n";
                }
                else
                {
                    $pad = str_repeat(' ', $max - strlen($key));
                    $text .= $indT . "'$key'$pad=> $val,\n";
                }
            }
            $text .= $ind . ")";
        }
        else
        {
            if (strpos($trans, "\n") !== false)
            {
                $text = "<<<'TEXT'\n$trans\nTEXT\n";
            }
            else
            {
                $trans = trim(str_replace("'", "\\'", $trans));
                $text = "'$trans'";
            }
        }
        return $text;
    }

    public
    function exportTranslations($group)
    {
        if (array_key_exists('indatabase_publish', $this->config()) && $this->config['indatabase_publish'])
        {
            if ($group && $group !== '*')
            {
                $this->translation->getConnection()->affectingStatement(<<<SQL
UPDATE ltm_translations SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?) AND `group` = ?
SQL
                    , [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED, $group]);
            }
            else
            {
                $this->translation->getConnection()->affectingStatement(<<<SQL
UPDATE ltm_translations SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?)
SQL
                    , [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED]);
            }

            /* @var $translations Collection */
            $translations = $this->translation->query()->where('status', '<>', Translation::STATUS_SAVED)->get(['group', 'key', 'locale', 'saved_value']);
            $translations->each(function ($tr)
            {
                $this->cacheTranslation($tr->group . '.' . $tr->key, $tr->saved_value, $tr->locale);
            });
        }
        else
        {
            if (!in_array($group, $this->config()['exclude_groups']))
            {
                if ($group == '*')
                    $this->exportAllTranslations();

                $this->clearCache($group);

                $tree = $this->makeTree(Translation::where('group', $group)->whereNotNull('value')->orderby('key')->get());

                foreach ($tree as $locale => $groups)
                {
                    if (isset($groups[$group]))
                    {
                        $translations = $groups[$group];

                        if (strpos($group, '::') !== false)
                        {
                            // package group
                            $packgroup = explode('::', $group, 2);
                            $package = array_shift($packgroup);
                            $packgroup = array_shift($packgroup);
                            $path = $this->app->make('path') . '/lang/packages/' . $locale . '/' . $package . '/' . $packgroup . '.php';
                        }
                        else
                        {
                            $path = $this->app->make('path') . '/lang/' . $locale . '/' . $group . '.php';
                        }

                        $output = "<?php\n\nreturn " . $this->formatForExport($translations) . ";\n";
                        $this->files->put($path, $output);
                    }
                }
                Translation::where('group', $group)->update(array('status' => Translation::STATUS_SAVED, 'saved_value' => (new Expression('value'))));
            }
        }
    }

    public
    function exportAllTranslations()
    {
        $groups = Translation::whereNotNull('value')->select(DB::raw('DISTINCT `group`'))->get('group');
        $this->clearCache();

        foreach ($groups as $group)
        {
            $this->exportTranslations($group->group);
        }
    }

    public
    function cleanTranslations()
    {
        Translation::whereNull('value')->delete();
    }

    public
    function truncateTranslations($group = '*')
    {
        xdebug_break();
        if ($group === '*')
        {
            Translation::truncate();
        }
        else
        {
            DB::statement("DELETE FROM ltm_translations WHERE `group` = ?", [$group]);
        }
    }

    protected
    function makeTree($translations)
    {
        $array = array();
        foreach ($translations as $translation)
        {
            array_set($array[$translation->locale][$translation->group], $translation->key, $translation->value);
        }
        return $array;
    }

    public
    function getConfig($key = null)
    {
        if ($key == null)
        {
            return $this->config();
        }
        else
        {
            return $this->config()[$key];
        }
    }
}
