<?php
/**
 * Created by PhpStorm.
 * User: vlad
 * Date: 15-07-20
 * Time: 4:50 PM
 */
use Vsch\TranslationManager\Translator;

if (!function_exists('getSupportedLocale')) {
    /**
     * @param $lang
     *
     * @return mixed
     *
     */
    function getSupportedLocale($lang)
    {
        $supported = false;
        $firstLocale = null;

        $supportedLocales = \Config::get('app.supported_locales', [\Config::get('app.locale', 'en')]);

        foreach ($supportedLocales as $locale) {
            if (!$firstLocale) $firstLocale = $locale;

            if ($lang === $locale) {
                $supported = true;
                break;
            }
        }

        if (!$supported) {
            $lang = $firstLocale ?: 'en';
        }

        return $lang;
    }
}

if (!function_exists('mapTrans')) {
    /**
     * @param       $string
     * @param       $prefix
     * @param array $params
     *
     * @return mixed
     */
    function mapTrans($string, $prefix, array $params = [])
    {
        $namespace = $prefix . '.' . $string;
        $trans = trans($namespace, $params);
        if ($trans === $namespace) $trans = $string;
        return $trans;
    }
}

if (!function_exists('arrayCopy')) {
    function arrayCopy(array $array)
    {
        $result = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $result[$key] = arrayCopy($val);
            } elseif (is_object($val)) {
                $result[$key] = clone $val;
            } else {
                $result[$key] = $val;
            }
        }
        return $result;
    }
}

if (!function_exists('transLang')) {
    /**
     * @param       $key
     * @param array $replace
     * @param null  $locale
     * @param null  $useDB
     *
     * @return mixed
     */
    function transLang($key, array $replace = array(), $locale = null, $useDB = null)
    {
        $trans = App::make('translator');
        return $trans->get($key, $replace, $locale, $useDB);
    }
}

if (!function_exists('noEditTransEmptyUndefined')) {
    /**
     * @param       $key
     * @param array $replace
     * @param null  $locale
     * @param null  $useDB
     *
     * @return mixed
     */
    function noEditTransEmptyUndefined($key, array $replace = array(), $locale = null, $useDB = null)
    {
        $trans = App::make('translator');
        if ($trans->isInPlaceEditing(1)) {
            /* @var $trans Translator */
            $trans->suspendInPlaceEditing();
            $text = $trans->get($key, $replace, $locale, true, $useDB);
            $trans->resumeInPlaceEditing();
        } else {
            $text = $trans->get($key, $replace, $locale, $useDB);
        }
        return $text === $key ? '' : $text;
    }
}

if (!function_exists('transChoice')) {
    /**
     * @param       $key
     * @param       $number
     * @param array $replace
     * @param null  $locale
     * @param null  $useDB
     *
     * @return mixed
     */
    function transChoice($key, $number, array $replace = array(), $locale = null, $useDB = null)
    {
        $trans = App::make('translator');
        return $trans->choice($key, $number, $replace, $locale, $useDB);
    }
}

if (!function_exists('noEditTrans')) {
    /**
     * @param       $key
     * @param array $parameters
     * @param null  $locale
     * @param null  $useDB
     *
     * @return mixed
     *
     */
    function noEditTrans($key, $parameters = null, $locale = null, $useDB = null)
    {
        $trans = App::make('translator');
        if ($trans->isInPlaceEditing(1)) {
            /* @var $trans Translator */
            $trans->suspendInPlaceEditing();
            $text = $trans->get($key, $parameters ?: [], $locale, true, $useDB);
            $trans->resumeInPlaceEditing();
            return $text;
        }
        return $trans->get($key, $parameters ?: [], $locale, $useDB);
    }
}

if (!function_exists('ifEditTrans')) {
    /**
     * @param       $key
     * @param array $parameters
     * @param null  $locale
     * @param null  $useDB
     * @param null  $noWrap
     *
     * @return mixed
     *
     */
    function ifEditTrans($key, $parameters = null, $locale = null, $useDB = null, $noWrap = null)
    {
        $trans = App::make('translator');
        if ($trans->isInPlaceEditing(1)) {
            /* @var $trans Translator */
            $text = $trans->getInPlaceEditLink($key, $parameters ?: [], $locale, $useDB);
            return $noWrap ? $text : "<br>[$text]";
        }
        return '';
    }
}

