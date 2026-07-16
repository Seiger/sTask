<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the separate live-state store used by supervisor schedules.
 */
return new class extends Migration {
    /**
     * Create the supervisor state table.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('s_supervisor_states', function (Blueprint $table): void {
            $table->comment('Live state and diagnostic cursor for sTask supervisors');
            $table->bigIncrements('id');
            $table->unsignedBigInteger('worker_id');
            $table->string('identifier');
            $table->string('supervisor_key');
            $table->char('key_hash', 64)->unique();
            $table->string('state', 24)->default('stopped');
            $table->unsignedBigInteger('pid')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('supervisor_started_at')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->text('message')->nullable();
            $table->char('fingerprint', 64)->nullable();
            $table->timestamp('last_transition_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('repeat_count')->default(0);
            $table->timestamp('launch_requested_at')->nullable();
            $table->timestamps();

            $table->index('worker_id');
            $table->index('identifier');
            $table->index('state');
            $table->index('last_seen_at');
        });
    }

    /**
     * Drop the supervisor state table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('s_supervisor_states');
    }
};
