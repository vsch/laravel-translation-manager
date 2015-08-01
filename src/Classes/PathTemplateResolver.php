<?php
/**
 * Created by PhpStorm.
 * User: vlad
 * Date: 15-07-28
 * Time: 11:17 PM
 */

namespace Vsch\TranslationManager\Classes;

class PathTemplateResolver
{
    /** @var \Illuminate\Filesystem\Filesystem */
    protected $files;
    /** @var array */
    protected $config;

    /** @var  string - base path for the project */
    protected $base_path;

    // used during loading of language file list
    protected $lang_files;
    protected $processed_dirs;
    protected $config_paths;
    protected $path_vars;
    protected $config_path;

    protected static $defaults = array(
        'lang' => [
            'db_group' => '{group}',
            'root' => '',
            'files' => [
                '4' => '/app/lang/{locale}/{group}',
                '*' => '/resources/lang/{locale}/{group}',
            ],
            'vars' => [
                '{vendor}' => null,
                '{package}' => null,
            ],
        ],
        'packages' => [
            'db_group' => '{package}::{group}',
            'include' => '*',
            'root' => '',
            'files' => [
                '4' => '/app/lang/packages/{locale}/{package}/{group}',
                '*' => '/resources/lang/vendor/{package}/{locale}/{group}',
            ],
            'vars' => [
                '{vendor}' => null,
            ],
        ],
        'workbench' => [
            'db_group' => 'wbn:{vendor}.{package}::{group}',
            'include' => '*/*',
            'root' => '/workbench/{vendor}/{package}',
            'files' => [
                '4' => 'src/lang/{locale}/{group}',
                '*' => 'resources/lang/{locale}/{group}',
            ],
            'vars' => [],
        ],
        'vendor' => [
            'db_group' => 'vnd:{vendor}.{package}::{group}',
            'include' => [],
            'root' => '/vendor/{vendor}/{package}',
            'files' => [
                '4' => 'src/lang/{locale}/{group}',
                '*' => 'resources/lang/{locale}/{group}',
            ],
            'vars' => [],
        ],
        //// these will be merged with vendor or workbench type and create their own types named by the package
        //// the first section whose include is satisfied will be used, the other ignored. Since vendor section requires
        //// opt-in, it is listed first, if this custom type is included then it will be a vendor type. Regardless of
        //// whether the directory exists or not. Therefore only include in vendor section if it is not located
        //// in workbench
        //'caouecs/laravel4-lang' => [
        //    '__merge' => ['vendor', 'workbench',],
        //    'files' => '{locale}/{group}',
        //],
        //'nesbot/carbon' => [
        //    '__merge' => ['vendor', 'workbench',],
        //    'files' => 'src/Carbon/Lang/{locale}',
        //    'vars' => [
        //        '{group}' => 'carbon',
        //    ],
        //],
    );

    /**
     * PathTemplateResolver constructor.
     *
     * @param $files
     * @param $base_path
     * @param $config
     * @param $version
     */
    public
    function __construct($files, $base_path, $config, $version)
    {
        $this->files = $files;
        $this->base_path = $base_path;

        // provide default mappings if needed. and normalize the config
        static::normalizeConfig($config, $version);

        $this->config = $config;
    }

