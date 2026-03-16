<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'external_order_id' => $this->external_order_id,
            'order_source'      => $this->order_source,
            'customer_name'     => $this->customer_name,
            'phone'             => $this->phone,
            'address'           => $this->address,
            'status'            => $this->status,
            'items'             => OrderItemResource::collection(
                                    $this->whenLoaded('items')
                                  ),
            'delivery'          => new DeliveryResource(
                                    $this->whenLoaded('delivery')
                                  ),
            'created_at'        => $this->created_at->toDateTimeString(),
        ];
    }
}