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
        echo "  " . str_pad($bench['name'], 28) .
            ($bench['result']
                ? number_format($bench['time'], 5) . " sec   "
                . "x" . number_format($bench['time'] / $min, 1) . " "
                . ($bench['time'] != $min
                    ? "(+" . number_format(($bench['time'] / $min - 1) * 100, 0, ".", "") . "%)"
                    : "(100%)")
                : "* " . $bench['message'])
            . "\n";
}

function benchmark_linq_groups ($name, $count, $opsPhp, $opsYaLinqo, $opsGinq, $opsPinq)
{
    $benches = E::from([
        "PHP    " => $opsPhp,
        "YaLinqo" => $opsYaLinqo,
        "Ginq   " => $opsGinq,
        "Pinq   " => $opsPinq,
    ])->selectMany(
        '$ops ==> $ops',
        '$op ==> $op',
        '($op, $name, $detail) ==> is_numeric($detail) ? $name : "$name [$detail]"'
    );
    benchmark_array($name, $count, $benches);
}

function not_implemented ()
{
    throw new Exception("Not implemented");
}

function consume ($array, $props = null)
{
    foreach ($array as $k => $v)
        if ($props !== null)
            foreach ($props as $prop => $subprops)
                consume($v[$prop], $subprops);
}

//------------------------------------------------------------------------------

$ITER_MAX = isset($_SERVER['argv'][1]) ? (int)$_SERVER['argv'][1] : 100;
$DATA = new SampleData($ITER_MAX);

