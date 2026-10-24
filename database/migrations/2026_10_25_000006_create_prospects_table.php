<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('place_id', 255);
            $table->string('name', 200);
            $table->foreignId('category_id')->constrained('categories');
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->string('city', 80)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('address_source', 20)->default('none');
            $table->string('phone', 40)->nullable();
            $table->boolean('phone_simulated')->default(false);
            $table->string('website', 255)->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->boolean('rating_simulated')->default(false);
            $table->unsignedInteger('review_count')->nullable();
            $table->boolean('reviews_simulated')->default(false);
            $table->string('opening_hours', 255)->nullable();
            $table->boolean('is_simulated')->default(false);
            $table->foreignId('nearest_outlet_id')->nullable()->constrained('outlets');
            $table->unsignedInteger('nearest_outlet_distance_m')->nullable();
            $table->unsignedSmallInteger('neighbor_count_500m')->default(0);
            $table->unsignedSmallInteger('area_density')->default(0);
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('score_breakdown')->nullable();
            $table->boolean('is_new_open')->default(false);
            $table->boolean('is_baseline')->default(false);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedTinyInteger('missed_runs')->default(0);
            $table->string('lifecycle_status', 12)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason', 40)->nullable();
            $table->string('pipeline_status', 12)->default('new');
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('demo_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('rejection_reasons');
            $table->foreignId('handled_by')->nullable()->constrained('users');
            $table->timestamp('last_contacted_at')->nullable();
            $table->foreignId('last_contacted_by')->nullable()->constrained('users');
            $table->unsignedSmallInteger('contact_count')->default(0);
            $table->boolean('do_not_contact')->default(false);
            $table->timestamp('do_not_contact_at')->nullable();
            $table->foreignId('do_not_contact_by')->nullable()->constrained('users');
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('needs_refresh')->default(false);
            $table->json('raw_tags')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'place_id']);
            $table->index(['lat', 'lng']);
            $table->index(['province_id', 'score']);
            $table->index('pipeline_status');
            $table->index('score');
            $table->index('lifecycle_status');
            $table->index('is_new_open');
            $table->index('last_contacted_at');
            $table->index('city');
            $table->index('first_seen_at');
            $table->index('won_at');
            $table->index('category_id');
            $table->index('nearest_outlet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
