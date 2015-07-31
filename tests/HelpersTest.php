<?php namespace Vsch\Tests;

use Exception;
use PHPUnit_Framework_TestCase as TestCase;

class ExampleTest extends TestCase
{

    private static $timing;
    private static $timers;

    static
    function startTimer($name, $time)
    {
        self::$timers[$name] = $time;
    }

    static
    function endTimer($name, $time)
    {
        $elapsedTime = $time - self::$timers[$name];
        unset(self::$timers[$name]);

        if (!array_key_exists($name, self::$timing))
        {
            self::$timing[$name] = [];
            self::$timing[$name]['name'] = $name;
            self::$timing[$name]['total'] = 0;
            self::$timing[$name]['count'] = 0;
        }
        self::$timing[$name]['total'] += $elapsedTime;
        self::$timing[$name]['count']++;
    }

    public static
    function setupBeforeClass()
    {
        self::$timers = [];
        self::$timing = [];
    }

    public static
    function tearDownAfterClass()
    {
        echo "Timing results : \n";
        printf("%20s %6s %-8s %-8s\n", 'name', 'count', 'total', 'avg');

        array_walk(self::$timing, function (&$value, $key)
        {
            $value['avg'] = round($value['total']/$value['count']*1000000,3);
        });

        usort(self::$timing, function ($a, $b)
        {
            $at = $a['avg'];
            $bt = $b['avg'];
            return $at === $bt ? 0 : ($at < $bt ? -1 : 1);
        });

        foreach (self::$timing as $timing)
        {
            printf("%20s %6d %3.3f ms %3.3f us\n", $timing['name'], $timing['count'], round($timing['total'] * 1000, 3), round($timing['total']/$timing['count'] * 1000000, 3));
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
    public
    function testStrReplace($search, $replace, $subject, $expectedResult, $expectedCount)
    {
        //echo $url . "\n";
        try
        {
            self::startTimer('str_replace', microtime(true));
            $result = str_replace($search, $replace, $subject, $count);
            self::endTimer('str_replace', microtime(true));
            $this->assertSame($expectedResult,$result,  "Mismatch in result");
            $this->assertSame($expectedCount, $count, "Mismatch in count");
        }
        catch (Exception $e)
        {
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
    public
    function testMbStrReplace($search, $replace, $subject, $expectedResult, $expectedCount)
    {
        //echo $url . "\n";
        try
        {
            self::startTimer('mb_str_replace', microtime(true));
            $result = mb_str_replace($search, $replace, $subject, $count);
            self::endTimer('mb_str_replace', microtime(true));
            $this->assertSame($expectedResult,$result,  "Mismatch in result");
            $this->assertSame($expectedCount, $count, "Mismatch in count");
        }
        catch (Exception $e)
        {
            echo "Exception " . $e->getMessage() . " on subject: '$subject'\n";
            var_dump($search);
            var_dump($replace);
            // rethrow it
            throw $e;
        }
    }

    ///**
    // * Text mb_replace() functionality
    // *
    // * @dataProvider getReplaceStringProvider
    // * @var mixed  $search
    // * @var mixed  $replace
    // * @var string $subject
    // * @var string $expectedResult
    // * @var int    $expectedCount
    // * @return void
    // */
    //public
    //function testMbReplace($search, $replace, $subject, $expectedResult, $expectedCount)
    //{
    //    //echo $url . "\n";
    //    try
    //    {
    //        self::startTimer('mb_replace', microtime(true));
    //        $result = mb_replace($search, $replace, $subject, $count);
    //        self::endTimer('mb_replace', microtime(true));
    //        $this->assertSame($expectedResult,$result,  "Mismatch in result");
    //        $this->assertSame($expectedCount, $count, "Mismatch in count");
    //    }
    //    catch (Exception $e)
    //    {
    //        echo "Exception " . $e->getMessage() . " on subject: '$subject'\n";
    //        var_dump($search);
    //        var_dump($replace);
    //        // rethrow it
    //        throw $e;
    //    }
    //}
    //
    ///**
    // * Text mb_replace2() functionality
    // *
    // * @dataProvider getReplaceStringProvider
    // * @var mixed  $search
    // * @var mixed  $replace
    // * @var string $subject
    // * @var string $expectedResult
    // * @var int    $expectedCount
    // * @return void
    // */
    //public
    //function testMbReplace2($search, $replace, $subject, $expectedResult, $expectedCount, $searchHasPrefixes = null)
    //{
    //    //echo $url . "\n";
    //    try
    //    {
    //        self::startTimer('mb_replace2' . ($searchHasPrefixes ? '(sort)' : '(nosort)'), microtime(true));
    //        $result = mb_replace2($search, $replace, $subject, $count, $searchHasPrefixes);
    //        self::endTimer('mb_replace2' . ($searchHasPrefixes ? '(sort)' : '(nosort)'), microtime(true));
    //        if ($result !== $expectedResult || $count !== $expectedCount)
    //        {
    //            // call again just in case we're debugging
    //            $result = mb_replace2($search, $replace, $subject, $count, $searchHasPrefixes);
    //        }
    //        $this->assertSame($expectedResult,$result,  "Mismatch in result");
    //        $this->assertSame($expectedCount, $count, "Mismatch in count");
    //    }
    //    catch (Exception $e)
    //    {
    //        echo "Exception " . $e->getMessage() . " on subject: '$subject'\n";
    //        var_dump($search);
    //        var_dump($replace);
    //        // rethrow it
    //        try
    //        {
    //            $result = mb_replace2($search, $replace, $subject, $count, $searchHasPrefixes);
    //        }
    //        catch (Exception $e)
    //        {
    //        }
    //
    //        throw $e;
    //    }
    //}
    //
    ///**
    // * Text mb_replace3() functionality
    // *
    // * @dataProvider getReplaceStringProvider
    // * @var mixed  $search
    // * @var mixed  $replace
    // * @var string $subject
    // * @var string $expectedResult
    // * @var int    $expectedCount
    // * @return void
    // */
    //public
    //function testMbReplace3($search, $replace, $subject, $expectedResult, $expectedCount, $searchHasPrefixes = null)
    //{
    //    //echo $url . "\n";
    //    try
    //    {
    //        self::startTimer('mb_replace3', microtime(true));
    //        $result = mb_replace3($search, $replace, $subject, $count, $searchHasPrefixes);
    //        self::endTimer('mb_replace3', microtime(true));
    //        if ($result !== $expectedResult || $count !== $expectedCount)
    //        {
    //            // call again just in case we're debugging
    //            $result = mb_replace3($search, $replace, $subject, $count, $searchHasPrefixes);
    //        }
    //        $this->assertSame($expectedResult,$result,  "Mismatch in result");
    //        $this->assertSame($expectedCount, $count, "Mismatch in count");
    //    }
    //    catch (Exception $e)
    //    {
    //        echo "Exception " . $e->getMessage() . " on subject: '$subject'\n";
    //        var_dump($search);
    //        var_dump($replace);
    //        // rethrow it
    //        try
    //        {
    //            $result = mb_replace3($search, $replace, $subject, $count, $searchHasPrefixes);
    //        }
    //        catch (Exception $e)
    //        {
    //        }
    //
    //        throw $e;
    //    }
    //}
    //
    /**
     * A basic functional test example.
     *
     * @return array()
     */
    public
    function getReplaceStringProvider($testName)
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
            array(['abc', 'def', 'ghi', 'jkl', 'mno', 'pqr', 'stu'], ['1', '2', '3', '4', '5', '6', '7'], 'abcdefghijklmno abcdefghijklmno abcdefghijklmno', false),
            // use the prefixes option
            array('abc', 'def', 'this is a test', true),
            array('abc', 'uvwxyz', 'abcdef abcdef', true),
            array(['abc', 'abcdef'], ['hijklm', 'rstuvwxyz',], 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], ['kl', 'uvwxy',], 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], ['k', 'uvwx',], 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], ['', 'uvw',], 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], ['', 'uv',], 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], ['', 'u',], 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], ['', '',], 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], ['hijklm', '',], 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], ['hijklm',], 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], 'hijklm', 'abcdef abcdef abcef', true),
            array(['abc', 'abcdef'], '\1$1', 'abcdef abcdef abcef', true),
            array(['abc', 'def', 'ghi', 'jkl', 'mno', 'pqr', 'stu'], ['1', '2', '3', '4', '5', '6', '7'], 'abcdefghijklmno abcdefghijklmno abcdefghijklmno', true),
        );

        $testData = [];
        foreach ($tests as $test)
        {
            if ($testName === 'testMbReplace2' && $test[3])
            {
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
            }
            else
            {
                $testData[] = [$test[0], $test[1], $test[2], str_replace($test[0], $test[1], $test[2], $count), $count];
            }
        }

        return $testData;
    }
}
