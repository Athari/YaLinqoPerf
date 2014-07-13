<?php

require_once 'vendor/autoload.php';
use YaLinqo\Enumerable as E;
use Ginq\Ginq as G;
use YaLinqo\Functions as F;

function is_cli ()
{
    // PROMPT var is set by cmd, but not by PHPStorm.
    return php_sapi_name() == 'cli' && isset($_SERVER['PROMPT']);
}

function benchmark_operation ($count, $operation)
{
    $time = microtime(true);
    $message = "Success";
    try {
        for ($i = 0; $i < $count; $i++)
            $operation();
        $result = true;
    }
    catch (Exception $e) {
        $result = false;
        $message = $e->getMessage();
    }
    return [
        'result' => $result,
        'message' => $message,
        'time' => (microtime(true) - $time) / $count,
    ];
}

function benchmark_array ($name, $count, $benches)
{
    $benches = E::from($benches)->select('[ "name" => $k, "op" => $v ]')->toArray();
    echo "\n$name ";
    foreach ($benches as $k => $bench) {
        $benches[$k] = array_merge($bench, benchmark_operation($count, $bench['op']));
        echo ".";
    }
    if (is_cli())
        echo str_repeat(chr(8), count($benches) + 1) . "    "; // remove progress dots with backspaces
    echo "\n" . str_repeat("-", strlen($name)) . "\n";
    $min = E::from($benches)->where('$v["result"]')->min('$v["time"]');
    foreach ($benches as $bench)
        echo "  " . str_pad($bench['name'], 35) .
            ($bench['result']
                ? number_format($bench['time'], 6) . " sec   "
                . "x" . number_format($bench['time'] / $min, 1) . " "
                . ($bench['time'] != $min
                    ? "(+" . number_format(($bench['time'] / $min - 1) * 100, 0) . "%)"
                    : "(minimum)"
                )
                : "* " . $bench['message'])
            . "\n";
}

function benchmark_linq ($name, $count, $opRaw, $opYaLinqo, $opGinq)
{
    benchmark_array($name, $count, [
        "PHP" => $opRaw,
        "YaLinqo" => $opYaLinqo,
        "Ginq" => $opGinq,
    ]);
}

function benchmark_linq_groups ($name, $count, $opsRaw, $opsYaLinqo, $opsGinq)
{
    benchmark_array($name, $count, E::from([
        "PHP    " => $opsRaw,
        "YaLinqo" => $opsYaLinqo,
        "Ginq   " => $opsGinq,
    ])
        ->selectMany(
            '$ops ==> $ops',
            '$op ==> $op',
            '($op, $name, $detail) ==> is_numeric($detail) ? $name : "$name [$detail]"')
        ->toArray());
}

function calc_math ($i)
{
    return pow(tan(sin($i) + cos($i)) + atan(sin($i) - cos($i)), $i);
}

function calc_array ($i)
{
    return [ 'a' => $i, 'b' => "$i", 'c' => $i + 100500, 'd' => [ $i, $i, $i ] ];
}

function not_implemented ()
{
    throw new Exception("Not implemented");
}

$ITER_MAX = 1000;
$CALC_ARRAY = [ ];
for ($i = 0; $i < $ITER_MAX; $i++)
    $CALC_ARRAY[] = calc_array($i);

////////////////////////////////
// Iterate over $ITER_MAX ints /
////////////////////////////////

benchmark_linq_groups("Iterate over $ITER_MAX ints", 100,
    [
        "for" =>
            function () use ($ITER_MAX) {
                $j = null;
                for ($i = 0; $i < $ITER_MAX; $i++)
                    $j = $i;
                return $j;
            },
        "array functions" =>
            function () use ($ITER_MAX) {
                $j = null;
                foreach (range(0, $ITER_MAX - 1) as $i)
                    $j = $i;
                return $j;
            },
    ],
    [
        function () use ($ITER_MAX) {
            $j = null;
            foreach (E::range(0, $ITER_MAX) as $i)
                $j = $i;
            return $j;
        },
    ],
    [
        function () use ($ITER_MAX) {
            $j = null;
            foreach (G::range(0, $ITER_MAX - 1) as $i)
                $j = $i;
            return $j;
        },
    ]);

benchmark_linq_groups("Iterate over $ITER_MAX ints, calculate floats and count", 100,
    [
        "for" =>
            function () use ($ITER_MAX) {
                $j = null;
                $n = 0;
                for ($i = 0; $i < $ITER_MAX; $i++) {
                    $j = calc_math($i);
                    $n++;
                }
                return [ $n, $j ];
            },
        "array functions" =>
            function () use ($ITER_MAX) {
                $a = range(0, $ITER_MAX - 1);
                $j = null;
                array_walk($a, function ($i) use (&$j) { $j = calc_math($i); });
                $n = count($a);
                return [ $n, $j ];
            },
    ],
    [
        function () use ($ITER_MAX) {
            $j = null;
            $n = E::range(0, $ITER_MAX)->call(function ($i) use (&$j) { $j = calc_math($i); })->count();
            return [ $n, $j ];
        },
    ],
    [
        function () use ($ITER_MAX) {
            $j = null;
            $n = G::range(0, $ITER_MAX - 1)->each(function ($i) use (&$j) { $j = calc_math($i); })->count();
            return [ $n, $j ];
        },
    ]);

