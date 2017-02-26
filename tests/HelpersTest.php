<?php namespace Vsch\Tests;

use Exception;

class HelpersTest extends \Vsch\Tests\TranslationManagerTestCase
{

    /**
     * Text mb_replace() functionality
     *
     * @dataProvider getReplaceStringProvider
     * @var mixed  $search
     * @var mixed  $replace
     * @var string $subject
     * @var string $expectedResult
     * @var int    $expectedCount
     * @return void
     */
    public function testStrReplace($search, $replace, $subject, $expectedResult, $expectedCount)
    {
        //echo $url . "\n";
        try {
            self::startTimer('str_replace', microtime(true));
            $result = str_replace($search, $replace, $subject, $count);
            self::endTimer('str_replace', microtime(true));
            $this->assertSame($expectedResult, $result, "Mismatch in result");
            $this->assertSame($expectedCount, $count, "Mismatch in count");
        } catch (Exception $e) {
            echo "Exception " . $e->getMessage() . " on subject: '$subject'\n";
            var_dump($search);
            var_dump($replace);
            // rethrow it
            throw $e;
        }
    }

    /**
     * Text mb_replace() functionality
     *
     * @dataProvider getReplaceStringProvider
     * @var mixed  $search
     * @var mixed  $replace
     * @var string $subject
     * @var string $expectedResult
     * @var int    $expectedCount
     * @return void
     */
    public function testMbStrReplace($search, $replace, $subject, $expectedResult, $expectedCount)
    {
        //echo $url . "\n";
        try {
            self::startTimer('mb_str_replace', microtime(true));
            $result = mb_str_replace($search, $replace, $subject, $count);
            self::endTimer('mb_str_replace', microtime(true));
            $this->assertSame($expectedResult, $result, "Mismatch in result");
            $this->assertSame($expectedCount, $count, "Mismatch in count");
        } catch (Exception $e) {
            echo "Exception " . $e->getMessage() . " on subject: '$subject'\n";
            var_dump($search);
            var_dump($replace);
            // rethrow it
            throw $e;
        }
    }

