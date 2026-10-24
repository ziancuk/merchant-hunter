<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->string('name', 80);
            $table->boolean('is_active')->default(false);
            $table->smallInteger('priority')->default(100);
            $table->text('api_key')->nullable();
            $table->json('config')->nullable();
            $table->integer('monthly_quota')->nullable();
            $table->decimal('cost_search_per_1000', 8, 4)->nullable();
            $table->decimal('cost_details_per_1000', 8, 4)->nullable();
            $table->integer('calls_this_month')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};
