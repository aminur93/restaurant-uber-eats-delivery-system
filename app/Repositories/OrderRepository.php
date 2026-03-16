<?php

namespace App\Repositories;

use App\Contracts\OrderRepositoryInterface;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository implements OrderRepositoryInterface
{
    public function create(array $data): Order
    {
        $order = Order::create($data['order']);

        foreach ($data['items'] as $item) {
            $order->items()->create([
                'item_name' => $item['name']      ?? $item['item_name'],
                'quantity'  => $item['qty']        ?? $item['quantity'],
                'price'     => $item['price']      ?? 0.00,
            ]);
        }

        return $order->load('items');
    }

    public function findById(int $id): ?Order
    {
        return Order::with(['items', 'delivery'])->find($id);
    }

    public function findByExternalId(string $externalId): ?Order
    {
        return Order::where('external_order_id', $externalId)->first();
    }

    public function updateStatus(int $id, string $status): bool
    {
        return (bool) Order::where('id', $id)->update(['status' => $status]);
    }

    public function all(): Collection
    {
        return Order::with(['items', 'delivery'])->latest()->get();
    }
}