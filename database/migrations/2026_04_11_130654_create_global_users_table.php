<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // We use Statement because Laravel doesn't support PARTITION BY in Schema Builder
    public function up(): void
    {
        // Using Raw SQL because Laravel Schema Builder doesn't support complex Partitioning
        DB::connection('metadata')->statement("
            CREATE TABLE global_users (
                id BIGINT UNSIGNED NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                shard_id TINYINT UNSIGNED NOT NULL,
                shard_key VARCHAR(50) NOT NULL,
                phase_id TINYINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id, email)
            ) ENGINE=InnoDB
            PARTITION BY HASH(id)
            PARTITIONS 26;
        ");

        // High-performance Indexes for Lookups
        DB::connection('metadata')->statement("CREATE INDEX idx_email_lookup ON global_users(email)");
        DB::connection('metadata')->statement("CREATE INDEX idx_phone_lookup ON global_users(phone)");
    }

    public function down(): void
    {
        Schema::connection('metadata')->dropIfExists('global_users');
    }
};
