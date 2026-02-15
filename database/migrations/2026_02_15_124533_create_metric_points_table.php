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
        Schema::create('metric_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->constrained()->onDelete('cascade');
            $table->decimal('value', 20, 4);
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();
            
            $table->index(['metric_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_points');
    }
};
