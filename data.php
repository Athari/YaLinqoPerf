<?php
function generate_product_categories ($n)
{
    $categories = [ ];
    for ($i = 0; $i < $n; $i++) {
        $categories[] = array(
            'name' => 'categoriy-' . uniqid(),
            'id' => $i
        );
    }
    return $categories;
}

function generate_products ($n, $cats)
{
    $products = [ ];
    for ($i = 0; $i < $n; $i++) {
        $products[] = [
            'name' => uniqid(),
            'catId' => $cats[rand(0, count($cats) - 1)]['id'],
            'id' => $i,
            'quantity' => rand(0, 1000)
        ];
    }
    return $products;
}