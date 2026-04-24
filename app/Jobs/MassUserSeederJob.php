<?php
// app/Jobs/MassUserSeederJob.php

namespace App\Jobs;

use App\Utils\IdGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class MassUserSeederJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $count;
    public $shardInfo;

    /**
     * The $count and $shardInfo are passed when we dispatch the job.
     */
    public function __construct($count, array $shardInfo)
    {
        $this->count = $count;
        $this->shardInfo = $shardInfo;
    }

    public function handle()
    {
        $users = [];
        $metadata = [];
        $password = Hash::make('password123');

        // Get shard connection name (e.g., shard_1)
        $connection = $this->shardInfo['shard_key'];
        $shardId = $this->shardInfo['shard_id'];

        for ($i = 0; $i < $this->count; $i++) {
            $id = IdGenerator::generate('user');
            $email = "user_{$id}@example.com";

            // Prepare shard database data
            $users[] = [
                'id' => $id,
                'name' => 'User_' . $id,
                'email' => $email,
                'password' => $password,
                'shard_key' => $connection,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Prepare metadata database data
            $metadata[] = [
                'id' => $id,
                'shard_id' => $shardId,
                'email' => $email,
            ];
        }

        // Optimized bulk insert (one query per DB)
        DB::connection($connection)->table('users')->insert($users);
        DB::connection('metadata')->table('global_users')->insert($metadata);
    }
}