<?php

namespace Vsch\TranslationManager;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Finder\Finder;
use Vsch\TranslationManager\Classes\PathTemplateResolver;
use Vsch\TranslationManager\Classes\TranslationFileRewriter;
use Vsch\TranslationManager\Models\Translation;
use Vsch\TranslationManager\Models\UserLocales;
use Vsch\TranslationManager\Repositories\Interfaces\ITranslatorRepository;
use ZipArchive;

/**
 * Class Manager
 *
 * @package Vsch\TranslationManager
 */
class Manager
{
    const INDATABASE_PUBLISH_KEY = 'indatabase_publish';
    const DEFAULT_DB_CONNECTION_KEY = 'default_connection';
    const USER_LOCALES_ENABLED = 'user_locales_enabled';
    const PDO_FETCH_MODE_ENABLED = 'pdo_fetch_mode_enabled';
    const USER_LIST_CONNECTION_KEY = 'user_list_connection';
    const DB_CONNECTIONS_KEY = 'db_connections';
    const PERSISTENT_PREFIX_KEY = 'persistent_prefix';
    const EXCLUDE_PAGE_EDIT_GROUPS_KEY = 'exclude_page_edit_groups';
    const LOG_MISSING_KEYS_KEY = 'log_missing_keys';
    const EXCLUDE_GROUPS_KEY = 'exclude_groups';
    const MISSING_KEYS_LOTTERY_KEY = 'missing_keys_lottery';
    const LOTTERY_PERSISTENT_SUFFIX = 'lottery';
    const LOG_KEY_USAGE_INFO_KEY = 'log_key_usage_info';
    const ADDITIONAL_LOCALES_KEY = 'locales';
    const SHOW_LOCALES_KEY = 'show_locales';
    const MARKDOWN_KEY_SUFFIX = 'markdown_key_suffix';

    const ABILITY_ADMIN_TRANSLATIONS = 'ltm-admin-translations';
    const ABILITY_BYPASS_LOTTERY = 'ltm-bypass-lottery';
    const ABILITY_LIST_EDITORS = 'ltm-list-editors';

    /** @var \Illuminate\Foundation\Application */
    protected $app;
    /** @var \Illuminate\Filesystem\Filesystem */
    protected $files;
    /** @var \Illuminate\Events\Dispatcher */
    protected $dispathesEvents;

    protected $config;
    protected $imported;

    /* @var $translation Translation */
    protected $translation;

    protected $persistentPrefix;
    protected $cache;
    protected $cacheIsDirty;
    protected $cacheTransKey;
    protected $usageCache;
    protected $usageCacheIsDirty;
    protected $usageCacheTransKey;
    protected $errors;
    protected $indatabase_publish;
    protected $default_connection;
    protected $default_translation_connection;
    protected $default_indatabase_publish;
    protected $groupList;
    protected $augmentedGroupList;
    protected $preloadedGroupKeys;
    protected $preloadedGroup;
    protected $preloadedGroupLocales;
    protected $ltmJsonKeys;
    protected $jsonLtmKeys;

    /**
     * @var   \ZipArchive
     */
    protected $zipExporting;

    private $package;
    private $translatorRepository;

    public function setConnectionName($connection = null)
    {
        if ($connection === null || $connection === '') {
            // resetting to default
            $connection = $this->default_translation_connection;
        }

        $this->translation->setConnection($connection);
        $this->indatabase_publish = $this->getConnectionInDatabasePublish($connection);

        $this->clearCache();
    }

    public function getConnectionName()
    {
        $connectionName = $this->translation->getConnectionName();
        return $connectionName;
    }

    /**
     * @param $connection
     *
     * @return bool
     */
    public function isDefaultTranslationConnection($connection)
    {
        return $connection == null || $connection == $this->default_translation_connection;
    }

    /**
     * @return \Vsch\TranslationManager\Models\Translation
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    public function getConnectionInDatabasePublish($connectionName)
    {
        if ($connectionName === null || $connectionName === '' || $this->isDefaultTranslationConnection($connectionName)) {
            return $this->config(self::INDATABASE_PUBLISH_KEY, 0);
        }
        return $this->getConnectionInfo($connectionName, self::INDATABASE_PUBLISH_KEY, $this->config(self::INDATABASE_PUBLISH_KEY, 0));
    }

    public function getUserListConnection($connectionName)
    {
        if ($connectionName === null || $connectionName === '' || $this->isDefaultTranslationConnection($connectionName)) {
            // use the default connection for the user list
            return '';
        }

        $userListConnection = $this->getConnectionInfo($connectionName, self::USER_LIST_CONNECTION_KEY, $connectionName);
        if (!$userListConnection) $userListConnection = $connectionName;
        return $userListConnection;
    }

    public function getUserListProvider($connection)
    {
        return function ($user, $connection_name, &$user_list) {
            return Gate::forUser($user)->allows(self::ABILITY_LIST_EDITORS, [$connection_name, &$user_list]);
        };
    }

    public function getUserLocalesTableName()
    {
        $userLocales = new UserLocales();
        $prefix = $this->translatorRepository->getConnection()->getTablePrefix();
        return $prefix . $userLocales->getTable();
    }

    public function getConnectionInfo($connectionName, $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config(self::DB_CONNECTIONS_KEY);
        }

        $db_connections = $this->config(self::DB_CONNECTIONS_KEY);
        $environment = App::environment();

        $db_connection = $connectionName !== null && array_key_exists($environment, $db_connections) && array_key_exists($connectionName, $db_connections[$environment]) ? $db_connections[$environment][$connectionName] : null;

        $value = $db_connection && array_key_exists($key, $db_connection)
            ? $db_connection[$key]
            : $default;

        return $value;
    }

    /**
     * @return bool
     */
    public function inDatabasePublishing()
    {
        return $this->zipExporting ? 3 : $this->indatabase_publish;
    }

