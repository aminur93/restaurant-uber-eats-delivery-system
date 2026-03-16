<?php

namespace App\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderServiceInterface
{
    public function createWebsiteOrder(array $data): Order;

    public function createUberEatsOrder(array $payload): Order;

    public function markReadyAndDispatch(int $orderId): void;

    public function getAllOrders(): Collection;
}