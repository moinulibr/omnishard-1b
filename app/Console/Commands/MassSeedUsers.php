<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\MassUserSeederJob;
use App\Services\ShardingConfig;

class MassSeedUsers extends Command
{
    protected $signature = 'db:mass-seed {total=10000}';
    protected $description = 'Seed users across shards using Queues';

    public function handle()
    {
        $total = $this->argument('total');
        $chunkSize = 1000; // Each job will handle 1000 records

        // Get all active shards from your config service
        $shards = app(ShardingConfig::class)->getAllShards();
        $shardCount = count($shards);

        $this->info("Dispatching jobs for $total records...");

        for ($i = 0; $i < ($total / $chunkSize); $i++) {
            // Round-robin distribution: Distribute jobs evenly among shards
            $shardInfo = $shards[$i % $shardCount];

            // Here we pass the $count and $shardInfo into the Job
            MassUserSeederJob::dispatch($chunkSize, $shardInfo);
        }

        $this->success("All jobs have been pushed to Redis Queue!");
    }
}