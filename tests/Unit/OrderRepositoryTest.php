<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new OrderRepository();
    }

    // ─── create() ────────────────────────────────────────────

    public function test_can_create_order_with_items(): void
    {
        $order = $this->repository->create([
            'order' => [
                'order_source'  => 'website',
                'customer_name' => 'John Doe',
                'phone'         => '+8801711000000',
                'address'       => '123 Main St',
                'status'        => 'pending',
            ],
            'items' => [
                ['name' => 'Burger', 'qty' => 1, 'price' => 5.00],
                ['name' => 'Fries',  'qty' => 2, 'price' => 2.50],
            ],
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals('John Doe', $order->customer_name);
        $this->assertEquals('website', $order->order_source);
        $this->assertCount(2, $order->items);
    }

    public function test_create_order_saves_correct_item_data(): void
    {
        $order = $this->repository->create([
            'order' => [
                'order_source'  => 'website',
                'customer_name' => 'Test User',
                'phone'         => '+8801700000000',
                'address'       => 'Test Address',
                'status'        => 'pending',
            ],
            'items' => [
                ['name' => 'Pizza', 'qty' => 3, 'price' => 10.00],
            ],
        ]);

        $item = $order->items->first();

        $this->assertEquals('Pizza', $item->item_name);
        $this->assertEquals(3, $item->quantity);
        $this->assertEquals(10.00, $item->price);
    }

    public function test_create_uber_eats_order_with_item_name_key(): void
    {
        // Uber Eats payload এ item_name key থাকতে পারে
        $order = $this->repository->create([
            'order' => [
                'order_source'      => 'uber_eats',
                'external_order_id' => 'UE-001',
                'customer_name'     => 'Alice',
                'phone'             => '',
                'address'           => '45 Broadway',
                'status'            => 'confirmed',
            ],
            'items' => [
                ['item_name' => 'Shawarma', 'quantity' => 2, 'price' => 8.00],
            ],
        ]);

        $this->assertEquals('Shawarma', $order->items->first()->item_name);
        $this->assertEquals(2, $order->items->first()->quantity);
    }

    // ─── findById() ──────────────────────────────────────────

    public function test_can_find_order_by_id(): void
    {
        $created = Order::factory()->create();

        $found = $this->repository->findById($created->id);

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_find_by_id_returns_null_for_missing_order(): void
    {
        $found = $this->repository->findById(9999);

        $this->assertNull($found);
    }

    public function test_find_by_id_loads_items_and_delivery(): void
    {
        $order = Order::factory()->create();

        $found = $this->repository->findById($order->id);

        $this->assertTrue($found->relationLoaded('items'));
        $this->assertTrue($found->relationLoaded('delivery'));
    }

    // ─── findByExternalId() ──────────────────────────────────

    public function test_can_find_order_by_external_id(): void
    {
        Order::factory()->create(['external_order_id' => 'UE-TEST-001']);

        $found = $this->repository->findByExternalId('UE-TEST-001');

        $this->assertNotNull($found);
        $this->assertEquals('UE-TEST-001', $found->external_order_id);
    }

    public function test_find_by_external_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findByExternalId('UE-NOTEXIST');

        $this->assertNull($found);
    }

    // ─── updateStatus() ──────────────────────────────────────

    public function test_can_update_order_status(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $result = $this->repository->updateStatus($order->id, 'ready_for_pickup');

        $this->assertTrue($result);
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'ready_for_pickup',
        ]);
    }

    public function test_update_status_returns_false_for_missing_order(): void
    {
        $result = $this->repository->updateStatus(9999, 'confirmed');

        $this->assertFalse($result);
    }

    // ─── all() ───────────────────────────────────────────────

    public function test_can_get_all_orders(): void
    {
        Order::factory()->count(5)->create();

        $orders = $this->repository->all();

        $this->assertCount(5, $orders);
    }

    public function test_all_orders_sorted_by_latest(): void
    {
        $first  = Order::factory()->create([
            'created_at' => now()->subMinutes(2),
        ]);
        $second = Order::factory()->create([
            'created_at' => now(),
        ]);

        $orders = $this->repository->all();

        $this->assertEquals($second->id, $orders->first()->id);
    }
}