<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

trait ShardedPaginator
{

    /**
     * Optimized Sharded Pagination using ID-based Filtering (Cursor)
     */
    public function getGlobalPaginatedData(array $shards, string $table, int $perPage = 20, $orderBy='ASC', $totalCount, array $select = ['*'])
    {
        $lastId = request()->input('last_id', 0);

        // 1. Fetch exactly $perPage IDs from Metadata DB
        $metadataRecords = DB::connection('metadata')
            ->table('global_users')
            ->select('id', 'shard_id')
            ->where('id', '>', $lastId)
            ->orderBy('id', $orderBy)
            ->limit($perPage)
            ->get();

        if ($metadataRecords->isEmpty()) {
            return $this->formatResponse(collect(), null, $perPage, $table);
        }

        // 2. Map shard_id to IDs to avoid nested loops later
        $shardGroups = $metadataRecords->groupBy('shard_id');
        $allShardData = collect();

        foreach ($shardGroups as $shardId => $records) {
            $ids = $records->pluck('id')->toArray();
            $connection = "shard_{$shardId}";

            // Fetch bulk data from each shard once
            $shardResults = DB::connection($connection)
                ->table($table)
                ->select($select)
                ->whereIn('id', $ids)
                ->get();

            $allShardData = $allShardData->concat($shardResults);
        }

        // 3. Sort and finalize results
        $finalResults = $allShardData->sortBy('id')->values()->map(function ($item) use ($metadataRecords) {
            // Find shard info from metadata without a loop
            $meta = $metadataRecords->firstWhere('id', $item->id);
            $item->shard_key = "shard_" . ($meta->shard_id ?? 'unknown');
            return $item;
        });

        $nextId = $finalResults->last()->id ?? null;

        return $this->formatResponse($finalResults, $nextId, $perPage, $table);
    }

    private function formatResponse($data, $nextId, $perPage, $table)
    {
        $totalCount = (int) Redis::get("total_{$table}_count") ?: 0;
        $lastId = request()->input('last_id', 0);
        $fullUrl = $data->count() === $perPage ? request()->fullUrlWithQuery(['last_id' => $nextId]) : request()->fullUrlWithQuery(['last_id' => $lastId]);
        return [
            'data' => $data,
            'meta' => [
                'next_id' => $nextId,
                'per_page' => $perPage,
                'total' => $totalCount,
                'fetched_total' => $data->count(),
                'has_more' => $data->count() === $perPage
            ],
            'links' => [
                //'next_url' => $nextId ? request()->fullUrlWithQuery(['last_id' => $nextId]) : null
                'next_url' => $fullUrl
            ]
        ];
    }


    /*  public function getGlobalPaginatedData(array $shards, string $table, int $perPage = 20, $orderBy, $totalCount, array $select = ['*'])
    {
        $lastId = request()->input('last_id', 0);

        // ১. সরাসরি Metadata DB থেকে ২০টি আইডি এবং তাদের শার্ড লোকেশন নিন
        // এটি আপনার রেজাল্টকে একদম নিখুঁত ২০টিই রাখবে।
        $metadataRecords = DB::connection('metadata')
            ->table('global_users')
            ->select('id', 'shard_id')
            ->where('id', '>', $lastId)
            ->orderBy('id', $orderBy)
            ->limit($perPage)
            ->get();

        if ($metadataRecords->isEmpty()) {
            return $this->formatResponse(collect(), null, $perPage, $table);
        }

        // ২. আইডিগুলোকে তাদের শার্ড অনুযায়ী গ্রুপ করুন যাতে ডাটাবেসে কুয়েরি কম লাগে
        $shardGroups = $metadataRecords->groupBy('shard_id');
        $finalResults = collect();

        foreach ($shardGroups as $shardId => $records) {
            $ids = $records->pluck('id')->toArray();
            $connection = "shard_{$shardId}"; // আপনার শার্ড কানেকশন নাম

            $shardData = DB::connection($connection)
                ->table($table)
                ->select($select)
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id'); // আইডি দিয়ে কি সেট করুন যাতে পরে সর্ট করা সহজ হয়

            foreach ($ids as $id) {
                if (isset($shardData[$id])) {
                    $item = $shardData[$id];
                    $item->shard_key = $connection;
                    $finalResults->push($item);
                }
            }
        }

        // ৩. গ্লোবাল আইডি অনুযায়ী আবার সর্ট করুন (সব শার্ডের ডাটা একসাথে মেলাতে)
        $finalResults = $finalResults->sortBy('id')->values();
        $nextId = $finalResults->last()->id ?? null;

        return $this->formatResponse($finalResults, $nextId, $perPage, $table);
    } */
    /**
     * Optimized Sharded Pagination using ID-based Filtering (Cursor)
     */
    /* public function getGlobalPaginatedData(array $shards, string $table, int $perPage = 15, $orderBy, $totalCount, array $select = ['*'])
    {
        // 2. Get the cursor (last_id) from the request
        $lastId = request()->input('last_id', 0);

        $allResults = collect();

        foreach ($shards as $shard) {
            // DB directly jumps to the record using Primary Key Index
            $data = DB::connection($shard)
                ->table($table)
                ->select($select)
                ->where('id', '>', $lastId)
                ->orderBy('id', $orderBy)
                ->limit($perPage)
                ->get()
                ->map(function ($item) use ($shard) {
                    $item->shard_key = $shard; // Tagging which shard it came from
                    return $item;
                });

            $allResults = $allResults->concat($data);
        }

        // 3. Merge results from all shards, sort by ID, and take only the perPage limit
        $finalResults = $allResults->sortBy('id')->take($perPage)->values();

        // 4. Get total count from Redis (Lightning fast for 1B records)
        //$totalCount = (int) Redis::get("total_{$table}_count") ?: 0;
        $totalCount = (int) Redis::get($totalCount) ?: 0;

        // 5. Calculate Next Cursor
        $nextCursor = $finalResults->isEmpty() ? null : $finalResults->last()->id;

        return [
            'data' => $finalResults,
            'meta' => [
                'per_page' => $perPage,
                'next_id' => $nextCursor,
                'total' => $totalCount,
                //'has_more' => $finalResults->count() === $perPage,
                'has_more' => $finalResults->count() >= $perPage,
            ],
            'links' => [
                'next_page_url' => $nextCursor ? request()->fullUrlWithQuery(['last_id' => $nextCursor]) : null,
            ]
        ];
    } */
   
}