    /**
     * @param &$config
     * @param $version
     */
    protected static
    function normalizeConfig(&$config, $version)
    {
        $toMerge = [];
        foreach ($config as $key => &$value)
        {
            if (!is_array($value)) $value = array('files' => $value);

            if (array_key_exists($key, static::$defaults))
            {
                foreach (static::$defaults[$key] as $defKey => $defValue)
                {
                    if ($defKey[0] === '_' && $defKey[1] === '_') continue;

                    if (!array_key_exists($defKey, $value))
                    {
                        // add it, use version if applicable
                        if ($defKey === 'files')
                        {
                            $value[$defKey] = is_array($defValue) ? (array_key_exists($version, $defValue) ? $defValue[$version] : $defValue['*']) : $defValue;
                        }
                        else
                        {
                            $value[$defKey] = $defValue;
                        }
                    }
                }
            }
            elseif (array_key_exists('__merge', $value))
            {
                $toMerge[] = $key;
            }
            elseif ($key[0] === '_' && $key[1] === '_')
            {
                // just handle the includes sub-key
                if (!array_key_exists('include', $value))
                {
                    $value['include'] = [];
                }
            }

            static::normalizeInclude($value);
        }

        // add custom sections as mergers with vendor and workbench, these are custom mappings
        foreach ($toMerge as $custom)
        {
            if (!is_array($config[$custom]['__merge'])) $config[$custom]['__merge'] = array($config[$custom]['__merge']);

            foreach ($config[$custom]['__merge'] as $mergeWith)
            {
                $default = $config[$mergeWith];

                // see if this one is included in that section and only add it's merged version if it is
                $vars = array_combine(['{vendor}', '{package}'], explode('/', $custom));
                if (static::isIncluded($custom, $default['include'], $vars))
                {
                    static::mergeConfig($config[$custom], $config[$mergeWith]);

                    // add the vendor, package, variables
                    if (!array_key_exists('vars', $config[$custom])) $config[$custom]['vars'] = [];
                    static::mergeConfig($config[$custom]['vars'], $vars);

                    // include itself so the rest of the code does not have to know anything special about it
                    $config[$custom]['include'] = array($custom);

                    break;
                }
            }
        }

        // can now create normalized path by combining root and files
        foreach ($config as $key => &$value)
        {
            // resolve any vendor and package now so that these get processed first
            $value['path'] = static::expandVars(appendPath($value['root'], $value['files']), array_key_exists('vars', $value) ? $value['vars'] : []);
        }
    }

    /**
     * @param $config
     * @param $default
     */
    protected static
    function mergeConfig(&$config, $default)
    {
        foreach ($default as $key => $value)
        {
            if (!array_key_exists($key, $config))
            {
                $config[$key] = $value;
            }
            elseif (is_array($config[$key]))
            {
                // can merge them recursively
                static::mergeConfig($config[$key], $value);
            }
        }
    }

    /**
     * @param $value
     */
    protected
    static
    function normalizeInclude(&$value)
    {
        if (!array_key_exists('include', $value)) return;

        if (!is_array($value['include'])) $value['include'] = $value['include'] ? array($value['include']) : [];

        $includeNormalize = [];
        if (!array_key_exists('{vendor}', $value['vars']) || $value['vars']['{vendor}'] !== null)
        {
            // this type has vendor
            $includeNormalize['|^/|'] = '*/';
            $includeNormalize['|/$|'] = '/*';
        }
        elseif (!array_key_exists('{package}', $value['vars']) || $value['vars']['{package}'] !== null)
        {
            // this type has package only
            $includeNormalize['|^/$|'] = '*';
        }

        if ($includeNormalize)
        {
            $includeSearch = array_keys($includeNormalize);
            $includeReplace = array_values($includeNormalize);

            foreach ($value['include'] as &$vendor_package)
            {
                $vendor_package = preg_replace($includeSearch, $includeReplace, $vendor_package);
            }
        }
    }

    public
    function configValues($setting)
    {
        $values = [];
        foreach ($this->config as $key => $value)
        {
            if (is_array($value) && array_key_exists($setting, $value))
            {
                if (!array_key_exists($value[$setting], $values))
                {
                    $values[$value[$setting]] = $value + ['section' => $key];
                }
            }
            elseif ($setting === 'path' && !is_array($value))
            {
                if (!array_key_exists($value, $values))
                {
                    $values[$value] = ['section' => $key, 'path' => $value];
                }
            }
        }
        return $values;
    }

