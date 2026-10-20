<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tuupola\KsuidFactory;

/**
 * `notifications` is the table Laravel's own database notification channel
 * and the Notifiable trait's notifications() relation expect, with a uuid key
 * and a polymorphic notifiable. This one had neither, so anything using the
 * framework's channel would have broken against it. It also had an integer
 * key, against the KSUID convention every other table follows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->char('id', 27)->primary();
            $table->char('user_id', 27);
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'read']);
            $table->index('created_at');
        });

        if (! Schema::hasTable('notifications') || Schema::hasColumn('notifications', 'notifiable_id')) {
            return;
        }

        DB::table('notifications')->orderBy('id')->chunk(500, function ($rows): void {
            DB::table('user_notifications')->insert($rows->map(fn ($row) => [
                'id' => (string) KsuidFactory::create(),
                'user_id' => $row->user_id,
                'type' => $row->type,
                'title' => $row->title,
                'message' => $row->message,
                'data' => $row->data,
                'read' => $row->read,
                'read_at' => $row->read_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])->all());
        });

        Schema::drop('notifications');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
