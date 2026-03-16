<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UberDirectWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function createDelivery(string $externalId = 'FAKE-DEL-TEST123'): Delivery
    {
        $order = Order::factory()->create([
            'order_source' => 'website',
            'status'       => 'ready_for_pickup',
        ]);

        return Delivery::create([
            'order_id'             => $order->id,
            'provider'             => 'uber_direct',
            'external_delivery_id' => $externalId,
            'delivery_status'      => 'pending',
        ]);
    }

    public function test_can_update_delivery_status_courier_assigned(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->postJson('/api/webhook/uber-direct/status', [
            'delivery_id' => $delivery->external_delivery_id,
            'status'      => 'courier_assigned',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Delivery status updated successfully.');

        $this->assertDatabaseHas('deliveries', [
            'external_delivery_id' => $delivery->external_delivery_id,
            'delivery_status'      => 'courier_assigned',
        ]);
    }

    public function test_can_update_delivery_status_courier_picked_up(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->postJson('/api/webhook/uber-direct/status', [
            'delivery_id' => $delivery->external_delivery_id,
            'status'      => 'courier_picked_up',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('deliveries', [
            'external_delivery_id' => $delivery->external_delivery_id,
            'delivery_status'      => 'courier_picked_up',
        ]);
    }

    public function test_can_update_delivery_status_delivered(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->postJson('/api/webhook/uber-direct/status', [
            'delivery_id' => $delivery->external_delivery_id,
            'status'      => 'delivered',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('deliveries', [
            'delivery_status' => 'delivered',
        ]);
    }

    public function test_can_update_delivery_status_cancelled(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->postJson('/api/webhook/uber-direct/status', [
            'delivery_id' => $delivery->external_delivery_id,
            'status'      => 'cancelled',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('deliveries', [
            'delivery_status' => 'cancelled',
        ]);
    }

    public function test_invalid_status_returns_validation_error(): void
    {
        $delivery = $this->createDelivery();

        $response = $this->postJson('/api/webhook/uber-direct/status', [
            'delivery_id' => $delivery->external_delivery_id,
            'status'      => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_unknown_delivery_id_returns_404(): void
    {
        $response = $this->postJson('/api/webhook/uber-direct/status', [
            'delivery_id' => 'UNKNOWN-DEL-999',
            'status'      => 'delivered',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Delivery not found.');
    }

    public function test_invalid_signature_returns_401(): void
    {
        // webhook_secret set করো
        config(['services.uber_direct.webhook_secret' => 'my-secret']);

        $response = $this->postJson(
            '/api/webhook/uber-direct/status',
            [
                'delivery_id' => 'FAKE-DEL-TEST123',
                'status'      => 'delivered',
            ],
            ['X-Uber-Signature' => 'wrong-signature']
        );

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_valid_signature_passes(): void
    {
        $delivery = $this->createDelivery();
        $secret   = 'my-secret';
        $body     = json_encode([
            'delivery_id' => $delivery->external_delivery_id,
            'status'      => 'delivered',
        ]);

        config(['services.uber_direct.webhook_secret' => $secret]);

        $signature = hash_hmac('sha256', $body, $secret);

        $response = $this->postJson(
            '/api/webhook/uber-direct/status',
            json_decode($body, true),
            ['X-Uber-Signature' => $signature]
        );

        $response->assertStatus(200);
    }
}