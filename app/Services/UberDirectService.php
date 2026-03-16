<?php

namespace App\Services;

use App\Contracts\DeliveryServiceInterface;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UberDirectService implements DeliveryServiceInterface
{
    private string $baseUrl;
    private string $customerId;
    private bool   $testMode;

    public function __construct(
        private readonly UberAuthService $uberAuthService,
    ) {
        $this->baseUrl    = 'https://api.uber.com/v1/customers';
        $this->customerId = config('services.uber_direct.customer_id');
        $this->testMode   = (bool) config('services.uber_direct.test_mode');
    }

    public function requestDelivery(Order $order): array
    {
        // Test mode a fake response — real Uber API call is skipped
        if ($this->testMode) {
            return $this->fakDeliveryResponse($order);
        }

        $token = $this->uberAuthService->getAccessToken();

        $response = Http::withToken($token)
            ->retry(3, 500)
            ->post("{$this->baseUrl}/{$this->customerId}/deliveries", [
                'pickup_address'    => config('restaurant.pickup_address'),
                'dropoff_address'   => $order->address,
                'customer_phone'    => $order->phone,
                'order_description' => $order->items
                    ->map(fn($i) => "{$i->quantity}x {$i->item_name}")
                    ->join(', '),
            ]);

        if ($response->failed()) {
            Log::error('Uber Direct API error', [
                'order_id' => $order->id,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new \RuntimeException('Uber Direct delivery request failed.');
        }

        $data = $response->json();

        $this->saveDelivery($order->id, $data['delivery_id'] ?? null);

        Log::info('Uber Direct delivery created', [
            'order_id'    => $order->id,
            'delivery_id' => $data['delivery_id'] ?? null,
        ]);

        return $data;
    }

    public function handleStatusUpdate(string $externalDeliveryId, string $status): bool
    {
        $delivery = Delivery::where('external_delivery_id', $externalDeliveryId)->first();

        if (! $delivery) {
            Log::warning('Delivery not found for status update', [
                'external_delivery_id' => $externalDeliveryId,
                'status'               => $status,
            ]);
            return false;
        }

        $updated = $delivery->update(['delivery_status' => $status]);

        Log::info('Delivery status updated', [
            'external_delivery_id' => $externalDeliveryId,
            'status'               => $status,
        ]);

        return (bool) $updated;
    }

    // ─── Test Mode ───────────────────────────────────────────────────────────

    private function fakDeliveryResponse(Order $order): array
    {
        $fakeDeliveryId = 'FAKE-DEL-' . strtoupper(Str::random(8));

        $this->saveDelivery($order->id, $fakeDeliveryId);

        Log::info('[TEST MODE] Fake Uber Direct delivery created', [
            'order_id'           => $order->id,
            'fake_delivery_id'   => $fakeDeliveryId,
        ]);

        return [
            'delivery_id'   => $fakeDeliveryId,
            'status'        => 'pending',
            'test_mode'     => true,
        ];
    }

    private function saveDelivery(int $orderId, ?string $externalDeliveryId): void
    {
        Delivery::create([
            'order_id'             => $orderId,
            'provider'             => 'uber_direct',
            'external_delivery_id' => $externalDeliveryId,
            'delivery_status'      => 'pending',
        ]);
    }
}