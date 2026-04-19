<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //docker exec -it omnishard-app php artisan migrate --database=metadata --path=database/migrations/metadata
        // $this->getConnection() In the command line --database Connection understand from this flag
        $connection = $this->getConnection() ?: 'metadata';

        DB::connection($connection)->statement("DROP TABLE IF EXISTS global_users");

        DB::connection($connection)->statement("
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

        DB::connection($connection)->statement("CREATE INDEX idx_email_lookup ON global_users(email)");
        DB::connection($connection)->statement("CREATE INDEX idx_phone_lookup ON global_users(phone)");
    }

    public function down(): void
    {
        Schema::connection($this->getConnection() ?: 'metadata')->dropIfExists('global_users');
    }
};