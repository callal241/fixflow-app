<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * The top-level categories to create for the demo business.
     *
     * Kept as data (not hard-coded create calls) so the catalogue shape is
     * easy to extend. Each entry is [name, [child names...]].
     */
    private const CATEGORIES = [
        ['Electronics', ['Phones & Tablets', 'Laptops', 'Audio']],
        ['Refurbished', ['Phones & Tablets', 'Consoles']],
        ['Parts & Accessories', ['Chargers & Cables', 'Screens & Batteries']],
        ['Oddities', []],
    ];

    public function run(): void
    {
        $businessId = Business::where('name', BusinessSeeder::DEMO_BUSINESS_NAME)
            ->value('id');

        if ($businessId === null) {
            return;
        }

        // Build the category tree for the demo business.
        foreach (self::CATEGORIES as [$name, $children]) {
            $parent = Category::factory()->create([
                'business_id' => $businessId,
                'name' => $name,
            ]);

            foreach ($children as $childName) {
                Category::factory()->childOf($parent)->create([
                    'business_id' => $businessId,
                    'name' => $childName,
                ]);
            }
        }

        // Seed products across the demo business' categories.
        $categories = Category::where('business_id', $businessId)->get();

        foreach ($categories as $category) {
            Product::factory()
                ->count(fake()->numberBetween(4, 8))
                ->inCategory($category)
                ->create([
                    'business_id' => $businessId,
                ]);
        }

        // Guarantee a couple of low-stock and out-of-stock items so the
        // dashboard "needs attention" panel has something to show.
        Product::factory()->lowStock()->inCategory($categories->random())->create([
            'business_id' => $businessId,
        ]);

        Product::factory()->outOfStock()->inCategory($categories->random())->create([
            'business_id' => $businessId,
        ]);
    }
}
