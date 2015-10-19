<?php namespace Vsch\TranslationManager;

use Illuminate\Routing\Controller as BaseController;
use Vsch\TranslationManager\Models\Translation;
use Vsch\UserPrivilegeMapper\Facade\Privilege as UserCan;

// Laravel 4
//include_once(__DIR__ . '/../../../scripts/finediff.php');

// Laravel 5
include_once(__DIR__ . '/Support/finediff.php');

class Controller extends BaseController
{
    /** @var \Vsch\TranslationManager\Manager */
    protected $manager;
    private $cookiePrefix;
    protected $sqltraces;
    protected $logSql;
    private $primaryLocale;
    private $translatingLocale;
    private $displayLocales;
    private $showUsageInfo;
    private $locales;
    private $transFilters;

    const COOKIE_LANG_LOCALE = 'lang';
    const COOKIE_TRANS_LOCALE = 'trans';
    const COOKIE_PRIM_LOCALE = 'prim';
    const COOKIE_DISP_LOCALES = 'disp';
    const COOKIE_SHOW_USAGE = 'show-usage';
    const COOKIE_CONNECTION_NAME = 'connection-name';
    const COOKIE_TRANS_FILTERS = 'trans-filters';

    protected $connectionList;

    protected
    function getConnection()
    {
        return $this->manager->getConnection();
    }

    public
    function getConnectionName()
    {
        return $this->manager->getConnectionName();
    }

    public
    function setConnectionName($connection)
    {
        if (!array_key_exists($connection, $this->connectionList)) $connection = '';
        \Cookie::queue($this->cookieName(self::COOKIE_CONNECTION_NAME), $connection, 60 * 24 * 365 * 1);
        $this->manager->setConnectionName($connection);
    }

    protected
    function getTranslation()
    {
        return $this->manager->getTranslation();
    }

    public
    function __construct()
    {
        $this->package = \Vsch\TranslationManager\ManagerServiceProvider::PACKAGE;
        $this->packagePrefix = $this->package . '::';
        $this->manager = \App::make($this->package);

        $this->connectionList = [];
        $this->connectionList[''] = 'default';
        $connections = $this->manager->config(Manager::DB_CONNECTIONS_KEY);
        if ($connections && array_key_exists(\App::environment(), $connections)) {
            foreach ($connections[\App::environment()] as $key => $value) {
                if (array_key_exists('description', $value)) {
                    $this->connectionList[$key] = $value['description'];
                } else {
                    $this->connectionList[$key] = $key;
                }
            }
        }

        $this->cookiePrefix = $this->manager->config('persistent_prefix', 'K9N6YPi9WHwKp6E3jGbx');

        $connectionName = \Cookie::has($this->cookieName(self::COOKIE_CONNECTION_NAME)) ? \Cookie::get($this->cookieName(self::COOKIE_CONNECTION_NAME)) : '';
        $this->setConnectionName($connectionName);

        $locale = \Cookie::get($this->cookieName(self::COOKIE_LANG_LOCALE), \Lang::getLocale());
        \App::setLocale($locale);
        $this->primaryLocale = \Cookie::get($this->cookieName(self::COOKIE_PRIM_LOCALE), $this->manager->config('primary_locale', 'en'));

        $this->locales = $this->loadLocales();

        $this->translatingLocale = \Cookie::get($this->cookieName(self::COOKIE_TRANS_LOCALE));
        $this->showUsageInfo = \Cookie::get($this->cookieName(self::COOKIE_SHOW_USAGE));
        $this->transFilters = \Cookie::get($this->cookieName(self::COOKIE_TRANS_FILTERS), ['filter' => 'show-all', 'regex' => '']);

        if (!$this->translatingLocale || ($this->translatingLocale === $this->primaryLocale && count($this->locales) > 1)) {
            $this->translatingLocale = count($this->locales) > 1 ? $this->locales[1] : $this->locales[0];
            \Cookie::queue($this->cookieName(self::COOKIE_TRANS_LOCALE), $this->translatingLocale, 60 * 24 * 365 * 1);
        }

        $this->displayLocales = \Cookie::has($this->cookieName(self::COOKIE_DISP_LOCALES)) ? \Cookie::get($this->cookieName(self::COOKIE_DISP_LOCALES)) : implode(',', array_slice($this->locales, 0, 5));
        $this->displayLocales .= implode(',', array_flatten(array_unique(explode(',', ($this->displayLocales ? ',' : '') . $this->primaryLocale . ',' . $this->translatingLocale))));

        //$this->sqltraces = [];
        //$this->logSql = 0;
        //
        //$thisController = $this;
        //\Event::listen('illuminate.query', function ($query, $bindings, $time, $name) use ($thisController)
        //{
        //    if ($thisController->logSql)
        //    {
        //        $thisController->sqltraces[] = ['query' => $query, 'bindings' => $bindings, 'time' => $time];
        //    }
        //});
    }

