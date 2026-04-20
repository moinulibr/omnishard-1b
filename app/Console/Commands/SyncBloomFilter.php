<?php
    namespace App\Console\Commands;

    use App\Services\ShardingConfig;
    use Illuminate\Console\Command;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Redis;

    /**
     * Class SyncBloomFilter
     * Synchronizes existing shard data (Email & Phone) with Redis Bloom Filter and Hash Maps.
     */
    class SyncBloomFilter extends Command
    {
        
        /** @var string */
        protected $signature = 'user-sync:bloom-filter';

        /** @var string */
        protected $description = 'Sync all shard users (Email & Phone) to Redis Bloom Filter and Hash Maps';

        /**
         * Execute the console command.
         * @return void
         */
        /**
         * Execute the console command.
         * @return void
         */

    /**
     * Execute the console command.
     * @return void
     */
    /**
     * Execute the console command.
     * @return void
     */
    public function handle(): void
    {
        // Fetch shards dynamically from ShardingConfig
        $shards = app(ShardingConfig::class)->getAllShards();

        $this->info("Initiating Multi-Key Sync Process (Email & Phone)...");

        foreach ($shards as $shard) {
            $this->info("\nProcessing Shard: {$shard}");

            // Using cursor() instead of chunk() for lower memory footprint in large data
            DB::connection($shard)->table('users')
                ->select('id', 'email', 'phone') // Only fetch needed fields
                ->orderBy('id')
                ->chunkById(2000, function ($users) use ($shard) {
                    Redis::pipeline(function ($pipe) use ($users, $shard) {
                        foreach ($users as $user) {
                            // 1. Add to Bloom Filter (Atomic)
                            $pipe->rawCommand('BF.ADD', 'user_bloom', $user->email);

                            // 2. Set Mapping with optimized TTL if needed
                            $pipe->set("map:email:{$user->email}", $shard);

                            if ($user->phone) {
                                $pipe->rawCommand('BF.ADD', 'user_bloom', $user->phone);
                                $pipe->set("map:phone:{$user->phone}", $shard);
                            }
                        }
                    });
                    $this->output->write('.');
                });

            $this->info("\nFinished Shard: {$shard}");
        }

        $this->info("\nMulti-Key Sync Completed Successfully!");
    }
    
}
