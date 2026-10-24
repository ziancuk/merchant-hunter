<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('daily_digests', function (Blueprint $table) {
            $table->id();
            $table->date('digest_date')->unique();
            $table->json('payload');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_digests');
        Schema::dropIfExists('settings');
    }
};
