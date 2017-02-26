<?php namespace Vsch\Tests;

class TranslationManagerTestCase extends \Illuminate\Foundation\Testing\TestCase
{
    private static $timing;
    private static $timers;
    private static $timeItOverhead;
    private static $startEndOverhead;

    static function startTimer($name, $time)
    {
        self::$timers[$name] = $time;
    }

    static function endTimer($name, $end)
    {
        $start = self::$timers[$name];
        unset(self::$timers[$name]);
        self::addTime($name, $start, $end, self::$startEndOverhead);
    }

    static function addTime($name, $start, $end, $overhead)
    {
        if (!array_key_exists($name, self::$timing)) {
            self::$timing[$name] = [];
            self::$timing[$name]['name'] = $name;
            self::$timing[$name]['total'] = 0;
            self::$timing[$name]['count'] = 0;
        }
        self::$timing[$name]['total'] += $end - $start - $overhead;
        self::$timing[$name]['count']++;
    }

    static function timeIt($name, \Closure $test)
    {
        $start = microtime(true);
        $test();
        $end = microtime(true);
        self::addTime($name, $start, $end, self::$timeItOverhead);
    }

    public static function setupBeforeClass()
    {
        parent::setUpBeforeClass();

        $iMax = 100;
        self::$timeItOverhead = 0;
        self::$startEndOverhead = 0;
        self::$timers = [];
        self::$timing = [];
        for ($i = 0; $i < $iMax; $i++) {
            self::timeIt('$timeItOverhead', function () {
            });
            self::startTimer('$startEndOverhead', microtime(true));
            self::endTimer('$startEndOverhead', microtime(true));
        }
        self::$timeItOverhead = self::$timing['$timeItOverhead']['total'] / $iMax;
        self::$startEndOverhead = self::$timing['$startEndOverhead']['total'] / $iMax;
        self::$timers = [];
        self::$timing = [];
    }

    public static function tearDownAfterClass()
    {
        if (!self::$timing) {
            echo "\n";
            return;
        }

        $class = get_called_class();
        echo "Timing results : $class\n";
        $s = sprintf("%40s %6s %9s %9s %4s\n", 'name', 'count', 'total', 'avg', '% best');
        echo str_repeat('=', strlen($s)) . "\n";
        echo $s;
        echo str_repeat('-', strlen($s)) . "\n";

        array_walk(self::$timing, function (&$value, $key) {
            $value['avg'] = round($value['total'] / $value['count'] * 1000000, 3);
        });

        usort(self::$timing, function ($a, $b) {
            $at = $a['avg'];
            $bt = $b['avg'];
            return $at === $bt ? 0 : ($at < $bt ? -1 : 1);
        });

        $best = self::$timing[0]['avg'];

        foreach (self::$timing as $timing) {
            printf("%40s %6d %7.3fms %7.3fus %4.1f%%\n", $timing['name'], $timing['count'],
                round($timing['total'] * 1000, 3),
                $timing['avg'],
                round($timing['avg'] / $best * 100, 3));
        }
        echo str_repeat('-', strlen($s)) . "\n\n";
        printf("\nTiming compensated for avg overhead for: timeIt of %.3fus and startTimer/endTimer of %.3fus per invocation\n\n", self::$timeItOverhead * 1000000, self::$startEndOverhead * 1000000);

        parent::tearDownAfterClass();
    }

    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    //protected $baseUrl = 'http://localhost';

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../../../../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