if (!function_exists('getEditableLinks')) {
    /**
     * @return string
     *
     */
    function getEditableLinks()
    {
        $trans = App::make('translator');
        return $trans->getEditableLinks();
    }
}

if (!function_exists('getEditableLinksOnly')) {
    /**
     * @return string
     *
     */
    function getEditableLinksOnly()
    {
        $trans = App::make('translator');
        return $trans->getEditableLinksOnly();
    }
}

if (!function_exists('getWebUITranslations')) {
    /**
     * @return string
     *
     */
    function getWebUITranslations()
    {
        $trans = App::make('translator');
        return $trans->getWebUITranslations();
    }
}

if (!function_exists('getEditableTranslationsButton')) {
    /**
     * @param string|null $style style attribute to apply
     *
     * @return string
     */
    function getEditableTranslationsButton($style = null)
    {
        $trans = App::make('translator');
        return $trans->getEditableTranslationsButton($style);
    }
}

if (!function_exists('ifInPlaceEdit')) {
    /**
     * @param       $text
     * @param array $replace
     * @param null  $locale
     * @param null  $useDB
     * @param null  $noWrap
     *
     * @return mixed
     */
    function ifInPlaceEdit($text, $replace = [], $locale = null, $useDB = null, $noWrap = null)
    {
        /* @var $trans Translator */
        $trans = App::make('translator');
        if ($trans->isInPlaceEditing(1)) {
            while (preg_match('/@lang\(\'([^\']+)\'\)/', $text, $matches)) {
                $repl = $trans->getInPlaceEditLink($matches[1], $replace, $locale, $useDB);
                $text = str_replace($matches[0], $repl, $text);
            }
            return $noWrap ? $text : "<br>[$text]";
        }
        return '';
    }
}

if (!function_exists('inPlaceEditing')) {
    /**
     * @return string
     *
     */
    function inPlaceEditing($inPlaceEditing = null)
    {
        $trans = App::make('translator');
        return $trans->inPlaceEditing($inPlaceEditing);
    }
}

if (!function_exists('isInPlaceEditing')) {
    /**
     * @param string|null $inPlaceEditing if null then only test to see if inplace eding is enabled, else test that the inplace edit mode == the passed value
     *
     * @return string
     */
    function isInPlaceEditing($inPlaceEditing = null)
    {
        $trans = App::make('translator');
        return $trans->isInPlaceEditing($inPlaceEditing);
    }
}

if (!function_exists('inPlaceEditingMode')) {
    /**
     * @return int
     *
     */
    function inPlaceEditingMode()
    {
        $trans = App::make('translator');
        $inPlaceEditingMode = $trans->getInPlaceEditingMode();
        return $inPlaceEditingMode;
    }
}

if (!function_exists('formSubmit')) {
    function formSubmit($value = null, $options = array())
    {
        if (isInPlaceEditing(1)) {
            $innerText = $value;
            if (preg_match('/^\s*(<a\s*[^>]*>[^<]*<\/a>)\s*\[(.*)\]$/', $value, $matches)) {
                $innerText = $matches[2];
                $value = $matches[1];
            } else if (preg_match('/^\s*(<a\s*[^>]*>([^<]*)<\/a>)\s*$/', $value, $matches)) {
                $innerText = $matches[2];
                $value = $matches[1];
            }
            if ($innerText !== $value) {
                return "[$value]" . Form::submit($innerText, $options);
            }
        }
        return Form::submit($value, $options);
    }
}

if (!function_exists('mb_str_replace')) {
    function mb_str_replace($search, $replace, $subject, &$count = 0)
    {
        if (!is_array($subject)) {
            $searches = is_array($search) ? array_values($search) : array($search);
            $replacements = is_array($replace) ? array_values($replace) : array($replace);
            $replacements = array_pad($replacements, count($searches), '');
            foreach ($searches as $key => $search) {
                $parts = mb_split(preg_quote($search), $subject);
                $count += count($parts) - 1;
                $subject = implode($replacements[$key], $parts);
            }
        } else {
            foreach ($subject as $key => $value) {
                $subject[$key] = mb_str_replace($search, $replace, $value, $count);
            }
        }
        return $subject;
    }
}

if (!function_exists('mb_chunk_split')) {
    function mb_chunk_split($body, $chunklen = 76, $end = "\r\n")
    {
        $split = '';
        $pos = 0;
        $len = mb_strlen($body);
        while ($pos < $len) {
            $split .= mb_substr($body, $pos, $chunklen) . $end;
            $pos += $chunklen;
        }
        return $split;
    }
}

