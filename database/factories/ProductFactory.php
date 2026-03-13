<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);
        return [
            'tenant_id' => Tenant::factory(),
            'external_id' => 'shopify_' . $this->faker->unique()->numerify('######'),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => $this->faker->optional()->paragraph(),
            'sku' => $this->faker->optional()->lexify('SKU-????'),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'platform' => $this->faker->randomElement(['shopify', 'shopware']),
            'last_synced_at' => $this->faker->optional()->dateTime(),
        ];
    }

    public function shopify(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'shopify',
            'external_id' => 'shopify_' . $this->faker->unique()->numerify('######'),
        ]);
    }

    public function shopware(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'shopware',
            'external_id' => 'shopware_' . $this->faker->unique()->numerify('######'),
        ]);
    }
}
