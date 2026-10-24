<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 40);
            $table->string('status', 12);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('outlets_total')->default(0);
            $table->unsignedSmallInteger('outlets_ok')->default(0);
            $table->unsignedSmallInteger('outlets_failed')->default(0);
            $table->unsignedInteger('prospects_new')->default(0);
            $table->unsignedInteger('prospects_updated')->default(0);
            $table->unsignedInteger('prospects_closed')->default(0);
            $table->unsignedInteger('api_calls')->default(0);
            $table->text('error')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['command', 'started_at']);
        });

        Schema::create('scheduler_run_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduler_run_id')->constrained('scheduler_runs')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets');
            $table->string('provider', 20);
            $table->string('status', 12);
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('closed_count')->default(0);
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_run_outlets');
        Schema::dropIfExists('scheduler_runs');
    }
};
