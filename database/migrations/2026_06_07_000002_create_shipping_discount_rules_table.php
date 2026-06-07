<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_discount_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('minimum_order_amount', 10, 2)->default(0);
            $table->string('discount_type');
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_discount_rules');
    }
};
