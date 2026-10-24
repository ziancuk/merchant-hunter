<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 160);
            $table->string('address', 255)->nullable();
            $table->string('city', 80);
            $table->foreignId('province_id')->constrained('provinces');
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->string('place_id', 255)->nullable();
            $table->string('geocode_source', 20)->nullable();
            $table->string('geocode_status', 20)->default('ok');
            $table->unsignedTinyInteger('geocode_score')->nullable();
            $table->json('geocode_candidates')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_simulated')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('baseline_done_at')->nullable();
            $table->timestamps();

            $table->index(['lat', 'lng']);
            $table->index('is_active');
            $table->index('province_id');
            $table->index('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
