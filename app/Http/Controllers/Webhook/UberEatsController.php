<?php

namespace App\Http\Controllers\Webhook;

use App\Contracts\OrderRepositoryInterface;
use App\Contracts\OrderServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UberEatsController extends Controller
{
    public function __construct(
        private readonly OrderServiceInterface    $orderService,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    // POST /api/webhook/uber-eats/orders
    public function handleOrder(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'order_id'      => 'required|string',
            'customer_name' => 'required|string',
            'address'       => 'required|string',
            'phone'         => 'nullable|string',
            'items'         => 'required|array|min:1',
            'items.*.name'  => 'required|string',
            'items.*.qty'   => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
        ]);

        // Duplicate order guard
        $existing = $this->orderRepository->findByExternalId($payload['order_id']);

        if ($existing) {
            return response()->json([
                'message' => 'Order already exists.',
                'data'    => new \App\Http\Resources\OrderResource($existing),
            ]);
        }

        $order = $this->orderService->createUberEatsOrder($payload);

        return response()->json([
            'message' => 'Uber Eats order received successfully.',
            'data'    => (new \App\Http\Resources\OrderResource($order->load('items'))),
        ], 201);
    }
}