    /**
     * @return bool
     */
    public function areUserLocalesEnabled()
    {
        return $this->config(self::USER_LOCALES_ENABLED, false);
    }

    function firstOrNewTranslation($attributes = null)
    {
        $checkDB = true;

        /* @var $query Builder */

        $translation = null;

        if ($this->preloadedGroupKeys && array_key_exists('group', $attributes) && $this->preloadedGroup === $attributes['group']
            && array_key_exists('locale', $attributes) && array_key_exists('key', $attributes)
            && array_key_exists($attributes['locale'], $this->preloadedGroupLocales)
        ) {
            $checkDB = false;

            if (array_key_exists($attributes['key'], $this->preloadedGroupKeys)
                && array_key_exists($attributes['locale'], $this->preloadedGroupKeys[$attributes['key']])
            ) {
                $translation = $this->preloadedGroupKeys[$attributes['key']][$attributes['locale']];
            }
        }

        if ($checkDB) {
            $query = $this->translation->on($this->getConnectionName());

            foreach ($attributes as $attribute => $value) {
                $query = $query->where($attribute, $value);
            }

            $translation = $query->first();
        }

        if (!$translation) {
            $translation = new Translation();
            $translation->fill($attributes);
            $translation->setConnection($this->getConnectionName());
        }

        return $translation;
    }

    function firstOrCreateTranslation($attributes = null)
    {
        $translation = $this->firstOrNewTranslation($attributes);
        if (!$translation->exists) {
            $translation->save();
        }

        return $translation;
    }

    public function cacheGroupTranslations($group, $locales, $translations)
    {
        $this->preloadedGroupKeys = $translations;
        $this->preloadedGroup = $group;
        $this->preloadedGroupLocales = array_combine($locales, $locales);
    }

    public function __construct(Application $app, Filesystem $files, Dispatcher $dispatchesEvents, Translation $translation, ITranslatorRepository $translatorRepository)
    {
        $this->app = $app;
        $this->translatorRepository = $translatorRepository;

        $this->package = ManagerServiceProvider::PACKAGE;
        $this->config = $this->app['config'][$this->package];

        $this->files = $files;
        $this->dispathesEvents = $dispatchesEvents;
        $this->translation = $translation;
        $this->default_connection = $translation->getConnectionName();
        $this->default_translation_connection = $this->config(self::DEFAULT_DB_CONNECTION_KEY, null);
        if (!$this->default_translation_connection) {
            $this->default_translation_connection = $this->default_connection;
        } else {
            $this->setConnectionName($this->default_translation_connection);
        }

        $this->preloadedGroupKeys = null;
        $this->preloadedGroup = null;

        $this->persistentPrefix = null;
        $this->cache = null;
        $this->usageCache = null;

        $this->indatabase_publish = $this->getConnectionInDatabasePublish($this->default_translation_connection);

        $this->groupList = null;
        $this->augmentedGroupList = null;
    }

    public function getPackagePublicPath()
    {
        return $this->app->publicPath() . $this->getPackagePublicSuffix();
    }

    public function getPackagePublicSuffix()
    {
        return '/vendor/' . $this->package;
    }

    public function afterRoute($request, $response)
    {
        $this->saveCache();
        $this->saveUsageCache();
    }

    public function getGroupList()
    {
        if ($this->groupList === null) {
            // read it in
            /* @var Builder $groups */
            $groups = $this->getTranslation()->groupBy('group');
            $excludedGroups = $this->config(Manager::EXCLUDE_GROUPS_KEY);
            if ($excludedGroups) {
                $groups = $groups->whereNotIn('group', $excludedGroups);
            }

            $this->groupList = ManagerServiceProvider::getLists($groups->pluck('group', 'group'));
        }
        return $this->groupList;
    }

    public function getGroupAugmentedList()
    {
        if ($this->augmentedGroupList === null) {
            // compute augmented list from vnd:{vendor}.{package}::group.key
            // remove the vnd:{vendor}. and add to augmented, map it to original name
            $groupList = $this->getGroupList();
            $this->augmentedGroupList = [];

            foreach ($groupList as $group) {
                if (starts_with($group, ["vnd:", "wbn:"])) {
                    // we need this one
                    $parts = explode('.', $group, 2);
                    if (count($parts) === 2) {
                        // if it is not included in the vendor resources
                        if (!array_key_exists($parts[1], $groupList)) {
                            $this->augmentedGroupList[$parts[1]] = $group;
                        }
                    }
                }
            }
        }
        return $this->augmentedGroupList;
    }

    public function config($key = null, $default = null)
    {
        // Version 5.1
        if (!$this->config) $this->config = $this->app['config'][$this->package];
        // Version 4.2
        //if (!$this->config) $this->config = $this->app['config'][$this->package.'::config'];

        if ($key === null) {
            return $this->config;
        }
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        return $default;
    }

    public function cacheEnabled()
    {
        return $this->cachePrefix() !== '';
    }

    public function cachePrefix()
    {
        if ($this->persistentPrefix === null) {
            if (array_key_exists(self::PERSISTENT_PREFIX_KEY, $this->config())) {
                $this->persistentPrefix = $this->config(self::PERSISTENT_PREFIX_KEY);
            } else {
                $this->persistentPrefix = '';
            }
        } elseif ($this->cacheTransKey == null) {
            $this->cacheTransKey = $this->persistentPrefix ? $this->persistentPrefix . 'translations' : '';
        }
        return $this->persistentPrefix;
    }

