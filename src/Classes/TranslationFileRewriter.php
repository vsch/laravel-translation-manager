<?php
/**
 * Created by PhpStorm.
 * User: vlad
 * Date: 15-07-22
 * Time: 6:33 PM
 */

namespace Vsch\TranslationManager\Classes;

/**
 * Class TranslationFileRewriter
 *
 * @package Vsch\TranslationManager\Classes
 * Class to handle parsing of PHP code aimed specifically at config files for the purpose of
 *          changing the declarations and values while preserving multi-line comments
 *  The format of the file is expected to be:
 *  <?php
 *  return [ or return array(
 *  ]; or );
 *          All multi-line comments inside the array() and first level keys will be collected. Single line comments are
 *          stripped out as are multi-line comments outside of the array definition.
 * Note:    No attempt is made to preserve single line comments, these are currently discarded.
 */
/**
 * Class TranslationFileRewriter
 *
 * @package Vsch\TranslationManager\Classes
 */
class TranslationFileRewriter
{

    //const OPT_PRESERVE_QUOTES = 1;      // NOT IMPLEMENTED
    //const OPT_PRESERVE_HEREDOC = 2;     // NOT IMPLEMENTED
    //const OPT_PRESERVE_ARRAY = 4;       // NOT IMPLEMENTED
    const OPT_PRESERVE_EMPTY_ARRAYS = 8;  // preserve empty first level arrays in translations

    const OPT_USE_QUOTES = 16;
    const OPT_USE_HEREDOC = 32;
    const OPT_USE_SHORT_ARRAY = 64;
    const OPT_SORT_KEYS = 128;

    public static $options = array(
        'PRESERVE_EMPTY_ARRAYS' => self::OPT_PRESERVE_EMPTY_ARRAYS,
        'USE_QUOTES'            => self::OPT_USE_QUOTES,
        'USE_HEREDOC'           => self::OPT_USE_HEREDOC,
        'USE_SHORT_ARRAY'       => self::OPT_USE_SHORT_ARRAY,
        'SORT_KEYS'             => self::OPT_SORT_KEYS,
    );

    /**
     * @var array
     */
    public $tokens;

    /**
     * @var string
     */
    public $source;

    /**
     * @var array   containing the multi-line comment text as they appear in the file, including leading and trailing
     *              blank lines. Indexed by the count of the multi-line comment.
     */
    public $sections;

    /**
     * @var array   contains the key portion of the first level identifier as the key and the value is
     *              [
     *              'section' => int,  // index of the section this identifier belongs to. -1 means before first.
     *                 'ordinal' => int,   // ordinal position of this identifier in the section
     *                 'quotes' => string, // type of quoting used for the key if it was a string, ', ", <<<NNN,
     *                 <<<'NNN'
     *                 'data_format' => string, // type of quoting used for value if it was a string, ', ", <<<NNN,
     *                 <<<'NNN', or if array then [ or array(
     *              ]
     */
    public $firstLevelKeys;

    /**
     * TranslationFileRewriter constructor.
     *
     * @param $tokens
     */
    public function __construct()
    {
    }

    public static function optionFlags($optionNames)
    {
        if (!is_array($optionNames)) $optionNames = array($optionNames);
        $options = 0;
        foreach ($optionNames as $optionName) {
            if (array_key_exists($optionName, self::$options)) {
                $options |= self::$options[$optionName];
            }
        }
        return $options;
    }

    public static function str_count_chars($chars, $string)
    {
        $iMax = mb_strlen($string);
        $counts = array_fill(0, $iMax, 0);
        for ($i = 0; $i < $iMax; $i++) {
            if (($pos = strpos(mb_substr($string, $i, 1), $chars)) !== false) {
                $counts[$pos]++;
            }
        }

        return !is_array($chars) || count($chars) === 1 ? $counts[0] : $counts;
    }

    public static function str_count_trailing($chars, $string)
    {
        $iMax = mb_strlen($string);
        $counts = array_fill(0, $iMax, 0);
        for ($i = $iMax; $i--;) {
            if (($pos = strpos(mb_substr($string, $i, 1), $chars)) === false) break;
            $counts[$pos]++;
        }

        return !is_array($chars) || count($chars) === 1 ? $counts[0] : $counts;
    }

