<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geocode_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->unique()->constrained('prospects')->cascadeOnDelete();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->string('status', 12)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_queue');
    }
};
