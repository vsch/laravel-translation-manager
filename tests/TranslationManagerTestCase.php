<?php namespace Vsch\Tests;

use PHPUnit_Framework_TestCase as TestCase;

class TranslationManagerTestCase extends TestCase
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

    static
    function addTime($name, $start, $end)
    {
        if (!array_key_exists($name, self::$timing))
        {
            self::$timing[$name] = [];
            self::$timing[$name]['name'] = $name;
            self::$timing[$name]['total'] = 0;
            self::$timing[$name]['count'] = 0;
        }
        self::$timing[$name]['total'] += $end - $start;
        self::$timing[$name]['count']++;
    }

    static
    function timeIt($name, \Closure $test)
    {
        $start = microtime(true);
        $test();
        $end = microtime(true);
        self::addTime($name, $start, $end);
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
        if (!self::$timing)
        {
            echo "\n";
            return;
        }

        $class = get_called_class();
        echo "Timing results : $class\n";
        $s = sprintf("%20s %6s %9s %9s %4s\n", 'name', 'count', 'total', 'avg', '% best');
        echo str_repeat('=', strlen($s)) . "\n";
        echo $s;
        echo str_repeat('-', strlen($s)) . "\n";

        array_walk(self::$timing, function (&$value, $key)
        {
            $value['avg'] = round($value['total'] / $value['count'] * 1000000, 3);
        });

        usort(self::$timing, function ($a, $b)
        {
            $at = $a['avg'];
            $bt = $b['avg'];
            return $at === $bt ? 0 : ($at < $bt ? -1 : 1);
        });

        $best = self::$timing[0]['avg'];

        foreach (self::$timing as $timing)
        {
            printf("%20s %6d %7.3fms %7.3fus %4.1f%%\n", $timing['name'], $timing['count'],
                round($timing['total'] * 1000, 3),
                $timing['avg'],
                round($timing['avg'] / $best * 100, 3));
        }
        echo str_repeat('-', strlen($s)) . "\n\n";
    }

    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    //protected $baseUrl = 'http://localhost';
    //
    ///**
    // * Creates the application.
    // *
    // * @return \Illuminate\Foundation\Application
    // */
    //public function createApplication()
    //{
    //    $app = require __DIR__.'/../bootstrap/app.php';
    //
    //    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    //
    //    return $app;
    //}
}
