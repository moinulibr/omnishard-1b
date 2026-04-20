<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\RedisCounterService;
use App\Repositories\UserRepository;
use App\Services\ShardingConfig;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MassUserSeeder extends Seeder
{
    protected $shardConfig;
    protected $counterService;
    protected $userRepo;

    public function __construct(
        ShardingConfig $shardConfig,
        RedisCounterService $counterService,
        UserRepository $userRepo
    ) {
        $this->shardConfig = $shardConfig;
        $this->counterService = $counterService;
        $this->userRepo = $userRepo;
    }

    public function run($totalRecords = 20000)
    {
        // Pre-fetch active phase and shards outside the loop for performance
        $activePhase = $this->shardConfig->getActivePhase();
        $activeShards = collect($activePhase['shards'])->where('is_active', true)->toArray();
        $phaseId = $activePhase['phase_no'];

        $batchSize = 4000;
        $defaultPassword = Hash::make('password');

        $this->command->info("Starting mass seeding of $totalRecords records into Phase $phaseId...");
        $bar = $this->command->getOutput()->createProgressBar($totalRecords);
        $bar->start();

        $insertedCount = 0;

        while ($insertedCount < $totalRecords) {
            $currentBatchSize = min($batchSize, $totalRecords - $insertedCount);
            $shardData = [];
            $metadataData = [];

            for ($i = 0; $i < $currentBatchSize; $i++) {
                // Collision-proof ID Generation
                $userId = (int) (now()->getTimestampMs() . $i . rand(10, 99));

                // Dynamic Shard Selection from Active Shards
                $shardId = array_rand($activeShards);
                $selectedShard = $activeShards[$shardId];
                $shardConnection = $selectedShard['connection'];

                $email = 'user_mail_' . $userId . '@example.com';
                $phone = '01' . rand(100000000, 999999999);

                // Prepare Shard User Table Data
                $shardData[$shardConnection][] = [
                    'id'         => $userId,
                    'name'       => 'User_' . Str::random(5),
                    'email'      => $email,
                    'phone'      => $phone,
                    'password'   => $defaultPassword,
                    'phase_id'   => $phaseId,
                    'shard_key'  => $shardConnection,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Prepare Global Metadata Data
                $metadataData[] = [
                    'id'         => $userId,
                    'email'      => $email,
                    'phone'      => $phone,
                    'phase_id'   => $phaseId,
                    'shard_key'  => $shardConnection,
                    'shard_id'   => $shardId,
                    'created_at' => now(),
                ];
            }

            // Bulk Insert to Target Shards
            foreach ($shardData as $connection => $users) {
                $this->userRepo->bulkInsertToShard($connection, $users);
            }

            // Bulk Insert to Metadata Database
            $this->userRepo->insertMetadata($metadataData);

            $insertedCount += $currentBatchSize;
            $bar->advance($currentBatchSize);
        }

        // Update Global Count in Redis
        $this->counterService->incrementBy('total_users_count', $totalRecords);

        $bar->finish();
        $this->command->info("\nSuccessfully seeded $totalRecords records across active shards.");
    }
}