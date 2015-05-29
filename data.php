<?php

class SampleData
{
    public $categories;
    public $products;
    public $users;
    public $orders;

    function __construct ($n)
    {
        if ($n < 10)
            throw new InvalidArgumentException('n must be 10 or larger');
        $this->categories = $this->generateProductCategories($n / 10);
        $this->products = $this->generateProducts($n);
        $this->users = $this->generateUsers($n / 10);
        $this->orders = $this->generateOrders($n);
    }

    private function generateProductCategories ($n)
    {
        $categories = [ ];
        for ($i = 1; $i <= $n; $i++) {
            $categories[] = array(
                'id' => $i,
                'name' => 'category-' . uniqid(),
                'desc' => 'category-desc-' . uniqid() . '-' . uniqid(),
            );
        }
        return $categories;
    }

    private function generateProducts ($n)
    {
        $products = [ ];
        for ($i = 1; $i <= $n; $i++) {
            $products[] = [
                'id' => $i,
                'name' => 'product-' . uniqid(),
                'catId' => array_rand($this->categories)['id'],
                'quantity' => rand(1, 100),
            ];
        }
        return $products;
    }

    private function generateUsers ($n)
    {
        $users = [ ];
        for ($i = 1; $i <= $n; $i++) {
            $users[] = [
                'id' => $i,
                'name' => 'user-' . uniqid(),
                'rating' => rand(0, 10),
            ];
        }
        return $users;
    }

    private function generateOrders ($n)
    {
        $orders = [ ];
        for ($i = 1; $i <= $n; $i++) {
            $orders[] = [
                'id' => $i,
                'customerId' => array_rand($this->users)['id'],
                'items' => $this->generateOrderItems(rand(1, 10)),
            ];
        }
        return $orders;
    }

    private function generateOrderItems ($n)
    {
        $items = [ ];
        for ($i = 1; $i <= $n; $i++) {
            $items[] = [
                'prodId' => array_rand($this->products)['id'],
                'quantity' => rand(0, 10),
            ];
        }
        return $items;
    }
}
