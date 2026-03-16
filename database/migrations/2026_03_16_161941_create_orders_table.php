<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('external_order_id')->nullable()->unique();
            $table->enum('order_source', ['website', 'uber_eats']);
            $table->string('customer_name');
            $table->string('phone');
            $table->text('address');
            $table->enum('status', [
                'pending',
                'confirmed',
                'preparing',
                'ready_for_pickup',
                'completed',
                'cancelled',
            ])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};