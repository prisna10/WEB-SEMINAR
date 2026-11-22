<?php

class Product
{
    private string $id;
    private string $category;
    private string $badge;
    private string $name;
    private int $price;
    private string $description;
    private string $image;

    public function __construct(
        string $id,
        string $category,
        string $badge,
        string $name,
        int $price,
        string $description,
        string $image
    ) {
        $this->id = $id;
        $this->category = $category;
        $this->badge = $badge;
        $this->name = $name;
        $this->price = $price;
        $this->description = $description;
        $this->image = $image;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getBadge(): string
    {
        return $this->badge;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): void
    {
        if ($price < 0) {
            throw new InvalidArgumentException('Harga produk tidak boleh negatif.');
        }

        $this->price = $price;
    }

    public function getFormattedPrice(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