    protected static
    function fixedPathPrefix($path)
    {
        return ($pos = mb_strpos('{', $path)) !== false ? mb_substr($path, 0, $pos) : $path;
    }

    public
    function langFileList()
    {
        $this->config_paths = $this->configValues('path');
        $sorted_paths = array_keys($this->config_paths);
        sort($sorted_paths, SORT_STRING);

        $this->lang_files = [];
        $this->processed_dirs = [];

        foreach ($sorted_paths as $path)
        {
            $this->config_path = $path;
            $this->path_vars = $this->config_paths[$path]['vars'];  // initialize to the vars in this section

            $full_path = appendPath($this->base_path, $path);
            $path_parts = explode('/', $full_path);

            // drop empty entry
            array_shift($path_parts);

            $this->loadFileList('/', $path_parts, []);
        }

        return $this->lang_files;
    }

    protected static
    function isIncluded($section, $include, $vars)
    {
        if ($section === 'lang') return (!array_key_exists('{package}', $vars) || $vars['{package}'] === null) && (!array_key_exists('{vendor}', $vars) || $vars['{vendor}'] === null);
        if (!$include) return false;

        $matchedVendor = !array_key_exists('{vendor}', $vars) ? null : ($vars['{vendor}'] != null ? false : null);

        foreach ($include as $vendor_package)
        {
            $parts = explode('/', $vendor_package, 2);

            if (count($parts) < 2)
            {
                $package = $parts[0];
                $vendor = null;
            }
            else
            {
                $vendor = $parts[0];
                $package = $parts[1];
            }

            if (array_key_exists('{vendor}', $vars) && $vars['{vendor}'] !== null && $vendor === null) return false; // no vendor allowed, can't be part of this

            if (!array_key_exists('{vendor}', $vars) && $vendor !== null) continue;    // can't tell yet, so we go on

            if ($vendor === null || $vendor === '*' || $vars['{vendor}'] === $vendor)
            {
                $matchedVendor = true;

                if (!array_key_exists('{package}', $vars)) continue;                       // can't tell yet

                if ($package === '*' || $vars['{package}'] === $package)
                {
                    return true;
                }
            }
        }

        return $matchedVendor;  // if we had no vendor match and vars contains a vendor then this will return false, otherwise it will be true
    }

    protected
    function loadFileList($prefix, $path_parts, $group_parts)
    {
        // collect all the fixed path parts
        // variable, read directory and sub dirs
        for (; ;)
        {
            if (array_key_exists($prefix, $this->processed_dirs) || !file_exists($prefix) || !is_dir($prefix))
            {
                // already handled this one or it does not exist
                return;
            }

            // check if this one needs inclusion
            $config = $this->config_paths[$this->config_path];

            // the last path part is not removed and will be scanned for files.
            // if it is {group} then it will also be scanned for directories.
            if (count($path_parts) > 1)
            {
                $part = array_shift($path_parts);
                if (mb_substr($part, 0, 1) === '{' && mb_substr($part, -1, 1) === '}')
                {
                    $this->processed_dirs[$prefix] = $path_parts;

                    $dirs = $this->files->directories($prefix);
                    foreach ($dirs as $dir)
                    {
                        $this->path_vars[$part] = basename($dir);

                        if ($part === '{vendor}' || $part === '{package}')
                        {
                            // we can test it here
                            if (static::isIncluded($config['section'], $config['include'], $this->path_vars) === false) continue;
                        }

                        $this->loadFileList($dir, $path_parts, $group_parts);
                        unset($this->path_vars[$part]);
                    }

                    break;
                }
                else
                {
                    // fixed, append it and keep going
                    $prefix = appendPath($prefix, $part);
                }
            }
            else
            {
                // we can scan for language files here and in subdirectories, we have no more dirs parts to
                // search
                if ($path_parts[0] === '{group}')
                {
                    $this->processed_dirs[$prefix] = $path_parts;

                    $dirs = $this->files->directories($prefix);
                    foreach ($dirs as $dir)
                    {
                        $this->loadFileList($dir, $path_parts, $group_parts + [$dir]);
                    }
                }

                $files = $this->files->files($prefix);
                $files = array_filter($files, function ($file)
                {
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    return $ext === 'php';
                });

                // now we add all these files to the list as keys with the resolved db_group as the value
                $db_group = static::expandVars($this->config_paths[$this->config_path]['db_group'], $this->path_vars);

                foreach ($files as $file)
                {
                    if (!array_key_exists($file, $this->lang_files))
                    {
                        $last_part = implode('.', $group_parts + [pathinfo($file, PATHINFO_FILENAME)]);
                        $this->path_vars[$path_parts[0]] = $last_part;
                        $this->lang_files[$file] = $this->path_vars + ['{db_group}' => static::expandVars($db_group, $this->path_vars)];
                    }
                    else
                    {
                        assert(false, "Language file was found twice: $file => " . $this->lang_files[$file]);
                    }
                    unset($this->path_vars[$path_parts[0]]);
                }

                break;
            }
        }
    }

