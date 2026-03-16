<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory;

    protected $table = 'deliveries';

    protected $fillable = [
        'order_id',
        'provider',
        'external_delivery_id',
        'delivery_status',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}