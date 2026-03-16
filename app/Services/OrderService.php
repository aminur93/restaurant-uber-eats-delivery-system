<?php

namespace App\Services;

use App\Contracts\DeliveryServiceInterface;
use App\Contracts\OrderRepositoryInterface;
use App\Contracts\OrderServiceInterface;
use App\Jobs\DispatchUberDirectDelivery;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService implements OrderServiceInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DeliveryServiceInterface $deliveryService,
    ) {}

    public function createWebsiteOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = $this->orderRepository->create([
                'order' => [
                    'order_source'  => 'website',
                    'customer_name' => $data['customer_name'],
                    'phone'         => $data['phone'],
                    'address'       => $data['address'],
                    'status'        => 'pending',
                ],
                'items' => $data['items'],
            ]);

            Log::info('Website order created', ['order_id' => $order->id]);

            return $order;
        });
    }

    public function createUberEatsOrder(array $payload): Order
    {
        return DB::transaction(function () use ($payload) {
            $order = $this->orderRepository->create([
                'order' => [
                    'external_order_id' => $payload['order_id'],
                    'order_source'      => 'uber_eats',
                    'customer_name'     => $payload['customer_name'],
                    'phone'             => $payload['phone'] ?? '',
                    'address'           => $payload['address'],
                    'status'            => 'confirmed',
                ],
                'items' => $payload['items'],
            ]);

            Log::info('Uber Eats order created', [
                'order_id'          => $order->id,
                'external_order_id' => $payload['order_id'],
            ]);

            return $order;
        });
    }

    public function markReadyAndDispatch(int $orderId): void
    {
        $this->orderRepository->updateStatus($orderId, 'ready_for_pickup');

        $order = $this->orderRepository->findById($orderId);

        if (! $order || $order->order_source !== 'website') {
            Log::info('Skipping Uber Direct dispatch — not a website order', [
                'order_id' => $orderId,
            ]);
            return;
        }

        // Queue Job এ dispatch — background এ চলবে
        // DispatchUberDirectDelivery::dispatch($order)
        //     ->onQueue('deliveries');

        Log::info('Uber Direct delivery job queued', [
            'order_id' => $orderId,
        ]);
    }

    public function getAllOrders(): Collection
    {
        return $this->orderRepository->all();
    }
}