    /**
     * @param $source
     * TODO: Quite a bit of kludge but does the job. Needs clean up, it is hard on the eyes.
     */
    public function parseSource($source)
    {
        $this->source = $source;
        $this->tokens = token_get_all($this->source);
        $this->sections = [];
        $this->firstLevelKeys = [];

        // parse first level tokens and multi-line comments only
        $inPhp = false;
        $inReturn = false;
        $inArray = 0;
        $sectionIndex = -1;
        $keyIndex = 0;
        $stringData = [];
        $lastString = '';
        $thisWasComma = false;
        $lastComment = '';
        $preCommentSpaces = '';
        $key = '';

        $arrayState = new \stdClass();
        $arrayState->seenDoubleArrow = 0;
        $arrayState->lastWasComma = false;
        $arrayState->arrayType = null;
        $arrayStack = [];

        foreach ($this->tokens as $token) {
            $token_name = is_array($token) ? $token[0] : null;
            $token_data = is_array($token) ? $token[1] : $token;

            if ($token_name === T_OPEN_TAG) {
                $inPhp = true;
                continue;
            }
            if (!$inPhp) continue;

            if ($token_name === T_RETURN) {
                $inReturn = true;
                continue;
            }
            if (!$inReturn) continue;

            if ($token_name === T_WHITESPACE) {
                // accumulate eol's only
                $lastComment .= str_repeat("\n", self::str_count_chars("\n", $token_data));
                $preCommentSpaces = str_repeat(" ", self::str_count_trailing(" ", $token_data));
                continue;
            } elseif ($token_name === T_COMMENT || $token_name === T_DOC_COMMENT) {
                if (substr($token_data, 0, 2) !== '//') {
                    $lastComment .= $preCommentSpaces . $token_data;
                }
                $preCommentSpaces = '';
                continue;
            } else {
                $preCommentSpaces = '';
                if ($inArray === 1 && trim($lastComment) !== '') {
                    // save it as a section
                    if (substr($lastComment, 0, 1) === "\n") {
                        $lastComment = substr($lastComment, 1);
                    }
                    $this->sections[] = $lastComment;
                    $sectionIndex++;
                }

                $lastComment = '';
            }

            $arrayState->lastWasComma = $thisWasComma;
            $thisWasComma = false;

            if ($token_name === T_ARRAY || ($token_name === null && $token_data === '[')) {
                // array opening
                $arrayState->arrayType = $token_name === T_ARRAY ? 'array(' : '[';
                $arrayStack[] = $arrayState;

                $arrayState = new \stdClass();
                $arrayState->seenDoubleArrow = 0;
                $arrayState->lastWasComma = false;
                $arrayState->arrayType = null;

                $inArray++;
                continue;
            }

            if ($arrayStack && ($token_name === null && ($token_data === ']' || $token_data === ')'))) {
                // array closing
                assert($inArray, ") or ] while not in array declaration");

                if ($arrayState->seenDoubleArrow && !$arrayState->lastWasComma) {
                    $arrayState->seenDoubleArrow--;
                    if (!$arrayState->seenDoubleArrow && $inArray === 1) {
                        $keyData['data_format'] = array_key_exists('quotes', $stringData) ? $stringData['quotes'] : null;
                        $this->firstLevelKeys[$key] = $keyData;
                    }
                }

                $stringData = [];
                $arrayState = array_pop($arrayStack);
                $stringData['quotes'] = $arrayState->arrayType;
                $inArray--;
                continue;
            }

            if ($token_name === T_DOUBLE_ARROW) {
                $arrayState->seenDoubleArrow++;

                if ($arrayState->seenDoubleArrow === 1 && $inArray === 1) {
                    // save the string last seen as the key
                    $keyData = $stringData;
                    $key = $lastString;
                    $keyData['section'] = $sectionIndex;
                    $keyData['ordinal'] = $keyIndex++;
                }

                $stringData = [];
                continue;
            }

            if ($token_name === null && $token_data === ',') {
                $thisWasComma = true;

                if ($arrayState->seenDoubleArrow) {
                    $arrayState->seenDoubleArrow--;
                    if (!$arrayState->seenDoubleArrow && $inArray === 1) {
                        $keyData['data_format'] = array_key_exists('quotes', $stringData) ? $stringData['quotes'] : null;
                        $this->firstLevelKeys[$key] = $keyData;
                    }
                }
                $stringData = [];
                continue;
            }

            if ($token_name === T_START_HEREDOC) {
                $stringData['quotes'] = substr($token_data, 0, -1);
                continue;
            }

            if ($token_name === T_END_HEREDOC) {
                continue;
            }

            if ($token_name === T_ENCAPSED_AND_WHITESPACE) {
                $lastString = $token_data;
                continue;
            }

            if ($token_name === T_CONSTANT_ENCAPSED_STRING) {
                $lastString = substr($token_data, 1, -1);
                $stringData['quotes'] = substr($token_data, 0, 1);
                continue;
            }
            //echo "Unexpected token: " . token_name($token_name) . "= $token_data\n";
        }
    }

