<?php namespace Vsch\Tests;

use Vsch\TranslationManager\Classes\PathTemplateResolver;

class PathTemplateResolverTest extends \Vsch\Tests\TranslationManagerTestCase
{
    protected $pathResolver;

    public function setUp()
    {
    }

    public function tearDown()
    {
    }

    /**
     * @coversNothing
     *
     * @dataProvider    getMergeConfigProvider
     * @var array $config
     * @var array $default
     * @var array $expectedResult
     * @return void
     *
     */
    //public
    //function testMergeConfig($config, $default, $expectedResult)
    //{
    //    self::startTimer('array_replace_recursive', microtime(true));
    //    $result = array_replace_recursive($default, $config);
    //    self::endTimer('array_replace_recursive', microtime(true));
    //
    //    $this->assertEquals($expectedResult, $result, "Mismatch in result");
    //}

    /**
     * A basic functional test example.
     *
     * @return array()
     */
    public function getMergeConfigProvider()
    {
        // @formatter:off
        return [
            /* 0 */[
                ['test' => 'test1', 'num' => 1, 'abc' => 'abc1', 'hij' => ['hijk1' => 'hijk11', 'hijk2' => 'hijk12', 'hijk3' => 'hijk13',],],
                ['test' => 'test2', 'num' => 2, 'num2' => 2, 'def' => 'def2', 'hij' => ['hijk1' => 'hijk21', 'hijk3' => 'hijk23', 'hijk4' => 'hijk24',],],
                ['test' => 'test1', 'num' => 1, 'abc' => 'abc1', 'hij' => ['hijk1' => 'hijk11', 'hijk2' => 'hijk12', 'hijk3' => 'hijk13', 'hijk4' => 'hijk24',], 'num2' => 2, 'def' => 'def2',],
            ],
            /* 1 */[
                [],
                ['test' => 'test2', 'num' => 2, 'num2' => 2, 'def' => 'def2', 'hij' => ['hijk1' => 'hijk21', 'hijk3' => 'hijk23', 'hijk4' => 'hijk24',],],
                ['test' => 'test2', 'num' => 2, 'num2' => 2, 'def' => 'def2', 'hij' => ['hijk1' => 'hijk21', 'hijk3' => 'hijk23', 'hijk4' => 'hijk24',],],
            ],
            /* 2 */[
                ['test' => 'test2', 'num' => 2, 'num2' => 2, 'def' => 'def2', 'hij' => ['hijk1' => 'hijk21', 'hijk3' => 'hijk23', 'hijk4' => 'hijk24',],],
                [],
                ['test' => 'test2', 'num' => 2, 'num2' => 2, 'def' => 'def2', 'hij' => ['hijk1' => 'hijk21', 'hijk3' => 'hijk23', 'hijk4' => 'hijk24',],],
            ],
        ];
        // @formatter:on
    }

    /**
     * @covers       PathTemplateResolver::normalizeInclude
     *
     * @dataProvider getNormalizeIncludeProvider
     * @var array $config
     * @var array $expectedResult
     * @return void
     */
    public function testNormalizeInclude($config, $expectedResult)
    {
        self::startTimer('normalizeInclude', microtime(true));
        $result = PathTemplateResolver::normalizeInclude($config);
        self::endTimer('normalizeInclude', microtime(true));

        $this->assertEquals($expectedResult, $result, "Mismatch in result");
    }

    /**
     * A basic functional test example.
     *
     * @return array()
     */
    public function getNormalizeIncludeProvider()
    {
        // @formatter:off
        return [
            /* 0 */[
                [],
                [],
            ],
            /* 1 */[
                ['include' => '', 'vars' => [],],
                ['include' => [], 'vars' => [],],
            ],
            /* 2 */[
                ['include' => '/', 'vars' => [],],
                ['include' => ['*/*'], 'vars' => [],],
            ],
            /* 3 */[
                ['include' => '', 'vars' => ['{vendor}' => null,],],
                ['include' => [], 'vars' => ['{vendor}' => null,],],
            ],
            /* 4 */[
                ['include' => '/', 'vars' => ['{vendor}' => null,],],
                ['include' => ['*'], 'vars' => ['{vendor}' => null,],],
            ],
            /* 5 */[
                ['include' => '/', 'vars' => ['{vendor}' => null, '{package}' => 'def',],],
                ['include' => ['*'], 'vars' => ['{vendor}' => null, '{package}' => 'def',],],
            ],
            /* 6 */[
                ['include' => '/', 'vars' => ['{vendor}' => 'abc', '{package}' => 'def',],],
                ['include' => ['*/*'], 'vars' => ['{vendor}' => 'abc', '{package}' => 'def',],],
            ],
            /* 7 */[
                ['include' => ['/', 'abc/', '/def', 'abc/def',], 'vars' => [],],
                ['include' => ['*/*', 'abc/*', '*/def', 'abc/def',], 'vars' => [],],
            ],
            /* 8 */[
                ['include' => '/', 'vars' => ['{vendor}' => null,],],
                ['include' => ['*'], 'vars' => ['{vendor}' => null,],],
            ],
            /* 9 */[
                ['include' => ['', '/', 'abc/', '/def', 'abc/def',], 'vars' => ['{vendor}' => null, '{package}' => null,],],
                ['include' => ['', '/', 'abc/', '/def', 'abc/def',], 'vars' => ['{vendor}' => null, '{package}' => null,],],
            ],
        ];
        // @formatter:on
    }

