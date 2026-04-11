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
    public function up(): void
    {
        $shards = ['shard_1', 'shard_2'];

        foreach ($shards as $shard) {
            // Drop table if exists to avoid errors during re-run
            DB::connection($shard)->statement("DROP TABLE IF EXISTS users");

            // Using Raw SQL for Partitioning inside Shards
            DB::connection($shard)->statement("
                CREATE TABLE users (
                    id BIGINT UNSIGNED NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL,
                    phone VARCHAR(20) NULL,
                    password VARCHAR(255) NOT NULL,
                    shard_key VARCHAR(50) NOT NULL,
                    phase_id TINYINT UNSIGNED DEFAULT 1,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id, email)
                ) ENGINE=InnoDB
                PARTITION BY KEY(email)
                PARTITIONS 10;
            ");

            // Adding Index for password and phone inside the shard
            DB::connection($shard)->statement("CREATE INDEX idx_shard_auth ON users(email, password)");
            DB::connection($shard)->statement("CREATE INDEX idx_shard_phone ON users(phone)");
        }
    }

    public function down(): void
    {
        foreach (['shard_1', 'shard_2'] as $shard) {
            Schema::connection($shard)->dropIfExists('users');
        }
    }
};
