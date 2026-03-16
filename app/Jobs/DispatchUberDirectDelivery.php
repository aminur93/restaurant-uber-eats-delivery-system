<?php

namespace App\Jobs;

use App\Contracts\DeliveryServiceInterface;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;


class DispatchUberDirectDelivery implements ShouldQueue
{
    use Queueable, InteractsWithQueue, Dispatchable, SerializesModels;

    // how many times the job may be attempted
    public int $tries = 3;

    // Job timeout (seconds)
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly Order $order)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DeliveryServiceInterface $deliverService): void
    {
        Log::info('[Job] Dispatching Uber Direct delivery', [
            'order_id' => $this->order->id,
        ]);

        $deliverService->requestDelivery($this->order);

        Log::info('[Job] Uber Direct delivery dispatched successfully', [
            'order_id' => $this->order->id,
        ]);
    }

     // Retry with gap how many seconds after failure
    public function backoff(): array
    {
        return [30, 60, 120]; // 1st retry 30s, 2nd 60s, 3rd 120s
    }

    // all retries failed permanently
    public function failed(\Throwable $exception): void
    {
        Log::error('[Job] Uber Direct delivery permanently failed', [
            'order_id' => $this->order->id,
            'error'    => $exception->getMessage(),
            'trace'    => $exception->getTraceAsString(),
        ]);
    }
}