    public function usageCachePrefix()
    {
        if ($this->usageCacheTransKey == null) {
            $this->cachePrefix();
            $this->usageCacheTransKey = $this->persistentPrefix ? $this->persistentPrefix . 'usage_info' : '';
        }
        return $this->persistentPrefix;
    }

    public function cache()
    {
        if ($this->cache === null) {
            $this->cache = $this->cachePrefix() !== '' && $this->indatabase_publish != 0 && Cache::has($this->cacheTransKey) ? Cache::get($this->cacheTransKey) : [];
            $this->cacheIsDirty = $this->persistentPrefix !== '' && !Cache::has($this->cacheTransKey);
        }
        return $this->cache;
    }

    public function usageCache()
    {
        if ($this->usageCache === null) {
            $this->usageCache = $this->usageCachePrefix() !== '' && Cache::has($this->usageCacheTransKey) ? Cache::get($this->usageCacheTransKey) : [];
            $this->usageCacheIsDirty = $this->persistentPrefix !== '' && !Cache::has($this->usageCacheTransKey);
        }
        return $this->usageCache;
    }

    public function saveCache()
    {
        if ($this->persistentPrefix && $this->cacheIsDirty) {
            Cache::put($this->cacheTransKey, $this->cache, 60 * 24 * 365);
            $this->cacheIsDirty = false;
        }
    }

    public function saveUsageCache()
    {
        if ($this->persistentPrefix && $this->usageCacheIsDirty) {
            // we never save it in the cache, it is only in database use, otherwise every page it will save the full cache to the database
            // instead of only the accessed keys
            //Cache::put($this->usageCacheTransKey, $this->usageCache, 60 * 24 * 365);
            Cache::put($this->usageCacheTransKey, [], 60 * 24 * 365);
            $this->usageCacheIsDirty = false;

            // now update the keys in the database
            foreach ($this->usageCache as $group => $keys) {
                $this->translatorRepository->updateUsedTranslationsForGroup($keys, $group);
            }
        }
    }

    public function clearCache($groups = null)
    {
        if (!$groups || $groups === '*') {
            $this->cache = [];
            $this->cacheIsDirty = !!$this->persistentPrefix;
        } elseif ($this->cache()) {
            if (!is_array($groups)) $groups = [$groups];

            foreach ($groups as $group) {
                if (array_key_exists($group, $this->cache)) {
                    unset($this->cache[$group]);
                    $this->cacheIsDirty = !!$this->persistentPrefix;
                }
            }
        }
    }

    public function clearUsageCache($clearDatabase, $groups = null)
    {
        if (!$groups || $groups === '*') {
            $this->usageCache();
            $this->usageCache = [];
            $this->usageCacheIsDirty = true;
            $this->saveUsageCache();

            if ($clearDatabase) {
                $this->translatorRepository->setNotUsedForAllTranslations();
            }
        } elseif ($this->usageCache()) {
            $this->usageCache();
            if (!is_array($groups)) {
                $groups = [$groups];
            }

            foreach ($groups as $group) {
                if (array_key_exists($group, $this->usageCache)) {
                    unset($this->usageCache[$group]);
                    $this->usageCacheIsDirty = !!$this->persistentPrefix;
                }

                if ($clearDatabase) {
                    $this->translatorRepository->updateUsedTranslationsForGroup(null, $group, 0);
                }
            }

            $this->saveUsageCache();
        }
    }

    public function cacheKey($key, $locale)
    {
        return $locale . ':' . $key;
    }

    public function usageCacheKey($key, $locale)
    {
        return $key;
    }

    protected static function groupKeyList($key)
    {
        $key = explode('.', $key, 2);
        if (count($key) > 1) {
            $group = self::fixGroup($key[0]);
            $key = $key[1];
        } else {
            $group = '';
            $key = $key[0];
        }
        return [$group, $key];
    }

    public function cacheTranslation($namespace, $group, $transKey, $value, $locale)
    {
        $group = self::fixGroup($group);
        $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

        if ($group) {
            if (!array_key_exists($group, $this->cache())) {
                $this->cache[$group] = [];
            }
            $this->cache[$group][$this->cacheKey($transKey, $locale)] = $value;
            $this->cacheIsDirty = $this->persistentPrefix !== '';
        }
    }
    
    /**
     * @param $namespace
     * @param $group
     * @param $transKey
     * @param $locale
     *
     * @return string|null translation value
     */
    public function cachedTranslation($namespace, $group, $transKey, $locale)
    {
        $group = self::fixGroup($group);
        $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

        $cacheKey = $this->cacheKey($transKey, $locale);
        $value = $group && array_key_exists($group, $this->cache()) && array_key_exists($cacheKey, $this->cache[$group]) ? $this->cache[$group][$cacheKey] : null;
        return $value;
    }

    public function cacheUsageInfo($namespace, $group, $transKey, $value, $locale)
    {
        $group = self::fixGroup($group);
        $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

        if ($group) {
            if (!array_key_exists($group, $this->usageCache())) {
                $this->usageCache[$group] = [];
            }
            $this->usageCache[$group][$this->usageCacheKey($transKey, $locale)] = $value;
            $this->usageCacheIsDirty = $this->persistentPrefix !== '';
        }
    }

    public function cachedUsageInfo($namespace, $group, $transKey, $locale)
    {
        $group = self::fixGroup($group);
        $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

        $cacheKey = $this->usageCacheKey($transKey, $locale);
        $value = $group && array_key_exists($group, $this->usageCache()) && array_key_exists($cacheKey, $this->usageCache[$group]) ? $this->usageCache[$group][$cacheKey] : null;
        return $value;
    }

