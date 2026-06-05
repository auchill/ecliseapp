<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_number')->unique();
            $table->string('customer_name');
            $table->string('email');
            $table->string('phone');
            $table->string('device_type');
            $table->string('device_brand')->nullable();
            $table->string('device_model')->nullable();
            $table->string('issue_category');
            $table->text('issue_description');
            $table->date('preferred_appointment_date')->nullable();
            $table->string('preferred_appointment_time')->nullable();
            $table->string('device_image_path')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->string('status')->default('Submitted');
            $table->date('estimated_completion_date')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_bookings');
    }
};
