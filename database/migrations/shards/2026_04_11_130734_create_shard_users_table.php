<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //docker exec -it omnishard-app php artisan migrate --database=shard_1 --path=database/migrations/shards
        //docker exec -it omnishard-app php artisan migrate --database=shard_2 --path=database/migrations/shards
        //if need refresh
        //docker exec -it omnishard-app php artisan migrate:refresh --database=shard_1 --path=database/migrations/shards
        //docker exec -it omnishard-app php artisan migrate:refresh --database=shard_2 --path=database/migrations/shards

        $connection = $this->getConnection();

        // if the table is existing, it will be droped for starting as feshness
        DB::connection($connection)->statement("DROP TABLE IF EXISTS users");

        DB::connection($connection)->statement("
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

        DB::connection($connection)->statement("CREATE INDEX idx_shard_auth ON users(email, password)");
        DB::connection($connection)->statement("CREATE INDEX idx_shard_phone ON users(phone)");
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('users');
    }
};