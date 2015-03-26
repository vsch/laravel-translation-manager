<?php namespace Barryvdh\TranslationManager;

use Illuminate\Database\Query\Expression;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Events\Dispatcher;
use Barryvdh\TranslationManager\Models\Translation;
use Illuminate\Foundation\Application;
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

    public
    function __construct(Application $app, Filesystem $files, Dispatcher $events)
    {
        $this->app = $app;
        $this->files = $files;
        $this->events = $events;

        // when instantiated from the service provider, config info is not yet loaded, trying to get it here
        // causes a problem since none of the keys are defined.
        $this->config = null;
    }

    protected
    function config()
    {
        return $this->config ?: $this->config = $this->app['config']['laravel-translation-manager::config'];
    }

    public
    function excludedPageEditGroup($group)
    {
        return in_array($group, $this->config()['exclude_page_edit_groups']);
    }

    /**
     * @param $namespace string
     * @param $group     string
     * @param $key       string
     *
     * @return null|Translation
     */
    public
    function missingKey($namespace, $group, $key, $useLottery = false, $findOrNew = false)
    {
        $group = $namespace && $namespace !== '*' ? $namespace . '::' . $group : $group;

        if (!in_array($group, $this->config()['exclude_groups']) && $this->config()['log_missing_keys'])
        {
            $lottery = 1;
            if ($useLottery)
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
                if ($findOrNew)
                {
                    $translation = Translation::firstOrNew(array(
                        'locale' => $this->app['config']['app.locale'],
                        'group' => $group,
                        'key' => $key,
                    ));
                }
                else
                {
                    $translation = Translation::firstOrCreate(array(
                        'locale' => $this->app['config']['app.locale'],
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
    function importTranslationLocale($replace = false, $locale, $langPath, $namespace = null)
    {
        $files = $this->files->files($langPath);
        $package = $namespace ? $namespace . '::' : '';
        foreach ($files as $file)
        {

            $info = pathinfo($file);
            $group = $info['filename'];

            if (in_array($package . $group, $this->config()['exclude_groups']))
            {
                continue;
            }

            $translations = array_dot(\Lang::getLoader()->load($locale, $group, $namespace));
            foreach ($translations as $key => $value)
            {
                $value = (string)$value;
                $translation = Translation::firstOrNew(array(
                    'locale' => $locale,
                    'group' => $package . $group,
                    'key' => $key,
                ));

                // Importing from the source, status is always saved. When it is changed by the user, then it is changed.
                //$newStatus = ($translation->value === $value || !$translation->exists) ? Translation::STATUS_SAVED : Translation::STATUS_CHANGED;
                $newStatus = Translation::STATUS_SAVED;
                if ($newStatus !== (int)$translation->status)
                {
                    $translation->status = $newStatus;
                }

                // Only replace when empty, or explicitly told so
                if ($replace || !$translation->value)
                {
                    $translation->value = $value;
                }

                $translation->saved_value = $value;

                $translation->save();

                $this->imported++;
            }
        }
    }

    public
    function importTranslations($replace = false, $packages = false)
    {
        if (!$packages) $this->imported = 0;

        $langdirs = $this->files->directories($this->app->make('path') . '/lang' . ($packages ? '/packages' : ''));
        foreach ($langdirs as $langdir)
        {
            $locale = basename($langdir);
            if ($locale === 'packages' && !$packages)
            {
                $this->importTranslations($replace, true);
            }
            else
            {
                if ($packages)
                {
                    $packdirs = $this->files->directories($langdir);
                    foreach ($packdirs as $packagedir)
                    {
                        $package = basename($packagedir);
                        $this->importTranslationLocale($replace, $locale, $packagedir, $package);
                    }
                }
                else
                {
                    $this->importTranslationLocale($replace, $locale, $langdir);
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
            $trans = trim(str_replace("'", "\\'", $trans));
            if (strpos($trans, "\n") !== false)
            {
                $text = "<<<'TEXT'\n$trans\nTEXT\n";
            }
            else
            {
                $text = "'$trans'";
            }
        }
        return $text;
    }

    public
    function exportTranslations($group)
    {
        if (!in_array($group, $this->config()['exclude_groups']))
        {
            if ($group == '*')
                $this->exportAllTranslations();

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

    public
    function exportAllTranslations()
    {
        $groups = Translation::whereNotNull('value')->select(DB::raw('DISTINCT `group`'))->get('group');

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
    function truncateTranslations()
    {
        Translation::truncate();
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
