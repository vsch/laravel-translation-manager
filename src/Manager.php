<?php

namespace Vsch\TranslationManager;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
    const DISABLE_REACT_UI = 'disable-react-ui';
    const DISABLE_REACT_UI_LINK = 'disable-react-ui-link';

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
    protected $augmentedGroupReverseList;
    protected $preloadedGroupKeys;
    protected $preloadedGroup;
    protected $preloadedGroupLocales;
    protected $ltmJsonKeys;
    protected $jsonLtmKeys;

    /**
     * @var   \ZipArchive
     */
    protected $zipExporting;
    protected $jsonKeyWordSeparator;
    protected $isWebUI;
    protected $createJsonKeysForWebUI; // not used, ignored
    protected $newJsonKeyFromPrimaryLocale;
    protected $primaryLocale;

    private $package;
    private $translatorRepository;
    private $translator;

    public const JSON_GROUP = 'JSON';

    public function __construct(Application $app, Filesystem $files, Dispatcher $dispatchesEvents, ITranslatorRepository $translatorRepository)
    {
        $this->app = $app;
        $this->translatorRepository = $translatorRepository;

        $this->package = ManagerServiceProvider::PACKAGE;
        $this->config = $this->app['config'][$this->package];

        $this->files = $files;
        $this->dispathesEvents = $dispatchesEvents;
        $this->translation = $translatorRepository->getTranslation();
        $this->default_connection = $this->translation->getConnectionName();
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

    public function getRepository()
    {
        return $this->translatorRepository;
    }

    public function setConnectionName($connection = null)
    {
        if ($this->isDefaultTranslationConnection($connection)) {
            // resetting to default
            $connection = $this->default_translation_connection;
        }

        $this->translation->setConnection($connection);
        $this->indatabase_publish = $this->getConnectionInDatabasePublish($connection);

        $this->resetCache();
    }

    public function getNormalizedConnectionName($connection)
    {
        if ($this->isDefaultTranslationConnection($connection)) {
            $connectionName = '';
        } else {
            $connectionName = $connection;
        }

        return $connectionName;
    }

    public function getResolvedConnectionName($connection)
    {
        if ($this->isDefaultTranslationConnection($connection)) {
            $connectionName = $this->default_translation_connection ?: '';
        } else {
            $connectionName = $connection;
        }

        return $connectionName;
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
        return $connection == null || $connection == '' || $connection == $this->default_translation_connection;
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
        return $this->zipExporting ? 3 : (int)$this->indatabase_publish;
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
                if ($attribute === 'locale' || $attribute === 'group' || $attribute === 'key') {
                    $query = $query->where($attribute, $value);
                }
            }

            $translation = $query->first();
        }

        if (!$translation) {
            $translation = new Translation();
            $translation->fill($attributes);
            $translation->setConnection($this->getConnectionName());

            // put it in the cache as empty so we don't hit the database every time
            $this->cacheTranslation('', $translation->group, $translation->key, null, $translation->locale);
        }

        return $translation;
    }

    function firstOrCreateTranslation($attributes = null)
    {
        $translation = $this->firstOrNewTranslation($attributes);
        if (!$translation->exists) {
            $translation->save();

            // put it in the cache as empty so we don't hit the database every time
            $this->cacheTranslation('', $translation->group, $translation->key, null, $translation->locale);
        }

        return $translation;
    }

    public function cacheGroupTranslations($group, $locales, $translations)
    {
        $this->preloadedGroupKeys = $translations;
        $this->preloadedGroup = $group;
        $this->preloadedGroupLocales = array_combine($locales, $locales);
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
        // only save cache if it is default connection
        if ($this->isDefaultTranslationConnection($this->getConnectionName())) {
            $this->saveCache();
            $this->saveUsageCache();
        }
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
            //            $groups = $groups->where('group','<>', self::JSON_GROUP);

            $this->groupList = ManagerServiceProvider::getLists($groups->pluck('group', 'group'));
        }
        return $this->groupList;
    }

    public function getAugmentedGroup($namespace, $group)
    {
        $augmentedGroup = $group;

        if ($namespace && $namespace != '*') {
            $augmentedGroup = "$namespace::$group";
            if (array_key_exists($augmentedGroup, $this->getGroupAugmentedList())) {
                $augmentedGroup = $this->augmentedGroupList[$augmentedGroup];
            } else {
                $augmentedGroup = $group;
            }
        }
        return $augmentedGroup;
    }

    public function getGroupAugmentedList()
    {
        if ($this->augmentedGroupList === null) {
            // compute augmented list from vnd:{vendor}.{package}::group.key
            // remove the vnd:{vendor}. and add to augmented, map it to original name
            $groupList = $this->getGroupList();
            $this->augmentedGroupList = [];
            $this->augmentedGroupReverseList = [];

            foreach ($groupList as $group) {
                if (starts_with($group, ["vnd:", "wbn:"])) {
                    // we need this one
                    $parts = explode('.', $group, 2);
                    if (count($parts) === 2) {
                        // if it is not included in the vendor resources
                        if (!array_key_exists($parts[1], $groupList)) {
                            $this->augmentedGroupList[$parts[1]] = $group;
                        }
                        $this->augmentedGroupReverseList[$group] = $parts[1];
                    }
                }
            }
        }
        return $this->augmentedGroupList;
    }

    public function getGroupAugmentedReverseList()
    {
        $this->getGroupAugmentedList();
        return $this->augmentedGroupReverseList;
    }

    public function getJsonKeyWordSeparator()
    {
        if (!isset($this->jsonKeyWordSeparator)) {
            $string = $this->config('new-json-keys-separator', '-');
            $this->jsonKeyWordSeparator = substr($string, 0, 1);
        }
        return $this->jsonKeyWordSeparator;
    }

    public function config($key = null, $default = null)
    {
        // Version 5.1
        if (!$this->config) {
            $this->config = $this->app['config'][$this->package];
        }
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
        }

        if ($this->cacheTransKey == null) {
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
            if ($this->isDefaultTranslationConnection($this->getConnectionName())) {
                $this->cache = $this->cachePrefix() !== '' && $this->indatabase_publish != 0 && Cache::has($this->cacheTransKey) ? Cache::get($this->cacheTransKey) : [];
                $this->cacheIsDirty = $this->persistentPrefix !== '' && !Cache::has($this->cacheTransKey);
            } else {
                $this->cache = [];   // only default connections use cache
            }
        }
        return $this->cache;
    }

    public function usageCache()
    {
        if ($this->usageCache === null) {
            if ($this->isDefaultTranslationConnection($this->getConnectionName())) {
                $this->usageCache = $this->usageCachePrefix() !== '' && Cache::has($this->usageCacheTransKey) ? Cache::get($this->usageCacheTransKey) : [];
                $this->usageCacheIsDirty = $this->persistentPrefix !== '' && !Cache::has($this->usageCacheTransKey);
            } else {
                $this->usageCache = [];
            }
        }
        return $this->usageCache;
    }

    public function saveCache()
    {
        if ($this->isDefaultTranslationConnection($this->getConnectionName())) {
            if ($this->persistentPrefix && $this->cacheIsDirty) {
                Cache::put($this->cacheTransKey, $this->cache, 60 * 24 * 365);
                $this->cacheIsDirty = false;
            }
        }
    }

    public function saveUsageCache()
    {
        if ($this->isDefaultTranslationConnection($this->getConnectionName())) {
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
    }

    public function clearCache($groups = null)
    {
        if (!$groups || $groups === '*') {
            $this->cache = [];
            $this->cacheIsDirty = !!$this->persistentPrefix;
        } else if ($this->cache()) {
            if (!is_array($groups)) $groups = [$groups];

            foreach ($groups as $group) {
                if (array_key_exists($group, $this->cache)) {
                    unset($this->cache[$group]);
                    $this->cacheIsDirty = !!$this->persistentPrefix;
                }
            }
        }
    }

    public function resetCache()
    {
        if ($this->isDefaultTranslationConnection($this->getConnectionName())) {
            $this->cache = null; // default means we need to use the cache
        } else {
            $this->cache = [];
            $this->cacheIsDirty = false;
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
        } else if ($this->usageCache()) {
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

    /**
     * @param $namespace
     * @param $group
     * @param $locale
     * @param $translations array|null  translations to override with cached translations
     *
     * @return array   of key=>translation of cached translations for the locale
     */
    public function cachedTranslations($namespace, $group, $locale, $translations = null)
    {
        $group = self::fixGroup($group);
        $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;
        $translations = $translations ?: [];

        $values = $group && array_key_exists($group, $this->cache()) ? $this->cache[$group] : null;
        if ($values) {
            $localePrefix = "$locale:";
            $prefixLen = strlen($localePrefix);
            foreach ($values as $key => $translation) {
                if (str_starts_with($key, $localePrefix)) {
                    $transKey = substr($key, $prefixLen);
                    $translations[$transKey] = $translation;
                }
            }
        }

        return $translations;
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

    // this is called to tell Missing Keys that the keys being passed in are LTM keys not jSON keys
    public function setWebUI($createJsonKeys = null)
    {
        $this->isWebUI = true;
        $this->createJsonKeysForWebUI = $createJsonKeys;
    }

    /**
     * @param             $namespace string
     * @param             $group     string
     * @param             $key       string
     * @param null|string $locale
     * @param bool $useLottery
     * @param bool $findOrNew
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
                    $augmentedGroup = $group;
                    if (array_key_exists($group, $augmentedGroupList)) {
                        $augmentedGroup = $augmentedGroupList[$group];
                    }

                    $locale = $locale ?: ($this->translator ?: ($this->translator = $this->app->make('translator')))->getLocale();

                    if ($findOrNew) {
                        $translation = $this->firstOrNewTranslation(array(
                            'locale' => $locale,
                            'group' => $augmentedGroup,
                            'key' => $key,
                        ));
                    } else {
                        $translation = $this->firstOrCreateTranslation(array(
                            'locale' => $locale,
                            'group' => $augmentedGroup,
                            'key' => $key,
                        ));
                    }

                    if ($group === 'JSON' && $locale !== 'json') {
                        // we create a key if one does not exist since we know what the json key's value should be

                        if (!$this->isWebUI) {
                            $this->loadLtmJsonKeys(false);
                            if (!array_key_exists($key, $this->jsonLtmKeys)) {
                                $ltmKey = $this->generateLtmKey($this->jsonLtmKeys, $key);

                                $jsonTranslation = $this->firstOrCreateTranslation(array(
                                    'locale' => 'json',
                                    'group' => $augmentedGroup,
                                    'key' => $ltmKey,
                                    'value' => $key,
                                    'saved_value' => $key,
                                    'status' => Translation::STATUS_CHANGED,
                                ));

                                $this->ltmJsonKeys[$ltmKey] = $key;
                                $this->jsonLtmKeys[$key] = $ltmKey;
                            }
                            //                        } else if ($this->createJsonKeysForWebUI) {
                            //                            $this->loadLtmJsonKeys(false);
                            //                            if (!array_key_exists($key, $this->ltmJsonKeys)) {
                            //                                $ltmKey = $key;
                            //
                            //                                $jsonTranslation = $this->firstOrCreateTranslation(array(
                            //                                    'locale' => 'json',
                            //                                    'group' => $augmentedGroup,
                            //                                    'key' => $ltmKey,
                            //                                    'value' => $key,
                            //                                    'saved_value' => $key,
                            //                                    'status' => Translation::STATUS_CHANGED,
                            //                                ));
                            //
                            //                                $this->ltmJsonKeys[$ltmKey] = $key;
                            //                                $this->jsonLtmKeys[$key] = $ltmKey;
                            //                            }
                        }
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
     * @param bool $useLottery
     */
    public
    function usingKey($namespace, $group, $key, $locale, $useLottery)
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
                    $locale = $locale ?: ($this->translator ?: ($this->translator = $this->app->make('translator')))->getLocale();
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
    protected
    function importTranslationFile($locale, $db_group, $translations, $replace)
    {
        $connectionName = $this->getConnectionName();
        $dbTranslations = $this->translatorRepository->selectTranslationsByLocaleAndGroup($locale, $db_group);
        $inDatabasePublishing = $this->inDatabasePublishing();

        // we are either in-database publishing or writing the files but pretending that we are for a remote 
        // connection which is really limited to do in-database publishing only
        $inDatabasePublishing = $inDatabasePublishing === 1 || $inDatabasePublishing === 2 ? $inDatabasePublishing : 0;

        // isLocal in this case means we are importing files local to the server
        // otherwise their values will not reflect what is local to the server
        $isLocal = $this->isDefaultTranslationConnection($this->getConnectionName()) && $inDatabasePublishing != 2;

        $timeStamp = 'now()';
        $dbTransMap = [];
        $dbTranslations->each(function ($trans) use (&$dbTransMap, $connectionName) {
            $dbTransMap[$trans->key] = $trans;
        });

        $values = [];
        $statusChangeOnly = [];
        foreach ($translations as $key => $value) {
            if (!$value) {
                // must have been manually modified file to empty string
                continue;
            }
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
            }

            $translation->is_deleted = 0;
            $translation->is_auto_added = 0;

            $status = (int)$translation->status;

            // replace if current translation has no value or we want to replace existing values
            if ($replace || !$translation->value) {
                $translation->value = $value;
            }

            $previousSaved = $translation->saved_value;
            $translation->saved_value = $value;

            if ($isLocal || $locale === 'json') {
                $status = $translation->saved_value === $translation->value ? Translation::STATUS_SAVED : Translation::STATUS_CHANGED;
            } else {
                if ($translation->value === $translation->saved_value) {
                    if ($value === $previousSaved) {
                        // if it was saved then it is still reflecting the file, otherwise consider it cached.
                        if ($status !== Translation::STATUS_SAVED) $status = Translation::STATUS_SAVED_CACHED;
                    } else {
                        // the file and database are the same, but the database may no longer reflect the file
                        $status = Translation::STATUS_SAVED_CACHED;
                    }
                } else {
                    $status = Translation::STATUS_CHANGED;
                }
            }

            if ($status !== (int)$translation->status) {
                $translation->status = $status;
            }

            if (!$translation->exists) {
                $values[] = $this->translatorRepository->getInsertTranslationsElement($translation, $timeStamp);
            } else if ($translation->isDirty()) {
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
            // these are no-ops, we don't delete
        }

        if ($values) {
            try {
                $this->translatorRepository->insertTranslations($values);
            } catch (\Exception $e) {
                $tmp = 0;
            }
        }
    }

    private
    function generateLtmKey($jsonLtmKeys, $jsonKey, $maxJsonKey = null)
    {
        // replace all symbols and spaces with _, remove duplicate _ and use that if it does not already exist
        // if it exists we append _# until we get a unique key
        if (!$maxJsonKey) {
            $maxJsonKey = $this->config('json_dbkey_length', 32);
            if ($maxJsonKey > 120) $maxJsonKey = 120;
        }

        $ltmKey = mb_strtolower($jsonKey);
        $iMax = strlen($ltmKey);
        $newKey = "";
        $hadUnderscore = null;
        $jsonKeyWordSeparator = $this->getJsonKeyWordSeparator();
        for ($i = 0; $i < $iMax; $i++) {
            $c = mb_substr($ltmKey, $i, 1);
            if (preg_match("/[\\p{L}0-9]/", $c)) {
                if ($hadUnderscore) $newKey .= $this->jsonKeyWordSeparator;
                $newKey .= $c;
                $hadUnderscore = false;
            } else if (!$hadUnderscore) {
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
        while (array_key_exists($ltmKey . $suffix, $jsonLtmKeys)) {
            $suffix = $this->jsonKeyWordSeparator . ++$count;
        }

        return $ltmKey . $suffix;
    }

    private
    function loadLtmJsonKeys($loadAllKeys = false, $ignoreDeleted = false, $loadValues = false)
    {
        if (!isset($this->ltmJsonKeys)) {
            $newKeyFromPrimary = $this->isNewJsonKeyFromPrimaryLocale();

            $q = $this->translation->query()
                ->where('group', '=', self::JSON_GROUP);

            if ($newKeyFromPrimary && $loadAllKeys) {
                $primaryLocale = $this->getPrimaryLocale();
                $q = $q
                    ->whereIn('locale', ['json', $primaryLocale]);
            } else {
                $q = $q
                    ->where('locale', '=', 'json');
            }

            if ($loadValues) {
                $q = $q
                    ->whereNotNull('value');
            } else {
                $q = $q
                    ->whereNotNull('saved_value');
            }

            if ($ignoreDeleted) {
                $q = $q
                    ->where('is_deleted', 0);
            }

            $q = $q
                ->get([
                    'key',
                    $loadValues ? 'value' : 'saved_value',
                ]);

            $this->ltmJsonKeys = [];
            $this->jsonLtmKeys = [];
            $primaryLocaleValues = [];
            $q->each(function ($tr) use ($loadValues, &$primaryLocaleValues) {
                $value = $loadValues ? $tr->value : $tr->saved_value;
                $key = $tr->key;
                if ($key === 'json') {
                    $this->ltmJsonKeys[$key] = $value;
                    $this->jsonLtmKeys[$value] = $key;
                } else {
                    $primaryLocaleValues[$key] = $value;
                }
            });

            if ($loadAllKeys) {
                $q = $this->translation->query()
                    ->where('group', '=', self::JSON_GROUP)
                    ->where('locale', '<>', 'json');

                if ($ignoreDeleted) {
                    $q = $q
                        ->where('is_deleted', 0);
                }

                if ($loadValues) {
                    $q = $q
                        ->whereNotNull('value');
                } else {
                    $q = $q
                        ->whereNotNull('saved_value');
                }

                $q = $q
                    ->groupBy(['key'])
                    ->get([
                        'key',
                    ]);

                $q->each(function ($tr) use ($primaryLocaleValues) {
                    $key = $tr->key;
                    if (!array_key_exists($key, $this->ltmJsonKeys)) {
                        $jsonKey = array_key_exists($key, $primaryLocaleValues) ? $primaryLocaleValues[$key] : $key;
                        $this->ltmJsonKeys[$key] = $jsonKey;
                        $this->jsonLtmKeys[$jsonKey] = $key;
                    }
                });
            }
        }
    }

    public
    function getTranslations($namespace, $group, $locale, $includeMissing = true, $useUnpublished = false, $mergeTranslations = null)
    {
        $group = self::fixGroup($group);
        $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

        // may need to create new jsonKeys from default locale values for any that are missing from json locale of JSON group
        $query = $this->translation->query()
            ->where('group', '=', $group)
            ->where('locale', '=', $locale);

        if (!$includeMissing) {
            if ($useUnpublished) {
                $query = $query->whereNotNull('value');
            } else {
                $query = $query->whereNotNull('saved_value');
            }
        }

        $translations = $mergeTranslations ?: [];

        if ($useUnpublished) {
            $jsonTranslations = $query->get([
                'key',
                'value',
                'is_deleted',
            ]);

            $jsonTranslations->each(function ($tr) use ($namespace, $group, $locale, &$translations, $includeMissing) {
                if ($tr->is_deleted) {
                    if ($includeMissing) {
                        $translations[$tr->key] = null;
                    } else {
                        if (array_key_exists($tr->key, $translations)) {
                            unset($translations[$tr->key]);
                        }
                    }
                } else {
                    $translations[$tr->key] = $tr->value;
                }
            });
        } else {
            $jsonTranslations = $query->get([
                'key',
                'saved_value',
            ]);

            $jsonTranslations->each(function ($tr) use ($namespace, $group, $locale, &$translations) {
                $translations[$tr->key] = $tr->saved_value;
            });
        }

        return $translations;
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

        $pathTemplateResolver = new PathTemplateResolver($this->files, $this->app->basePath(), $this->config('language_dirs'), '5');

        $langFiles = $pathTemplateResolver->langFileList();

        // generate JSON to LTM keys here and put them in the JSON group as json locale
        $jsonFiles = [];

        foreach ($langFiles as $langFile => $vars) {
            $locale = $vars['{locale}'];
            $db_group = $vars['{db_group}'];
            if (in_array($db_group, $this->config(self::EXCLUDE_GROUPS_KEY))) continue;
            if ($db_group === self::JSON_GROUP) {
                $json = file_get_contents($langFile);
                if ($locale !== 'json') {
                    $jsonFiles[$langFile] = json_decode($json, true);
                }
            }
        }

        $jsonTranslations = []; // locale => [ ltmKey => jsonTranslation ]
        $ltmJsonKeys = [];
        if ($jsonFiles) {
            $maxJsonKey = $this->config('json_dbkey_length', 32);
            if ($maxJsonKey > 120) $maxJsonKey = 120;

            // get only existing keys, any missing mapping may come from the imported files
            $this->loadLtmJsonKeys();
            $this->config(); // init config and sep for json keys

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

            // need to update database JSON group json locale with key map of ltmKey to jsonKey for exporting, use the same replace level
            $this->importTranslationFile('json', self::JSON_GROUP, $ltmJsonKeys, $replace);
        }

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
            if ($db_group === self::JSON_GROUP) {
                // just update the locale with translations, keys are already LTM keys here
                $translations = $jsonTranslations[$locale];
            } else {
                $translations = array_dot(include($langFile));
            }
            $this->importTranslationFile($locale, $db_group, $translations, $replace);
        }

        return $this->imported;
    }

    public
    function findTranslations($path = null)
    {
        // functions is a replacement variable in $pattern
        /** @noinspection PhpUnusedLocalVariableInspection */
        $functions = config('laravel-translation-manager.find.functions');
        $pattern = implode('', config('laravel-translation-manager.find.pattern'));

        // Find all PHP + Twig files in the app folder, except for storage
        $paths = $path ? [$path] : array_merge([$this->app->basePath() . '/app'], $this->app['config']['view']['paths']);
        $keys = array();
        foreach ($paths as $path) {
            $finder = new Finder();
            $finder->in($path)->name("*.{" . implode(',', config('laravel-translation-manager.find.files')) . "}")->files();

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
            $basePath = $this->app->basePath();
            $basePathLen = strlen($basePath);
            foreach ($filePathsAndLocation as $filePath => $locations) {
                $relativePath = substr($filePath, $basePathLen);
                $paths .= $relativePath . ':' . implode(',', $locations) . "\n";
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

    public
    static function offsetLine($lines, $offset)
    {
        $iMax = count($lines);
        for ($i = 0; $i < $iMax; $i++) {
            if ($lines[$i] > $offset) {
                return $i + 1;
            }
        }

        return $iMax;
    }

    public
    static function computeFileLines($fileContents)
    {
        $lines = [];
        if (preg_match_all("/(\\n)/siU", $fileContents, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $index => $key) {
                $lines[] = $key[1];
            }
        }
        return $lines;
    }

    public
    function makeDirPath($path)
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

    /**
     * @param $group
     * @param $translations
     */
    private
    function cacheTranslationGroup($group, $translations): void
    {
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

    public
    function exportTranslations($group, $fromAllTranslations = false)
    {
        assert($group && $group !== '*', "exportTranslations only exports one group at a time, given" . $group);
        if (in_array($group, $this->config(self::EXCLUDE_GROUPS_KEY))) return;

        $inDatabasePublishing = $this->inDatabasePublishing();

        if (!$fromAllTranslations) {
            $this->clearErrors();
            $group = self::fixGroup($group);

            if ($inDatabasePublishing !== 3) {
                $this->clearCache($group);
                $this->clearUsageCache(false, $group);
                $this->translatorRepository->deleteTranslationWhereIsDeleted($group);
            }
        }

        if ($group === self::JSON_GROUP) {
            unset($this->ltmJsonKeys);
            $this->loadLtmJsonKeys(true, $inDatabasePublishing != 3, $inDatabasePublishing != 3);

            // save these mappings to the database replacing old values, these are now the standard
            if ($inDatabasePublishing != 3) {
                $this->importTranslationFile('json', self::JSON_GROUP, $this->ltmJsonKeys, 2);
            }
        }

        if (!$fromAllTranslations && ($inDatabasePublishing === 1 || $inDatabasePublishing === 2)) {
            // locked down file system or remote access to one
            $this->translatorRepository->updatePublishTranslations(Translation::STATUS_SAVED_CACHED, $group);

            $translations = $this->translation->query()
                ->where('status', '<>', Translation::STATUS_SAVED)->where('group', '=', $group)->get([
                    'group',
                    'key',
                    'locale',
                    'saved_value',
                ]);
            /* @var $translations Collection */
            $this->cacheTranslationGroup($group, $translations);
        }

        if ($inDatabasePublishing != 1) {
            // not locked down, or remote to locked down or zipping
            // set to published
            if (!$inDatabasePublishing) {
                // not cached and not zipped, so nothing has been set, if cached then already set to cached
                $this->translatorRepository->updatePublishTranslations(Translation::STATUS_SAVED, $group);
            }

            // at this point if published then no deleted translations, if zipping we ignore them
            $rawTranslations = $this->translation->query()
                ->where('group', $group)
                ->whereNotNull('value');

            $rawTranslations = $rawTranslations
                ->orderby('key')
                ->get();

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

            foreach ($tree as $locale => $groups) {
                if (isset($groups[$group])) {
                    $translations = $groups[$group];

                    // use the new path mapping
                    $computedPath = $pathTemplateResolver->groupFilePath($group, $locale);
                    $path = $base_path . $computedPath;

                    if ($computedPath) {
                        if ($group === self::JSON_GROUP) {
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
        }
    }

    public
    function exportAllTranslations()
    {
        $groups = $this->translatorRepository->findFilledGroups();

        $inDatabasePublishing = $this->inDatabasePublishing();

        if ($inDatabasePublishing != 3) {
            // not zipping, remove deleted items
            $this->clearCache();
            $this->clearUsageCache(false);
            $this->translatorRepository->deleteTranslationWhereIsDeleted();
        }

        if ($inDatabasePublishing === 1 || $inDatabasePublishing === 2) {
            // locked down file system or remote access to one
            $this->translatorRepository->updatePublishTranslations(Translation::STATUS_SAVED_CACHED);

            $translations = $this->translation->query()->where('status', '<>', Translation::STATUS_SAVED)->get([
                'group',
                'key',
                'locale',
                'saved_value',
            ]);

            /* @var $translations Collection */
            $this->cacheTranslationGroup(null, $translations);
        }

        foreach ($groups as $group) {
            $this->exportTranslations($group->group);
        }
    }

    public
    function zipTranslations($groups)
    {
        $zip_name = @tempnam('Translations_' . time(), 'zip'); // Zip name
        $this->zipExporting = new ZipArchive();
        $this->zipExporting->open($zip_name, ZipArchive::OVERWRITE);

        if (!is_array($groups)) {
            if (!$groups || $groups === '*') {
                $groups = $this->translatorRepository->findFilledGroups();
                foreach ($groups as $group) {
                    // Stuff with content
                    $this->exportTranslations($group->group);
                }
            } else {
                // Stuff with content
                $this->exportTranslations($groups);
            }
        } else {
            foreach ($groups as $group) {
                // Stuff with content
                $this->exportTranslations($group);
            }
        }

        $this->zipExporting->close();
        $this->zipExporting = null;

        return $zip_name;
    }

    public
    function cleanTranslations()
    {
        $this->translation->query()->whereNull('value')->delete();
    }

    public
    function truncateTranslations($group = null)
    {
        if ($group === '*' || $group === null) {
            $this->translation->query()->truncate();
        } else {
            $this->translatorRepository->deleteTranslationByGroup($group);
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

    protected
    function getLostDotTranslation($translations, $tree)
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

    /**
     * @return true if new json keys take primary locale translation value
     */
    public function isNewJsonKeyFromPrimaryLocale()
    {
        if (!isset($this->newJsonKeyFromPrimaryLocale)) {
            $this->newJsonKeyFromPrimaryLocale = $this->config('new-json-keys-primary-locale', true);
        }
        return $this->newJsonKeyFromPrimaryLocale;
    }

    public function getPrimaryLocale()
    {
        if (!isset($this->primaryLocale)) {
            $this->primaryLocale = $this->config($this->package . '.primary_locale', 'en');
        }
        return $this->primaryLocale;
    }

    public function setPrimaryLocale($primaryLocale)
    {
        $this->primaryLocale = $primaryLocale;
    }
}
