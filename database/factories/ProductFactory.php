<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string
     */
    protected $model = Product::class;

    /**
     * A small, business-appropriate catalogue of "electronics and odd items".
     */
    private const ITEMS = [
        ['name' => 'Replacement Screen - Generic 5.5"', 'category' => 'Parts'],
        ['name' => 'USB-C Charging Cable 1m', 'category' => 'Accessories'],
        ['name' => 'Used Tablet (refurbished)', 'category' => 'Refurbished'],
        ['name' => 'Vintage Cassette Walkman', 'category' => 'Oddities'],
        ['name' => 'Bluetooth Speaker', 'category' => 'Accessories'],
        ['name' => 'SSD 1TB (new, sealed)', 'category' => 'Parts'],
        ['name' => 'Broken Laptop (for parts)', 'category' => 'Oddities'],
        ['name' => 'Phone Case - Clear', 'category' => 'Accessories'],
        ['name' => 'Used Game Console', 'category' => 'Refurbished'],
        ['name' => 'Antique Radio (non-working)', 'category' => 'Oddities'],
    ];

    public function definition(): array
    {
        $item = fake()->randomElement(self::ITEMS);

        $cost = fake()->randomFloat(2, 1, 60);

        return [
            'name' => $item['name'],
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'barcode' => fake()->optional()->ean13(),
            'description' => fake()->optional()->sentence(),
            'condition' => fake()->randomElement(['new', 'refurbished', 'used', 'faulty', null]),
            'price' => round($cost * fake()->randomFloat(1, 1.2, 2.2), 2),
            'cost' => round($cost, 2),
            'stock' => fake()->numberBetween(0, 20),
            'reorder_level' => fake()->numberBetween(1, 5),
            'is_active' => true,
        ];
    }

    // STATES //////////////////////////////////////////////////////////////////////////////////////

    /**
     * Indicate that the product belongs to a category.
     */
    public function inCategory(Category $category): self
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    /**
     * Indicate that the product is in stock.
     */
    public function inStock(): self
    {
        return $this->state(fn (array $attributes) => [
            'stock' => fake()->numberBetween(1, 20),
        ]);
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): self
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    /**
     * Indicate that the product is at or below its reorder level.
     */
    public function lowStock(): self
    {
        return $this->state(fn (array $attributes) => [
            'stock' => fake()->numberBetween(0, 2),
            'reorder_level' => fake()->numberBetween(2, 5),
        ]);
    }
}