//////////////////////////////////////
// Generate array of $ITER_MAX items /
//////////////////////////////////////

benchmark_linq_groups("Generate array of $ITER_MAX sequental integers", 100,
    [
        "for" =>
            function () use ($ITER_MAX) {
                $a = [ ];
                for ($i = 0; $i < $ITER_MAX; $i++)
                    $a[] = $i;
                return $a;
            },
        "array functions" =>
            function () use ($ITER_MAX) {
                return range(0, $ITER_MAX - 1);
            },
    ],
    [
        function () use ($ITER_MAX) {
            return E::range(0, $ITER_MAX)->toArray();
        },
    ],
    [
        function () use ($ITER_MAX) {
            return G::range(0, $ITER_MAX - 1)->toArray();
        },
    ]);

benchmark_linq_groups("Generate array of $ITER_MAX calculated floats", 100,
    [
        "for" =>
            function () use ($ITER_MAX) {
                $a = [ ];
                for ($i = 0; $i < $ITER_MAX; $i++)
                    $a[] = calc_math($i);
                return $a;
            },
        "array functions" =>
            function () use ($ITER_MAX) {
                return array_map(function ($i) { return calc_math($i); }, range(0, $ITER_MAX - 1));
            },
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX) {
                return E::range(0, $ITER_MAX)->select(function ($i) { return calc_math($i); })->toArray();
            },
        "string lambda" =>
            function () use ($ITER_MAX) {
                return E::range(0, $ITER_MAX)->select('calc_math($v)')->toArray();
            },
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX) {
                return G::range(0, $ITER_MAX - 1)->select(function ($i) { return calc_math($i); })->toArray();
            },
        "string lambda" =>
            function () use ($ITER_MAX) {
                not_implemented();
            },
    ]);

benchmark_linq_groups("Generate array of $ITER_MAX calculated arrays", 100,
    [
        "for" =>
            function () use ($ITER_MAX) {
                $a = [ ];
                for ($i = 0; $i < $ITER_MAX; $i++)
                    $a[] = calc_array($i);
                return $a;
            },
        "array functions" =>
            function () use ($ITER_MAX) {
                return array_map(function ($i) { return calc_array($i); }, range(0, $ITER_MAX - 1));
            },
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX) {
                return E::range(0, $ITER_MAX)->select(function ($i) { return calc_array($i); })->toArray();
            },
        "string lambda" =>
            function () use ($ITER_MAX) {
                return E::range(0, $ITER_MAX)->select('calc_array($v)')->toArray();
            },
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX) {
                return G::range(0, $ITER_MAX - 1)->select(function ($i) { return calc_array($i); })->toArray();
            },
        "string lambda" =>
            function () use ($ITER_MAX) {
                not_implemented();
            },
    ]);

benchmark_linq_groups("Generate array of $ITER_MAX calculated values from array", 100,
    [
        "for" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                $a = [ ];
                foreach ($CALC_ARRAY as $v)
                    $a[] = $v['d'][0];
            },
        "array functions" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                return array_column(array_column($CALC_ARRAY, 'd'), 0);
            },
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                E::from($CALC_ARRAY)->select(function ($v) { return $v["d"][0]; })->toArray();
            },
        "string lambda" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                E::from($CALC_ARRAY)->select('$v["d"][0]')->toArray();
            },
        "string property path" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                not_implemented();
            },
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                G::from($CALC_ARRAY)->select(function ($v) { return $v["d"][0]; })->toArray();
            },
        "string lambda" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                not_implemented();
            },
        "string property path" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                G::from($CALC_ARRAY)->select('[d][0]')->toArray();
            },
    ]);

/////////////////
// Dictionaries /
/////////////////

benchmark_linq_groups("Generate lookup of 100 floats, calculate sum", 100,
    [
        function () use ($ITER_MAX) {
            $dic = [ ];
            for ($i = 0; $i < $ITER_MAX; $i++)
                $dic[(string)tan($i % 100)][] = $i % 2 ? sin($i) : cos($i);
            $sum = 0;
            foreach ($dic as $g)
                foreach ($g as $v)
                    $sum += $v;
            return $sum;
        },
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX) {
                $dic = E::range(0, $ITER_MAX)->toLookup(
                    function ($v) { return (string)tan($v % 100); },
                    function ($v) { return $v % 2 ? sin($v) : cos($v); });
                return E::from($dic)->selectMany(F::$value)->sum();
            },
        "string lambda" =>
            function () use ($ITER_MAX) {
                $dic = E::range(0, $ITER_MAX)->toLookup('(string)tan($v % 100)', '$v % 2 ? sin($v) : cos($v)');
                return E::from($dic)->selectMany(F::$value)->sum();
            },
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX) {
                $dic = G::range(0, $ITER_MAX - 1)->toLookup(
                    function ($v) { return (string)tan($v % 100); },
                    function ($v) { return $v % 2 ? sin($v) : cos($v); });
                return G::from($dic)->selectMany(F::$value, F::$key)->sum();
            },
        "string lambda" =>
            function () use ($ITER_MAX) {
                not_implemented();
            },
    ]);

echo "\nDone!\n";