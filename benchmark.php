<?php

require_once 'vendor/autoload.php';
use YaLinqo\Enumerable as E;
use Ginq\Ginq as G;

function is_cli ()
{
    // PROMPT var is set by cmd, but not by PHPStorm.
    return php_sapi_name() == 'cli' && isset($_SERVER['PROMPT']);
}

function benchmark ($count, $operation)
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

function benchmark_linq ($name, $count, $opRaw, $opYaLinqo, $opGinq)
{
    $benches = [
        ['name' => "Raw PHP", 'op' => $opRaw],
        ['name' => "YaLinqo", 'op' => $opYaLinqo],
        ['name' => "Ginq", 'op' => $opGinq],
    ];
    echo "$name: ";
    foreach ($benches as $k => $bench) {
        $benches[$k] = array_merge($bench, benchmark($count, $bench['op']));
        echo ".";
    }
    if (is_cli())
        echo str_repeat(chr(8), 4) . "    ";
    echo "\n";
    $min = E::from($benches)->where('$v["result"]')->min('$v["time"]');
    foreach ($benches as $bench)
        echo "  " . str_pad($bench['name'], 10) .
            ($bench['result']
                ? number_format($bench['time'], 6) . " sec   x" . number_format($bench['time'] / $min, 1)
                : $bench['message'])
            . "\n";
}

function calc_math ($i)
{
    return pow(tan(sin($i) + cos($i)) + atan(sin($i) - cos($i)), $i);
}

function calc_array ($i)
{
    return ['a' => $i, 'b' => "$i", 'c' => $i + 100500, 'd' => array($i, $i, $i)];
}

function not_implemented ()
{
    throw new Exception("Not implemented");
}

$ITER_MAX = 1000;
$CALC_ARRAY = [];
for ($i = 0; $i < $ITER_MAX; $i++)
    $CALC_ARRAY[] = calc_array($i);

////////////////////////////////
// Iterate over $ITER_MAX ints /
////////////////////////////////

benchmark_linq("Iterate over $ITER_MAX ints", 100,
    function () use ($ITER_MAX) {
        $j = null;
        for ($i = 0; $i < $ITER_MAX; $i++)
            $j = $i;
        return $j;
    },
    function () use ($ITER_MAX) {
        $j = null;
        foreach (E::range(0, $ITER_MAX) as $i)
            $j = $i;
        return $j;
    },
    function () use ($ITER_MAX) {
        $j = null;
        foreach (G::range(0, $ITER_MAX) as $i)
            $j = $i;
        return $j;
    });

benchmark_linq("Iterate over $ITER_MAX ints and calculate floats", 100,
    function () use ($ITER_MAX) {
        $j = null;
        for ($i = 0; $i < $ITER_MAX; $i++)
            $j = calc_math($i);
        return $j;
    },
    function () use ($ITER_MAX) {
        $j = null;
        foreach (E::range(0, $ITER_MAX) as $i)
            $j = calc_math($i);
        return $j;
    },
    function () use ($ITER_MAX) {
        $j = null;
        foreach (G::range(0, $ITER_MAX) as $i)
            $j = calc_math($i);
        return $j;
    });

//////////////////////////////////////
// Generate array of $ITER_MAX items /
//////////////////////////////////////

benchmark_linq("Generate array of $ITER_MAX sequental integers", 100,
    function () use ($ITER_MAX) {
        $a = [];
        for ($i = 0; $i < $ITER_MAX; $i++)
            $a[] = $i;
    },
    function () use ($ITER_MAX) {
        E::range(0, $ITER_MAX)->toArray();
    },
    function () use ($ITER_MAX) {
        G::range(0, $ITER_MAX)->toArray();
    });

benchmark_linq("Generate array of $ITER_MAX calculated floats - anonymous function", 100,
    function () use ($ITER_MAX) {
        $a = [];
        for ($i = 0; $i < $ITER_MAX; $i++)
            $a[] = calc_math($i);
    },
    function () use ($ITER_MAX) {
        E::range(0, $ITER_MAX)->select(function ($i) { return calc_math($i); })->toArray();
    },
    function () use ($ITER_MAX) {
        G::range(0, $ITER_MAX)->select(function ($i) { return calc_math($i); })->toArray();
    });

benchmark_linq("Generate array of $ITER_MAX calculated floats - string lambda", 100,
    function () use ($ITER_MAX) {
        $a = [];
        for ($i = 0; $i < $ITER_MAX; $i++)
            $a[] = calc_math($i);
    },
    function () use ($ITER_MAX) {
        E::range(0, $ITER_MAX)->select('calc_math($v)')->toArray();
    },
    function () use ($ITER_MAX) {
        not_implemented();
    });

benchmark_linq("Generate array of $ITER_MAX calculated arrays - anonymous function", 100,
    function () use ($ITER_MAX) {
        $a = [];
        for ($i = 0; $i < $ITER_MAX; $i++)
            $a[] = calc_array($i);
    },
    function () use ($ITER_MAX) {
        E::range(0, $ITER_MAX)->select(function ($i) { return calc_array($i); })->toArray();
    },
    function () use ($ITER_MAX) {
        G::range(0, $ITER_MAX)->select(function ($i) { return calc_array($i); })->toArray();
    });

benchmark_linq("Generate array of $ITER_MAX calculated arrays - string lambda", 100,
    function () use ($ITER_MAX) {
        $a = [];
        for ($i = 0; $i < $ITER_MAX; $i++)
            $a[] = calc_array($i);
    },
    function () use ($ITER_MAX) {
        E::range(0, $ITER_MAX)->select('calc_array($v)')->toArray();
    },
    function () use ($ITER_MAX) {
        not_implemented();
    });

benchmark_linq("Generate array of $ITER_MAX calculated values from array - property path", 100,
    function () use ($ITER_MAX, $CALC_ARRAY) {
        $a = [];
        foreach ($CALC_ARRAY as $v)
            $a[] = $v['d'][0];
    },
    function () use ($ITER_MAX, $CALC_ARRAY) {
        E::from($CALC_ARRAY)->select('$v["d"][0]')->toArray();
    },
    function () use ($ITER_MAX, $CALC_ARRAY) {
        G::from($CALC_ARRAY)->select('[d][0]')->toArray();
    });

echo "Done!";