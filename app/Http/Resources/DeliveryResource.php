<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'provider'             => $this->provider,
            'external_delivery_id' => $this->external_delivery_id,
            'delivery_status'      => $this->delivery_status,
            'created_at'           => $this->created_at->toDateTimeString(),
        ];
    }
}