<?php

namespace Tests\Unit;

use App\Models\Delivery;
use App\Models\Order;
use App\Services\UberAuthService;
use App\Services\UberDirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class UberDirectServiceTest extends TestCase
{
    use RefreshDatabase;

    private UberDirectService $service;
    private $uberAuthService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uberAuthService = Mockery::mock(UberAuthService::class);

        $this->service = new UberDirectService(
            $this->uberAuthService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── requestDelivery() — Test Mode ───────────────────────

    public function test_request_delivery_in_test_mode_returns_fake_response(): void
    {
        config(['services.uber_direct.test_mode' => true]);

        $order = Order::factory()->create([
            'order_source' => 'website',
            'status'       => 'ready_for_pickup',
        ]);

        $order->items()->create([
            'item_name' => 'Burger',
            'quantity'  => 1,
            'price'     => 5.00,
        ]);

        $order->load('items');

        // Test mode এ auth service call
        $this->uberAuthService->shouldNotReceive('getAccessToken');

        $result = $this->service->requestDelivery($order);

        $this->assertArrayHasKey('delivery_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('test_mode', $result);
        $this->assertTrue($result['test_mode']);
        $this->assertStringStartsWith('FAKE-DEL-', $result['delivery_id']);
    }

    public function test_request_delivery_saves_delivery_to_database(): void
    {
        config(['services.uber_direct.test_mode' => true]);

        $order = Order::factory()->create([
            'order_source' => 'website',
            'status'       => 'ready_for_pickup',
        ]);

        $order->items()->create([
            'item_name' => 'Pizza',
            'quantity'  => 1,
            'price'     => 10.00,
        ]);

        $order->load('items');

        $this->service->requestDelivery($order);

        $this->assertDatabaseHas('deliveries', [
            'order_id'        => $order->id,
            'provider'        => 'uber_direct',
            'delivery_status' => 'pending',
        ]);
    }

    // ─── handleStatusUpdate() ────────────────────────────────

    public function test_can_update_delivery_status(): void
    {
        $order = Order::factory()->create();

        $delivery = Delivery::create([
            'order_id'             => $order->id,
            'provider'             => 'uber_direct',
            'external_delivery_id' => 'FAKE-DEL-TEST123',
            'delivery_status'      => 'pending',
        ]);

        $result = $this->service->handleStatusUpdate(
            'FAKE-DEL-TEST123',
            'courier_assigned'
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('deliveries', [
            'external_delivery_id' => 'FAKE-DEL-TEST123',
            'delivery_status'      => 'courier_assigned',
        ]);
    }

    public function test_handle_status_update_returns_false_when_delivery_not_found(): void
    {
        $result = $this->service->handleStatusUpdate(
            'NONEXISTENT-DEL',
            'delivered'
        );

        $this->assertFalse($result);
    }

    public function test_all_delivery_statuses_can_be_set(): void
    {
        $order    = Order::factory()->create();
        $statuses = ['courier_assigned', 'courier_picked_up', 'delivered', 'cancelled'];

        foreach ($statuses as $status) {
            $delivery = Delivery::create([
                'order_id'             => $order->id,
                'provider'             => 'uber_direct',
                'external_delivery_id' => 'FAKE-DEL-' . strtoupper(Str::random(6)),
                'delivery_status'      => 'pending',
            ]);

            $result = $this->service->handleStatusUpdate(
                $delivery->external_delivery_id,
                $status
            );

            $this->assertTrue($result);
            $this->assertDatabaseHas('deliveries', [
                'external_delivery_id' => $delivery->external_delivery_id,
                'delivery_status'      => $status,
            ]);
        }
    }
}