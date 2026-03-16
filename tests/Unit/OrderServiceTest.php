<?php

namespace Tests\Unit;

use App\Contracts\DeliverServiceInterface;
use App\Contracts\DeliveryServiceInterface;
use App\Contracts\OrderRepositoryInterface;
use App\Jobs\DispatchUberDirectDelivery;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;
    private $orderRepository;
    private $deliveryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->deliveryService = Mockery::mock(DeliveryServiceInterface::class);

        $this->service = new OrderService(
            $this->orderRepository,
            $this->deliveryService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── createWebsiteOrder() ────────────────────────────────

    public function test_can_create_website_order(): void
    {
        $fakeOrder = Order::factory()->make([
            'id'           => 1,
            'order_source' => 'website',
            'status'       => 'pending',
        ]);

        $this->orderRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($fakeOrder);

        $order = $this->service->createWebsiteOrder([
            'customer_name' => 'John Doe',
            'phone'         => '+8801711000000',
            'address'       => '123 Main St',
            'items'         => [
                ['name' => 'Burger', 'qty' => 1],
            ],
        ]);

        $this->assertEquals('website', $order->order_source);
        $this->assertEquals('pending', $order->status);
    }

    public function test_website_order_sets_correct_source(): void
    {
        $this->orderRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['order']['order_source'] === 'website'
                    && $data['order']['status'] === 'pending';
            }))
            ->andReturn(Order::factory()->make());

        $this->service->createWebsiteOrder([
            'customer_name' => 'John',
            'phone'         => '+880',
            'address'       => 'Dhaka',
            'items'         => [['name' => 'Item', 'qty' => 1]],
        ]);
    }

    // ─── createUberEatsOrder() ───────────────────────────────

    public function test_can_create_uber_eats_order(): void
    {
        $fakeOrder = Order::factory()->make([
            'id'                => 2,
            'order_source'      => 'uber_eats',
            'external_order_id' => 'UE-TEST-001',
            'status'            => 'confirmed',
        ]);

        $this->orderRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($fakeOrder);

        $order = $this->service->createUberEatsOrder([
            'order_id'      => 'UE-TEST-001',
            'customer_name' => 'Alice',
            'address'       => '45 Broadway',
            'items'         => [['name' => 'Pizza', 'qty' => 1]],
        ]);

        $this->assertEquals('uber_eats', $order->order_source);
        $this->assertEquals('confirmed', $order->status);
    }

    public function test_uber_eats_order_sets_confirmed_status(): void
    {
        $this->orderRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['order']['order_source'] === 'uber_eats'
                    && $data['order']['status'] === 'confirmed'
                    && $data['order']['external_order_id'] === 'UE-ABC';
            }))
            ->andReturn(Order::factory()->make());

        $this->service->createUberEatsOrder([
            'order_id'      => 'UE-ABC',
            'customer_name' => 'Bob',
            'address'       => 'Dhaka',
            'items'         => [['name' => 'Item', 'qty' => 1]],
        ]);
    }

    // ─── markReadyAndDispatch() ──────────────────────────────

    public function test_marks_website_order_ready_and_queues_job(): void
    {
        Queue::fake();

        $fakeOrder = Order::factory()->make([
            'id'           => 1,
            'order_source' => 'website',
        ]);

        $this->orderRepository
            ->shouldReceive('updateStatus')
            ->once()
            ->with(1, 'ready_for_pickup')
            ->andReturn(true);

        $this->orderRepository
            ->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($fakeOrder);

        $this->service->markReadyAndDispatch(1);

        Queue::assertPushedOn('deliveries', DispatchUberDirectDelivery::class);
    }

    public function test_does_not_dispatch_for_uber_eats_order(): void
    {
        Queue::fake();

        $fakeOrder = Order::factory()->make([
            'id'           => 2,
            'order_source' => 'uber_eats',
        ]);

        $this->orderRepository
            ->shouldReceive('updateStatus')
            ->once()
            ->andReturn(true);

        $this->orderRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($fakeOrder);

        $this->service->markReadyAndDispatch(2);

        // uber_eats order এ job dispatch হওয়া উচিত না
        Queue::assertNothingPushed();
    }

    public function test_does_not_dispatch_when_order_not_found(): void
    {
        Queue::fake();

        $this->orderRepository
            ->shouldReceive('updateStatus')
            ->once()
            ->andReturn(false);

        $this->orderRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn(null);

        $this->service->markReadyAndDispatch(9999);

        Queue::assertNothingPushed();
    }

    // ─── getAllOrders() ──────────────────────────────────────

    public function test_can_get_all_orders(): void
    {
        $fakeOrders = Order::factory()->count(3)->make();

        $this->orderRepository
            ->shouldReceive('all')
            ->once()
            ->andReturn($fakeOrders);

        $orders = $this->service->getAllOrders();

        $this->assertCount(3, $orders);
    }
}