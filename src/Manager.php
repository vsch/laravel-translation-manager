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
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Vsch\TranslationManager\Classes\PathTemplateResolver;
use Vsch\TranslationManager\Classes\TranslationFileRewriter;
use Vsch\TranslationManager\Models\Translation;
use Vsch\TranslationManager\Models\UserLocales;
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
    protected $events;

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

    /**
     * @var   \ZipArchive
     */
    protected $zipExporting;

    private $package;

    public
    function setConnectionName($connection = null)
    {
        if ($connection === null || $connection === '') {
            // resetting to default
            $connection = $this->default_translation_connection;
        }

        $this->translation->setConnection($connection);
        $this->indatabase_publish = $this->getConnectionInDatabasePublish($connection);

        $this->clearCache();
    }

    public
    function getConnectionName()
    {
        $connectionName = $this->translation->getConnectionName();
        return $connectionName;
    }

    /**
     * @param $connection
     *
     * @return bool
     */
    public
    function isDefaultTranslationConnection($connection)
    {
        return $connection == null || $connection == $this->default_translation_connection;
    }

    public
    function getConnection()
    {
        return $this->translation->getConnection();
    }

    /**
     * @return \Vsch\TranslationManager\Models\Translation
     */
    public
    function getTranslation()
    {
        return $this->translation;
    }

    public
    function getConnectionInDatabasePublish($connection)
    {
        if ($connection === null || $connection === '' || $this->isDefaultTranslationConnection($connection)) {
            return $this->config(self::INDATABASE_PUBLISH_KEY, 0);
        }
        return $this->getConnectionInfo($connection, self::INDATABASE_PUBLISH_KEY, $this->config(self::INDATABASE_PUBLISH_KEY, 0));
    }

    public
    function getUserListConnection($connection)
    {
        if ($connection === null || $connection === '' || $this->isDefaultTranslationConnection($connection)) {
            // use the default connection for the user list
            return '';
        }

        $userListConnection = $this->getConnectionInfo($connection, self::USER_LIST_CONNECTION_KEY, $connection);
        if (!$userListConnection) $userListConnection = $connection;
        return $userListConnection;
    }

    public
    function getUserListProvider($connection)
    {
        return function ($user, $connection_name, &$user_list) {
            return \Gate::forUser($user)->allows(self::ABILITY_LIST_EDITORS, [$connection_name, &$user_list]);
        };
    }

    public
    function getTranslationsTableName()
    {
        $prefix = $this->translation->getConnection()->getTablePrefix();
        return $prefix . $this->translation->getTable();
    }

    public
    function getUserLocalesTableName()
    {
        $userLocales = new UserLocales();
        $prefix = $this->translation->getConnection()->getTablePrefix();
        return $prefix . $userLocales->getTable();
    }

    public
    function getConnectionInfo($connection, $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config(self::DB_CONNECTIONS_KEY);
        }

        $db_connections = $this->config(self::DB_CONNECTIONS_KEY);
        $environment = \App::environment();

        $db_connection = $connection !== null && array_key_exists($environment, $db_connections) && array_key_exists($connection, $db_connections[$environment]) ? $db_connections[$environment][$connection] : null;

        $value = $db_connection && array_key_exists($key, $db_connection)
            ? $db_connection[$key]
            : $default;

        return $value;
    }

    /**
     * @return bool
     */
    public
    function inDatabasePublishing()
    {
        return $this->zipExporting ? 3 : $this->indatabase_publish;
    }

    /**
     * @return bool
     */
    public
    function areUserLocalesEnabled()
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

    public
    function cacheGroupTranslations($group, $locales, $translations)
    {
        $this->preloadedGroupKeys = $translations;
        $this->preloadedGroup = $group;
        $locales = explode(',', $locales);
        $this->preloadedGroupLocales = array_combine($locales, $locales);
    }

    public
    function __construct(Application $app, Filesystem $files, Dispatcher $events, Translation $translation)
    {
        $this->app = $app;
        $this->package = ManagerServiceProvider::PACKAGE;
        $this->config = $this->app['config'][$this->package];

        $this->files = $files;
        $this->events = $events;
        $this->translation = $translation;
        $this->default_connection = $translation->getConnectionName();
        $this->default_translation_connection = $this->config(self::DEFAULT_DB_CONNECTION_KEY, null);
        if (!$this->default_translation_connection) $this->default_translation_connection = $this->default_connection;
        else $this->setConnectionName($this->default_translation_connection);

        $this->preloadedGroupKeys = null;
        $this->preloadedGroup = null;

        $this->persistentPrefix = null;
        $this->cache = null;
        $this->usageCache = null;

        $this->indatabase_publish = $this->getConnectionInDatabasePublish($this->default_translation_connection);

        $this->groupList = null;
        $this->augmentedGroupList = null;
    }

    public
    function afterRoute($request, $response)
    {
        $this->saveCache();
        $this->saveUsageCache();
    }

    public
    function getGroupList()
    {
        if ($this->groupList === null) {
            // read it in
            $groups = $this->getTranslation()->groupBy('group');
            $excludedGroups = $this->config(Manager::EXCLUDE_GROUPS_KEY);
            if ($excludedGroups) {
                $groups = $groups->whereNotIn('group', $excludedGroups);
            }

            $this->groupList = ManagerServiceProvider::getLists($groups->lists('group', 'group'));
        }
        return $this->groupList;
    }

    public
    function getGroupAugmentedList()
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

    public
    function config($key = null, $default = null)
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

    public
    function cacheEnabled()
    {
        return $this->cachePrefix() !== '';
    }

    public
    function cachePrefix()
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

    public
    function usageCachePrefix()
    {
        if ($this->usageCacheTransKey == null) {
            $this->cachePrefix();
            $this->usageCacheTransKey = $this->persistentPrefix ? $this->persistentPrefix . 'usage_info' : '';
        }
        return $this->persistentPrefix;
    }

    public
    function cache()
    {
        if ($this->cache === null) {
            $this->cache = $this->cachePrefix() !== '' && $this->indatabase_publish != 0 && \Cache::has($this->cacheTransKey) ? \Cache::get($this->cacheTransKey) : [];
            $this->cacheIsDirty = $this->persistentPrefix !== '' && !\Cache::has($this->cacheTransKey);
        }
        return $this->cache;
    }

    public
    function usageCache()
    {
        if ($this->usageCache === null) {
            $this->usageCache = $this->usageCachePrefix() !== '' && \Cache::has($this->usageCacheTransKey) ? \Cache::get($this->usageCacheTransKey) : [];
            $this->usageCacheIsDirty = $this->persistentPrefix !== '' && !\Cache::has($this->usageCacheTransKey);
        }
        return $this->usageCache;
    }

    public
    function saveCache()
    {
        if ($this->persistentPrefix && $this->cacheIsDirty) {
            \Cache::put($this->cacheTransKey, $this->cache, 60 * 24 * 365);
            $this->cacheIsDirty = false;
        }
    }

    public
    function saveUsageCache()
    {
        if ($this->persistentPrefix && $this->usageCacheIsDirty) {
            // we never save it in the cache, it is only in database use, otherwise every page it will save the full cache to the database
            // instead of only the accessed keys
            //\Cache::put($this->usageCacheTransKey, $this->usageCache, 60 * 24 * 365);
            \Cache::put($this->usageCacheTransKey, [], 60 * 24 * 365);
            $this->usageCacheIsDirty = false;
            $ltm_translations = $this->getTranslationsTableName();

            // now update the keys in the database
            foreach ($this->usageCache as $group => $keys) {
                $setKeys = "";
                $resetKeys = "";
                foreach ($keys as $key => $usage) {
                    if ($usage) {
                        if ($setKeys) $setKeys .= ',';
                        $setKeys .= "'$key'";
                    } else {
                        if ($resetKeys) $resetKeys .= ',';
                        $resetKeys .= "'$key'";
                    }
                }

                if ($setKeys) {
                    $this->getConnection()->affectingStatement(<<<SQL
UPDATE $ltm_translations SET was_used = 1 WHERE was_used <> 1 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?) AND `key` IN ($setKeys)
SQL
                        , [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
                }
                if ($resetKeys) {
                    $this->getConnection()->affectingStatement(<<<SQL
UPDATE $ltm_translations SET was_used = 0 WHERE was_used <> 0 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?) AND `key` IN ($resetKeys)
SQL
                        , [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
                }
            }
        }
    }

    public
    function clearCache($groups = null)
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

    public
    function clearUsageCache($clearDatabase, $groups = null)
    {
        $ltm_translations = $this->getTranslationsTableName();
        if (!$groups || $groups === '*') {
            $this->usageCache();
            $this->usageCache = [];
            $this->usageCacheIsDirty = true;
            $this->saveUsageCache();

            if ($clearDatabase) {
                $this->getConnection()->affectingStatement(<<<SQL
UPDATE $ltm_translations SET was_used = 0 WHERE was_used <> 0
SQL
                );
            }
        } elseif ($this->usageCache()) {
            $this->usageCache();
            if (!is_array($groups)) $groups = [$groups];

            foreach ($groups as $group) {
                if (array_key_exists($group, $this->usageCache)) {
                    unset($this->usageCache[$group]);
                    $this->usageCacheIsDirty = !!$this->persistentPrefix;
                }

                if ($clearDatabase) {
                    $this->getConnection()->affectingStatement(<<<SQL
UPDATE $ltm_translations SET was_used = 0 WHERE was_used <> 0 AND (`group` = ? OR `group` LIKE ? OR `group` LIKE ?)
SQL
                        , [$group, 'vnd:%.' . $group, 'wbn:%.' . $group]);
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
        if (count($key) > 1) {
            $group = self::fixGroup($key[0]);
            $key = $key[1];
        } else {
            $group = '';
            $key = $key[0];
        }
        return [$group, $key];
    }

    public
    function cacheTranslation($key, $value, $locale)
    {
        list($group, $transKey) = self::groupKeyList($key);

        if ($group) {
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

        if ($group) {
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
        return
            in_array($group, $this->config(self::EXCLUDE_PAGE_EDIT_GROUPS_KEY, []))
            || in_array($group, $this->config(self::EXCLUDE_GROUPS_KEY, []));
    }

    public static
    function fixGroup($group)
    {
        if ($group !== null) $group = str_replace('/', '.', $group);
        return $group;
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
        if (!$useLottery || $this->config(self::LOG_MISSING_KEYS_KEY)) {
            // Fucking L5 changes
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

                if ($lottery === 1) {
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
                            'group' => $group,
                            'key' => $key,
                        ));
                    } else {
                        $translation = $this->firstOrCreateTranslation(array(
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
     */
    public
    function usingKey($namespace, $group, $key, $locale = null, $useLottery = false)
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

                if ($lottery === 1) {
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
        $connectionName = $this->getConnectionName();
        $ltm_translations = $this->getTranslationsTableName();
        $dbTranslations = $this->translation->hydrateRaw(<<<SQL
SELECT * FROM $ltm_translations WHERE locale = ? AND `group` = ?

SQL
            , [$locale, $db_group], $connectionName);

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
                    'group' => $db_group,
                    'key' => $key,
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
            $translation->saved_value = $value;

            $newStatus = ($translation->value === $translation->saved_value ? Translation::STATUS_SAVED : ($translation->status === Translation::STATUS_SAVED ? Translation::STATUS_SAVED_CACHED : Translation::STATUS_CHANGED));

            if ($newStatus !== (int)$translation->status) {
                $translation->status = $newStatus;
            }

            if (!$translation->exists) {
                $sql = '(' .
                    self::dbValue($translation->status, Translation::STATUS_SAVED) . ',' .
                    self::dbValue($translation->locale) . ',' .
                    self::dbValue($translation->group) . ',' .
                    self::dbValue($translation->key) . ',' .
                    self::dbValue($translation->value) . ',' .
                    self::dbValue($translation->created_at, $timeStamp) . ',' .
                    self::dbValue($translation->updated_at, $timeStamp) . ',' .
                    self::dbValue($translation->source) . ',' .
                    self::dbValue($translation->saved_value) . ',' .
                    self::dbValue($translation->is_deleted, 0) . ',' .
                    self::dbValue($translation->was_used, 0) .
                    ')';

                $values[] = $sql;
            } else if ($translation->isDirty()) {
                if ($translation->isDirty(['value', 'source', 'saved_value', 'was_used',])) {
                    $translation->save();
                } else {
                    if (!array_key_exists($translation->status, $statusChangeOnly)) $statusChangeOnly[$translation->status] = $translation->id;
                    else $statusChangeOnly[$translation->status] .= ',' . $translation->id;
                }
            }

            $this->imported++;
        }

        // now batch update those with status changes only
        $updated_at = new Carbon();
        foreach ($statusChangeOnly as $status => $translationIds) {
            $this->getConnection()->affectingStatement(<<<SQL
UPDATE $ltm_translations SET status = ?, is_deleted = 0, updated_at = ? WHERE id IN ($translationIds)
SQL
                , [$status, $updated_at]);
        }

        //$sql = "INSERT INTO `ltm_translations`(status, locale, `group`, `key`, value, created_at, updated_at, source, saved_value, is_deleted, was_used) VALUES ";
        // now process all the new translations that were not in the files
        if ($replace == 2) {
            // we delete all translations that were not in the files
            if ($dbTransMap) {
                $translationIds = '';
                foreach ($dbTransMap as $translation) {
                    $translationIds .= ',' . $translation->id;
                }

                $translationIds = trim_prefix($translationIds, ',');
                $this->getConnection()->unprepared(<<<SQL
DELETE FROM $ltm_translations WHERE id IN ($translationIds)
SQL
                );
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
            $sql = "INS" . "ERT INTO $ltm_translations (status, locale, `group`, `key`, value, created_at, updated_at, source, saved_value, is_deleted, was_used) VALUES " . implode(",", $values);

            //$this->getConnection()->unprepared('LOCK TABLES `ltm_translations` WRITE');
            try {
                $this->getConnection()->unprepared($sql);
            } catch (\Exception $e) {
                $tmp = 0;
            }
            //$this->getConnection()->unprepared('UNLOCK TABLES');
        }
    }

    protected static
    function dbValue($value, $nullValue = 'NULL')
    {
        if ($value === null) return $nullValue;
        if (is_string($value)) return '\'' . str_replace('\'', '\'\'', $value) . '\'';
        if (is_bool($value)) return $value ? 1 : 0;
        return $value;
    }

    public
    function importTranslations($replace, $groups = null)
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

        // Laravel 5.1
        $pathTemplateResolver = new PathTemplateResolver($this->files, $this->app->basePath(), $this->config('language_dirs'), '5');
        // Laravel 4.2
        //$pathTemplateResolver = new PathTemplateResolver($this->files, base_path(), $this->config('language_dirs'), '4');
        $langFiles = $pathTemplateResolver->langFileList();

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

            if (in_array($db_group, $this->config(self::EXCLUDE_GROUPS_KEY))) continue;
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
        foreach ($finder as $file) {
            // Search the current file for the pattern
            if (preg_match_all("/$pattern/siU", $file->getContents(), $matches)) {
                // Get all matches
                foreach ($matches[2] as $key) {
                    $keys[] = $key;
                }
            }
        }
        // Remove duplicates
        $keys = array_unique($keys);

        // Add the translations to the database, if not existing.
        foreach ($keys as $key) {
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
        foreach ($directories as $directory) {
            $dirpath .= $directory . "/";
            if (!$this->files->exists($dirpath)) {
                try {
                    $this->files->makeDirectory($dirpath);
                } catch (Exception $e) {
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
        // this can come from the command line
        $ltm_translations = $this->getTranslationsTableName();
        if (!$recursing) {
            $this->clearErrors();
            $group = self::fixGroup($group);
        }

        if ($group && $group !== '*') {
            $this->getConnection()->affectingStatement("DELETE FROM $ltm_translations WHERE is_deleted = 1");
        } elseif (!$recursing) {
            $this->getConnection()->affectingStatement("DELETE FROM $ltm_translations WHERE is_deleted = 1 AND `group` = ?", [$group]);
        }

        $inDatabasePublishing = $this->inDatabasePublishing();
        if ($inDatabasePublishing < 3 && $inDatabasePublishing && ($inDatabasePublishing < 2 || !$recursing)) {
            if ($group && $group !== '*') {
                $this->getConnection()->affectingStatement(<<<SQL
UPDATE $ltm_translations SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?) AND `group` = ?
SQL
                    , [Translation::STATUS_SAVED_CACHED, Translation::STATUS_SAVED, $group]);

                $translations = $this->translation->query()->where('status', '<>', Translation::STATUS_SAVED)->where('group', '=', $group)->get([
                    'group',
                    'key',
                    'locale',
                    'saved_value'
                ]);
            } else {
                $this->getConnection()->affectingStatement(<<<SQL
UPDATE $ltm_translations SET saved_value = value, status = ? WHERE (saved_value <> value || status <> ?)
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
            $translations->each(function ($tr) {
                $this->cacheTranslation($tr->group . '.' . $tr->key, $tr->saved_value, $tr->locale);
            });
        }

        if (!$inDatabasePublishing || $inDatabasePublishing === 2 || $inDatabasePublishing === 3) {
            if (!in_array($group, $this->config(self::EXCLUDE_GROUPS_KEY))) {
                if ($group == '*') $this->exportAllTranslations(1);

                if ($inDatabasePublishing !== 3) {
                    $this->clearCache($group);
                    $this->clearUsageCache(false, $group);
                }

                $tree = $this->makeTree($this->translation->where('group', $group)->whereNotNull('value')->orderby('key')->get());
                $configRewriter = new TranslationFileRewriter();
                $exportOptions = array_key_exists('export_format', $this->config()) ? TranslationFileRewriter::optionFlags($this->config('export_format')) : null;

                // Laravel 5.1
                $base_path = $this->app->basePath();
                $pathTemplateResolver = new PathTemplateResolver($this->files, $base_path, $this->config('language_dirs'), '5');
                $zipRoot = $base_path . $this->config('zip_root', mb_substr($this->app->langPath(), 0, -4));
                // Laravel 4.2
                //$base_path = base_path();
                //$pathTemplateResolver = new PathTemplateResolver($this->files, $base_path, $this->config('language_dirs'), '4');
                //$zipRoot = $base_path . $this->config('zip_root', mb_substr($this->app->make('path').'/lang', 0, -4));

                if (mb_substr($zipRoot, -1) === '/') $zipRoot = substr($zipRoot, 0, -1);

                foreach ($tree as $locale => $groups) {
                    if (isset($groups[$group])) {
                        $translations = $groups[$group];

                        // use the new path mapping
                        $computedPath = $pathTemplateResolver->groupFilePath($group, $locale);
                        $path = $base_path . $computedPath;

                        if ($computedPath) {
                            $configRewriter->parseSource($this->files->exists($path) && $this->files->isFile($path) ? $this->files->get($path) : '');
                            $output = $configRewriter->formatForExport($translations, $exportOptions);

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
        $groups = $this->translation->whereNotNull('value')->select(DB::raw('DISTINCT `group`'))->get('group');
        $this->clearCache();
        $this->clearUsageCache(false);

        foreach ($groups as $group) {
            $this->exportTranslations($group->group, $recursing);
        }
    }

    public
    function zipTranslations($groups)
    {
        $zip_name = tempnam("Translations_" . time(), "zip"); // Zip name
        $this->zipExporting = new ZipArchive();
        $this->zipExporting->open($zip_name, ZipArchive::OVERWRITE);

        if (!is_array($groups)) {
            if ($groups === '*') {
                $groups = $this->translation->whereNotNull('value')->select(DB::raw('DISTINCT `group`'))->get('group');
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

    public
    function cleanTranslations()
    {
        $this->translation->whereNull('value')->delete();
    }

    public
    function truncateTranslations($group = null)
    {
        if ($group === '*' || $group === null) {
            $this->translation->truncate();
        } else {
            $ltm_translations = $this->getTranslationsTableName();
            $this->getConnection()->affectingStatement("DELETE FROM $ltm_translations WHERE `group` = ?", [$group]);
        }
    }

    protected
    function makeTree($translations)
    {
        $array = array();
        foreach ($translations as $translation) {
            array_set($array[$translation->locale][$translation->group], $translation->key, $translation->value);
        }
        return $array;
    }

}
