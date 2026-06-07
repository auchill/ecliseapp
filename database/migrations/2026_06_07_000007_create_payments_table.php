<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('payable');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repair_order_id')->nullable()->constrained('repair_bookings')->nullOnDelete();
            $table->string('gateway');
            $table->string('gateway_reference_id')->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('paypal_order_id')->nullable()->index();
            $table->string('paypal_capture_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('cad');
            $table->string('status')->default('pending')->index();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'gateway_reference_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('cart_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('payment_gateway')->nullable()->after('payment_provider');
            $table->decimal('payment_amount', 10, 2)->default(0)->after('payment_status');
            $table->string('currency', 3)->default('cad')->after('payment_amount');
            $table->timestamp('paid_at')->nullable()->after('currency');
            $table->timestamp('inventory_committed_at')->nullable()->after('paid_at');
        });

        Schema::table('repair_bookings', function (Blueprint $table): void {
            $table->string('payment_status')->default('pending')->after('status');
            $table->string('payment_gateway')->nullable()->after('payment_status');
            $table->decimal('payment_amount', 10, 2)->default(0)->after('payment_gateway');
            $table->string('currency', 3)->default('cad')->after('payment_amount');
            $table->timestamp('paid_at')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('repair_bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_status',
                'payment_gateway',
                'payment_amount',
                'currency',
                'paid_at',
            ]);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cart_id');
            $table->dropColumn([
                'payment_gateway',
                'payment_amount',
                'currency',
                'paid_at',
                'inventory_committed_at',
            ]);
        });

        Schema::dropIfExists('payments');
    }
};
