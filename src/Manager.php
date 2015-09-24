<?php namespace Vsch\TranslationManager;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Vsch\TranslationManager\Classes\PathTemplateResolver;
use Vsch\TranslationManager\Classes\TranslationFileRewriter;
use Vsch\TranslationManager\Models\Translation;
use ZipArchive;

/**
 * Class Manager
 *
 * @package Vsch\TranslationManager
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
    protected $persistentPrefix;
    protected $cache;
    protected $cacheIsDirty;
    protected $cacheTransKey;
    protected $usageCache;
    protected $usageCacheIsDirty;
    protected $usageCacheTransKey;
    private $package;
    protected $errors;

    /**
     * @var   \ZipArchive
     */
    protected $zipExporting;

    public
    function packageName($package)
    {
        $this->package = $package;
    }

    public
    function __construct(Application $app, Filesystem $files, Dispatcher $events, Translation $translation)
    {
        $this->app = $app;
        $this->files = $files;
        $this->events = $events;
        $this->translation = $translation;

        $this->persistentPrefix = null;
        $this->cache = null;
        $this->usageCache = null;

        // when instantiated from the service provider, config info is not yet loaded, trying to get it here
        // causes a problem since none of the keys are defined.
        $this->config = null;

        $manager = $this;
        $events = \App::make('events');
        $events->listen('router.after', function () use ($manager)
        {
            $manager->saveCache();
            $manager->saveUsageCache();
        });
    }

    public
    function config($key = null, $default = null)
    {
        // Version 5.1
        if (!$this->config) $this->config = $this->app['config'][$this->package];
        // Version 4.2
        //if (!$this->config) $this->config = $this->app['config'][$this->package.'::config'];

        if ($key === null)
        {
            return $this->config;
        }
        if (array_key_exists($key, $this->config))
        {
            return $this->config[$key];
        }
        return $default;
    }

    public
    function cacheEnabled()
    {
        return $this->cachePrefix() !== '';
    }

    public
    function cachePrefix()
    {
        if ($this->persistentPrefix === null)
        {
            if (array_key_exists('persistent_prefix', $this->config()))
            {
                $this->persistentPrefix = $this->config()['persistent_prefix'];
                $this->cacheTransKey = $this->persistentPrefix ? $this->persistentPrefix . 'translations' : '';
            }
            else
            {
                $this->persistentPrefix = '';
            }
        }
        return $this->persistentPrefix;
    }

    public
    function usageCachePrefix()
    {
        if ($this->usageCacheTransKey == null)
        {
            if ($this->persistentPrefix === null)
            {
                if (array_key_exists('persistent_prefix', $this->config()))
                {
                    $this->persistentPrefix = $this->config()['persistent_prefix'];
                }
                else
                {
                    $this->persistentPrefix = '';
                }
            }
            $this->usageCacheTransKey = $this->persistentPrefix ? $this->persistentPrefix . 'usage_info' : '';
        }
        return $this->persistentPrefix;
    }

    public
    function cache()
    {
        if ($this->cache === null)
        {
            $this->cache = $this->cachePrefix() !== '' && \Cache::has($this->cacheTransKey) ? \Cache::get($this->cacheTransKey) : [];
            $this->cacheIsDirty = $this->persistentPrefix !== '' && !\Cache::has($this->cacheTransKey);
        }
        return $this->cache;
    }

    public
    function usageCache()
    {
        if ($this->usageCache === null)
        {
            $this->usageCache = $this->usageCachePrefix() !== '' && \Cache::has($this->usageCacheTransKey) ? \Cache::get($this->usageCacheTransKey) : [];
            $this->usageCacheIsDirty = $this->persistentPrefix !== '' && !\Cache::has($this->usageCacheTransKey);
        }
        return $this->usageCache;
    }

    public
    function saveCache()
    {
        if ($this->persistentPrefix && $this->cacheIsDirty)
        {
            \Cache::put($this->cacheTransKey, $this->cache, 60 * 24 * 365);
            $this->cacheIsDirty = false;
        }
    }

    public
    function saveUsageCache()
    {
        if ($this->persistentPrefix && $this->usageCacheIsDirty)
        {
            // we never save it in the cache, it is only in database use, otherwise every page it will save the full cache to the database
            // instead of only the accessed keys
            //\Cache::put($this->usageCacheTransKey, $this->usageCache, 60 * 24 * 365);
            \Cache::put($this->usageCacheTransKey, [], 60 * 24 * 365);
            $this->usageCacheIsDirty = false;

            // now update the keys in the database
            foreach ($this->usageCache as $group => $keys)
            {
                $setKeys = "";
                $resetKeys = "";
                foreach ($keys as $key => $usage)
                {
                    if ($usage)
                    {
                        if ($setKeys) $setKeys .= ',';
                        $setKeys .= "'$key'";
                    }
                    else
                    {
                        if ($resetKeys) $resetKeys .= ',';
                        $resetKeys .= "'$key'";
                    }
                }

                if ($setKeys)
                {
                    $this->translation->getConnection()->affectingStatement(<<<SQL
UPDATE ltm_translations SET was_used = 1 WHERE was_used <> 1 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?) AND `key` IN ($setKeys)
SQL
                        , [$group, 'vnd:%.'.$group, 'wbn:%.'.$group]);
                }
                if ($resetKeys)
                {
                    $this->translation->getConnection()->affectingStatement(<<<SQL
UPDATE ltm_translations SET was_used = 0 WHERE was_used <> 0 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?) AND `key` IN ($resetKeys)
SQL
                        , [$group, 'vnd:%.'.$group, 'wbn:%.'.$group]);
                }
            }
        }
    }

    public
    function clearCache($groups = null)
    {
        if (!$groups || $groups === '*')
        {
            $this->cache = [];
            $this->cacheIsDirty = !!$this->persistentPrefix;
        }
        elseif ($this->cache())
        {
            if (!is_array($groups)) $groups = [$groups];

            foreach ($groups as $group)
            {
                if (array_key_exists($group, $this->cache))
                {
                    unset($this->cache[$group]);
                    $this->cacheIsDirty = !!$this->persistentPrefix;
                }
            }
        }
    }

    public
    function clearUsageCache($clearDatabase, $groups = null)
    {
        if (!$groups || $groups === '*')
        {
            $this->usageCache();
            $this->usageCache = [];
            $this->usageCacheIsDirty = true;
            $this->saveUsageCache();

            if ($clearDatabase)
            {
                $this->translation->getConnection()->affectingStatement(<<<SQL
UPDATE ltm_translations SET was_used = 0 WHERE was_used <> 0
SQL
                );
            }
        }
        elseif ($this->usageCache())
        {
            $this->usageCache();
            if (!is_array($groups)) $groups = [$groups];

            foreach ($groups as $group)
            {
                if (array_key_exists($group, $this->usageCache))
                {
                    unset($this->usageCache[$group]);
                    $this->usageCacheIsDirty = !!$this->persistentPrefix;
                }

                if ($clearDatabase)
                {
                    $this->translation->getConnection()->affectingStatement(<<<SQL
UPDATE ltm_translations SET was_used = 0 WHERE was_used <> 0 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?)
SQL
                        , [$group, 'vnd:%.'.$group, 'wbn:%.'.$group]);
                }
            }

            $this->saveUsageCache();
        }
    }

    public
    function cacheKey($key, $locale)
    {
        return $locale . ':' . $key;
    }

    public
    function usageCacheKey($key, $locale)
    {
        return $key;
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
            $this->cache[$group][$this->cacheKey($transKey, $locale)] = $value;
            $this->cacheIsDirty = $this->persistentPrefix !== '';
        }
    }

    public
    function cachedTranslation($key, $locale)
    {
        list($group, $transKey) = self::groupKeyList($key);
        $cacheKey = $this->cacheKey($transKey, $locale);
        $value = $group && array_key_exists($group, $this->cache()) && array_key_exists($cacheKey, $this->cache[$group]) ? $this->cache[$group][$cacheKey] : null;
        return $value;
    }

    public
    function cacheUsageInfo($key, $value, $locale)
    {
        list($group, $transKey) = self::groupKeyList($key);

        if ($group)
        {
            if (!array_key_exists($group, $this->usageCache())) $this->usageCache[$group] = [];
            $this->usageCache[$group][$this->usageCacheKey($transKey, $locale)] = $value;
            $this->usageCacheIsDirty = $this->persistentPrefix !== '';
        }
    }

    public
    function cachedUsageInfo($key, $locale)
    {
        list($group, $transKey) = self::groupKeyList($key);
        $cacheKey = $this->usageCacheKey($transKey, $locale);
        $value = $group && array_key_exists($group, $this->usageCache()) && array_key_exists($cacheKey, $this->usageCache[$group]) ? $this->usageCache[$group][$cacheKey] : null;
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
     * @return \Vsch\TranslationManager\Models\Translation|null
     */
    public
    function missingKey($namespace, $group, $key, $locale = null, $useLottery = false, $findOrNew = false)
    {
        if ($this->config()['log_missing_keys'])
        {
            $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

            if (!in_array($group, $this->config()['exclude_groups']))
            {
                $lottery = 1;
                if ($useLottery && $this->config()['missing_keys_lottery'] !== 1)
                {
                    $lottery = \Session::get($this->config()['persistent_prefix'] . 'lottery', '');
                    if ($lottery === '')
                    {
                        $lottery = rand(1, $this->config()['missing_keys_lottery']);
                        \Session::put($this->config()['persistent_prefix'] . 'lottery', $lottery);
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
        }
        return null;
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
     * @return void
     */
    public
    function usingKey($namespace, $group, $key, $locale = null, $useLottery = false)
    {
        if ($this->config()['log_key_usage_info'])
        {
            $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

            if (!in_array($group, $this->config()['exclude_groups']))
            {
                $lottery = 1;
                if ($useLottery && $this->config()['missing_keys_lottery'] !== 1)
                {
                    $lottery = \Session::get($this->config()['persistent_prefix'] . 'lottery', '');
                    if ($lottery === '')
                    {
                        $lottery = rand(1, $this->config()['missing_keys_lottery']);
                        \Session::put($this->config()['persistent_prefix'] . 'lottery', $lottery);
                    }
                }

                if ($lottery === 1)
                {
                    $locale = $locale ?: $this->app['config']['app.locale'];
                    $this->cacheUsageInfo($group . '.' . $key, 1, $locale);
                }
            }
        }
    }

    /**
     * @param $locale
     * @param $db_group
     * @param $translations
     * @param $replace
     */
    protected
    function importTranslationFile($locale, $db_group, $translations, $replace)
    {
        $dbTranslations = $this->translation->hydrateRaw(<<<SQL
SELECT * FROM ltm_translations WHERE locale = ? AND `group` = ?

SQL
            , [$locale, $db_group]);

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
                unset($dbTransMap[$key]);
            }
            else
            {
                $translation = new Translation(array(
                    'locale' => $locale,
                    'group' => $db_group,
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

            $translation->is_deleted = 0;
            $translation->saved_value = $value;

            $newStatus = ($translation->value === $translation->saved_value ? Translation::STATUS_SAVED : ($translation->status === Translation::STATUS_SAVED ? Translation::STATUS_SAVED_CACHED : Translation::STATUS_CHANGED));

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

        // now process all the new translations that are not in the files
        foreach ($dbTransMap as $translation)
        {
            // mark it as saved cached or changed
            if (((int)$translation->status) === Translation::STATUS_SAVED)
            {
                $translation->status = Translation::STATUS_SAVED_CACHED;
                $translation->save();
            }
        }
    }

    public
    function importTranslations($replace, $groups = null)
    {
        $this->imported = 0;
        $this->clearCache($groups);
        $this->clearUsageCache(false, $groups);

        // Laravel 5.1
        $pathTemplateResolver = new PathTemplateResolver($this->files, $this->app->basePath(), $this->config()['language_dirs'], '5');
        // Laravel 4.2
        //$pathTemplateResolver = new PathTemplateResolver($this->files, base_path(), $this->config()['language_dirs'], '4');
        $langFiles = $pathTemplateResolver->langFileList();

        if ($groups !== null)
        {
            // now we can filter to the list of given groups or
            $files = [];
            if (!is_array($groups)) $groups = array($groups);
            $groups = array_combine($groups, $groups);

            foreach ($langFiles as $file => $values)
            {
                if (array_key_exists($values['{db_group}'], $groups))
                {
                    $files[$file] = $values;
                }
            }

            $langFiles = $files;
        }

        foreach ($langFiles as $langFile => $vars)
        {
            $locale = $vars['{locale}'];
            $db_group = $vars['{db_group}'];

            if (in_array($db_group, $this->config()['exclude_groups'])) continue;
            $translations = array_dot(include($langFile));
            $this->importTranslationFile($locale, $db_group, $translations, $replace);
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

    public
    function makeDirPath($path)
    {
        $directories = explode("/", $path);
        array_shift($directories);

        $filename = array_pop($directories);
        $dirpath = "/";

        // Build path and create dirrectories if needed
        foreach ($directories as $directory)
        {
            $dirpath .= $directory . "/";
            if (!$this->files->exists($dirpath))
            {
                try
                {
                    $this->files->makeDirectory($dirpath);
                }
                catch (Exception $e)
                {
                    $this->errors[] = $e->getMessage() . " for $dirpath";
                }
            }
        }
    }

    public
    function clearErrors()
    {
        $this->errors = [];
    }

    public
    function errors()
    {
        return $this->errors;
    }

    public
    function exportTranslations($group, $recursing = 0)
    {
        // TODO: clean up this recursion crap
        if (!$recursing) $this->clearErrors();

        if ($group && $group !== '*')
        {
            $this->translation->getConnection()->affectingStatement("DELETE FROM ltm_translations WHERE is_deleted = 1");
        }
        elseif (!$recursing)
        {
            $this->translation->getConnection()->affectingStatement("DELETE FROM ltm_translations WHERE is_deleted = 1 AND `group` = ?", [$group]);
        }

        $inDatabasePublishing = $this->inDatabasePublishing();
        if ($inDatabasePublishing < 3 && $inDatabasePublishing && ($inDatabasePublishing < 2 || !$recursing))
        {
            if ($group && $group !== '*')
            {
                $this->translation->getConnection()->affectingStatement(<<<SQL
UPDATE ltm_translations SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?) AND `group` = ?
SQL
                    , [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED, $group]);

                $translations = $this->translation->query()->where('status', '<>', Translation::STATUS_SAVED)->where('group', '=', $group)->get([
                    'group',
                    'key',
                    'locale',
                    'saved_value'
                ]);
            }
            else
            {
                $this->translation->getConnection()->affectingStatement(<<<SQL
UPDATE ltm_translations SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?)
SQL
                    , [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED]);
                $translations = $this->translation->query()->where('status', '<>', Translation::STATUS_SAVED)->get([
                    'group',
                    'key',
                    'locale',
                    'saved_value'
                ]);
            }

            /* @var $translations Collection */
            $this->clearCache($group);
            $this->clearUsageCache(false, $group);
            $translations->each(function ($tr)
            {
                $this->cacheTranslation($tr->group . '.' . $tr->key, $tr->saved_value, $tr->locale);
            });
        }

        if (!$inDatabasePublishing || $inDatabasePublishing === 2 || $inDatabasePublishing === 3)
        {
            if (!in_array($group, $this->config()['exclude_groups']))
            {
                if ($group == '*') $this->exportAllTranslations(1);

                if ($inDatabasePublishing !== 3)
                {
                    $this->clearCache($group);
                    $this->clearUsageCache(false, $group);
                }

                $tree = $this->makeTree(Translation::where('group', $group)->whereNotNull('value')->orderby('key')->get());
                $configRewriter = new TranslationFileRewriter();
                $exportOptions = array_key_exists('export_format', $this->config()) ? TranslationFileRewriter::optionFlags($this->config()['export_format']) : null;

                // Laravel 5.1
                $base_path = $this->app->basePath();
                $pathTemplateResolver = new PathTemplateResolver($this->files, $base_path, $this->config()['language_dirs'], '5');
                $zipRoot = $base_path . $this->config('zip_root', mb_substr($this->app->langPath(), 0, -4));
                // Laravel 4.2
                //$base_path = base_path();
                //$pathTemplateResolver = new PathTemplateResolver($this->files, $base_path, $this->config()['language_dirs'], '4');
                //$zipRoot = $base_path . $this->config('zip_root', mb_substr($this->app->make('path').'/lang', 0, -4));

                if (mb_substr($zipRoot, -1) === '/') $zipRoot = substr($zipRoot, 0, -1);

                foreach ($tree as $locale => $groups)
                {
                    if (isset($groups[$group]))
                    {
                        $translations = $groups[$group];

                        // use the new path mapping
                        $computedPath = $base_path . $pathTemplateResolver->groupFilePath($group, $locale);
                        $path = $computedPath;

                        if ($path)
                        {
                            $configRewriter->parseSource($this->files->exists($path) ? $this->files->get($path) : '');
                            $output = $configRewriter->formatForExport($translations, $exportOptions);

                            if ($this->zipExporting)
                            {
                                $pathPrefix = mb_substr($path, 0, mb_strlen($zipRoot));
                                $filePathName = ($pathPrefix === $zipRoot) ? mb_substr($path, mb_strlen($zipRoot)) : $path;
                                //$this->makeDirPath($filePathName);
                                $this->zipExporting->addFromString($filePathName, $output);
                            }
                            else
                            {
                                try
                                {
                                    $this->makeDirPath($path);
                                    if (($result = $this->files->put($path, $output)) === false)
                                    {
                                        $this->errors[] = "Failed to write to $path";
                                    };
                                }
                                catch (Exception $e)
                                {
                                    $this->errors[] = $e->getMessage();
                                }
                            }
                        }
                    }
                }

                if (!$inDatabasePublishing)
                {
                    Translation::where('group', $group)->update(array(
                        'status' => Translation::STATUS_SAVED,
                        'saved_value' => (new Expression('value'))
                    ));
                }
            }
        }
    }

    public
    function exportAllTranslations($recursing = 0)
    {
        $groups = Translation::whereNotNull('value')->select(DB::raw('DISTINCT `group`'))->get('group');
        $this->clearCache();
        $this->clearUsageCache(false);

        foreach ($groups as $group)
        {
            $this->exportTranslations($group->group, $recursing);
        }
    }

    public
    function zipTranslations($groups)
    {
        $zip_name = tempnam("Translations_" . time(), "zip"); // Zip name
        $this->zipExporting = new ZipArchive();
        $this->zipExporting->open($zip_name, ZipArchive::OVERWRITE);

        if (!is_array($groups))
        {
            if ($groups === '*')
            {
                $groups = Translation::whereNotNull('value')->select(DB::raw('DISTINCT `group`'))->get('group');
                foreach ($groups as $group)
                {
                    // Stuff with content
                    $this->exportTranslations($group->group, 0);
                }
            }
            else
            {
                // Stuff with content
                $this->exportTranslations($groups, 0);
            }
        }
        else
        {
            foreach ($groups as $group)
            {
                // Stuff with content
                $this->exportTranslations($group, 0);
            }
        }

        $this->zipExporting->close();
        $this->zipExporting = null;

        return $zip_name;
    }

    public
    function cleanTranslations()
    {
        Translation::whereNull('value')->delete();
    }

    public
    function truncateTranslations($group = '*')
    {
        if ($group === '*')
        {
            Translation::truncate();
        }
        else
        {
            $this->translation->getConnection()->affectingStatement("DELETE FROM ltm_translations WHERE `group` = ?", [$group]);
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
    function getConfig($key = null, $default = null)
    {
        if ($key == null)
        {
            return $this->config();
        }
        else
        {
            return $this->config($key, $default);
        }
    }

    /**
     * @return bool
     */
    public
    function inDatabasePublishing()
    {
        return $this->zipExporting ? 3 : (array_key_exists('indatabase_publish', $this->config()) ? (int)$this->config['indatabase_publish'] : 0);
    }

}