    public static
    function expandVars($text, array $vars)
    {
        return str_replace(array_keys($vars), array_values($vars), $text);
    }

    protected static
    function extractTemplateVars($template, $text)
    {
        // return vars array or null if no match
        $lastpos = 0;
        $len = mb_strlen($template);
        $vars = [];
        $regEx = '^';
        while ($lastpos < $len && preg_match('/(\{[a-zA-Z0-9_-]+\})/', $template, $matches, PREG_OFFSET_CAPTURE, $lastpos))
        {
            $endpos = ($pos = $matches[1][1]) + strlen($matches[1][0]);

            if ($pos > $lastpos) $regEx .= preg_quote(mb_substr($template, $lastpos, $pos - $lastpos), '/');
            $vars[$matches[1][0]] = '';
            $regEx .= $matches[1][0] === '{group}' ? '([^\:\n]+)' : '([^\.\:\n]+)';
            $lastpos = $endpos;
        }
        $regEx .= preg_quote(mb_substr($template, $lastpos), '/') . '$';

        if (preg_match("/$regEx/u", $text, $matches))
        {
            $i = 0;
            foreach ($vars as $name => &$value)
            {
                $value = $matches[++$i];
            }
            return $vars;
        }

        return null;
    }

    protected static
    function getDbGroupPath($partVars, $group, $locale)
    {
        $db_group = $partVars['db_group'];
        $path = $partVars['path'];
        if ($vars = static::extractTemplateVars($db_group, $group))
        {
            $fixed_vars = array_key_exists('vars', $partVars) ? $partVars['vars'] : [];

            foreach ($fixed_vars as $key => $value)
            {
                if (array_key_exists($key, $vars) && $vars[$key] !== $value) return null;
                $vars[$key] = $value; // copy it so we have a full picture
            }

            if (!array_key_exists('{group}', $vars)) return null; // can't be
            $vars['{group}'] = str_replace('.', '/', $vars['{group}']); // convert group to path parts

            // see if this one is included, if not, then it cannot possibly be the one
            if (!static::isIncluded($partVars['section'], array_key_exists('include', $partVars) ? $partVars['include'] : null, $vars)) return null;

            $vars['{locale}'] = $locale;
            return str_replace(array_keys($vars), array_values($vars), $partVars['path']) . '.php';
        }
        return null;
    }

    public
    function groupFilePath($group, $locale)
    {
        $config_paths = $this->configValues('path');
        $sorted_paths = array_keys($config_paths);
        sort($sorted_paths, SORT_STRING);

        // find section in config whose db_group pattern matches, try in sorted path order, just like they were imported
        // first match must be it
        foreach ($sorted_paths as $path)
        {
            if ($path = static::getDbGroupPath($config_paths[$path], $group, $locale))
            {
                return $path;
            }
        }

        return null;
    }
}

