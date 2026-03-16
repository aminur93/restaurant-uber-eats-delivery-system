<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── POST /api/orders ────────────────────────────────────

    public function test_can_create_website_order(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_name' => 'John Doe',
            'phone'         => '+8801711000000',
            'address'       => '123 Main St, Dhaka',
            'items'         => [
                ['name' => 'Burger', 'qty' => 1, 'price' => 5.00],
                ['name' => 'Fries',  'qty' => 2, 'price' => 2.50],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.order_source', 'website')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.customer_name', 'John Doe')
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'John Doe',
            'order_source'  => 'website',
            'status'        => 'pending',
        ]);

        $this->assertDatabaseHas('order_items', [
            'item_name' => 'Burger',
            'quantity'  => 1,
        ]);
    }

    public function test_create_order_validates_required_fields(): void
    {
        $response = $this->postJson('/api/orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_name',
                'phone',
                'address',
                'items',
            ]);
    }

    public function test_create_order_validates_items_array(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_name' => 'John Doe',
            'phone'         => '+8801711000000',
            'address'       => '123 Main St',
            'items'         => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_create_order_validates_item_fields(): void
    {
        $response = $this->postJson('/api/orders', [
            'customer_name' => 'John Doe',
            'phone'         => '+8801711000000',
            'address'       => '123 Main St',
            'items'         => [
                ['name' => '', 'qty' => 0],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.name',
                'items.0.qty',
            ]);
    }

    // ─── GET /api/orders ─────────────────────────────────────

    public function test_can_get_all_orders(): void
    {
        Order::factory()->count(3)->create();

        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'order_source',
                        'customer_name',
                        'phone',
                        'address',
                        'status',
                        'items',
                        'delivery',
                        'created_at',
                    ],
                ],
            ]);
    }

    public function test_get_orders_returns_empty_array_when_no_orders(): void
    {
        $response = $this->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ─── PATCH /api/orders/{id}/ready ────────────────────────

    public function test_can_mark_order_ready_and_dispatch_delivery(): void
    {
        $order = Order::factory()->create([
            'order_source' => 'website',
            'status'       => 'pending',
        ]);

        $response = $this->patchJson("/api/orders/{$order->id}/ready");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Order marked as ready. Delivery dispatched to queue.');

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'ready_for_pickup',
        ]);

        // TEST_MODE=true + QUEUE=sync delivery ready
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'provider' => 'uber_direct',
        ]);
    }

    public function test_uber_eats_order_does_not_dispatch_delivery(): void
    {
        $order = Order::factory()->create([
            'order_source' => 'uber_eats',
            'status'       => 'confirmed',
        ]);

        $this->patchJson("/api/orders/{$order->id}/ready");

        // uber_eats order এ delivery dispatch never
        $this->assertDatabaseMissing('deliveries', [
            'order_id' => $order->id,
        ]);
    }

    public function test_mark_ready_returns_404_for_invalid_order(): void
    {
        $response = $this->patchJson('/api/orders/9999/ready');

        $response->assertStatus(404);
    }
}