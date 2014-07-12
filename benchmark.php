<?php

require_once 'vendor/autoload.php';
use YaLinqo\Enumerable;
use Ginq\Ginq;

function benchmark ($name, $count, $operation)
{
    $time = microtime(true);
    for ($i = 0; $i < $count; $i++)
        $operation();
    return (microtime(true) - $time) / $count;
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
        $benches[$k] = array_merge($bench, ['time' => benchmark($bench['name'], $count, $bench['op'])]);
        echo ".";
    }
    echo "\n";
    $min = Enumerable::from($benches)->min('$v["time"]');
    foreach ($benches as $bench)
        echo "  " . str_pad($bench['name'], 10) . number_format($bench['time'], 6) . " sec   x" . number_format($bench['time'] / $min, 1) . "\n";
}

$ITER_MAX = 10000;

benchmark_linq("Iterate from 1 to $ITER_MAX", 100,
    function () use ($ITER_MAX) {
        $j = null;
        for ($i = 0; $i < $ITER_MAX; $i++)
            $j = $i;
    },
    function () use ($ITER_MAX) {
        $j = null;
        foreach (Enumerable::range(0, $ITER_MAX) as $i)
            $j = $i;
    },
    function () use ($ITER_MAX) {
        $j = null;
        foreach (Ginq::range(0, $ITER_MAX) as $i)
            $j = $i;
    });

benchmark_linq("Get array from 1 to $ITER_MAX", 100,
    function () use ($ITER_MAX) {
        $a = [];
        for ($i = 0; $i < $ITER_MAX; $i++)
            $a[] = $i;
    },
    function () use ($ITER_MAX) {
        Enumerable::range(0, $ITER_MAX)->toArray();
    },
    function () use ($ITER_MAX) {
        Ginq::range(0, $ITER_MAX)->toArray();
    });

echo "Done!";