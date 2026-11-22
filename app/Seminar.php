<?php

require_once __DIR__ . '/Product.php';

class Seminar
{
    private string $title;
    private string $theme;
    private array $products = [];

    public function __construct(string $title, string $theme)
    {
        $this->title = $title;
        $this->theme = $theme;
    }

    public function addProduct(Product $product): void
    {
        $this->products[] = $product;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * @return Product[]
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * @return Product[]
     */
    public function getProductsByCategory(string $category): array
    {
        return array_values(array_filter(
            $this->products,
            static fn(Product $product): bool => $product->getCategory() === $category
        ));
    }

    public function calculateTotal(Product $product, int $quantity): int
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah pesanan minimal satu.');
        }

        return $product->getPrice() * $quantity;
    }
}