    /**
     * A basic functional test example.
     *
     * @return array()
     */
    public function getReplaceStringProvider($testName)
    {
        $tests = array(
            array('abc', 'def', 'this is a test', false),
            array('abc', 'uvwxyz', 'abcdef abcdef', false),
            array(['abc', 'abcdef'], ['hijklm', 'rstuvwxyz',], 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], ['kl', 'uvwxy',], 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], ['k', 'uvwx',], 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], ['', 'uvw',], 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], ['', 'uv',], 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], ['', 'u',], 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], ['', '',], 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], ['hijklm', '',], 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], ['hijklm',], 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], 'hijklm', 'abcdef abcdef abcef', false),
            array(['abc', 'abcdef'], '\1$1', 'abcdef abcdef abcef', false),
            array(
                ['abc', 'def', 'ghi', 'jkl', 'mno', 'pqr', 'stu'],
                ['1', '2', '3', '4', '5', '6', '7'],
                'abcdefghijklmno abcdefghijklmno abcdefghijklmno',
                false
            ),
            
            array('abc', 'def', 'this is a test', false),
            array('abc', 'uvwxyz', 'abcdef abcdef', false),
            array(['abc', 'abcdef'], ['hijklm', 'rstuvwxyz',], ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], ['kl', 'uvwxy',], ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], ['k', 'uvwx',], ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], ['', 'uvw',], ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], ['', 'uv',], ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], ['', 'u',], ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], ['', '',], ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], ['hijklm', '',], ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], ['hijklm',], ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], 'hijklm', ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(['abc', 'abcdef'], '\1$1', ['abcdef abcdef abcef', 'abcdef abcdef abcef',], false),
            array(
                ['abc', 'def', 'ghi', 'jkl', 'mno', 'pqr', 'stu'],
                ['1', '2', '3', '4', '5', '6', '7'],
                'abcdefghijklmno abcdefghijklmno abcdefghijklmno',
                false
            ),
            //// use the prefixes option
            //array('abc', 'def', 'this is a test', true),
            //array('abc', 'uvwxyz', 'abcdef abcdef', true),
            //array(['abc', 'abcdef'], ['hijklm', 'rstuvwxyz',], 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], ['kl', 'uvwxy',], 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], ['k', 'uvwx',], 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], ['', 'uvw',], 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], ['', 'uv',], 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], ['', 'u',], 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], ['', '',], 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], ['hijklm', '',], 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], ['hijklm',], 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], 'hijklm', 'abcdef abcdef abcef', true),
            //array(['abc', 'abcdef'], '\1$1', 'abcdef abcdef abcef', true),
            //array(
            //    ['abc', 'def', 'ghi', 'jkl', 'mno', 'pqr', 'stu'],
            //    ['1', '2', '3', '4', '5', '6', '7'],
            //    'abcdefghijklmno abcdefghijklmno abcdefghijklmno',
            //    true
            //),
        );

        $testData = [];
        foreach ($tests as $test) {
            if ($testName === 'testMbReplace2' && $test[3]) {
                $replace = $test[1];
                $search = $test[0];
                if (!is_array($replace) && is_array($search)) $replace = array_fill(0, count($search), $test[1]);
                if (is_array($replace)) $replace = array_reverse(array_pad($replace, count($search), ''));
                if (is_array($search)) $search = array_reverse($search);

                $testData[] = [
                    $test[0],
                    $test[1],
                    $test[2],
                    $result = str_replace($search, $replace, $test[2], $count),
                    $count,
                    $test[3]
                ];
            } else {
                $testData[] = [$test[0], $test[1], $test[2], str_replace($test[0], $test[1], $test[2], $count), $count];
            }
        }

        return $testData;
    }

    /**
     * Text appendPath() functionality
     *
     * @dataProvider getAppendPathProvider
     * @var string $path
     * @var string $part
     * @var string $expectedResult
     * @return void
     */
    public function testAppendPath($path, $part, $expectedResult)
    {
        //echo $url . "\n";
        try {
            self::timeIt('appendPath', function () use (&$result, $path, $part) {
                $result = appendPath($path, $part);
            });

            if ($result !== $expectedResult) {
                $result = appendPath($path, $part);
            }
            $this->assertSame($expectedResult, $result, "Mismatch in result on: '$path', '$part'");
        } catch (Exception $e) {
            echo "Exception " . $e->getMessage() . " on: '$path', '$part'\n";
            // rethrow it
            throw $e;
        }
    }

    /**
     * A basic functional test example.
     *
     * @return array()
     */
    public function getAppendPathProvider($testName)
    {
        return array(
            array('', '', ''),
            array('', '/', '/'),
            array('/', '', '/'),
            array('/', '/', '/'),

            array('', 'abc', 'abc'),
            array('', '/abc', '/abc'),
            array('/', 'abc', '/abc'),
            array('/', '/abc', '/abc'),

            array('', 'abc/', 'abc/'),
            array('', '/abc/', '/abc/'),
            array('/', 'abc/', '/abc/'),
            array('/', '/abc/', '/abc/'),

            array('/abc', '', '/abc'),
            array('/abc', '/', '/abc/'),
            array('/abc/', '', '/abc/'),
            array('/abc/', '/', '/abc/'),

            array('/abc',   'def', '/abc/def'),
            array('/abc',  '/def', '/abc/def'),
            array('/abc/',  'def', '/abc/def'),
            array('/abc/', '/def', '/abc/def'),

            array('/abc',   'def/', '/abc/def/'),
            array('/abc',  '/def/', '/abc/def/'),
            array('/abc/',  'def/', '/abc/def/'),
            array('/abc/', '/def/', '/abc/def/'),
        );
    }
}
