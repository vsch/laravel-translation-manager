<?php namespace Vsch\TranslationManager;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\URL;
use Vsch\TranslationManager\Models\Translation;
use Vsch\UserPrivilegeMapper\Facade\Privilege as UserCan;

include_once(__DIR__ . '/../../../scripts/finediff.php');

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
    private $locales;

    const COOKIE_LANG_LOCALE = 'lang';
    const COOKIE_TRANS_LOCALE = 'trans';
    const COOKIE_PRIM_LOCALE = 'prim';
    const COOKIE_DISP_LOCALES = 'disp';

    public
    function __construct()
    {
        $this->package = \Vsch\TranslationManager\ManagerServiceProvider::PACKAGE;
        $this->packagePrefix = $this->package . '::';
        $this->manager = App::make($this->package);
        $this->cookiePrefix = $this->manager->getConfig('persistent_prefix', 'K9N6YPi9WHwKp6E3jGbx');
        $locale = Cookie::get($this->cookieName(self::COOKIE_LANG_LOCALE), \Lang::getLocale());
        App::setLocale($locale);
        $this->primaryLocale = Cookie::get($this->cookieName(self::COOKIE_PRIM_LOCALE), $this->manager->getConfig('primary_locale', 'en'));

        $this->locales = $this->loadLocales();
        $this->translatingLocale = Cookie::get($this->cookieName(self::COOKIE_TRANS_LOCALE));
        if (!$this->translatingLocale || ($this->translatingLocale === $this->primaryLocale && count($this->locales) > 1))
        {
            $this->translatingLocale = count($this->locales) > 1 ? $this->locales[1] : $this->locales[0];
            Cookie::queue($this->cookieName(self::COOKIE_TRANS_LOCALE), $this->translatingLocale, 60 * 24 * 365 * 1);
        }

        $this->displayLocales = Cookie::has($this->cookieName(self::COOKIE_DISP_LOCALES)) ? Cookie::get($this->cookieName(self::COOKIE_DISP_LOCALES)) : implode(',', array_slice($this->locales, 0, 5));
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
        $req = str_replace('https:', 'http:', Request::url());
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

        $groups = Translation::groupBy('group');
        $excludedGroups = $this->manager->getConfig('exclude_groups');
        if ($excludedGroups)
        {
            $groups->whereNotIn('group', $excludedGroups);
        }

        $groups = array('' => noEditTrans($this->packagePrefix.'messages.choose-group')) + $groups->lists('group', 'group');
        $numChanged = Translation::where('group', $group)->where('status', Translation::STATUS_CHANGED)->count();

        // to allow proper handling of nested directory structure we need to copy the keys for the group for all missing
        // translations, otherwise we don't know what the group and key looks like.
        //$allTranslations = Translation::where('group', $group)->orderBy('key', 'asc')->get();
        $displayWhere = $this->displayLocales ? ' AND locale IN (\'' . implode("','", explode(',', $this->displayLocales)) . "')" : '';
        $allTranslations = Translation::hydrateRaw($sql = <<<SQL
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
    NULL is_deleted
FROM
(SELECT * FROM (SELECT DISTINCT locale FROM ltm_translations WHERE 1=1 $displayWhere) lcs
    CROSS JOIN (SELECT DISTINCT `group`, `key` FROM ltm_translations WHERE `group` = ? $displayWhere) grp) m
WHERE NOT EXISTS(SELECT * FROM ltm_translations t WHERE t.locale = m.locale AND t.`group` = m.`group` AND t.`key` = m.`key`)
ORDER BY `key` ASC
SQL
            , [$group, $group]);

        if (!count($allTranslations) && $group)
        {
            $pos = strrpos($url = Request::url(), '/index');
            if ($pos !== false)
            {
                $url = substr($url, 0, $pos);
                return Redirect::to($url);
            }
        }

        $numTranslations = count($allTranslations);
        $translations = array();
        foreach ($allTranslations as $translation)
        {
            $translations[$translation->key][$translation->locale] = $translation;
        }

        $stats = DB::select(<<<SQL
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
        foreach ($stats as $stat)
        {
            if (!isset($summary[$stat->group]))
            {
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
        $mismatchEnabled = $this->manager->getConfig('mismatch_enabled');

        if ($mismatchEnabled)
        {
            // get mismatches
            $mismatches = DB::select(<<<SQL
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
            foreach ($mismatches as $mismatch)
            {
                if ($mismatch->key !== $key)
                {
                    if ($key)
                    {
                        // process diff for key
                        $txtru = '';
                        $txten = '';
                        if (count($ens) > 1)
                        {
                            $maxen = 0;
                            foreach ($ens as $en => $cnt)
                            {
                                if ($maxen < $cnt)
                                {
                                    $maxen = $cnt;
                                    $txten = $en;
                                }
                            }
                            $enbases[$key] = $txten;
                        }
                        else
                        {
                            $txten = array_keys($ens)[0];
                            $enbases[$key] = $txten;
                        }
                        if (count($rus) > 1)
                        {
                            $maxru = 0;
                            foreach ($rus as $ru => $cnt)
                            {
                                if ($maxru < $cnt)
                                {
                                    $maxru = $cnt;
                                    $txtru = $ru;
                                }
                            }
                            $rubases[$key] = $txtru;
                        }
                        else
                        {
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

            foreach ($mismatches as $mismatch)
            {
                $mismatch->en_value = $mismatch->ru;
                $mismatch->en = mb_renderDiffHtml($enbases[$mismatch->key], $mismatch->en);
                $mismatch->ru_value = $mismatch->ru;
                $mismatch->ru = mb_renderDiffHtml($rubases[$mismatch->key], $mismatch->ru);
            }
        }

        // returned result set lists group key ru, en columns for the locale translations, ru has different values for same values in en
        $displayLocales = explode(',', $this->displayLocales);
        $displayLocales = array_combine($displayLocales, $displayLocales);

        return \View::make($this->packagePrefix . 'index')
            ->with('controller', get_class($this))
            ->with('package', $this->package)
            ->with('public_prefix', '/packages/vsch/')
            ->with('translations', $translations)
            ->with('yandex_key', !!$this->manager->getConfig('yandex_translator_key'))
            ->with('locales', $locales)
            ->with('primaryLocale', $primaryLocale)
            ->with('currentLocale', $currentLocale)
            ->with('translatingLocale', $translatingLocale)
            ->with('displayLocales', $displayLocales)
            ->with('groups', $groups)
            ->with('group', $group)
            ->with('numTranslations', $numTranslations)
            ->with('numChanged', $numChanged)
            ->with('adminEnabled', $this->manager->getConfig('admin_enabled') && UserCan::admin_translations())
            ->with('mismatchEnabled', $mismatchEnabled)
            ->with('stats', $summary)
            ->with('mismatches', $mismatches);
    }

    public
    function getSearch()
    {
        $q = \Input::get('q');

        if ($q === '') $translations = [];
        else
        {
            $displayWhere = $this->displayLocales ? ' AND locale IN (\'' . implode("','", explode(',', $this->displayLocales)) . "')" : '';

            if (strpos($q, '%') === false) $q = "%$q%";

            //$translations = Translation::where('key', 'like', "%$q%")->orWhere('value', 'like', "%$q%")->orderBy('group', 'asc')->orderBy('key', 'asc')->get();

            // need to fill-in missing locale's that match the key
            $translations = DB::select(<<<SQL
SELECT * FROM ltm_translations rt WHERE (`key` LIKE ? OR value LIKE ?) $displayWhere
UNION ALL
SELECT NULL id, 0 status, lt.locale, kt.`group`, kt.`key`, NULL value, NULL created_at, NULL updated_at, NULL source, NULL saved_value, NULL is_deleted
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
            ->with('controller', get_class($this))
            ->with('package', $this->package)
            ->with('translations', $translations)
            ->with('numTranslations', $numTranslations);
    }

    public
    function getView($group)
    {
        return $this->getIndex($group);
    }

    protected
    function loadLocales()
    {
        //Set the default locale as the first one.
        $currentLocale = Config::get('app.locale');
        $primaryLocale = $this->primaryLocale;
        $translatingLocale = Cookie::get($this->cookieName(self::COOKIE_TRANS_LOCALE), $currentLocale);

        $locales = Translation::groupBy('locale')->lists('locale') ?: [];
        $locales = array_merge(array($primaryLocale, $translatingLocale, $currentLocale), $locales);
        return array_flatten(array_unique($locales));
    }

    public
    function postAdd($group)
    {
        $keys = explode("\n", trim(Input::get('keys')));
        $suffixes = explode("\n", trim(Input::get('suffixes')));
        $group = explode('::', $group, 2);
        $namespace = '*';
        if (count($group) > 1) $namespace = array_shift($group);
        $group = $group[0];

        foreach ($keys as $key)
        {
            $key = trim($key);
            if ($group && $key)
            {
                if ($suffixes)
                {
                    foreach ($suffixes as $suffix)
                    {
                        $this->manager->missingKey($namespace, $group, $key . trim($suffix));
                    }
                }
                else
                {
                    $this->manager->missingKey($namespace, $group, $key);
                }
            }
        }
        //Session::flash('_old_data', Input::except('keys'));
        return Redirect::back()->withInput();
    }

    public
    function postDeleteSuffixedKeys($group)
    {
        if (!in_array($group, $this->manager->getConfig('exclude_groups')) && $this->manager->getConfig('admin_enabled'))
        {
            $keys = explode("\n", trim(Input::get('keys')));
            $suffixes = explode("\n", trim(Input::get('suffixes')));

            if (count($suffixes) === 1 && $suffixes[0] === '') $suffixes = [];

            foreach ($keys as $key)
            {
                $key = trim($key);
                if ($group && $key)
                {
                    if ($suffixes)
                    {
                        foreach ($suffixes as $suffix)
                        {
                            //Translation::where('group', $group)->where('key', $key . trim($suffix))->delete();
                            $result = DB::update(<<<SQL
UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND `group` = ? AND `key` = ?
SQL
                                , [$group, $key . trim($suffix)]);
                        }
                    }
                    else
                    {
                        //Translation::where('group', $group)->where('key', $key)->delete();
                        $result = DB::update(<<<SQL
UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND `group` = ? AND `key` = ?
SQL
                            , [$group, $key]);
                    }
                }
            }
            return Redirect::back()->withInput();
        }
        return Redirect::back()->withInput();
    }

    public
    function postEdit($group)
    {
        if (!in_array($group, $this->manager->getConfig('exclude_groups')))
        {
            $name = Input::get('name');
            $value = Input::get('value');

            list($locale, $key) = explode('|', $name, 2);
            $translation = Translation::firstOrNew(array(
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
        if (!in_array($group, $this->manager->getConfig('exclude_groups')) && $this->manager->getConfig('admin_enabled'))
        {
            //Translation::where('group', $group)->where('key', $key)->delete();
            $result = DB::update(<<<SQL
UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND `group` = ? AND `key` = ?
SQL
                , [$group, $key]);
        }
        return array('status' => 'ok');
    }

    public
    function postUndelete($group, $key)
    {
        if (!in_array($group, $this->manager->getConfig('exclude_groups')) && $this->manager->getConfig('admin_enabled'))
        {
            //Translation::where('group', $group)->where('key', $key)->delete();
            $result = DB::update(<<<SQL
UPDATE ltm_translations SET is_deleted = 0 WHERE is_deleted = 1 AND `group` = ? AND `key` = ?
SQL
                , [$group, $key]);
        }
        return array('status' => 'ok');
    }

    protected static
    function keyGroup($group, $key)
    {
        $parts = explode('.', $key, 2);
        if (count($parts) === 1)
        {
            $tgroup = $group;
            $tkey = $key;
        }
        else
        {
            $tgroup = $parts[0];
            $tkey = $parts[1];
        }

        return [$tgroup, $tkey];
    }

    protected
    function keyOp($group, $op = 'preview')
    {
        $errors = [];
        $keymap = [];
        $this->logSql = 1;
        $this->sqltraces = [];

        if (!in_array($group, $this->manager->getConfig('exclude_groups')) && $this->manager->getConfig('admin_enabled'))
        {
            $srckeys = explode("\n", trim(Input::get('srckeys')));
            $dstkeys = explode("\n", trim(Input::get('dstkeys')));

            array_walk($srckeys, function (&$val, $key) use (&$srckeys)
            {
                $val = trim($val);
                if ($val === '') unset($srckeys[$key]);
            });

            array_walk($dstkeys, function (&$val, $key) use (&$dstkeys)
            {
                $val = trim($val);
                if ($val === '') unset($dstkeys[$key]);
            });

            if (!$group)
            {
                $errors[] = trans($this->packagePrefix . 'messages.keyop-need-group');
            }
            elseif (count($srckeys) !== count($dstkeys) && ($op === 'copy' || $op === 'move' || count($dstkeys)))
            {
                $errors[] = trans($this->packagePrefix . 'messages.keyop-count-mustmatch');
            }
            elseif (!count($srckeys))
            {
                $errors[] = trans($this->packagePrefix . 'messages.keyop-need-keys');
            }
            else
            {
                if (!count($dstkeys)) $dstkeys = array_fill(0, count($srckeys), null);

                $keys = array_combine($srckeys, $dstkeys);
                $hadErrors = false;

                foreach ($keys as $src => $dst)
                {
                    $keyerrors = [];

                    if ($dst !== null)
                    {

                        if ((substr($src, 0, 1) === '*') !== (substr($dst, 0, 1) === '*'))
                        {
                            $keyerrors[] = trans($this->packagePrefix . 'messages.keyop-wildcard-mustmatch');
                        }

                        if ((substr($src, -1, 1) === '*') !== (substr($dst, -1, 1) === '*'))
                        {
                            $keyerrors[] = trans($this->packagePrefix . 'messages.keyop-wildcard-mustmatch');
                        }

                        if ((substr($src, 0, 1) === '*') && (substr($src, -1, 1) === '*'))
                        {
                            $keyerrors[] = trans($this->packagePrefix . 'messages.keyop-wildcard-once');
                        }
                    }

                    if (!empty($keyerrors))
                    {
                        $hadErrors = true;
                        $keymap[$src] = ['errors' => $keyerrors, 'dst' => $dst,];
                        continue;
                    }

                    list($srcgrp, $srckey) = self::keyGroup($group, $src);
                    list($dstgrp, $dstkey) = $dst === null ? [null, null] : self::keyGroup($group, $dst);

                    if ((substr($src, 0, 1) === '*'))
                    {
                        if ($dst === null)
                        {
                            $rows = DB::select($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ?
ORDER BY locale, `key`

SQL
                                , [
                                    $srcgrp,
                                    '%' . mb_substr($srckey, 1),
                                ]);
                        }
                        else
                        {
                            $rows = DB::select($sql = <<<SQL
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
                    }
                    elseif ((substr($src, -1, 1) === '*'))
                    {
                        if ($dst === null)
                        {
                            $rows = DB::select($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ?
ORDER BY locale, `key`

SQL
                                , [
                                    $srcgrp,
                                    mb_substr($srckey, 0, -1) . '%',
                                ]);
                        }
                        else
                        {
                            $rows = DB::select($sql = <<<SQL
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
                    }
                    else
                    {
                        if ($dst === null)
                        {
                            $rows = DB::select($sql = <<<SQL
SELECT DISTINCT `group`, `key`, locale, id, NULL dst, NULL dstgrp FROM ltm_translations t1
WHERE `group` = ? AND `key` LIKE BINARY ?
ORDER BY locale, `key`

SQL
                                , [
                                    $srcgrp,
                                    $srckey,
                                ]);
                        }
                        else
                        {
                            $rows = DB::select($sql = <<<SQL
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

                if (!$hadErrors && ($op === 'copy' || $op === 'move' || $op === 'delete'))
                {
                    foreach ($keys as $src => $dst)
                    {
                        $rows = $keymap[$src]['rows'];

                        $rowids = array_reduce($rows, function ($carry, $row)
                        {
                            return $carry . ',' . $row->id;
                        }, '');
                        $rowids = substr($rowids, 1);

                        list($srcgrp, $srckey) = self::keyGroup($group, $src);
                        if ($op === 'move')
                        {
                            foreach ($rows as $row)
                            {
                                list($dstgrp, $dstkey) = self::keyGroup($row->dstgrp, $row->dst);
                                $to_delete = DB::select(<<<SQL
SELECT GROUP_CONCAT(id SEPARATOR ',') ids FROM ltm_translations tr
WHERE `group` = ? AND `key` = ? AND locale = ? AND id NOT IN ($rowids)

SQL
                                    , [$dstgrp, $dstkey, $row->locale]);

                                if (!empty($to_delete))
                                {
                                    $to_delete = $to_delete[0]->ids;
                                    if ($to_delete)
                                    {
                                        //DB::delete("DELETE FROM ltm_translations WHERE id IN ($to_delete)");
                                        DB::update("UPDATE ltm_translations SET is_deleted = 1 WHERE id IN ($to_delete)");
                                    }
                                }

                                DB::update("UPDATE ltm_translations SET `group` = ?, `key` = ?, status = 1 WHERE id = ?"
                                    , [$dstgrp, $dstkey, $row->id]);
                            }
                        }
                        elseif ($op === 'delete')
                        {
                            //DB::delete("DELETE FROM ltm_translations WHERE id IN ($rowids)");
                            DB::update("UPDATE ltm_translations SET is_deleted = 1 WHERE is_deleted = 0 AND id IN ($rowids)");
                        }
                        elseif ($op === 'copy')
                        {
                            foreach ($rows as $row)
                            {
                                list($dstgrp, $dstkey) = self::keyGroup($row->dstgrp, $row->dst);
                                $to_delete = DB::select(<<<SQL
SELECT GROUP_CONCAT(id SEPARATOR ',') ids FROM ltm_translations tr
WHERE `group` = ? AND `key` = ? AND locale = ? AND id NOT IN ($rowids)

SQL
                                    , [$dstgrp, $dstkey, $row->locale]);

                                if (!empty($to_delete))
                                {
                                    $to_delete = $to_delete[0]->ids;
                                    if ($to_delete)
                                    {
                                        //DB::delete("DELETE FROM ltm_translations WHERE id IN ($to_delete)");
                                        DB::update("UPDATE ltm_translations SET is_deleted = 1 WHERE id IN ($to_delete)");
                                    }
                                }

                                DB::insert($sql = <<<SQL
INSERT INTO ltm_translations
SELECT
    NULL,
    1 status,
    locale,
    ? `group`,
    ? `key`,
    value,
    sysdate() created_at,
    sysdate() updated_at,
    source,
    saved_value
FROM ltm_translations t1
WHERE id = ?

SQL
                                    , [$dstgrp, $dstkey, $row->id]);
                            }
                        }
                    }
                }
            }
        }
        else
        {
            $errors[] = trans($this->packagePrefix . 'messages.keyops-not-authorized');
        }

        $this->logSql = 0;
        return \View::make($this->packagePrefix . 'keyop')
            ->with('controller', get_class($this))
            ->with('package', $this->package)
            ->with('errors', $errors)
            ->with('keymap', $keymap)
            ->with('op', $op)
            ->with('group', $group);
    }

    public
    function getKeyop($group)
    {
        return $this->keyOp($group);
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
        $replace = Input::get('replace', false);
        if ($replace == 2) $this->manager->truncateTranslations($group);
        $counter = $this->manager->importTranslations($group !== '*' ? true : $replace, false, $group === '*' ? null : [$group]);

        return Response::json(array('status' => 'ok', 'counter' => $counter));
    }

    public
    function getImport()
    {
        $replace = Input::get('replace', false);
        $group = Input::get('group', '*');
        $counter = $this->manager->importTranslations(($group !== '*' ? !$this->manager->inDatabasePublishing() : $replace), false, $group === '*' ? null : [$group]);

        return Response::json(array('status' => 'ok', 'counter' => $counter));
    }

    public
    function postFind()
    {
        $numFound = $this->manager->findTranslations();

        return Response::json(array('status' => 'ok', 'counter' => (int)$numFound));
    }

    public
    function postDeleteAll($group)
    {
        $this->manager->truncateTranslations($group);

        return Response::json(array('status' => 'ok', 'counter' => (int)0));
    }

    public
    function getPublish($group)
    {
        $this->manager->exportTranslations($group);

        return Response::json(array('status' => 'ok'));
    }

    public
    function postPublish($group)
    {
        $this->manager->exportTranslations($group);

        return Response::json(array('status' => 'ok'));
    }

    public
    function getToggleInPlaceEdit()
    {
        inPlaceEditing(!inPlaceEditing());
        if (App::runningUnitTests()) return Redirect::to('/');
        return !is_null(Request::header('referer')) ? Redirect::back() : Redirect::to('/');
    }

    public
    function getInterfaceLocale()
    {
        $locale = Input::get("l");
        $translating = Input::get("t");
        $primary = Input::get("p");
        $displayLocales = Input::get("d");
        $display = implode(',', $displayLocales ?: []);

        App::setLocale($locale);
        Cookie::queue($this->cookieName(self::COOKIE_LANG_LOCALE), $locale, 60 * 24 * 365 * 1);
        Cookie::queue($this->cookieName(self::COOKIE_TRANS_LOCALE), $translating, 60 * 24 * 365 * 1);
        Cookie::queue($this->cookieName(self::COOKIE_PRIM_LOCALE), $primary, 60 * 24 * 365 * 1);
        Cookie::queue($this->cookieName(self::COOKIE_DISP_LOCALES), $display, 60 * 24 * 365 * 1);

        if (App::runningUnitTests()) return Redirect::to('/');
        return !is_null(Request::header('referer')) ? Redirect::back() : Redirect::to('/');
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
        return Response::json(array(
            'status' => 'ok',
            'yandex_key' => $this->manager->getConfig('yandex_translator_key', null)
        ));
    }

}
