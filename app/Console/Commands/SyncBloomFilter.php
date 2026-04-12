<?php
    namespace App\Console\Commands;

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
        protected $signature = 'sync:bloom';

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
    public function handle(): void
    {
        $shards = ['shard_1', 'shard_2'];
        $this->info("Initiating Multi-Key Sync Process (Email & Phone)...");

        foreach ($shards as $shard) {
            $count = DB::connection($shard)->table('users')->count();
            $this->info("\nFound {$count} users in {$shard}. Starting sync...");

            DB::connection($shard)->table('users')->orderBy('id')->chunk(2000, function ($users) use ($shard) {
                // We use Redis::connection() to ensure we are talking to the correct driver
                Redis::pipeline(function ($pipe) use ($users, $shard) {
                    foreach ($users as $user) {
                        // BF.ADD via rawCommand/command inside pipeline
                        $pipe->command('BF.ADD', ['user_bloom', $user->email]);
                        $pipe->set("map:email:{$user->email}", $shard);

                        if ($user->phone) {
                            $pipe->command('BF.ADD', ['user_bloom', $user->phone]);
                            $pipe->set("map:phone:{$user->phone}", $shard);
                        }
                    }
                });
                $this->output->write('.');
            });
            $this->info("\nFinished {$shard}");
        }

        $this->info("\nMulti-Key Sync Completed Successfully!");
    }
    
}
