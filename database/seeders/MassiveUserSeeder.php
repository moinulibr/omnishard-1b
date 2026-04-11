<?php

namespace Database\Seeders;

use App\Services\BloomFilterService;
use App\Services\ShardRoutingService;
use App\Utils\PhoneFormatter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MassiveUserSeeder extends Seeder
{
    public function run(BloomFilterService $bf, ShardRoutingService $router): void
    {
        $totalRecords = 1000000;
        $chunkSize = 5000; // Adjusted for memory safety
        $password = Hash::make('secret123');
        $availableShards = $router->getAllShards();

        for ($i = 0; $i < ($totalRecords / $chunkSize); $i++) {
            $batchData = []; // Temporary store for bulk insert

            for ($j = 0; $j < $chunkSize; $j++) {
                $id = (int)(microtime(true) * 10000) + mt_rand(1000, 9999);
                $email = Str::random(12) . "@omni.com";
                $phone = "01" . mt_rand(100000000, 999999999);

                // Use Global Routing
                $dest = $router->getNextAvailableDestination();

                // Prepare Data
                $batchData[$dest['shard_name']][] = [
                    'id' => $id,
                    'name' => 'User_' . $id,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $password,
                    'shard_key' => $dest['shard_key']
                ];

                $batchMetadata[] = [
                    'id' => $id,
                    'email' => $email,
                    'phone' => $phone,
                    'shard_id' => $dest['shard_id'],
                    'shard_key' => $dest['shard_key'],
                    'phase_id' => $dest['phase_id']
                ];

                $bf->addToFilter($email, $phone);
            }

            // Bulk Insert into respective Shards
            foreach ($batchData as $shardName => $data) {
                DB::connection($shardName)->table('users')->insert($data);
            }

            // Bulk Insert into Metadata
            DB::connection('metadata')->table('global_users')->insert($batchMetadata);

            unset($batchData, $batchMetadata); // Free Memory
            $this->command->info("Chunk " . ($i + 1) . " completed.");
        }
    }
}