    public
    function cookieName($cookie)
    {
        return $this->cookiePrefix . $cookie;
    }

    /**
     * @return mixed
     */
    public static
    function active($url)
    {
        $url = url($url, null, false);
        $url = str_replace('https:', 'http:', $url);
        $req = str_replace('https:', 'http:', \Request::url());
        $ret = ($pos = strpos($req, $url)) === 0 && (strlen($req) === strlen($url) || substr($req, strlen($url), 1) === '?' || substr($req, strlen($url), 1) === '#');
        return $ret;
    }

    public
    function getIndex($group = null)
    {
        $locales = $this->locales;
        $currentLocale = \Lang::getLocale();
        $primaryLocale = $this->primaryLocale;
        $translatingLocale = $this->translatingLocale;

        $groups = array('' => noEditTrans($this->packagePrefix . 'messages.choose-group')) + $this->manager->getGroupList();

        if ($group != null && !array_key_exists($group, $groups)) {
            return \Redirect::action(ManagerServiceProvider::CONTROLLER_PREFIX . get_class($this) . '@getIndex');
        }

        $numChanged = $this->getTranslation()->where('group', $group)->where('status', Translation::STATUS_CHANGED)->count();

        // to allow proper handling of nested directory structure we need to copy the keys for the group for all missing
        // translations, otherwise we don't know what the group and key looks like.
        //$allTranslations = $this->getTranslation()->where('group', $group)->orderBy('key', 'asc')->get();
        $displayWhere = $this->displayLocales ? ' AND locale IN (\'' . implode("','", explode(',', $this->displayLocales)) . "')" : '';
        $allTranslations = $this->getTranslation()->hydrateRaw($sql = <<<SQL
SELECT * FROM ltm_translations WHERE `group` = ? $displayWhere
UNION ALL
SELECT DISTINCT
    NULL id,
    NULL status,
    locale,
    `group`,
    `key`,
    NULL value,
    NULL created_at,
    NULL updated_at,
    NULL source,
    NULL saved_value,
    NULL is_deleted,
    NULL was_used
FROM
(SELECT * FROM (SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lcs
    CROSS JOIN (SELECT DISTINCT `group`, `key` FROM ltm_translations WHERE `group` = ? $displayWhere) grp) m
WHERE NOT EXISTS(SELECT * FROM ltm_translations t WHERE t.locale = m.locale AND t.`group` = m.`group` AND t.`key` = m.`key`)
ORDER BY `key` ASC
SQL
            , [$group, $group], $this->getConnectionName());

        $numTranslations = count($allTranslations);
        $translations = array();
        foreach ($allTranslations as $translation) {
            $translations[$translation->key][$translation->locale] = $translation;
        }

        $stats = $this->getConnection()->select(<<<SQL
SELECT (mx.total_keys - lcs.total) missing, lcs.changed, lcs.deleted, lcs.locale, lcs.`group`
FROM
    (SELECT sum(total) total, sum(changed) changed, sum(deleted) deleted, `group`, locale
     FROM
         (SELECT count(value) total, sum(status) changed, sum(is_deleted) deleted, `group`, locale FROM ltm_translations lt WHERE 1=1 $displayWhere GROUP BY `group`, locale
          UNION ALL
          SELECT DISTINCT 0, 0, 0, `group`, locale FROM (SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lc
              CROSS JOIN (SELECT DISTINCT `group` FROM ltm_translations) lg) a
     GROUP BY `group`, locale) lcs
    JOIN (SELECT count(DISTINCT `key`) total_keys, `group` FROM ltm_translations WHERE 1=1 $displayWhere GROUP BY `group`) mx
        ON lcs.`group` = mx.`group`
WHERE lcs.total < mx.total_keys OR lcs.changed > 0 OR lcs.deleted > 0
SQL
        );

        // returned result set lists mising, changed, group, locale
        $summary = [];
        foreach ($stats as $stat) {
            if (!isset($summary[$stat->group])) {
                $item = $summary[$stat->group] = new \stdClass();
                $item->missing = '';
                $item->changed = '';
                $item->deleted = '';
                $item->group = $stat->group;
            }

            $item = $summary[$stat->group];
            if ($stat->missing) $item->missing .= $stat->locale . ":" . $stat->missing . " ";
            if ($stat->changed) $item->changed .= $stat->locale . ":" . $stat->changed . " ";
            if ($stat->deleted) $item->deleted .= $stat->locale . ":" . $stat->deleted . " ";
        }

        $mismatches = null;
        $mismatchEnabled = $this->manager->config('mismatch_enabled');

        if ($mismatchEnabled) {
            // get mismatches
            $mismatches = $this->getConnection()->select(<<<SQL
SELECT DISTINCT lt.*, ft.ru, ft.en
FROM (SELECT * FROM ltm_translations WHERE 1=1 $displayWhere) lt
    JOIN
    (SELECT DISTINCT mt.`key`, BINARY mt.ru ru, BINARY mt.en en
     FROM (SELECT lt.`group`, lt.`key`, group_concat(CASE lt.locale WHEN '$primaryLocale' THEN VALUE ELSE NULL END) en, group_concat(CASE lt.locale WHEN '$translatingLocale' THEN VALUE ELSE NULL END) ru
           FROM (SELECT value, `group`, `key`, locale FROM ltm_translations WHERE 1=1 $displayWhere
                 UNION ALL
                 SELECT NULL, `group`, `key`, locale FROM ((SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lc
                     CROSS JOIN (SELECT DISTINCT `group`, `key` FROM ltm_translations WHERE 1=1 $displayWhere) lg)
                ) lt
           GROUP BY `group`, `key`) mt
         JOIN (SELECT lt.`group`, lt.`key`, group_concat(CASE lt.locale WHEN '$primaryLocale' THEN VALUE ELSE NULL END) en, group_concat(CASE lt.locale WHEN '$translatingLocale' THEN VALUE ELSE NULL END) ru
               FROM (SELECT value, `group`, `key`, locale FROM ltm_translations WHERE 1=1 $displayWhere
                     UNION ALL
                     SELECT NULL, `group`, `key`, locale FROM ((SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lc
                         CROSS JOIN (SELECT DISTINCT `group`, `key` FROM ltm_translations WHERE 1=1 $displayWhere) lg)
                    ) lt
               GROUP BY `group`, `key`) ht ON mt.`key` = ht.`key`
     WHERE (mt.ru NOT LIKE BINARY ht.ru AND mt.en LIKE BINARY ht.en) OR (mt.ru LIKE BINARY ht.ru AND mt.en NOT LIKE BINARY ht.en)
    ) ft
        ON (lt.locale = '$translatingLocale' AND lt.value LIKE BINARY ft.ru) AND lt.`key` = ft.key
ORDER BY `key`, `group`
SQL
            );

            $key = '';
            $rus = [];
            $ens = [];
            $rubases = [];      // by key
            $enbases = [];    // by key
            $extra = new \stdClass();
            $extra->key = '';
            $mismatches[] = $extra;
            foreach ($mismatches as $mismatch) {
                if ($mismatch->key !== $key) {
                    if ($key) {
                        // process diff for key
                        $txtru = '';
                        $txten = '';
                        if (count($ens) > 1) {
                            $maxen = 0;
                            foreach ($ens as $en => $cnt) {
                                if ($maxen < $cnt) {
                                    $maxen = $cnt;
                                    $txten = $en;
                                }
                            }
                            $enbases[$key] = $txten;
                        } else {
                            $txten = array_keys($ens)[0];
                            $enbases[$key] = $txten;
                        }
                        if (count($rus) > 1) {
                            $maxru = 0;
                            foreach ($rus as $ru => $cnt) {
                                if ($maxru < $cnt) {
                                    $maxru = $cnt;
                                    $txtru = $ru;
                                }
                            }
                            $rubases[$key] = $txtru;
                        } else {
                            $txtru = array_keys($rus)[0];
                            $rubases[$key] = $txtru;
                        }
                    }
                    $key = $mismatch->key;
                    $rus = [];
                    $ens = [];
                }

                if ($mismatch->key === '') break;

                if (!isset($ens[$mismatch->en])) $ens[$mismatch->en] = 1;
                else $ens[$mismatch->en]++;
                if (!isset($rus[$mismatch->ru])) $rus[$mismatch->ru] = 1;
                else $rus[$mismatch->ru]++;
            }

            array_splice($mismatches, count($mismatches) - 1, 1);

            foreach ($mismatches as $mismatch) {
                $mismatch->en_value = $mismatch->ru;
                $mismatch->en = mb_renderDiffHtml($enbases[$mismatch->key], $mismatch->en);
                $mismatch->ru_value = $mismatch->ru;
                $mismatch->ru = mb_renderDiffHtml($rubases[$mismatch->key], $mismatch->ru);
            }
        }

        // returned result set lists group key ru, en columns for the locale translations, ru has different values for same values in en
        $displayLocales = array_intersect($locales, explode(',', $this->displayLocales));

        // need to put display locales first in the $locales list
        $locales = array_merge($displayLocales, array_diff($locales, $displayLocales));

        $displayLocales = array_combine($displayLocales, $displayLocales);

        $show_usage_enabled = $this->manager->config('log_key_usage_info', false);

        return \View::make($this->packagePrefix . 'index')
            ->with('controller', ManagerServiceProvider::CONTROLLER_PREFIX . get_class($this))
            ->with('package', $this->package)
            ->with('public_prefix', ManagerServiceProvider::PUBLIC_PREFIX)
            ->with('translations', $translations)
            ->with('yandex_key', !!$this->manager->config('yandex_translator_key'))
            ->with('locales', $locales)
            ->with('primaryLocale', $primaryLocale)
            ->with('currentLocale', $currentLocale)
            ->with('translatingLocale', $translatingLocale)
            ->with('displayLocales', $displayLocales)
            ->with('groups', $groups)
            ->with('group', $group)
            ->with('numTranslations', $numTranslations)
            ->with('numChanged', $numChanged)
            ->with('adminEnabled', $this->manager->config('admin_enabled') && UserCan::admin_translations())
            ->with('mismatchEnabled', $mismatchEnabled)
            ->with('stats', $summary)
            ->with('mismatches', $mismatches)
            ->with('show_usage', $this->showUsageInfo && $show_usage_enabled)
            ->with('usage_info_enabled', $show_usage_enabled)
            ->with('connection_list', $this->connectionList)
            ->with('transFilters', $this->transFilters)
            ->with('connection_name', $this->getConnectionName());
    }

    public
    function getSearch()
    {
        $q = \Input::get('q');

        if ($q === '') $translations = [];
        else {
            $displayWhere = $this->displayLocales ? ' AND locale IN (\'' . implode("','", explode(',', $this->displayLocales)) . "')" : '';

            if (strpos($q, '%') === false) $q = "%$q%";

            //$translations = $this->getTranslation()->where('key', 'like', "%$q%")->orWhere('value', 'like', "%$q%")->orderBy('group', 'asc')->orderBy('key', 'asc')->get();

            // need to fill-in missing locale's that match the key
            $translations = $this->getConnection()->select(<<<SQL
SELECT * FROM ltm_translations rt WHERE (`key` LIKE ? OR value LIKE ?) $displayWhere
UNION ALL
SELECT NULL id, 0 status, lt.locale, kt.`group`, kt.`key`, NULL value, NULL created_at, NULL updated_at, NULL source, NULL saved_value, NULL is_deleted, NULL was_used
FROM (SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lt
    CROSS JOIN (SELECT DISTINCT `key`, `group` FROM ltm_translations WHERE 1=1 $displayWhere) kt
WHERE NOT exists(SELECT * FROM ltm_translations tr WHERE tr.`key` = kt.`key` AND tr.`group` = kt.`group` AND tr.locale = lt.locale)
      AND `key` LIKE ?
ORDER BY `key`, `group`, locale
SQL
                , [$q, $q, $q,]);
        }

        $numTranslations = count($translations);

        return \View::make($this->packagePrefix . 'search')
            ->with('controller', ManagerServiceProvider::CONTROLLER_PREFIX . get_class($this))
            ->with('package', $this->package)
            ->with('translations', $translations)
            ->with('numTranslations', $numTranslations);
    }

    public
    function getView($group = null)
    {
        return $this->getIndex($group);
    }

    protected
    function loadLocales()
    {
        //Set the default locale as the first one.
        $currentLocale = \Config::get('app.locale');
        $primaryLocale = $this->primaryLocale;
        $translatingLocale = \Cookie::get($this->cookieName(self::COOKIE_TRANS_LOCALE), $currentLocale);

        $locales = ManagerServiceProvider::getLists($this->getTranslation()->groupBy('locale')->lists('locale')) ?: [];

        // limit the locale list to what is in the config
        $configShowLocales = $this->manager->config(Manager::SHOW_LOCALES_KEY, []);
        if ($configShowLocales) {
            if (!is_array($configShowLocales)) $configShowLocales = array($configShowLocales);
            $locales = array_intersect($locales, $configShowLocales);
        }

        $configLocales = $this->manager->config(Manager::ADDITIONAL_LOCALES_KEY, []);
        if (!is_array($configLocales)) $configLocales = array($configLocales);

        $locales = array_merge(array($primaryLocale, $translatingLocale, $currentLocale), $configLocales, $locales);
        return array_flatten(array_unique($locales));
    }

    public
    function postAdd($group)
    {
        $keys = explode("\n", trim(\Input::get('keys')));
        $suffixes = explode("\n", trim(\Input::get('suffixes')));
        $group = explode('::', $group, 2);
        $namespace = '*';
        if (count($group) > 1) $namespace = array_shift($group);
        $group = $group[0];

        foreach ($keys as $key) {
            $key = trim($key);
            if ($group && $key) {
                if ($suffixes) {
                    foreach ($suffixes as $suffix) {
                        $this->manager->missingKey($namespace, $group, $key . trim($suffix));
                    }
                } else {
                    $this->manager->missingKey($namespace, $group, $key);
                }
            }
        }
        //Session::flash('_old_data', \Input::except('keys'));
        return \Redirect::back()->withInput();
    }

    public
    function postDeleteSuffixedKeys($group)
    {
        if (!in_array($group, $this->manager->config(Manager::EXCLUDE_GROUPS_KEY)) && $this->manager->config('admin_enabled')) {
            $keys = explode("\n", trim(\Input::get('keys')));
            $suffixes = explode("\n", trim(\Input::get('suffixes')));

            if (count($suffixes) === 1 && $suffixes[0] === '') $suffixes = [];

            foreach ($keys as $key) {
                $key = trim($key);
                if ($group && $key) {
                    if ($suffixes) {
                        foreach ($suffixes as $suffix) {
                            //$this->getTranslation()->where('group', $group)->where('key', $key . trim($suffix))->delete();
                            $result = $this->getConnection()->update(<<<SQL
UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND `group` = ? AND `key` = ?
SQL
                                , [$group, $key . trim($suffix)]);
                        }
                    } else {
                        //$this->getTranslation()->where('group', $group)->where('key', $key)->delete();
                        $result = $this->getConnection()->update(<<<SQL
UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND `group` = ? AND `key` = ?
SQL
                            , [$group, $key]);
                    }
                }
            }
            return \Redirect::back()->withInput();
        }
        return \Redirect::back()->withInput();
    }

    public
    function postEdit($group)
    {
        if (!in_array($group, $this->manager->config(Manager::EXCLUDE_GROUPS_KEY))) {
            $name = \Input::get('name');
            $value = \Input::get('value');

            list($locale, $key) = explode('|', $name, 2);
            $translation = $this->getTranslation()->firstOrNew(array(
                'locale' => $locale,
                'group' => $group,
                'key' => $key,
            ));
            // strip off trailing spaces and eol's and &nbsps; that seem to be added when multiple spaces are entered in the x-editable textarea
            $value = trim(str_replace("\xc2\xa0", ' ', $value));
            $value = $value !== '' ? $value : null;

            $translation->value = $value;
            $translation->status = (($translation->isDirty() && $value != $translation->saved_value) ? Translation::STATUS_CHANGED : Translation::STATUS_SAVED);
            $translation->save();
        }
        return array('status' => 'ok');
    }

    public
    function postDelete($group, $key)
    {
        if (!in_array($group, $this->manager->config(Manager::EXCLUDE_GROUPS_KEY)) && $this->manager->config('admin_enabled')) {
            //$this->getTranslation()->where('group', $group)->where('key', $key)->delete();
            $result = $this->getConnection()->update(<<<SQL
UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND `group` = ? AND `key` = ?
SQL
                , [$group, $key]);
        }
        return array('status' => 'ok');
    }

    public
    function postUndelete($group, $key)
    {
        if (!in_array($group, $this->manager->config(Manager::EXCLUDE_GROUPS_KEY)) && $this->manager->config('admin_enabled')) {
            //$this->getTranslation()->where('group', $group)->where('key', $key)->delete();
            $result = $this->getConnection()->update(<<<SQL
UPDATE ltm_translations SET is_deleted = 0 WHERE is_deleted = 1 AND `group` = ? AND `key` = ?
SQL
                , [$group, $key]);
        }
        return array('status' => 'ok');
    }

    protected
    static
    function keyGroup($group, $key)
    {
        $prefix = '';
        $gkey = $key;

        if (starts_with($key, 'vnd:') || starts_with($key, 'wbn:')) {
            // these have vendor with . afterwards in the group
            $parts = explode('.', $key, 2);

            if (count($parts) === 2) {
                $prefix = $parts[0] . '.';
                $gkey = $parts[1];
            }
        }

        $parts = explode('.', $gkey, 2);

        if (count($parts) === 1) {
            $tgroup = $group;
            $tkey = $gkey;
        } else {
            $tgroup = $parts[0];
            $tkey = $parts[1];
        }

        return [$prefix . $tgroup, $tkey];
    }

    protected
    function keyOp($group, $op = 'preview')
    {
        $errors = [];
        $keymap = [];
        $this->logSql = 1;
        $this->sqltraces = [];

        if (!in_array($group, $this->manager->config(Manager::EXCLUDE_GROUPS_KEY)) && $this->manager->config('admin_enabled')) {
            $srckeys = explode("\n", trim(\Input::get('srckeys')));
            $dstkeys = explode("\n", trim(\Input::get('dstkeys')));

            array_walk($srckeys, function (&$val, $key) use (&$srckeys) {
                $val = trim($val);
                if ($val === '') unset($srckeys[$key]);
            });

            array_walk($dstkeys, function (&$val, $key) use (&$dstkeys) {
                $val = trim($val);
                if ($val === '') unset($dstkeys[$key]);
            });

            if (!$group) {
                $errors[] = trans($this->packagePrefix . 'messages.keyop-need-group');
            } elseif (count($srckeys) !== count($dstkeys) && ($op === 'copy' || $op === 'move' || count($dstkeys))) {
                $errors[] = trans($this->packagePrefix . 'messages.keyop-count-mustmatch');
            } elseif (!count($srckeys)) {
                $errors[] = trans($this->packagePrefix . 'messages.keyop-need-keys');
            } else {
                if (!count($dstkeys)) $dstkeys = array_fill(0, count($srckeys), null);

                $keys = array_combine($srckeys, $dstkeys);
                $hadErrors = false;

                foreach ($keys as $src => $dst) {
                    $keyerrors = [];

                    if ($dst !== null) {

                        if ((substr($src, 0, 1) === '*') !== (substr($dst, 0, 1) === '*')) {
                            $keyerrors[] = trans($this->packagePrefix . 'messages.keyop-wildcard-mustmatch');
                        }

                        if ((substr($src, -1, 1) === '*') !== (substr($dst, -1, 1) === '*')) {
                            $keyerrors[] = trans($this->packagePrefix . 'messages.keyop-wildcard-mustmatch');
                        }

                        if ((substr($src, 0, 1) === '*') && (substr($src, -1, 1) === '*')) {
                            $keyerrors[] = trans($this->packagePrefix . 'messages.keyop-wildcard-once');
                        }
                    }

                    if (!empty($keyerrors)) {
                        $hadErrors = true;
                        $keymap[$src] = ['errors' => $keyerrors, 'dst' => $dst,];
                        continue;
                    }

                    list($srcgrp, $srckey) = self::keyGroup($group, $src);
                    list($dstgrp, $dstkey) = $dst === null ? [null, null] : self::keyGroup($group, $dst);

                    if ((substr($src, 0, 1) === '*')) {
                        if ($dst === null) {
                            $rows = $this->getConnection()->select($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ?
ORDER BY locale, `key`

SQL
                                , [
                                    $srcgrp,
                                    '%' . mb_substr($srckey, 1),
                                ]);
                        } else {
                            $rows = $this->getConnection()->select($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, CONCAT(SUBSTR(`key`, 1, CHAR_LENGTH(`key`)-?), ?) dst, ? dstgrp FROM ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ?
AND NOT exists(SELECT * FROM ltm_translations t2 WHERE t2.value IS NOT NULL AND t2.`group` = ? AND t1.locale = t2.locale
                AND t2.`key` LIKE BINARY CONCAT(SUBSTR(t1.`key`, 1, CHAR_LENGTH(t1.`key`)-?), ?))
ORDER BY locale, `key`

SQL
                                , [
                                    mb_strlen($srckey) - 1,
                                    mb_substr($dstkey, 1),
                                    $dstgrp,
                                    $srcgrp,
                                    '%' . mb_substr($srckey, 1),
                                    $dstgrp,
                                    mb_strlen($srckey) - 1,
                                    mb_substr($dstkey, 1)
                                ]);
                        }
                    } elseif ((substr($src, -1, 1) === '*')) {
                        if ($dst === null) {
                            $rows = $this->getConnection()->select($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ?
ORDER BY locale, `key`

SQL
                                , [
                                    $srcgrp,
                                    mb_substr($srckey, 0, -1) . '%',
                                ]);
                        } else {
                            $rows = $this->getConnection()->select($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, CONCAT(?, SUBSTR(`key`, ?+1, CHAR_LENGTH(`key`)-?)) dst, ? dstgrp FROM ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ?
AND NOT exists(SELECT * FROM ltm_translations t2 WHERE t2.value IS NOT NULL AND t2.`group` = ? AND t1.locale = t2.locale
                AND t2.`key` LIKE BINARY CONCAT(?, SUBSTR(t1.`key`, ?+1, CHAR_LENGTH(t1.`key`)-?)))
ORDER BY locale, `key`

SQL
                                , [
                                    mb_substr($dstkey, 0, -1),
                                    mb_strlen($srckey) - 1,
                                    mb_strlen($srckey) - 1,
                                    $dstgrp,
                                    $srcgrp,
                                    mb_substr($srckey, 0, -1) . '%',
                                    $dstgrp,
                                    mb_substr($dstkey, 0, -1),
                                    mb_strlen($srckey) - 1,
                                    mb_strlen($srckey) - 1
                                ]);
                        }
                    } else {
                        if ($dst === null) {
                            $rows = $this->getConnection()->select($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ?
ORDER BY locale, `key`

SQL
                                , [
                                    $srcgrp,
                                    $srckey,
                                ]);
                        } else {
                            $rows = $this->getConnection()->select($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, ? dst, ? dstgrp FROM ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ?
AND NOT exists(SELECT * FROM ltm_translations t2 WHERE t2.value IS NOT NULL AND t2.`group` = ? AND t1.locale = t2.locale AND t2.`key` LIKE BINARY ?)
ORDER BY locale, `key`

SQL
                                , [
                                    $dstkey,
                                    $dstgrp,
                                    $srcgrp,
                                    $srckey,
                                    $dstgrp,
                                    $dstkey,
                                ]);
                        }
                    }

                    $keymap[$src] = ['dst' => $dst, 'rows' => $rows];
                }

                if (!$hadErrors && ($op === 'copy' || $op === 'move' || $op === 'delete')) {
                    foreach ($keys as $src => $dst) {
                        $rows = $keymap[$src]['rows'];

                        $rowids = array_reduce($rows, function ($carry, $row) {
                            return $carry . ',' . $row->id;
                        }, '');
                        $rowids = substr($rowids, 1);

                        list($srcgrp, $srckey) = self::keyGroup($group, $src);
                        if ($op === 'move') {
                            foreach ($rows as $row) {
                                list($dstgrp, $dstkey) = self::keyGroup($row->dstgrp, $row->dst);
                                $to_delete = $this->getConnection()->select(<<<SQL
SELECT GROUP_CONCAT(id SEPARATOR ',') ids FROM ltm_translations tr
WHERE `group` = ? AND `key` = ? AND locale = ? AND id NOT IN ($rowids)

SQL
                                    , [$dstgrp, $dstkey, $row->locale]);

                                if (!empty($to_delete)) {
                                    $to_delete = $to_delete[0]->ids;
                                    if ($to_delete) {
                                        //$this->getConnection()->update("UPDATE ltm_translations SET is_deleted = 1 WHERE id IN ($to_delete)");
                                        // have to delete right away, we will be bringing another key here
                                        // TODO: copy value to new key's saved value
                                        $this->getConnection()->delete("DELETE FROM ltm_translations WHERE id IN ($to_delete)");
                                    }
                                }

                                $this->getConnection()->update("UPDATE ltm_translations SET `group` = ?, `key` = ?, status = 1 WHERE id = ?"
                                    , [$dstgrp, $dstkey, $row->id]);
                            }
                        } elseif ($op === 'delete') {
                            //$this->getConnection()->delete("DELETE FROM ltm_translations WHERE id IN ($rowids)");
                            $this->getConnection()->update("UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND id IN ($rowids)");
                        } elseif ($op === 'copy') {
                            // TODO: split operation into update and insert so that conflicting keys get new values instead of being replaced
                            foreach ($rows as $row) {
                                list($dstgrp, $dstkey) = self::keyGroup($row->dstgrp, $row->dst);
                                $to_delete = $this->getConnection()->select(<<<SQL
SELECT GROUP_CONCAT(id SEPARATOR ',') ids FROM ltm_translations tr
WHERE `group` = ? AND `key` = ? AND locale = ? AND id NOT IN ($rowids)

SQL
                                    , [$dstgrp, $dstkey, $row->locale]);

                                if (!empty($to_delete)) {
                                    $to_delete = $to_delete[0]->ids;
                                    if ($to_delete) {
                                        //$this->getConnection()->update("UPDATE ltm_translations SET is_deleted = 1 WHERE id IN ($to_delete)");
                                        $this->getConnection()->delete("DELETE FROM ltm_translations WHERE id IN ($to_delete)");
                                    }
                                }

                                $this->getConnection()->insert($sql = <<<SQL
INSERT INTO ltm_translations
SELECT
    NULL id,
    1 status,
    locale,
    ? `group`,
    ? `key`,
    value,
    sysdate() created_at,
    sysdate() updated_at,
    source,
    saved_value,
    is_deleted,
    was_used
FROM ltm_translations t1
WHERE id = ?

SQL
                                    , [$dstgrp, $dstkey, $row->id]);
                            }
                        }
                    }
                }
            }
        } else {
            $errors[] = trans($this->packagePrefix . 'messages.keyops-not-authorized');
        }

        $this->logSql = 0;
        return \View::make($this->packagePrefix . 'keyop')
            ->with('controller', ManagerServiceProvider::CONTROLLER_PREFIX . get_class($this))
            ->with('package', $this->package)
            ->with('errors', $errors)
            ->with('keymap', $keymap)
            ->with('op', $op)
            ->with('group', $group);
    }

    public
    function getKeyop($group, $op = 'preview')
    {
        return $this->keyOp($group, $op);
    }

    public
    function postCopyKeys($group)
    {
        return $this->keyOp($group, 'copy');
    }

    public
    function postMoveKeys($group)
    {
        return $this->keyOp($group, 'move');
    }

    public
    function postDeleteKeys($group)
    {
        return $this->keyOp($group, 'delete');
    }

    public
    function postPreviewKeys($group)
    {
        return $this->keyOp($group, 'preview');
    }

    public
    function postImport($group)
    {
        $replace = \Input::get('replace', false);
        if ($replace == 2) $this->manager->truncateTranslations($group);
        //$counter = $this->manager->importTranslations($group !== '*' ? true : $replace, false, $group === '*' ? null : [$group]);
        $counter = $this->manager->importTranslations(($group !== '*' ? !$this->manager->inDatabasePublishing() : $replace), $group === '*' ? null : [$group]);

        return \Response::json(array('status' => 'ok', 'counter' => $counter));
    }

    public
    function getImport()
    {
        $replace = \Input::get('replace', false);
        $group = \Input::get('group', '*');
        //$counter = $this->manager->importTranslations(($group !== '*' ? !$this->manager->inDatabasePublishing() : $replace), false, $group === '*' ? null : [$group]);
        $counter = $this->manager->importTranslations(($group !== '*' ? !$this->manager->inDatabasePublishing() : $replace), $group === '*' ? null : [$group]);

        return \Response::json(array('status' => 'ok', 'counter' => $counter));
    }

    public
    function postFind()
    {
        $numFound = $this->manager->findTranslations();

        return \Response::json(array('status' => 'ok', 'counter' => (int)$numFound));
    }

    public
    function postDeleteAll($group)
    {
        $this->manager->truncateTranslations($group);

        return \Response::json(array('status' => 'ok', 'counter' => (int)0));
    }

    public
    function getPublish($group)
    {
        $this->manager->exportTranslations($group);

        return \Response::json(array('status' => 'ok'));
    }

    public
    function postPublish($group)
    {
        $this->manager->exportTranslations($group);
        $errors = $this->manager->errors();

        return \Response::json(array('status' => $errors ? 'errors' : 'ok', 'errors' => $errors));
    }

    public
    function getToggleInPlaceEdit()
    {
        inPlaceEditing(!inPlaceEditing());
        if (\App::runningUnitTests()) return \Redirect::to('/');
        return !is_null(\Request::header('referer')) ? \Redirect::back() : \Redirect::to('/');
    }

    public
    function getInterfaceLocale()
    {
        $locale = \Input::get("l");
        $translating = \Input::get("t");
        $primary = \Input::get("p");
        $connection = \Input::get("c");
        $displayLocales = \Input::get("d");
        $display = implode(',', $displayLocales ?: []);

        \App::setLocale($locale);
        \Cookie::queue($this->cookieName(self::COOKIE_LANG_LOCALE), $locale, 60 * 24 * 365 * 1);
        \Cookie::queue($this->cookieName(self::COOKIE_TRANS_LOCALE), $translating, 60 * 24 * 365 * 1);
        \Cookie::queue($this->cookieName(self::COOKIE_PRIM_LOCALE), $primary, 60 * 24 * 365 * 1);
        \Cookie::queue($this->cookieName(self::COOKIE_DISP_LOCALES), $display, 60 * 24 * 365 * 1);

        $this->setConnectionName($connection);

        if (\App::runningUnitTests()) return \Redirect::to('/');
        return !is_null(\Request::header('referer')) ? \Redirect::back() : \Redirect::to('/');
    }

    public
    function getUsageInfo()
    {
        $group = \Input::get("group");
        $reset = \Input::get("reset-usage-info");
        $show = \Input::get("show-usage-info");

        // need to store this so that it can be displayed again
        \Cookie::queue($this->cookieName(self::COOKIE_SHOW_USAGE), $show, 60 * 24 * 365 * 1);

        if ($reset) {
            // TODO: add show usage info to view variables so that a class can be added to keys that have no usage info
            // need to clear the usage information
            $this->manager->clearUsageCache(true, $group);
        }

        if (\App::runningUnitTests()) return \Redirect::to('/');
        return !is_null(\Request::header('referer')) ? \Redirect::back() : \Redirect::to('/');
    }

    public
    function getTransFilters()
    {
        $filter = null;
        $regex = null;

        if (\Input::has('filter')) {
            $filter = \Input::get("filter");
            $this->transFilters['filter'] = $filter;
        }

        $regex = \Input::get("regex", null);
        if ($regex !== null) {
            $this->transFilters['regex'] = $regex;
        }

        \Cookie::queue($this->cookieName(self::COOKIE_TRANS_FILTERS), $this->transFilters, 60 * 24 * 365 * 1);

        if (\Request::wantsJson()) {
            return \Response::json(array(
                'status' => 'ok',
                'transFilters' => $this->transFilters,
            ));
        }

        return !is_null(\Request::header('referer')) ? \Redirect::back() : \Redirect::to('/');
    }

    public
    function getZippedTranslations($group = null)
    {
        $file = $this->manager->zipTranslations($group);

        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: attachment; filename="Translations_' . date('Ymd-His') . '.zip"');
        ob_clean();
        flush();
        readfile($file);
        unlink($file);
    }

    public
    function postYandexKey()
    {
        return \Response::json(array(
            'status' => 'ok',
            'yandex_key' => $this->manager->config('yandex_translator_key', null)
        ));
    }
}