    protected function wrapQuotes($value, $options = null)
    {
        // replace Unicode non-break space with a regular space
        $str = str_replace("\xc2\xa0", ' ', $value);
        if (($options & self::OPT_USE_HEREDOC) && strpos($str, "\n") !== false) {
            $text = "<<<'TEXT'\n$str\nTEXT\n";
        } elseif ($options & self::OPT_USE_QUOTES) {
            $str = trim(str_replace("\"", "\\\"", $str));
            $text = "\"$str\"";
        } else {
            $str = trim(str_replace("'", "\\'", $str));
            $text = "'$str'";
        }
        return $text;
    }

    protected function formatSection($trans, $options = null, $indent = 0)
    {
        $ind = str_repeat(' ', $indent * 4);
        $text = '';
        if (is_array($trans)) {
            if (!$trans) {
                if ($indent) {
                    if ($options & self::OPT_USE_SHORT_ARRAY) $text .= "[]";
                    else $text .= "array()";
                }
            } else {
                if ($indent) $text .= ($options & self::OPT_USE_SHORT_ARRAY) ? "[\n" : "array(\n";

                $indT = $ind . str_repeat(' ', 4);
                $max = 0;
                foreach ($trans as $key => $val) {
                    if (strlen($key) > $max) $max = strlen($key) + 1;
                }
                $max += (($max + 2) & 3) ? 4 - (($max + 2) & 3) : 0;

                $keys = array_keys($trans);
                if ($options & self::OPT_SORT_KEYS) {
                    sort($keys, SORT_STRING);
                }

                foreach ($keys as $key) {
                    $val = $trans[$key];
                    $val = $this->formatSection($val, $options, $indent + 1);

                    $pad = str_repeat(' ', $max - strlen($key));
                    $str = self::wrapQuotes($key, $options);
                    $text .= $indT . "$str$pad=> $val,\n";
                }

                if ($indent) $text .= $ind . (($options & self::OPT_USE_SHORT_ARRAY) ? "]" : ")");
            }
        } else {
            $text = self::wrapQuotes($trans, $options);
        }
        return $text;
    }

    /**
     * @param      array $trans translation to output
     * @param null|integer $options formatting options
     *
     * @return string result of output of translations taking sections into account
     */
    public function formatForExport($trans, $options = null)
    {
        $text = "<?php\n\nreturn " . (($options & self::OPT_USE_SHORT_ARRAY) ? "[\n" : "array(\n");

        // create sections to match the comments
        $iMax = count($this->sections);
        $sections = array_fill(0, $iMax + 1, []);

        // assemble keys by sections
        foreach ($trans as $key => $value) {
            if (array_key_exists($key, $this->firstLevelKeys)) {
                $sections[$this->firstLevelKeys[$key]['section'] + 1][$key] = $value;
            } else {
                $sections[$iMax ? 1 : 0][$key] = $value;
            }
        }

        if ($options & self::OPT_PRESERVE_EMPTY_ARRAYS) {
            foreach ($this->firstLevelKeys as $key => $keyData) {
                if (!array_key_exists($key, $trans) && ($keyData['data_format'] === '[' || $keyData['data_format'] === 'array(')) {
                    // add it to its section
                    $sections[$keyData['section'] + 1][$key] = [];
                }
            }
        }

        for ($i = 0; $i < $iMax + 1; $i++) {
            if ($i) {
                $text .= $this->sections[$i - 1];
            }

            if ($sections[$i]) $text .= $this->formatSection($sections[$i], $options);
        }

        $text .= (($options & self::OPT_USE_SHORT_ARRAY) ? "]" : ")") . ";\n";
        return $text;
    }

    /**
     * The __toString method allows a class to decide how it will react when it is converted to a string.
     *
     * @return string
     * @link http://php.net/manual/en/language.oop5.magic.php#language.oop5.magic.tostring
     */
    function __toString()
    {
        ob_start();
        foreach ($this->tokens as $token) {
            $token_name = is_array($token) ? $token[0] : null;
            $token_data = is_array($token) ? $token[1] : $token;

            $token_name = $token_name ? token_name($token_name) : null;
            var_dump([$token_name, $token_data]);
        }
        return ob_get_clean();
    }
}
