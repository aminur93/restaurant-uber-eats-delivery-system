<?php

namespace App\Http\Controllers\Api;

use App\Contracts\OrderServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderServiceInterface $orderService,
    ) {}

    // GET /api/orders
    public function index(): AnonymousResourceCollection
    {
        $pagination = request()->boolean('pagination', true);
        
        $perPage    = (int) request()->get('per_page', 10);

        if ($pagination) {
            // Paginated + Redis cached
            $orders = $this->orderService->getPaginatedOrders($perPage);
        } else {
            // All orders without pagination
            $orders = $this->orderService->getAllOrders();
        }

        return OrderResource::collection($orders);
    }

    // POST /api/orders
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createWebsiteOrder($request->validated());

        return (new OrderResource($order->load('items')))
            ->response()
            ->setStatusCode(201);
    }

    // PATCH /api/orders/{id}/ready
    public function markReady(int $id): JsonResponse
    {
        $order = app(\App\Contracts\OrderRepositoryInterface::class)->findById($id);

        if (! $order) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        $this->orderService->markReadyAndDispatch($id);

        return response()->json([
            'message' => 'Order marked as ready. Delivery dispatched to queue.',
        ]);
    }
}