benchmark_linq_groups("Iterate over $ITER_MAX ints", 100,
    [
        "for" => function () use ($ITER_MAX) {
            $j = null;
            for ($i = 0; $i < $ITER_MAX; $i++)
                $j = $i;
            return $j;
        },
        "array functions" => function () use ($ITER_MAX) {
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

benchmark_linq_groups("Generate array of $ITER_MAX integers", 100,
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
        function () use ($ITER_MAX) {
            $dic = E::range(0, $ITER_MAX)->toLookup(
                function ($v) { return (string)tan($v % 100); },
                function ($v) { return $v % 2 ? sin($v) : cos($v); });
            return E::from($dic)->selectMany(F::$value)->sum();
        },
        "string lambda" => function () use ($ITER_MAX) {
            $dic = E::range(0, $ITER_MAX)->toLookup('(string)tan($v % 100)', '$v % 2 ? sin($v) : cos($v)');
            return E::from($dic)->selectMany('$v')->sum();
        },
    ],
    [
        function () use ($ITER_MAX) {
            $dic = G::range(0, $ITER_MAX - 1)->toLookup(
                function ($v) { return (string)tan($v % 100); },
                function ($v) { return $v % 2 ? sin($v) : cos($v); });
            return G::from($dic)->selectMany(F::$value, F::$key)->sum();
        },
    ],
    [
        function () use ($ITER_MAX) {
            not_implemented();
        },
    ]);

benchmark_linq_groups("Counting values in arrays", 100,
    [
        "for" => function () use ($DATA) {
            $numberOrders = 0;
            foreach ($DATA->orders as $order) {
                if (count($order['items']) > 5)
                    $numberOrders++;
            }
            return $numberOrders;
        },
        "arrays functions" => function () use ($DATA) {
            return count(
                array_filter(
                    $DATA->orders,
                    function ($order) { return count($order['items']) > 5; }
                )
            );
        },
    ],
    [
        function () use ($DATA) {
            return E::from($DATA->orders)
                ->count(function ($order) { return count($order['items']) > 5; });
        },
        "string lambda" => function () use ($DATA) {
            return E::from($DATA->orders)
                ->count('$o ==> count($o["items"]) > 5');
        },
    ],
    [
        function () use ($DATA) {
            return G::from($DATA->orders)
                ->count(function ($order) { return count($order['items']) > 5; });
        },
    ],
    [
        function () use ($DATA) {
            return P::from($DATA->orders)
                ->where(function ($order) { return count($order['items']) > 5; })
                ->count();
        },
    ]);

benchmark_linq_groups("Counting values in arrays deep", 100,
    [
        "for" => function () use ($DATA) {
            $numberOrders = 0;
            foreach ($DATA->orders as $order) {
                $numberItems = 0;
                foreach ($order['items'] as $item) {
                    if ($item['quantity'] > 5)
                        $numberItems++;
                }
                if ($numberItems > 2)
                    $numberOrders++;
            }
            return $numberOrders;
        },
        "arrays functions" => function () use ($DATA) {
            return count(
                array_filter(
                    $DATA->orders,
                    function ($order) {
                        return count(
                            array_filter(
                                $order['items'],
                                function ($item) { return $item['quantity'] > 5; }
                            )
                        ) > 2;
                    })
            );
        },
    ],
    [
        function () use ($DATA) {
            return E::from($DATA->orders)
                ->count(function ($order) {
                    return E::from($order['items'])
                        ->count(function ($item) { return $item['quantity'] > 5; }) > 2;
                });
        },
    ],
    [
        function () use ($DATA) {
            return G::from($DATA->orders)
                ->count(function ($order) {
                    return G::from($order['items'])
                        ->count(function ($item) { return $item['quantity'] > 5; }) > 2;
                });
        },
    ],
    [
        function () use ($DATA) {
            return P::from($DATA->orders)
                ->where(function ($order) {
                    return P::from($order['items'])
                        ->where(function ($item) { return $item['quantity'] > 5; })
                        ->count() > 2;
                })
                ->count();
        },
    ]);

benchmark_linq_groups("Filtering values in arrays", 100,
    [
        "for" => function () use ($DATA) {
            $filteredOrders = [ ];
            foreach ($DATA->orders as $order) {
                if (count($order['items']) > 5)
                    $filteredOrders[] = $order;
            }
            consume($filteredOrders);
        },
        "arrays functions" => function () use ($DATA) {
            consume(
                array_filter(
                    $DATA->orders,
                    function ($order) { return count($order['items']) > 5; }
                )
            );
        },
    ],
    [
        function () use ($DATA) {
            consume(
                E::from($DATA->orders)
                    ->where(function ($order) { return count($order['items']) > 5; })
            );
        },
        "string lambda" => function () use ($DATA) {
            consume(
                E::from($DATA->orders)
                    ->where('$order ==> count($order["items"]) > 5')
            );
        },
    ],
    [
        function () use ($DATA) {
            consume(
                G::from($DATA->orders)
                    ->where(function ($order) { return count($order['items']) > 5; })
            );
        },
    ],
    [
        function () use ($DATA) {
            consume(
                P::from($DATA->orders)
                    ->where(function ($order) { return count($order['items']) > 5; })
            );
        },
    ]);

benchmark_linq_groups("Filtering property values in arrays", 100,
    [
        "for" => function () use ($DATA) {
            $filteredItems = [ ];
            foreach ($DATA->orders as $order) {
                $firstItem = $order['items'][0];
                if ($firstItem['quantity'] > 0)
                    $filteredItems[] = $firstItem;
            }
            consume($filteredItems);
        },
        "arrays functions" => function () use ($DATA) {
            consume(
                array_map(
                    function ($order) { return $order['items'][0]; },
                    array_filter(
                        $DATA->orders,
                        function ($order) { return $order['items'][0]['quantity'] > 0; }
                    )
                )
            );
        },
    ],
    [
        function () use ($DATA) {
            consume(
                E::from($DATA->orders)
                    ->select(function ($order) { return $order['items'][0]; })
                    ->where(function ($firstItem) { return $firstItem['quantity'] > 0; })
            );
        },
        "string lambda" => function () use ($DATA) {
            consume(
                E::from($DATA->orders)->select('$v["items"][0]')->where('$v["quantity"] > 0')
            );
        },
    ],
    [
        function () use ($DATA) {
            consume(
                G::from($DATA->orders)
                    ->select(function ($order) { return $order['items'][0]; })
                    ->where(function ($firstItem) { return $firstItem['quantity'] > 0; })
            );
        },
        "property path" => function () use ($DATA) {
            consume(
                G::from($DATA->orders)->select('[items][0]')->where('[quantity]')
            );
        },
    ],
    [
        function () use ($DATA) {
            consume(
                P::from($DATA->orders)
                    ->select(function ($order) { return $order['items'][0]; })
                    ->where(function ($firstItem) { return $firstItem['quantity'] > 0; })
            );
        },
    ]);

function consume_filtering_arrays ($e)
{
    consume($e, [ 'items' => null ]);
}

benchmark_linq_groups("Filtering values in arrays deep", 100,
    [
        "for" => function () use ($DATA) {
            $filteredOrders = [ ];
            foreach ($DATA->orders as $order) {
                $filteredItems = [ ];
                foreach ($order['items'] as $item) {
                    if ($item['quantity'] > 5)
                        $filteredItems[] = $item;
                }
                if (count($filteredItems) > 0) {
                    $order['items'] = $filteredItems;
                    $filteredOrders[] = [
                        'id' => $order['id'],
                        'items' => $filteredItems,
                    ];
                }
            }
            consume_filtering_arrays($filteredOrders);
        },
        "arrays functions" => function () use ($DATA) {
            consume_filtering_arrays(
                array_filter(
                    array_map(
                        function ($order) {
                            return [
                                'id' => $order['id'],
                                'items' => array_filter(
                                    $order['items'],
                                    function ($item) { return $item['quantity'] > 5; }
                                )
                            ];
                        },
                        $DATA->orders
                    ),
                    function ($order) {
                        return count($order['items']) > 0;
                    }
                )
            );
        },
    ],
    [
        function () use ($DATA) {
            consume_filtering_arrays(
                E::from($DATA->orders)
                    ->select(function ($order) {
                        return [
                            'id' => $order['id'],
                            'items' => E::from($order['items'])
                                ->where(function ($item) { return $item['quantity'] > 5; })
                                ->toArray()
                        ];
                    })
                    ->where(function ($order) {
                        return count($order['items']) > 0;
                    })
            );
        },
        "string lambda" => function () use ($DATA) {
            consume_filtering_arrays(
                E::from($DATA->orders)
                    ->select(function ($order) {
                        return [
                            'id' => $order['id'],
                            'items' => E::from($order['items'])->where('$v["quantity"] > 5')->toArray()
                        ];
                    })
                    ->where('count($v["items"]) > 0')
            );
        },
    ],
    [
        function () use ($DATA) {
            consume_filtering_arrays(
                G::from($DATA->orders)
                    ->select(function ($order) {
                        return [
                            'id' => $order['id'],
                            'items' => G::from($order['items'])
                                ->where(function ($item) { return $item['quantity'] > 5; })
                                ->toArray()
                        ];
                    })
                    ->where(function ($order) {
                        return count($order['items']) > 2;
                    })
            );
        },
    ],
    [
        function () use ($DATA) {
            consume_filtering_arrays(
                P::from($DATA->orders)
                    ->select(function ($order) {
                        return [
                            'id' => $order['id'],
                            'items' => P::from($order['items'])
                                ->where(function ($item) { return $item['quantity'] > 5; })
                                ->asArray()
                        ];
                    })
                    ->where(function ($order) {
                        return count($order['items']) > 2;
                    })
            );
        },
    ]);

function consume_readme_sample ($e)
{
    consume($e, [ 'products' => null ]);
}

benchmark_linq_groups("Process data from ReadMe example", 5,
    [
        function () use ($DATA) {
            $productsSorted = [ ];
            foreach ($DATA->products as $product) {
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
            $categoriesSorted = $DATA->categories;
            usort($categoriesSorted, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            foreach ($categoriesSorted as $category) {
                $categoryId = $category['id'];
                $result[$category['id']] = [
                    'name' => $category['name'],
                    'products' => isset($productsSorted[$categoryId]) ? $productsSorted[$categoryId] : [ ],
                ];
            }
            consume_readme_sample($result);
        },
    ],
    [
        function () use ($DATA) {
            consume_readme_sample(E::from($DATA->categories)
                ->orderBy(function ($cat) { return $cat['name']; })
                ->groupJoin(
                    from($DATA->products)
                        ->where(function ($prod) { return $prod['quantity'] > 0; })
                        ->orderByDescending(function ($prod) { return $prod['quantity']; })
                        ->thenBy(function ($prod) { return $prod['name']; }),
                    function ($cat) { return $cat['id']; },
                    function ($prod) { return $prod['catId']; },
                    function ($cat, $prods) {
                        return array(
                            'name' => $cat['name'],
                            'products' => $prods
                        );
                    }
                ));
        },
        "string lambda" => function () use ($DATA) {
            consume_readme_sample(E::from($DATA->categories)
                ->orderBy('$cat ==> $cat["name"]')
                ->groupJoin(
                    from($DATA->products)
                        ->where('$prod ==> $prod["quantity"] > 0')
                        ->orderByDescending('$prod ==> $prod["quantity"]')
                        ->thenBy('$prod ==> $prod["name"]'),
                    '$cat ==> $cat["id"]', '$prod ==> $prod["catId"]',
                    '($cat, $prods) ==> [
                            "name" => $cat["name"],
                            "products" => $prods
                        ]'
                ));
        },
    ],
    [
        function () use ($DATA) {
            consume_readme_sample(G::from($DATA->categories)
                ->orderBy(function ($cat) { return $cat['name']; })
                ->groupJoin(
                    G::from($DATA->products)
                        ->where(function ($prod) { return $prod['quantity'] > 0; })
                        ->orderByDesc(function ($prod) { return $prod['quantity']; })
                        ->thenBy(function ($prod) { return $prod['name']; }),
                    function ($cat) { return $cat['id']; },
                    function ($prod) { return $prod['catId']; },
                    function ($cat, $prods) {
                        return array(
                            'name' => $cat['name'],
                            'products' => $prods
                        );
                    }
                ));
        },
    ],
    [
        function () use ($DATA) {
            consume_readme_sample(P::from($DATA->categories)
                ->orderByAscending(function ($cat) { return $cat['name']; })
                ->groupJoin(
                    P::from($DATA->products)
                        ->where(function ($prod) { return $prod['quantity'] > 0; })
                        ->orderByDescending(function ($prod) { return $prod['quantity']; })
                        ->thenByAscending(function ($prod) { return $prod['name']; })
                )
                ->on(function ($cat, $prod) { return $cat['id'] == $prod['catId']; })
                ->to(function ($cat, $prods) {
                    return array(
                        'name' => $cat['name'],
                        'products' => $prods
                    );
                }));
        },
    ]);

echo "\nDone!\n";