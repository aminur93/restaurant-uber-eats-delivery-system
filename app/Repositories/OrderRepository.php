<?php

namespace App\Repositories;

use App\Contracts\OrderRepositoryInterface;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class OrderRepository implements OrderRepositoryInterface
{
    private int $cacheTtl = 300;
    
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

    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        $page     = request()->get('page', 1);
        $cacheKey = "orders:page:{$page}:per:{$perPage}";

        //dd('paginate called', $page, $cacheKey); // ← এটা add করো

        if ((int) $page === 1) {
            return Cache::store('redis')->remember(
                $cacheKey,
                $this->cacheTtl,
                fn () => $this->fetchPaginated($perPage)
            );
        }

        return $this->fetchPaginated($perPage);
    }

    private function fetchPaginated(int $perPage): LengthAwarePaginator
    {
        return Order::with(['items', 'delivery'])
            ->latest()
            ->paginate($perPage);
    }

    public function clearCache(): void
    {
        Cache::store('redis')->forget('orders:page:1:per:10');
    }
}