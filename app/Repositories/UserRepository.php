<?php

namespace App\Repositories;

use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface
{
    public function createInShard(array $data, string $shard): int
    {
        return DB::connection($shard)->table('users')->insertGetId($data);
    }

    public function findInShard(string $identifier, string $column, string $shard): ?object
    {
        return DB::connection($shard)->table('users')
            ->where($column, $identifier)
            ->first();
    }

    public function updateInShard(int $id, array $data, string $shard): bool
    {
        return DB::connection($shard)->table('users')
            ->where('id', $id)
            ->update($data);
    }

    public function deleteInShard(int $id, string $shard): bool
    {
        return DB::connection($shard)->table('users')
            ->where('id', $id)
            ->delete();
    }
}
