<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 *
 * @method static hasProducts(int $count = 1, array $attributes = [])
 */
class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(2, true)),
            'description' => fake()->optional()->sentence(),
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    // STATES //////////////////////////////////////////////////////////////////////////////////////

    /**
     * Indicate that the category is a child of another category.
     */
    public function childOf(Category $parent): self
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Indicate that the category is inactive (hidden from lists).
     */
    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
