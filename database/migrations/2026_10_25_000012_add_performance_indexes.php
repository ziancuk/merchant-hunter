<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Session 4 performance indexes (D-100): the kanban/table keyset sorts and a few unindexed filters.
 * DESC columns need raw DDL (the Blueprint cannot express them); the syntax is valid on SQLite, MySQL 8 and PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kanban column: WHERE lifecycle_status = ? AND pipeline_status = ? ORDER BY score DESC, id DESC.
        DB::statement('create index prospects_lifecycle_pipeline_score_id_index on prospects (lifecycle_status, pipeline_status, score desc, id desc)');
        // Table view / map listing: WHERE lifecycle_status = ? ORDER BY score DESC, id.
        DB::statement('create index prospects_lifecycle_score_id_index on prospects (lifecycle_status, score desc, id)');

        // The single-column lifecycle index is now the left prefix of both composites.
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex('prospects_lifecycle_status_index');
            $table->index('handled_by');
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->index('geocode_status');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropIndex(['geocode_status']);
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex(['handled_by']);
            $table->dropIndex('prospects_lifecycle_score_id_index');
            $table->dropIndex('prospects_lifecycle_pipeline_score_id_index');
            $table->index('lifecycle_status');
        });
    }
};
