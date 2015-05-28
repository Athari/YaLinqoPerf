<?php

require_once 'vendor/autoload.php';
require_once 'data.php';
use YaLinqo\Enumerable as E;
use Ginq\Ginq as G;
use Pinq\Traversable as P;
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
    if (is_cli()) // remove progress dots with backspaces
        echo str_repeat(chr(8), count($benches) + 1) . str_repeat(' ', count($benches) + 1);
    echo "\n" . str_repeat("-", strlen($name)) . "\n";
    $min = E::from($benches)->where('$v["result"]')->min('$v["time"]');
    if ($min == 0)
        $min = 0.0001;
    foreach ($benches as $bench)
        echo "  " . str_pad($bench['name'], 35) .
            ($bench['result']
                ? number_format($bench['time'], 6) . " sec   "
                . "x" . number_format($bench['time'] / $min, 1) . " "
                . ($bench['time'] != $min
                    ? "(+" . number_format(($bench['time'] / $min - 1) * 100, 0, ".", "") . "%)"
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

function benchmark_linq_groups ($name, $count, $opsRaw, $opsYaLinqo, $opsGinq, $opsPinq)
{
    benchmark_array($name, $count, E::from([
        "PHP    " => $opsRaw,
        "YaLinqo" => $opsYaLinqo,
        "Ginq   " => $opsGinq,
        "Pinq   " => $opsPinq,
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

$ITER_MAX = isset($_SERVER['argv'][1]) ? (int)$_SERVER['argv'][1] : 100;
$CALC_ARRAY = [ ];
for ($i = 0; $i < $ITER_MAX; $i++)
    $CALC_ARRAY[] = calc_array($i);
$DATA_CATEGORIES = generate_product_categories(1000);
$DATA_PRODUCTS = generate_products($ITER_MAX * 100, $DATA_CATEGORIES);

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
    ],
    [
        function () use ($ITER_MAX) {
            $j = null;
            foreach (P::from(range(0, $ITER_MAX - 1)) as $i)
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
    ],
    [
        function () use ($ITER_MAX) {
            // NOTE No foreach method, the best approximation.
            $j = null;
            $n = P::from(range(0, $ITER_MAX - 1))->select(function ($i) use (&$j) { $j = calc_math($i); })->count();
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
    ],
    [
        function () use ($ITER_MAX) {
            return P::from(range(0, $ITER_MAX - 1))->asArray();
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
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX) {
                return P::from(range(0, $ITER_MAX - 1))->select(function ($i) { return calc_math($i); })->asArray();
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
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX) {
                return P::from(range(0, $ITER_MAX - 1))->select(function ($i) { return calc_array($i); })->asArray();
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
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                G::from($CALC_ARRAY)->select(function ($v) { return $v["d"][0]; })->toArray();
            },
        "string property path" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                G::from($CALC_ARRAY)->select('[d][0]')->toArray();
            },
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX, $CALC_ARRAY) {
                P::from($CALC_ARRAY)->select(function ($v) { return $v["d"][0]; })->asArray();
            },
    ]);

/////////////////
// Dictionaries /
/////////////////

benchmark_linq_groups("Generate lookup of $ITER_MAX floats, calculate sum", 100,
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
                return E::from($dic)->selectMany('$v')->sum();
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
    ],
    [
        "anonymous function" =>
            function () use ($ITER_MAX) {
                not_implemented();
            },
    ]);

benchmark_linq_groups("Process data from ReadMe example", 1,
    [
        function () use ($DATA_CATEGORIES, $DATA_PRODUCTS) {
            $productsSorted = [ ];
            foreach ($DATA_PRODUCTS as $product) {
                if ($product['quantity'] > 0) {
                    if (empty($productsSorted[$product['catId']]))
                        $productsSorted[$product['catId']] = [ ];
                    $productsSorted[$product['catId']][] = $product;
                }
            }
            foreach ($productsSorted as $catId => $products) {
                usort($productsSorted[$catId], function ($a, $b) {
                    $diff = $a['quantity'] - $b['quantity'];
                    if ($diff != 0)
                        return -$diff;
                    $diff = strcmp($a['name'], $b['name']);
                    return $diff;
                });
            }
            $result = [ ];
            usort($DATA_CATEGORIES, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            foreach ($DATA_CATEGORIES as $category) {
                $result[$category['id']] = [
                    'name' => $category['name'],
                    'products' => $productsSorted[$category['id']]
                ];
            }
        },
    ],
    [
        "anonymous function" =>
            function () use ($DATA_CATEGORIES, $DATA_PRODUCTS) {
                E::from($DATA_CATEGORIES)
                    ->orderBy(function ($cat) { return $cat['name']; })
                    ->groupJoin(
                        from($DATA_PRODUCTS)
                            ->where(function ($prod) { return $prod['quantity'] > 0; })
                            ->orderByDescending(function ($prod) { return $prod['quantity']; })
                            ->thenBy(function ($prod) { return $prod['name']; }),
                        function ($cat) { return $cat['id']; },
                        function ($prod) { return $prod['catId']; },
                        function ($cat, E $prods) {
                            return array(
                                'name' => $cat['name'],
                                'products' => $prods->toArray()
                            );
                        }
                    )
                    ->toArray();
            },
        "string lambda" =>
            function () use ($DATA_CATEGORIES, $DATA_PRODUCTS) {
                E::from($DATA_CATEGORIES)
                    ->orderBy('$cat ==> $cat["name"]')
                    ->groupJoin(
                        from($DATA_PRODUCTS)
                            ->where('$prod ==> $prod["quantity"] > 0')
                            ->orderByDescending('$prod ==> $prod["quantity"]')
                            ->thenBy('$prod ==> $prod["name"]'),
                        '$cat ==> $cat["id"]', '$prod ==> $prod["catId"]',
                        '($cat, $prods) ==> [
                            "name" => $cat["name"],
                            "products" => $prods->toArray()
                        ]'
                    )
                    ->toArray();
            },
    ],
    [
        "anonymous function" =>
            function () use ($DATA_CATEGORIES, $DATA_PRODUCTS) {
                G::from($DATA_CATEGORIES)
                    ->orderBy(function ($cat) { return $cat['name']; })
                    ->groupJoin(
                        G::from($DATA_PRODUCTS)
                            ->where(function ($prod) { return $prod['quantity'] > 0; })
                            ->orderByDesc(function ($prod) { return $prod['quantity']; })
                            ->thenBy(function ($prod) { return $prod['name']; }),
                        function ($cat) { return $cat['id']; },
                        function ($prod) { return $prod['catId']; },
                        function ($cat, G $prods) {
                            return array(
                                'name' => $cat['name'],
                                'products' => $prods->toArray()
                            );
                        }
                    )
                    ->toArray();
            },
    ],
    [
        "anonymous function" =>
            function () use ($DATA_CATEGORIES, $DATA_PRODUCTS) {
                P::from($DATA_CATEGORIES)
                    ->orderByAscending(function ($cat) { return $cat['name']; })
                    ->groupJoin(
                        P::from($DATA_PRODUCTS)
                            ->where(function ($prod) { return $prod['quantity'] > 0; })
                            ->orderByDescending(function ($prod) { return $prod['quantity']; })
                            ->thenByAscending(function ($prod) { return $prod['name']; })
                    )
                    ->on(function ($cat, $prod) { return $cat['id'] == $prod['catId']; })
                    ->to(function ($cat, P $prods) {
                        return array(
                            'name' => $cat['name'],
                            'products' => $prods->asArray()
                        );
                    })
                    ->asArray();
            },
    ]);

echo "\nDone!\n";