    /**
     * @covers       PathTemplateResolver::configValues
     *
     * @dataProvider getConfigValuesProvider
     *
     * @param array  $config
     * @param string $setting
     * @param array  $expectedResult
     */
    public function testConfigValues($config, $setting, $expectedResult)
    {
        self::startTimer('configValues', microtime(true));
        $result = PathTemplateResolver::configValues($config, $setting);
        self::endTimer('configValues', microtime(true));

        $this->assertEquals($expectedResult, $result, "Mismatch in result");
    }

    /**
     * A basic functional test example.
     *
     * @return array()
     */
    public function getConfigValuesProvider()
    {
        // @formatter:off
        return [
            /* 0 */[
                [
                    'section 1' => ['include' => 'include 1', 'path' => 'path1', 'vars' => ['{vendor}' => 'abc',]],
                    'section 2' => ['include' => 'include 2', 'path' => 'path2', 'vars' => ['{vendor}' => 'abc',]],
                    'section 3' => 'path3',
                    'section 4' => ['include' => 'include 1', 'path' => 'path1', 'vars' => ['{vendor}' => 'abc',]],
                    'section 5' => 'path3',
                ],
                'path',
                [
                    'path1' => ['include' => 'include 1', 'path' => 'path1', 'vars' => ['{vendor}' => 'abc',], 'section' => 'section 1',],
                    'path2' => ['include' => 'include 2', 'path' => 'path2', 'vars' => ['{vendor}' => 'abc',], 'section' => 'section 2',],
                    'path3' => ['section' => 'section 3', 'path' => 'path3',],
                ],
            ],
            /* 1 */[
                [
                    'section 1' => ['include' => 'include 1', 'path' => 'path1', 'vars' => ['{vendor}' => 'abc',]],
                    'section 2' => ['include' => 'include 2', 'path' => 'path2', 'vars' => ['{vendor}' => 'abc',]],
                    'section 3' => 'path3',
                    'section 4' => ['include' => 'include 1', 'path' => 'path1', 'vars' => ['{vendor}' => 'abc',]],
                    'section 5' => 'path3',
                ],
                'include',
                [
                    'include 1' => [
                        'include' => 'include 1',
                        'path' => 'path1',
                        'vars' => ['{vendor}' => 'abc',],
                        'section' => 'section 1',
                    ],
                    'include 2' => [
                        'include' => 'include 2',
                        'path' => 'path2',
                        'vars' => ['{vendor}' => 'abc',],
                        'section' => 'section 2',
                    ],
                ],
            ],
            /* 2 */[
                [
                    'section 1' => ['include' => 'include 1', 'path' => 'path1', 'vars' => ['{vendor}' => 'abc',]],
                    'section 2' => ['include' => 'include 2', 'path' => 'path2', 'vars' => ['{vendor}' => 'abc',]],
                    'section 3' => 'path3',
                    'section 4' => ['include' => 'include 1', 'path' => 'path1', 'vars' => ['{vendor}' => 'abc',]],
                    'section 5' => 'path3',
                ],
                'vars',
                [
                ],
            ],
        ];
        // @formatter:on
    }

    /**
     * @covers       PathTemplateResolver::isPathIncluded
     *
     * @dataProvider getIsPathIncludedProvider
     *
     * @param array $config
     * @param array $vars
     * @param bool  $partial
     * @param bool  $expectedResult
     */
    public function testIsPathIncluded($config, $vars, $partial, $expectedResult)
    {
        self::startTimer('isPathIncluded', microtime(true));
        $result = PathTemplateResolver::isPathIncluded($config, $vars, $partial);
        self::endTimer('isPathIncluded', microtime(true));
        if ($result != $expectedResult) {
            PathTemplateResolver::isPathIncluded($config, $vars, $partial);
        }
        $this->assertEquals($expectedResult, $result, "Mismatch in result");
    }

