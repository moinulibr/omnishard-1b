<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

trait ShardedPaginator
{
    /**
     * Optimized Sharded Pagination using ID-based Filtering (Cursor)
     */
    public function getGlobalPaginatedData(array $shards, string $table, int $perPage = 15, $orderBy, $totalCount, array $select = ['*'])
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
    }
}