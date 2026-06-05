<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('device_type');
            $table->string('brand');
            $table->string('model_compatibility')->nullable();
            $table->string('part_category');
            $table->string('image_path')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('stock_status')->default('Check availability');
            $table->string('supplier')->default('MobileSentrix');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
