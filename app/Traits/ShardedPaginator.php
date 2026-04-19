<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

trait ShardedPaginator
{
    /**
     * Globally handle pagination across multiple shards for any table.
     */
    public function getGlobalPaginatedData(array $shards, string $table, int $perPage, string $redisKey, array $selectColumns = ['*'])
    {
        $allResults = collect();
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $offset = ($currentPage - 1) * $perPage;

        foreach ($shards as $shard) {
            $data = DB::connection($shard)
                ->table($table)
                ->select($selectColumns) // You can pass specific columns as a parameter too
                ->orderBy('created_at', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(function ($item) use ($shard) {
                    $item->shard_key = $shard;
                    return $item;
                });

            $allResults = $allResults->concat($data);
        }

        // Merge and take the top results for the current page
        $finalResults = $allResults->sortByDesc('created_at')->take($perPage)->values();

        // Get count from Redis (Lightning fast)
        $totalCount = (int) Redis::get($redisKey) ?: 0;

        return new LengthAwarePaginator(
            $finalResults,
            $totalCount,
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()]
        );
    }
}