<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_order_id' => null,
            'order_source'      => $this->faker->randomElement(['website', 'uber_eats']),
            'customer_name'     => $this->faker->name(),
            'phone'             => $this->faker->phoneNumber(),
            'address'           => $this->faker->address(),
            'status'            => 'pending',
        ];
    }

    public function website(): static
    {
        return $this->state(['order_source' => 'website']);
    }

    public function uberEats(): static
    {
        return $this->state([
            'order_source'      => 'uber_eats',
            'external_order_id' => 'UE-' . strtoupper(fake()->lexify('????????')),
            'status'            => 'confirmed',
        ]);
    }
}