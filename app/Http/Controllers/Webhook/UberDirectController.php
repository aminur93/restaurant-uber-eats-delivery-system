<?php

namespace App\Http\Controllers\Webhook;

use App\Contracts\DeliveryServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UberDirectController extends Controller
{
    private array $allowedStatuses = [
        'courier_assigned',
        'courier_picked_up',
        'delivered',
        'cancelled',
    ];

    public function __construct(
        private readonly DeliveryServiceInterface $deliveryService,
    ) {}

    // POST /api/webhook/uber-direct/status
    public function handleStatus(Request $request): JsonResponse
    {
        // Signature verify
        if (! $this->verifySignature($request)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $payload = $request->validate([
            'delivery_id' => 'required|string',
            'status'      => 'required|string|in:' . implode(',', $this->allowedStatuses),
        ]);

        $updated = $this->deliveryService->handleStatusUpdate(
            $payload['delivery_id'],
            $payload['status'],
        );

        if (! $updated) {
            return response()->json([
                'message' => 'Delivery not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Delivery status updated successfully.',
        ]);
    }

    private function verifySignature(Request $request): bool
    {
        $secret    = config('services.uber_direct.webhook_secret');
        $signature = $request->header('X-Uber-Signature');

        // if not configured, skip verification (e.g. local dev)
        if (! $secret) {
            return true;
        }

        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}