    /**
     * A basic functional test example.
     *
     * @return array()
     */
    public function getIsPathIncludedProvider()
    {
        // @formatter:off
        return [
            // no vendor or package in section, include ignored, partials allowed
        /*  0 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => ''], true, true,],
        /*  1 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => ''], true, true,],
        /*  2 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], [], true, true,],
        /*  3 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /*  4 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc',], true, false,],
        /*  5 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /*  6 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => 'def'], true, false,],
        /*  7 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => ''], true, true,],
        /*  8 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => ''], true, true,],
        /*  9 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], [], true, true,],
        /* 10 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 11 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc',], true, false,],
        /* 12 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 13 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => 'def'], true, false,],
        /* 14 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => ''], true, true,],
        /* 15 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => ''], true, true,],
        /* 16 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], [], true, true,],
        /* 17 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 18 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc',], true, false,],
        /* 19 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 20 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => 'def'], true, false,],

        // no vendor or package in section, include ignored, partials not allowed
        /* 21 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => ''], false, true,],
        /* 22 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => ''], false, false,],
        /* 23 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], [], false, false,],
        /* 24 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 25 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc',], false, false,],
        /* 26 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 27 */   [['vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => 'def'], false, false,],
        /* 28 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => ''], false, true,],
        /* 29 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => ''], false, false,],
        /* 30 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], [], false, false,],
        /* 31 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 32 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc',], false, false,],
        /* 33 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 34 */   [['include' => [], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => 'def'], false, false,],
        /* 35 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => ''], false, true,],
        /* 36 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => ''], false, false,],
        /* 37 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], [], false, false,],
        /* 38 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 39 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc',], false, false,],
        /* 40 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 41 */   [['include' => ['*', '*/*', 'def', 'abc/*', '*/def', ], 'vars' => ['{vendor}' => '', '{package}' => '',],], ['{package}' => 'def'], false, false,],

        // no vendor in section, include must match, partials allowed
        /* 42 */   [['vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 43 */   [['vars' => ['{vendor}' => '', ],], ['{package}' => ''], true, false,],
        /* 44 */   [['vars' => ['{vendor}' => '', ],], [], true, true,],
        /* 45 */   [['vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 46 */   [['vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc',], true, false,],
        /* 47 */   [['vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 48 */   [['vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], true, false,],
        /* 49 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 50 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{package}' => ''], true, false,],
        /* 51 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], [], true, true,],
        /* 52 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 53 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc',], true, false,],
        /* 54 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 55 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], true, false,],
        /* 56 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 57 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{package}' => ''], true, false,],
        /* 58 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], [], true, true,],
        /* 59 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], true, true,],
        /* 60 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc',], true, false,],
        /* 61 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 62 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], true, true,],
        /* 63 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 64 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{package}' => ''], true, false,],
        /* 65 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], [], true, true,],
        /* 66 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], true, true,],
        /* 67 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc',], true, false,],
        /* 68 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 69 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], true, true,],
        /* 70 */   [['include' => ['abc', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 71 */   [['include' => ['abc', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], true, false,],

        // no vendor in section, include must match, partials not allowed
        /* 72 */   [['vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 73 */   [['vars' => ['{vendor}' => '', ],], ['{package}' => ''], false, false,],
        /* 74 */   [['vars' => ['{vendor}' => '', ],], [], false, false,],
        /* 75 */   [['vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 76 */   [['vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc',], false, false,],
        /* 77 */   [['vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 78 */   [['vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], false, false,],
        /* 79 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 80 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{package}' => ''], false, false,],
        /* 81 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], [], false, false,],
        /* 82 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 83 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc',], false, false,],
        /* 84 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 85 */   [['include' => [], 'vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], false, false,],
        /* 86 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 87 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{package}' => ''], false, false,],
        /* 88 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], [], false, false,],
        /* 89 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], false, true,],
        /* 90 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc',], false, false,],
        /* 91 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 92 */   [['include' => ['*',], 'vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], false, true,],
        /* 93 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 94 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{package}' => ''], false, false,],
        /* 95 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], [], false, false,],
        /* 96 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], false, true,],
        /* 97 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc',], false, false,],
        /* 98 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 99 */   [['include' => ['abc', 'def', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], false, true,],
        /* 100 */   [['include' => ['abc', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 101 */   [['include' => ['abc', 'ghi', ], 'vars' => ['{vendor}' => '', ],], ['{package}' => 'def'], false, false,],

        // vendor and package expected in section, include must match, partials allowed
        /* 102 */   [['vars' => [],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 103 */   [['vars' => [],], ['{package}' => ''], true, false,],
        /* 104 */   [['vars' => [],], [], true, true,],
        /* 105 */   [['vars' => [],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 106 */   [['vars' => [],], ['{vendor}' => 'abc',], true, false,],
        /* 107 */   [['vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 108 */   [['vars' => [],], ['{package}' => 'def'], true, false,],
        /* 109 */   [['include' => [], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 110 */   [['include' => [], 'vars' => [],], ['{package}' => ''], true, false,],
        /* 111 */   [['include' => [], 'vars' => [],], [], true, true,],
        /* 112 */   [['include' => [], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 113 */   [['include' => [], 'vars' => [],], ['{vendor}' => 'abc',], true, false,],
        /* 114 */   [['include' => [], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 115 */   [['include' => [], 'vars' => [],], ['{package}' => 'def'], true, false,],
        /* 116 */   [['include' => ['*/*',], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 117 */   [['include' => ['*/*',], 'vars' => [],], ['{package}' => ''], true, false,],
        /* 118 */   [['include' => ['*/*',], 'vars' => [],], [], true, true,],
        /* 119 */   [['include' => ['*/*',], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 120 */   [['include' => ['*/*',], 'vars' => [],], ['{vendor}' => 'abc',], true, true,],
        /* 121 */   [['include' => ['*/*',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 122 */   [['include' => ['*/*',], 'vars' => [],], ['{package}' => 'def'], true, true,],
        /* 123 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 124 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{package}' => ''], true, false,],
        /* 125 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], [], true, true,],
        /* 126 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 127 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => 'abc',], true, true,],
        /* 128 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 129 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{package}' => 'def'], true, true,],
        /* 130 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], true, true,],
        /* 131 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 132 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{package}' => ''], true, false,],
        /* 133 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], [], true, true,],
        /* 134 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 135 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => 'abc',], true, true,],
        /* 136 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 137 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{package}' => 'def'], true, true,],
        /* 138 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], true, true,],
        /* 139 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], true, false,],
        /* 140 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{package}' => ''], true, false,],
        /* 141 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], [], true, true,],
        /* 142 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], true, false,],
        /* 143 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => 'abc',], true, true,],
        /* 144 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], true, false,],
        /* 145 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{package}' => 'def'], true, true,],
        /* 146 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], true, false,],
        /* 146 */   [['include' => ['xyz/*', ], 'vars' => [],], ['{vendor}' => 'abc',], true, false,],
        /* 147 */   [['include' => ['*/pqr', ], 'vars' => [],], ['{package}' => 'def'], true, false,],
        /* 148 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], true, false,],
        /* 149 */   [['include' => ['abc/def'], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], true, true,],

        // vendor and package expected in section, include must match, partials not allowed
        /* 150 */   [['vars' => [],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 151 */   [['vars' => [],], ['{package}' => ''], false, false,],
        /* 152 */   [['vars' => [],], [], false, false,],
        /* 153 */   [['vars' => [],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 154 */   [['vars' => [],], ['{vendor}' => 'abc',], false, false,],
        /* 155 */   [['vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 156 */   [['vars' => [],], ['{package}' => 'def'], false, false,],
        /* 157 */   [['include' => [], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 158 */   [['include' => [], 'vars' => [],], ['{package}' => ''], false, false,],
        /* 159 */   [['include' => [], 'vars' => [],], [], false, false,],
        /* 160 */   [['include' => [], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 161 */   [['include' => [], 'vars' => [],], ['{vendor}' => 'abc',], false, false,],
        /* 162 */   [['include' => [], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 163 */   [['include' => [], 'vars' => [],], ['{package}' => 'def'], false, false,],
        /* 164 */   [['include' => ['*/*',], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 165 */   [['include' => ['*/*',], 'vars' => [],], ['{package}' => ''], false, false,],
        /* 166 */   [['include' => ['*/*',], 'vars' => [],], [], false, false,],
        /* 167 */   [['include' => ['*/*',], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 168 */   [['include' => ['*/*',], 'vars' => [],], ['{vendor}' => 'abc',], false, false,],
        /* 169 */   [['include' => ['*/*',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 170 */   [['include' => ['*/*',], 'vars' => [],], ['{package}' => 'def'], false, false,],
        /* 171 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 172 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{package}' => ''], false, false,],
        /* 173 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], [], false, false,],
        /* 174 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 175 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => 'abc',], false, false,],
        /* 176 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 177 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{package}' => 'def'], false, false,],
        /* 178 */   [['include' => ['xyz/*', 'abc/*',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], false, true,],
        /* 179 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 180 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{package}' => ''], false, false,],
        /* 181 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], [], false, false,],
        /* 182 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 183 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => 'abc',], false, false,],
        /* 184 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 185 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{package}' => 'def'], false, false,],
        /* 186 */   [['include' => ['xyz/*', '*/def',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], false, true,],
        /* 187 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => '', '{package}' => ''], false, false,],
        /* 188 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{package}' => ''], false, false,],
        /* 189 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], [], false, false,],
        /* 190 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => '', '{package}' => 'def'], false, false,],
        /* 191 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => 'abc',], false, false,],
        /* 192 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => ''], false, false,],
        /* 193 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{package}' => 'def'], false, false,],
        /* 194 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], false, false,],
        /* 194 */   [['include' => ['xyz/*', ], 'vars' => [],], ['{vendor}' => 'abc',], false, false,],
        /* 195 */   [['include' => ['*/pqr', ], 'vars' => [],], ['{package}' => 'def'], false, false,],
        /* 196 */   [['include' => ['xyz/*', '*/pqr',], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], false, false,],
        /* 197 */   [['include' => ['abc/def'], 'vars' => [],], ['{vendor}' => 'abc', '{package}' => 'def'], false, true,],
        ];
        // @formatter:on
    }

    /**
     * @covers       PathTemplateResolver::expandVars
     *
     * @dataProvider getExpandVarsProvider
     * @var string $text
     * @var array  $vars
     * @var string $expectedResult
     * @return void
     */
    public function testExpandVars($text, $vars, $expectedResult)
    {
        self::startTimer('expandVars', microtime(true));
        $result = PathTemplateResolver::expandVars($text, $vars);
        self::endTimer('expandVars', microtime(true));
        if ($result !== $expectedResult) {
            PathTemplateResolver::expandVars($text, $vars);
        }
        $this->assertSame($expectedResult, $result, "Mismatch in result");
    }

    /**
     * A basic functional test example.
     *
     * @return array()
     */
    public function getExpandVarsProvider()
    {
        return [
            /* 0 */ ['', ['{var1}' => 'var1', '{var2}' => 'var2', '{var3}' => 'var3',], ''],
            /* 1 */ ['abc', ['{var1}' => 'var1', '{var2}' => 'var2', '{var3}' => 'var3',], 'abc'],
            /* 2 */ ['{var1}{var2}{var3}', ['{var1}' => 'var1', '{var2}' => 'var2', '{var3}' => 'var3',], 'var1var2var3'],
            /* 3 */ ['{var1} {var2} {var3}', ['{var1}' => 'var1', '{var2}' => 'var2', '{var3}' => 'var3',], 'var1 var2 var3'],
        ];
    }

    /**
     * @covers       PathTemplateResolver::extractTemplateVars
     *
     * @dataProvider getExtractTemplateVarsProvider
     * @var array $template
     * @var array $text
     * @var array $expectedResult
     * @return void
     */
    public function testExtractTemplateVars($template, $text, $expectedResult)
    {
        self::startTimer('extractTemplateVars', microtime(true));
        $result = PathTemplateResolver::extractTemplateVars($template, $text);
        self::endTimer('extractTemplateVars', microtime(true));
        if ($result !== $expectedResult) {
            PathTemplateResolver::extractTemplateVars($template, $text);
        }
        $this->assertSame($expectedResult, $result, "Mismatch in result");
    }

    /**
     * A basic functional test example.
     *
     * @return array()
     */
    public function getExtractTemplateVarsProvider()
    {
        // @formatter:off
        return [
            /* 0 */ ['{vendor}.{package}::{group}', 'vendor.package::group', ['{vendor}' => 'vendor', '{package}' => 'package', '{group}' => 'group',]],
            /* 1 */ ['{vendor}.{package}::{group}', 'vendor.package::group.group', ['{vendor}' => 'vendor', '{package}' => 'package', '{group}' => 'group.group',]],
            /* 2 */ ['prefix:{vendor}.{package}::{group}', 'prefix:vendor.package::group.group', ['{vendor}' => 'vendor', '{package}' => 'package', '{group}' => 'group.group',]],
            /* 3 */ ['prefix:{vendor}.{package}::{group}', 'prefix:vendor.package::group', ['{vendor}' => 'vendor', '{package}' => 'package', '{group}' => 'group',]],
            /* 4 */ ['prefix:{vendor}.{package}::{group}', 'vendor.package::group.group', null],
            /* 5 */ ['prefix:{vendor}.{package}::{group}', 'vendor.package::group', null],
        ];
        // @formatter:on
    }
}
