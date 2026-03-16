<?php

namespace App\Contracts;

use App\Models\Order;

interface DeliveryServiceInterface
{
    public function requestDelivery(Order $order): array;

    public function handleStatusUpdate(string $externalDeliveryId, string $status): bool;
}