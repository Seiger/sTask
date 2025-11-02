<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Expression;

/**
 * Migration: sTask tables creation.
 * Permissions and groups are handled by seeder.
 */
return new class extends Migration {
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | The workers table structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_workers', function (Blueprint $table) {
            $table->comment('Workers for sTask');
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('identifier')->unique();
            $table->string('scope')->default('');
            $table->string('class');
            $table->boolean('active')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('settings')->default(new Expression('(JSON_ARRAY())'));
            $table->unsignedInteger('hidden')->default(0);
            $table->timestamps();

            $table->index('identifier');
            $table->index('scope');
            $table->index('active');
            $table->index('position');
        });

        /*
        |--------------------------------------------------------------------------
        | The tasks table structure
        |--------------------------------------------------------------------------
        */
        Schema::create('s_tasks', function (Blueprint $table) {
            $table->comment('Async tasks for sTask');
            $table->bigIncrements('id');
            $table->string('identifier');
            $table->string('action');
            $table->unsignedSmallInteger('status')->default(10);
            $table->text('message')->nullable();
            $table->unsignedInteger('started_by')->nullable();
            $table->longText('meta')->nullable();
            $table->longText('result')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->string('priority')->default('normal');
            $table->integer('progress')->default(0);
            $table->timestamps();

            $table->index(['identifier', 'action']);
            $table->index('status');
            $table->index('started_by');
            $table->index('start_at');
            $table->index('created_at');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s_tasks');
        Schema::dropIfExists('s_workers');
    }
};
