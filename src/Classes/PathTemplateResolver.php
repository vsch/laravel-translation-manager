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
    /** @var \Illuminate\Foundation\Application */
    protected $app;
    /** @var \Illuminate\Filesystem\Filesystem */
    protected $files;
    /** @var array */
    protected $config;

    // used during loading of language file list
    protected $lang_files;
    protected $processed_dirs;
    protected $config_paths;
    protected $path_vars;
    protected $config_path;

    /**
     * PathTemplateResolver constructor.
     *
     * @param $config
     */
    public
    function __construct($app, $files, $config, $version)
    {
        $defaults = array(
            'lang' => [
                'db_group' => '{group}',
                'path' => [
                    '4' => '/app/lang/{locale}',
                    '*' => '/resources/lang/{locale}',
                ],
            ],
            'packages' => [
                'db_group' => '{package}::{group}',
                'include' => '*',
                'path' => [
                    '4' => '/app/lang/packages/{locale}/{package}',
                    '*' => '/resources/lang/vendor/{package}/{locale}',
                ],
            ],
            'workbench' => [
                'db_group' => 'wbn:{vendor}.{package}::{group}',
                'include' => '*/*',
                'path' => [
                    '4' => '/workbench/{vendor}/{package}/src/lang/{locale}',
                    '*' => '/workbench/{vendor}/{package}/resources/lang/{locale}',
                ],
            ],
            'vendor' => [
                'db_group' => 'vnd:{vendor}.{package}::{group}',
                'include' => [],
                'path' => [
                    '4' => '/vendor/{vendor}/{package}/src/lang/{locale}',
                    '*' => '/vendor/{vendor}/{package}/resources/lang/{locale}',
                ],
            ],
        );

        $this->app = $app;
        $this->files = $files;

        // provide default mappings if needed.
        foreach ($config as $key => &$value)
        {
            if (!is_array($value)) $value = array('path' => $value);

            if (array_key_exists($key, $defaults))
            {
                foreach ($defaults[$key] as $defKey => $defValue)
                {
                    if (!array_key_exists($defKey, $value))
                    {
                        // add it, use version if applicable
                        if (is_array($defValue))
                        {
                            $value[$defKey] = array_key_exists($version, $defValue) ? $defValue[$version] : $defValue['*'];
                        }
                        else
                        {
                            $value[$defKey] = $defValue;
                        }
                    }

                    if ($defKey === 'include')
                    {
                        // process this so we can assume format and values
                        if (!is_array($value['include'])) $value['include'] = $value['include'] ? array($value['include']) : [];

                        foreach ($value['include'] as &$vendor_package)
                        {
                            if ($key !== 'package' && $key !== 'lang')
                            {
                                $parts = explode('/', $vendor_package, 2);
                                if (count($parts) > 1)
                                {
                                    array_walk($parts, function (&$part)
                                    {
                                        if ($part === '') $part = '*';
                                    });
                                    $vendor_package = implode('/', $parts);
                                }
                            }
                        }
                    }
                }
            }
            else
            {
                // just handle the includes sub-key if it is an array
                if (!is_array($value)) $value = array('path' => $value);

                if (array_key_exists('include', $value))
                {
                    if (!is_array($value['include'])) $value['include'] = $value['include'] ? array($value['include']) : [];

                    foreach ($value['include'] as &$vendor_package)
                    {
                        $parts = explode('/', $vendor_package, 2);
                        if (count($parts) > 1)
                        {
                            array_walk($parts, function (&$part)
                            {
                                if ($part === '') $part = '*';
                            });
                            $vendor_package = implode('/', $parts);
                        }
                    }
                }
                else
                {
                    $value['include'] = [];
                }
            }
        }

        $this->config = $config;
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
                    $values[$value[$setting]] = $value + ['part' => $key];
                }
            }
            elseif ($setting === 'path' && !is_array($value))
            {
                if (!array_key_exists($value, $values))
                {
                    $values[$value] = ['part' => $key, 'path' => $value];
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
        $this->path_vars = [];

        foreach ($sorted_paths as $path)
        {
            $this->config_path = $path;

            $full_path = appendPath($this->app->basePath(), $path);
            $path_parts = explode('/', $full_path);

            $this->loadFileList('/', $path_parts, []);
        }

        return $this->lang_files;
    }

    protected static
    function isIncluded($part, $include, $vars)
    {
        if ($part === 'lang') return !array_key_exists('{package}', $vars) && !array_key_exists('{vendor}', $vars);
        if (!$include) return false;

        foreach ($include as $vendor_part)
        {
            $parts = explode('/', $vendor_part, 2);

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

            if (array_key_exists('{vendor}', $vars) && $vendor === null) return false;
            if (!array_key_exists('{vendor}', $vars) && $vendor !== null) return null;
            if (!array_key_exists('{package}', $vars)) return null;

            if ($vendor !== '*' && $vars['{vendor}'] !== $vendor)
            {
                return false;
            }

            if ($package !== '*' && $vars['{package}'] !== $package)
            {
                return false;
            }
        }

        return true;
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

            if ($path_parts)
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
                            if (self::isIncluded($config['part'], $config['include'], $this->path_vars) === false) continue;
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

                $dirs = $this->files->directories($prefix);
                foreach ($dirs as $dir)
                {
                    $this->loadFileList($dir, $path_parts, $group_parts + [$dir]);
                }

                $files = $this->files->files($prefix);
                $files = array_filter($files, function ($file)
                {
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    return $ext === 'php';
                });

                // now we add all these files to the list as keys with the resolved db_group as the value
                $db_group = self::expandVars($this->config_paths[$this->config_path]['db_group'], $this->path_vars);

                foreach ($files as $file)
                {
                    if (!array_key_exists($file, $this->lang_files))
                    {
                        $group = implode('.', $group_parts + [pathinfo($file, PATHINFO_FILENAME)]);
                        $this->lang_files[$file] = $this->path_vars + ['{db_group}' => self::expandVars($db_group, ['{group}' => $group,])];
                    }
                    else
                    {
                        assert(false, "Language file was found twice: $file => " . $this->lang_files[$file]);
                    }
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
        $regEx = '';
        while ($lastpos < $len && preg_match('/(\{[a-zA-Z0-9_-]+\})/', $template, $matches, PREG_OFFSET_CAPTURE, $lastpos))
        {
            $endpos = ($pos = $matches[1][1]) + strlen($matches[1][0]);

            if ($pos > $lastpos) $regEx .= preg_quote(mb_substr($template, $lastpos, $pos - $lastpos), '/');
            $vars[$matches[1][0]] = '';
            $regEx .= '([a-zA-Z0-9_\.-]+)';
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
        if ($vars = self::extractTemplateVars($db_group, $group))
        {
            if (array_key_exists('{group}', $vars))
            {
                // convert group to path parts
                $vars['{group}'] = str_replace('.', '/', $vars['{group}']) . '.php';
            }

            // see if this one is included, if not, then it cannot possibly be the one
            if (!self::isIncluded($partVars['part'], $partVars['part'] !== 'lang' ? $partVars['include'] : null, $vars)) return null;

            $vars['{locale}'] = $locale;
            return str_replace(array_keys($vars), array_values($vars), appendPath($partVars['path'], '/{group}'));
        }
        return null;
    }

    public
    function groupFilePath($group, $locale)
    {
        $config_paths = $this->configValues('path');
        $sorted_paths = array_keys($config_paths);
        sort($sorted_paths, SORT_STRING);

        // find part in config whose db_group pattern matches, try in sorted path order, just like they were imported
        // first match must be it
        foreach ($sorted_paths as $path)
        {
            if ($path = self::getDbGroupPath($config_paths[$path], $group, $locale))
            {
                return $path;
            }
        }

        return null;
    }
}

