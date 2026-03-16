<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UberEatsWebhookTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'order_id'      => 'UE-TEST-001',
        'customer_name' => 'Alice Rahman',
        'address'       => '45 Broadway, Dhaka',
        'phone'         => '+8801811000000',
        'items'         => [
            ['name' => 'Pizza', 'qty' => 1, 'price' => 12.00],
            ['name' => 'Garlic Bread', 'qty' => 2, 'price' => 3.50],
        ],
    ];

    public function test_can_receive_uber_eats_order(): void
    {
        $response = $this->postJson('/api/webhook/uber-eats/orders', $this->validPayload);

        $response->assertStatus(201)
            ->assertJsonPath('data.order_source', 'uber_eats')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.external_order_id', 'UE-TEST-001')
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('orders', [
            'external_order_id' => 'UE-TEST-001',
            'order_source'      => 'uber_eats',
            'status'            => 'confirmed',
        ]);
    }

    public function test_duplicate_uber_eats_order_returns_existing(): void
    {
        // First request
        $this->postJson('/api/webhook/uber-eats/orders', $this->validPayload);

        // Second request — same order_id
        $response = $this->postJson('/api/webhook/uber-eats/orders', $this->validPayload);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Order already exists.');

        // DB তে শুধু একটা order থাকবে
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_uber_eats_webhook_validates_required_fields(): void
    {
        $response = $this->postJson('/api/webhook/uber-eats/orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'order_id',
                'customer_name',
                'address',
                'items',
            ]);
    }

    public function test_uber_eats_webhook_validates_items(): void
    {
        $payload          = $this->validPayload;
        $payload['items'] = [];

        $response = $this->postJson('/api/webhook/uber-eats/orders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }
}