if (!function_exists('mb_unsplit')) {
    function mb_unsplit($body, $end = "\r\n")
    {
        $split = '';
        $pos = 0;
        $len = mb_strlen($body);
        $skip = mb_strlen($end);
        while ($pos < $len) {
            $next = strpos($body, $end, $pos);
            if ($next === false) {
                $split .= mb_substr($body, $pos);
                break;
            }

            $split .= mb_substr($body, $pos, $next - $pos);
            $pos = $next + $skip;
            if (mb_substr($body, $pos, $skip) === $end) {
                // keep the second
                $split .= mb_substr($body, $pos, $skip);
                $pos += $skip;
            }
        }
        return $split;
    }
}

if (!function_exists('mb_renderDiffHtml')) {

    /**
     * @param      $from_text
     * @param      $to_text
     *
     * @param bool $charDiff
     *
     * @return array
     */
    function mb_renderDiffHtml($from_text, $to_text, $charDiff = null)
    {
        //if ($from_text === 'Lang' && $to_text === 'Language') xdebug_break();
        if ($from_text == $to_text) return $to_text;

        $removeSpaces = false;
        if ($charDiff === null) {
            $charDiff = mb_strtolower($from_text) === mb_strtolower($to_text)
                || abs(mb_strlen($from_text) - mb_strlen($to_text)) <= 2
                || ($from_text && $to_text
                    && ((strpos($from_text, $to_text) !== false)
                        || ($to_text && strpos($to_text, $from_text) !== false)));
        }

        if ($charDiff) {
            //use word diff but space all entities so that we get char diff
            $removeSpaces = true;
            $from_text = mb_chunk_split($from_text, 1, ' ');
            $to_text = mb_chunk_split($to_text, 1, ' ');
        }
        $from_text = mb_convert_encoding($from_text, 'HTML-ENTITIES', 'UTF-8');
        $to_text = mb_convert_encoding($to_text, 'HTML-ENTITIES', 'UTF-8');
        $opcodes = \FineDiff::getDiffOpcodes($from_text, $to_text, \FineDiff::$wordGranularity);
        $diff = \FineDiff::renderDiffToHTMLFromOpcodes($from_text, $opcodes);
        $diff = mb_convert_encoding($diff, 'UTF-8', 'HTML-ENTITIES');
        if ($removeSpaces) {
            $diff = mb_unsplit($diff, ' ');
        }
        return $diff;
    }
}

if (!function_exists('appendPath')) {
    function appendPath($path, $part)
    {
        if ($path !== '' && $part !== '') {
            // have both, combine them
            $pathTerminated = $path[strlen($path) - 1] === '/';
            $partPrefixed = $part[0] === '/';
            return $path . ($pathTerminated || $partPrefixed ? '' : '/') . ($pathTerminated && $partPrefixed ? substr($part, 1) : $part);
        } else {
            return $path ?: $part;
        }
    }
}

if (!function_exists('trim_prefix')) {
    /**
     * @param $text   string
     * @param $prefix array|string
     *
     * @return string
     */
    function trim_prefix($text, $prefix)
    {
        if (!is_array($prefix)) $prefix = array($prefix);
        foreach ($prefix as $pre) {
            if (strpos($text, $pre) === 0) {
                $text = substr($text, strlen($pre));
                break;
            }
        }
        return $text;
    }
}

if (!function_exists('trim_suffix')) {
    /**
     * @param $text   string
     * @param $suffix array|string
     *
     * @return string
     */
    function trim_suffix($text, $suffix)
    {
        if (!is_array($suffix)) $suffix = array($suffix);
        $textLen = strlen($text);

        foreach ($suffix as $suff) {
            if (strpos($text, $suff) === $textLen - strlen($suff)) {
                $text = substr($text, 0, -strlen($suff));
                break;
            }
        }
        return $text;
    }
}

if (!function_exists('encodeKey')) {
    /**
     * @param $text   string
     *
     * @return string
     */
    function encodeKey($text)
    {
        return urlencode(urlencode($text));
    }
}

if (!function_exists('decodeKey')) {
    /**
     * @param $text   string
     *
     * @return string
     */
    function decodeKey($text)
    {
        return urldecode(urldecode($text));
    }
}