    public function excludedPageEditGroup($group)
    {
        return
            in_array($group, $this->config(self::EXCLUDE_PAGE_EDIT_GROUPS_KEY, []))
            || in_array($group, $this->config(self::EXCLUDE_GROUPS_KEY, []));
    }

    public static function fixGroup($group)
    {
        if ($group !== null) {
            $group = str_replace('/', '.', $group);
        }
        return $group;
    }

    /**
     * @param             $namespace string
     * @param             $group     string
     * @param             $key       string
     * @param null|string $locale
     * @param bool        $useLottery
     * @param bool        $findOrNew
     *
     * @return \Vsch\TranslationManager\Models\Translation|null
     */
    public function missingKey($namespace, $group, $key, $locale = null, $useLottery = true, $findOrNew = false)
    {
        if (!$useLottery || $this->config(self::LOG_MISSING_KEYS_KEY)) {
            // L5 changes
            $group = self::fixGroup($group);
            $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;
            if (!in_array($group, $this->config(self::EXCLUDE_GROUPS_KEY))) {
                $lottery = 1;
                if ($useLottery && $this->config(self::MISSING_KEYS_LOTTERY_KEY) !== 1) {
                    $lottery = \Session::get($this->config(self::PERSISTENT_PREFIX_KEY) . self::LOTTERY_PERSISTENT_SUFFIX, '');
                    if ($lottery === '') {
                        $lottery = rand(1, $this->config(self::MISSING_KEYS_LOTTERY_KEY));
                        \Session::put($this->config(self::PERSISTENT_PREFIX_KEY) . self::LOTTERY_PERSISTENT_SUFFIX, $lottery);
                    }
                }

                if ($lottery == 1) {
                    // here need to map a local group to wbn: or vnd: package if the local file does not already exist so that
                    // new keys will be added to the appropriate package
                    $augmentedGroupList = $this->getGroupAugmentedList();
                    if (array_key_exists($group, $augmentedGroupList)) {
                        $group = $augmentedGroupList[$group];
                    }

                    $locale = $locale ?: $this->app['config']['app.locale'];

                    if ($findOrNew) {
                        $translation = $this->firstOrNewTranslation(array(
                            'locale' => $locale,
                            'group'  => $group,
                            'key'    => $key,
                        ));
                    } else {
                        $translation = $this->firstOrCreateTranslation(array(
                            'locale' => $locale,
                            'group'  => $group,
                            'key'    => $key,
                        ));
                    }

                    /* @var $translation \Vsch\TranslationManager\Models\Translation */
                    return $translation;
                }
            }
        }
        return null;
    }

