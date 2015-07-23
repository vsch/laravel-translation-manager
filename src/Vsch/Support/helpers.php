<?php
/**
 * Created by PhpStorm.
 * User: vlad
 * Date: 15-07-20
 * Time: 4:50 PM
 */
if (!function_exists('mapTrans'))
{
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

if (!function_exists('transLang'))
{
    /**
     * @param       $string
     * @param       $prefix
     * @param array $params
     *
     * @return mixed
     */
    function transLang($key, array $replace = array(), $locale = null, $useDB = null)
    {
        $trans = App::make('translator');
        return $trans->get($key, $replace, $locale, $useDB);
    }
}

if (!function_exists('noEditTransEmptyUndefined'))
{
    /**
     * @param       $string
     * @param       $prefix
     * @param array $params
     *
     * @return mixed
     */
    function noEditTransEmptyUndefined($key, array $replace = array(), $locale = null, $useDB = null)
    {
        $trans = App::make('translator');
        if ($trans->inPlaceEditing())
        {
            /* @var $trans Translator */
            $trans->suspendInPlaceEditing();
            $text = $trans->get($key, $replace, $locale, $useDB);
            $trans->resumeInPlaceEditing();
        }
        else
        {
            $text = $trans->get($key, $replace, $locale, $useDB);
        }
        return $text === $key ? '' : $text;
    }
}

if (!function_exists('transChoice'))
{
    /**
     * @param       $string
     * @param       $prefix
     * @param array $params
     *
     * @return mixed
     */
    function transChoice($key, $number, array $replace = array(), $locale = null, $useDB = null)
    {
        $trans = App::make('translator');
        return $trans->choice($key, $number, $replace, $locale, $useDB);
    }
}

if (!function_exists('noEditTrans'))
{
    /**
     * @param       $key
     * @param array $parameters
     * @param null  $locale
     * @param null  $useDB
     *
     * @return mixed
     *
     */
    function noEditTrans($key, $parameters = array(), $locale = null, $useDB = null)
    {
        $trans = App::make('translator');
        if ($trans->inPlaceEditing())
        {
            /* @var $trans Translator */
            $trans->suspendInPlaceEditing();
            $text = $trans->get($key, $parameters, $locale, $useDB);
            $trans->resumeInPlaceEditing();
            return $text;
        }
        return $trans->get($key, $parameters, $locale, $useDB);
    }
}

if (!function_exists('ifEditTrans'))
{
    /**
     * @param       $key
     * @param array $parameters
     * @param null  $locale
     * @param null  $useDB
     *
     * @return mixed
     *
     */
    function ifEditTrans($key, $parameters = array(), $locale = null, $useDB = null, $noWrap = null)
    {
        $trans = App::make('translator');
        if ($trans->inPlaceEditing())
        {
            /* @var $trans Translator */
            $text = $trans->getInPlaceEditLink($key, $parameters, $locale, $useDB);
            return $noWrap ? $text : "<br>[$text]";
        }
        return '';
    }
}

if (!function_exists('ifInPlaceEdit'))
{
    /**
     * @param       $string
     * @param       $prefix
     * @param array $params
     *
     * @return mixed
     */
    function ifInPlaceEdit($text, $replace = [], $locale = null, $useDB = null, $noWrap = null)
    {
        /* @var $trans Translator */
        $trans = App::make('translator');
        if ($trans->inPlaceEditing())
        {
            while (preg_match('/@lang\(\'([^\']+)\'\)/', $text, $matches))
            {

                $repl = $trans->getInPlaceEditLink($matches[1], $replace, $locale, $useDB);
                $text = str_replace($matches[0], $repl, $text);
            }
            return $noWrap ? $text : "<br>[$text]";
        }
        return '';
    }
}

if (!function_exists('inPlaceEditing'))
{
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

if (!function_exists('formSubmit'))
{
    function formSubmit($value = null, $options = array())
    {
        if (inPlaceEditing())
        {
            $innerText = preg_match('/^\s*<a\s*[^>]*>([^<]*)<\/a>\s*$/', $value, $matches) ? $matches[1] : $value;
            if ($innerText !== $value)
            {
                return Form::submit($innerText, $options) . "[$value]";
            }
        }
        return Form::submit($value, $options);
    }
}

if (!function_exists('mb_replace'))
{
    function mb_replace($search, $replace, $subject, &$count = null)
    {
        if (!is_array($search)) $search = array($search);
        if (!is_array($replace)) $replace = array($replace);
        $sMax = count($search);
        $rMax = count($replace);

        $result = '';
        $count = 0;
        for ($s = 0; $s < $sMax; $s++)
        {
            $find = $search[$s];
            $pos = 0;

            while ($pos < $sMax)
            {
                $lastPos = $pos;
                if (($pos = mb_strpos($subject, $find, $pos)) === false)
                {
                    $result .= mb_substr($subject, $lastPos);
                    break;
                }

                $result .= mb_substr($subject, $lastPos, $pos-$lastPos);
                if ($s < $rMax) $result .= $replace[$s];
                $pos += mb_strlen($find);
                $count++;
            }
        }

        return $result;
    }
}
