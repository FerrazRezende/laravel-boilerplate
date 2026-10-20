<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * feature_histories is the audit trail for flag changes. Cascading on the
 * actor meant deleting an admin's account erased every change they had made,
 * so the actor is now nulled instead and the entry reads "Removed user".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feature_histories', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
        });

        Schema::table('feature_histories', function (Blueprint $table) {
            $table->char('actor_id', 27)->nullable()->change();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('feature_histories', function (Blueprint $table) {
            $table->dropForeign(['actor_id']);
        });

        DB::table('feature_histories')->whereNull('actor_id')->delete();

        Schema::table('feature_histories', function (Blueprint $table) {
            $table->char('actor_id', 27)->nullable(false)->change();
            $table->foreign('actor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
