<?php

namespace App\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderRepositoryInterface
{
    public function create(array $data): Order;

    public function findById(int $id): ?Order;

    public function findByExternalId(string $externalId): ?Order;

    public function updateStatus(int $id, string $status): bool;

    public function all(): Collection;
}