    /**
     * @param             $namespace string
     * @param             $group     string
     * @param             $key       string
     * @param null|string $locale
     * @param bool        $useLottery
     */
    public function usingKey($namespace, $group, $key, $locale, $useLottery)
    {
        if ($this->config(self::LOG_KEY_USAGE_INFO_KEY)) {
            $group = self::fixGroup($group);
            $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

            if (!in_array($group, $this->config(self::EXCLUDE_GROUPS_KEY))) {
                $lottery = 1;
                if ($useLottery && $this->config(self::MISSING_KEYS_LOTTERY_KEY) !== 1) {
                    $lottery = \Session::get($this->config(self::PERSISTENT_PREFIX_KEY) . self::LOTTERY_PERSISTENT_SUFFIX, '');
                    if ($lottery === '') {
                        $lottery = rand(1, $this->config(self::MISSING_KEYS_LOTTERY_KEY));
                        \Session::put($this->config(self::PERSISTENT_PREFIX_KEY) . self::LOTTERY_PERSISTENT_SUFFIX, $lottery);
                    }
                }

                if ($lottery == 1) {
                    $locale = $locale ?: $this->app['config']['app.locale'];
                    $this->cacheUsageInfo('', $group, $key, 1, $locale);
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
    protected function importTranslationFile($locale, $db_group, $translations, $replace)
    {
        $connectionName = $this->getConnectionName();
        $dbTranslations = $this->translatorRepository->selectTranslationsByLocaleAndGroup($locale, $db_group);

        $timeStamp = 'now()';
        $dbTransMap = [];
        $dbTranslations->each(function ($trans) use (&$dbTransMap, $connectionName) {
            $dbTransMap[$trans->key] = $trans;
        });

        $values = [];
        $statusChangeOnly = [];
        foreach ($translations as $key => $value) {
            if (is_array($value)) {
                if ($value) $this->errors[] = "translation value is an array: $db_group.$key locale $locale";
                continue;
            }
            $value = (string)$value;

            if (array_key_exists($key, $dbTransMap)) {
                $translation = $dbTransMap[$key];
                unset($dbTransMap[$key]);
            } else {
                $translation = new Translation(array(
                    'locale' => $locale,
                    'group'  => $db_group,
                    'key'    => $key,
                ));

                $translation->setConnection($connectionName);
                $tmp = 0;
            }

            // Importing from the source, status is always saved. When it is changed by the user, then it is changed.
            //$newStatus = ($translation->value === $value || !$translation->exists) ? Translation::STATUS_SAVED : Translation::STATUS_CHANGED;
            // Only replace when empty, or explicitly told so
            if ($replace || !$translation->value) {
                $translation->value = $value;
            }

            $translation->is_deleted = 0;
            $translation->is_auto_added = 0;
            $translation->saved_value = $value;

            $newStatus = ($translation->value === $translation->saved_value ? Translation::STATUS_SAVED : ($translation->status === Translation::STATUS_SAVED ? Translation::STATUS_SAVED_CACHED : Translation::STATUS_CHANGED));

            if ($newStatus !== (int)$translation->status) {
                $translation->status = $newStatus;
            }

            if (!$translation->exists) {
                $values[] = $this->translatorRepository->getInsertTranslationsElement($translation, $timeStamp);
            } elseif ($translation->isDirty()) {
                if ($translation->isDirty(['value', 'source', 'saved_value', 'was_used',])) {
                    $translation->save();
                } else {
                    if (!array_key_exists($translation->status, $statusChangeOnly)) {
                        $statusChangeOnly[$translation->status] = $translation->id;
                    } else {
                        $statusChangeOnly[$translation->status] .= ',' . $translation->id;
                    }
                }
            }

            $this->imported++;
        }

        // now batch update those with status changes only
        $updated_at = new Carbon();
        foreach ($statusChangeOnly as $status => $translationIds) {
            $this->translatorRepository->updateStatusForTranslations($status, $updated_at, $translationIds);
        }

        // now process all the new translations that were not in the files
        if ($replace == 2) {
            // we delete all translations that were not in the files
            if ($dbTransMap) {
                $translationIds = '';
                foreach ($dbTransMap as $translation) {
                    $translationIds .= ',' . $translation->id;
                }

                $translationIds = trim_prefix($translationIds, ',');
                $this->translatorRepository->deleteTranslationsForIds($translationIds);
            }
        } else {
            // update their status
            foreach ($dbTransMap as $translation) {
                // mark it as saved cached or changed
                if (((int)$translation->status) === Translation::STATUS_SAVED) {
                    $translation->status = Translation::STATUS_SAVED_CACHED;
                    $translation->save();
                }
            }
        }

        if ($values) {
            try {
                $this->translatorRepository->insertTranslations($values);
            } catch (\Exception $e) {
                $tmp = 0;
            }
            //$this->getConnection()->unprepared('UNLOCK TABLES');
        }
    }

    private static function generateLtmKey($jsonKeys, $jsonKey, $maxJsonKey)
    {
        // replace all symbols and spaces with _, remove duplicate _ and use that if it does not already exist
        // if it exists we append _# until we get a unique key
        $ltmKey = mb_strtolower($jsonKey);
        $iMax = strlen($ltmKey);
        $newKey = "";
        $hadUnderscore = null;
        for ($i = 0; $i < $iMax; $i++) {
            $c = mb_substr($ltmKey, $i, 1);
            if (preg_match("/[\\p{L}0-9]/", $c)) {
                if ($hadUnderscore) $newKey .= "_";
                $newKey .= $c;
                $hadUnderscore = false;
            } elseif (!$hadUnderscore) {
                $hadUnderscore = true;
            }
            if (strlen($newKey) >= $maxJsonKey) {
                break;
            }
        }
        // either messes up unicode if using preg_replace, or does not recognize char class if using mb_ereg_replace
        //$ltmKey = mb_eregi_replace("/[^\\p{L}0-9]/", "_", $ltmKey);
        //$ltmKey = preg_replace("/_+/", "_", $ltmKey);
        //$ltmKey = substr($ltmKey, 0, 120);
        $ltmKey = $newKey;
        $suffix = "";
        $count = 0;
        while (array_key_exists($ltmKey . $suffix, $jsonKeys)) {
            $suffix = "_" . ++$count;
        }

        return $ltmKey . $suffix;
    }

    private function loadLtmJsonKeys()
    {
        if (!isset($this->ltmJsonKeys)) {
            // may need to create new jsonKeys from default locale values for any that are missing from json locale of JSON group
            $jsonTranslations = $this->translation->query()->where('group', '=', 'JSON')->where('locale', '=', 'json')->get([
                'key',
                'value',
            ]);

            $this->ltmJsonKeys = [];
            $this->jsonLtmKeys = [];
            $jsonTranslations->each(function ($tr) {
                $this->ltmJsonKeys[$tr->key] = $tr->value;
                $this->jsonLtmKeys[$tr->value] = $tr->key;
            });
        }
    }

    public function getTranslations($namespace, $group, $locale)
    {
        $group = self::fixGroup($group);
        $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;
        
        // may need to create new jsonKeys from default locale values for any that are missing from json locale of JSON group
        $jsonTranslations = $this->translation->query()->where('group', '=', $group)->where('locale', '=', $locale)->get([
            'key',
            'value',
        ]);

        $translations = [];
        $jsonTranslations->each(function ($tr) use ($namespace, $group, $locale, &$translations) {
            $translation = $this->cachedTranslation($namespace, $group, $tr->key, $locale);
            if ($translation) {
                $translations[$tr->key] = $translation;
            } else {
                $translations[$tr->key] = $tr->value;
            }
        });
        
        return $translations; 
    }

    public function importTranslations($replace, $groups = null)
    {
        // this can come from the command line
        if (is_array($groups)) {
            foreach ($groups as &$group) {
                $group = self::fixGroup($group);
            }
        } else {
            $groups = self::fixGroup($groups);
        }

        // if we don't track usage information and replace == 2 we can truncate the translations for the groups or group
        if (!$this->config(self::LOG_KEY_USAGE_INFO_KEY) && $replace == 2) {
            $this->truncateTranslations($groups);
        }

        $this->imported = 0;
        $this->clearCache($groups);
        $this->clearUsageCache(false, $groups);

        $pathTemplateResolver = new PathTemplateResolver($this->files, $this->app->basePath(), $this->config('language_dirs'), '5');

        $langFiles = $pathTemplateResolver->langFileList();

        // generate JSON to LTM keys here and put them in the JSON group as json locale
        $jsonFiles = [];
        $maxJsonKey = $this->config('json_dbkey_length', 32);
        if ($maxJsonKey > 120) $maxJsonKey = 120;

        $this->loadLtmJsonKeys();

        foreach ($langFiles as $langFile => $vars) {
            $locale = $vars['{locale}'];
            $db_group = $vars['{db_group}'];
            if (in_array($db_group, $this->config(self::EXCLUDE_GROUPS_KEY))) continue;
            if ($db_group === 'JSON') {
                $json = file_get_contents($langFile);
                if ($locale !== 'json') {
                    $jsonFiles[$langFile] = json_decode($json, true);
                }
            }
        }

        $jsonTranslations = []; // locale => [ ltmKey => jsonTranslation ]
        $ltmJsonKeys = [];
        if ($jsonFiles) {
            foreach ($jsonFiles as $langFile => $json) {
                // create unique keys based on json keys
                $vars = $langFiles[$langFile];
                $locale = $vars['{locale}'];
                $translations = [];
                foreach ($json as $jsonKey => $translation) {
                    if (!array_key_exists($jsonKey, $this->jsonLtmKeys)) {
                        $ltmKey = $this->generateLtmKey($this->ltmJsonKeys, $jsonKey, $maxJsonKey);
                        $this->jsonLtmKeys[$jsonKey] = $ltmKey;
                        $this->ltmJsonKeys[$ltmKey] = $jsonKey;
                        $ltmJsonKeys[$ltmKey] = $jsonKey;
                    } else {
                        $ltmKey = $this->jsonLtmKeys[$jsonKey];
                        $ltmJsonKeys[$ltmKey] = $jsonKey;
                    }

                    // now store the translation for the locale with the ltmKey
                    $translations[$ltmKey] = $translation;
                }

                // save it, if we need it
                $jsonTranslations[$locale] = $translations;
            }
        }

        // need to update database JSON group json locale with key map of ltmKey to jsonKey for exporting, but
        // only those that were imported so as not to overwrite the not yet published keys
        $this->importTranslationFile('json', 'JSON', $ltmJsonKeys, true);

        if ($groups !== null) {
            // now we can filter to the list of given groups or
            $files = [];
            if (!is_array($groups)) $groups = array($groups);
            $groups = array_combine($groups, $groups);

            foreach ($langFiles as $file => $values) {
                if (array_key_exists($values['{db_group}'], $groups)) {
                    $files[$file] = $values;
                }
            }

            $langFiles = $files;
        }

        foreach ($langFiles as $langFile => $vars) {
            $locale = $vars['{locale}'];
            $db_group = $vars['{db_group}'];
            if ($locale == 'json') continue; // don't import keys. Use Database ones
            if (in_array($db_group, $this->config(self::EXCLUDE_GROUPS_KEY))) continue;
            if ($db_group === 'JSON') {
                // just update the locale with translations, keys are already LTM keys here
                $translations = $jsonTranslations[$locale];
            } else {
                $translations = array_dot(include($langFile));
            }
            $this->importTranslationFile($locale, $db_group, $translations, $replace);
        }

        return $this->imported;
    }

    public function findTranslations($path = null)
    {
        $functions = array(
            'trans',
            'trans_choice',
            'noEditTrans',
            'ifEditTrans',
            'Lang::get',
            'Lang::choice',
            'Lang::trans',
            'Lang::transChoice',
            '@lang',
            '@choice',
            '__',
        );
        $pattern =                                  // See http://regexr.com/392hu
            "(" . implode('|', $functions) . ")" .  // Must start with one of the functions
            "\\(" .                                 // Match opening parentheses
            "(['\"])" .                             // Match " or '
            "(" .                                   // Start a new group to match:
            "[a-zA-Z0-9_-]+" .                  // Must start with group
            "([.][^\1)]+)+" .                   // Be followed by one or more items/keys
            ")" .                                   // Close group
            "['\"]" .                               // Closing quote
            "[\\),]";                               // Close parentheses or new parameter

        // Find all PHP + Twig files in the app folder, except for storage
        $paths = $path ? [$path] : array_merge([$this->app->basePath() . '/app'], $this->app['config']['view']['paths']);
        $keys = array();
        foreach ($paths as $path) {
            $finder = new Finder();
            $finder->in($path)->name('*.php')->name('*.twig')->files();

            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            foreach ($finder as $file) {
                // Search the current file for the pattern
                $fileContents = $file->getContents();
                if (preg_match_all("/$pattern/siU", $fileContents, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE)) {
                    // Get all matches
                    $fileLines = null;
                    foreach ($matches[3] as $index => $key) {
                        $quote = $matches[2][$index][0];
                        $keyValue = $key[0];
                        if ($quote == '\'' && !str_contains($keyValue, ["\"", "'", "->",]) ||
                            $quote == '"' && !str_contains($keyValue, ["$", "\"", "'", "->",])
                        ) {
                            if ($fileLines == null) {
                                $fileLines = self::computeFileLines($fileContents);
                            }
                            $keys[$keyValue][$file->getPath() . '/' . $file->getFilename()][] = self::offsetLine($fileLines, $key[1]);
                        }
                    }
                }
            }
        }

        // Add the translations to the database, if not existing.
        $count = 0;
        foreach ($keys as $key => $filePathsAndLocation) {
            // Split the group and item
            list($group, $item) = explode('.', $key, 2);

            $translation = $this->missingKey('', $group, $item, null, false, true);

            // create references
            $paths = '';
            foreach ($filePathsAndLocation as $filePath => $locations) {
                $paths .= $filePath . ':' . implode(',', $locations) . "\n";
            }

            if (!$translation->exists) {
                // this one is new
                $translation->is_auto_added = true;
                $count++;
            }

            $translation->source = $paths;
            $translation->save();
        }

        // Return the number of found translations
        return $count;
    }

    public static function offsetLine($lines, $offset)
    {
        $iMax = count($lines);
        for ($i = 0; $i < $iMax; $i++) {
            if ($lines[$i] > $offset) {
                return $i + 1;
            }
        }

        return $iMax;
    }

    public static function computeFileLines($fileContents)
    {
        $lines = [];
        if (preg_match_all("/(\\n)/siU", $fileContents, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $index => $key) {
                $lines[] = $key[1];
            }
        }
        return $lines;
    }

    public function makeDirPath($path)
    {
        $directories = explode("/", $path);
        array_shift($directories);

        $filename = array_pop($directories);
        $dirpath = "/";

        $full = "/" . implode('/', $directories);
        // find the first existing folder backwards
        for ($i = count($directories); $i > 0; $i--) {
            if ($this->files->exists($full)) {
                break;
            }
            $lastpart = substr(strrchr($full, '/'), 1);
            $full = substr($full, 0, -strlen($lastpart) - 1);
        }

        // Build path and create directories if needed
        for ($k = 0; $k < count($directories); $k++) {
            $dirpath .= $directories[$k] . "/";
            if ($k < $i) continue;
            if (!$this->files->exists($dirpath)) {
                try {
                    $this->files->makeDirectory($dirpath);
                } catch (Exception $e) {
                    $this->errors[] = $e->getMessage() . " for $dirpath";
                }
            }
        }
    }

    public function clearErrors()
    {
        $this->errors = [];
    }

    public function errors()
    {
        return $this->errors;
    }

    public function exportTranslations($group, $recursing = 0)
    {
        // TODO: clean up this recursion crap
        // this can come from the command line
        if (!$recursing) {
            $this->clearErrors();
            $group = self::fixGroup($group);
        }

        if ($group && $group !== '*') {
            $this->translatorRepository->deleteTranslationWhereIsDeleted();
        } elseif (!$recursing) {
            $this->translatorRepository->deleteTranslationWhereIsDeleted($group);
        }

        $primaryLocale = $this->config("primary_locale", 'en');

        if (($group === 'JSON' || !$group || $group == '*') && !isset($this->ltmJsonKeys)) {
            // may need to create new jsonKeys from default locale values for any that are missing from json locale of JSON group
            $this->loadLtmJsonKeys();

            // need to map all keys to primary locale translation
            $rawTranslations = $this->translation->where('group', 'JSON')->whereNotNull('value')->orderby('key')->get();

            $newKeys = [];
            $fallBackTranslations = [];
            foreach ($rawTranslations as $translation) {
                $locale = $translation->locale;
                if ($locale !== 'json') {
                    $key = $translation->key;
                    $value = $translation->saved_value;
                    if (!array_key_exists($key, $this->ltmJsonKeys)) {
                        // new key create an ltm key for it, and update database after all were exported 
                        if (!array_key_exists($key, $newKeys)) {
                            $newKeys[] = $key;
                            if ($locale === $primaryLocale || !array_key_exists($key, $fallBackTranslations)) {
                                $fallBackTranslations[$key] = mb_strtolower($value);
                            }
                        }
                    }
                }
            }

            if ($newKeys) {
                // set all primary locale keys that are missing to the key itself
                // assign the json key based on fallback translation value 
                foreach ($newKeys as $key) {
                    if (!array_key_exists($key, $fallBackTranslations)) {
                        $fallBackTranslations[$key] = $key;
                    }
                    $value = $fallBackTranslations[$key];
                    $this->ltmJsonKeys[$key] = $value;
                    $this->jsonLtmKeys[$value] = $key;
                }

                // save these mappings to the database as new so that they will be cached if we are not saving to a file
                $this->importTranslationFile('json', 'JSON', $this->ltmJsonKeys, false); // these will be marked as new
            }
        }

        $inDatabasePublishing = $this->inDatabasePublishing();
        if ($inDatabasePublishing < 3 && $inDatabasePublishing && ($inDatabasePublishing < 2 || !$recursing)) {
            if ($group && $group !== '*') {
                $this->translatorRepository->updateValueInGroup($group);

                $translations = $this->translation->query()->where('status', '<>', Translation::STATUS_SAVED)->where('group', '=', $group)->get([
                    'group',
                    'key',
                    'locale',
                    'saved_value',
                ]);
            } else {
                $this->translatorRepository->updateValuesByStatus();

                $translations = $this->translation->query()->where('status', '<>', Translation::STATUS_SAVED)->get([
                    'group',
                    'key',
                    'locale',
                    'saved_value',
                ]);
            }

            /* @var $translations Collection */
            $this->clearCache($group);
            $this->clearUsageCache(false, $group);
            $translations->each(function ($tr) {
                if ($tr->group === 'JSON' && $tr->locale === 'json') {
                    // we have to cache the reverse, we need jsonKey to ltmKey for this one
                    $this->cacheTranslation('', $tr->group, $tr->saved_value, $tr->key, $tr->locale);
                } else {
                    $this->cacheTranslation('', $tr->group, $tr->key, $tr->saved_value, $tr->locale);
                }
            });
        }

        if (!$inDatabasePublishing || $inDatabasePublishing == 2 || $inDatabasePublishing == 3) {
            if (!in_array($group, $this->config(self::EXCLUDE_GROUPS_KEY))) {
                if ($group == '*') {
                    $this->exportAllTranslations(1);
                    return;
                }

                if ($inDatabasePublishing != 3) {
                    $this->clearCache($group);
                    $this->clearUsageCache(false, $group);
                }

                $rawTranslations = $this->translation->where('group', $group)->whereNotNull('value')->orderby('key')->get();
                $tree = $this->makeTree($rawTranslations);
                $lostDotTranslations = $this->getLostDotTranslation($rawTranslations, $tree);
                if ($lostDotTranslations) {
                    $errorText = "Incorrect use of dot convention for translation keys (value will be overwritten with an array of child values):";

                    foreach ($lostDotTranslations as $group => $groupKeys) {
                        $keys = $groupKeys;
                        $errorText .= "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>$group::</strong>";

                        foreach ($keys as $key => $value) {
                            $errorText .= "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$key";
                        }
                    }
                    $this->errors[] = $errorText;
                }
                $configRewriter = new TranslationFileRewriter();
                $exportOptions = array_key_exists('export_format', $this->config()) ? TranslationFileRewriter::optionFlags($this->config('export_format')) : null;

                $base_path = $this->app->basePath();
                $pathTemplateResolver = new PathTemplateResolver($this->files, $base_path, $this->config('language_dirs'), '5');
                $zipRoot = $base_path . $this->config('zip_root', mb_substr($this->app->langPath(), 0, -4));

                if (mb_substr($zipRoot, -1) === '/') {
                    $zipRoot = substr($zipRoot, 0, -1);
                }

                if ($group === 'JSON') {
                    // make sure primary locale has a translation for all the keys since its keys are used during import and for translation keys in the app
                    foreach ($this->ltmJsonKeys as $ltmKey => $jsonKey) {
                        // if the primary locale does not have a translation for this key, then add it as the key so future imports will have a fixed key to work with
                        if (!isset($tree[$primaryLocale]['JSON'][$ltmKey])) {
                            $tree[$primaryLocale]['JSON'][$ltmKey] = $jsonKey;
                        }
                    }
                }

                foreach ($tree as $locale => $groups) {
                    if (isset($groups[$group])) {
                        $translations = $groups[$group];

                        // use the new path mapping
                        $computedPath = $pathTemplateResolver->groupFilePath($group, $locale);
                        $path = $base_path . $computedPath;

                        if ($computedPath) {
                            if ($group === 'JSON') {
                                if ($locale !== 'json') {
                                    // we need translation mapping keys
                                    $jsonTranslations = [];
                                    foreach ($translations as $key => $value) {
                                        $jsonTranslations[$this->ltmJsonKeys[$key]] = $value;
                                    }

                                    $output = json_encode($jsonTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                } else {
                                    // save jsonKey => ltmKey in the json.json file
                                    $output = json_encode($this->jsonLtmKeys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                }
                            } else {
                                $configRewriter->parseSource($this->files->exists($path) && $this->files->isFile($path) ? $this->files->get($path) : '');
                                $output = $configRewriter->formatForExport($translations, $exportOptions);
                            }

                            if ($this->zipExporting) {
                                $pathPrefix = mb_substr($path, 0, mb_strlen($zipRoot));
                                $filePathName = ($pathPrefix === $zipRoot) ? mb_substr($path, mb_strlen($zipRoot)) : $path;
                                //$this->makeDirPath($filePathName);
                                $this->zipExporting->addFromString($filePathName, $output);
                            } else {
                                try {
                                    $this->makeDirPath($path);
                                    if (($result = $this->files->put($path, $output)) === false) {
                                        $this->errors[] = "Failed to write to $path";
                                    };
                                } catch (Exception $e) {
                                    $this->errors[] = $e->getMessage();
                                }
                            }
                        }
                    }
                }

                if (!$inDatabasePublishing) {
                    $this->translation->where('group', $group)->update(array(
                        'status'        => Translation::STATUS_SAVED,
                        'is_auto_added' => 0,
                        'saved_value'   => (new Expression('value')),
                    ));
                }
            }
        }
    }

    public function exportAllTranslations($recursing = 0)
    {
        $groups = $this->translatorRepository->findFilledGroups();
        $this->clearCache();
        $this->clearUsageCache(false);

        foreach ($groups as $group) {
            $this->exportTranslations($group->group, $recursing);
        }
    }

    public function zipTranslations($groups)
    {
        $zip_name = @tempnam('Translations_' . time(), 'zip'); // Zip name
        $this->zipExporting = new ZipArchive();
        $this->zipExporting->open($zip_name, ZipArchive::OVERWRITE);

        if (!is_array($groups)) {
            if ($groups === '*') {
                $groups = $this->translatorRepository->findFilledGroups();
                foreach ($groups as $group) {
                    // Stuff with content
                    $this->exportTranslations($group->group, 0);
                }
            } else {
                // Stuff with content
                $this->exportTranslations($groups, 0);
            }
        } else {
            foreach ($groups as $group) {
                // Stuff with content
                $this->exportTranslations($group, 0);
            }
        }

        $this->zipExporting->close();
        $this->zipExporting = null;

        return $zip_name;
    }

    public function cleanTranslations()
    {
        $this->translation->whereNull('value')->delete();
    }

    public function truncateTranslations($group = null)
    {
        if ($group === '*' || $group === null) {
            $this->translation->truncate();
        } else {
            $this->translatorRepository->deleteTranslationByGroup($group);
        }
    }

    protected function makeTree($translations)
    {
        $array = array();
        foreach ($translations as $translation) {
            array_set($array[$translation->locale][$translation->group], $translation->key, $translation->value);
        }
        return $array;
    }

    protected function getLostDotTranslation($translations, $tree)
    {
        // check if all translation values are in the array or some were lost because of invalid dot notation for keys 
        $nonArrays = array();
        foreach ($translations as $translation) {
            $group = $translation->group;
            $key = $translation->key;
            if (!array_key_exists($key, $nonArrays)) {
                $value = array_get($tree[$translation->locale][$translation->group], $translation->key);

                if (is_array($value)) {
                    // this one is an array while it is a translation in the source 
                    $nonArrays[$group][$key] = $translation;
                }
            }
        }

        return $nonArrays;
    }

}
