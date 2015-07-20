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

