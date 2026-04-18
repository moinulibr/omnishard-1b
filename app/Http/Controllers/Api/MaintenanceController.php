<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

//redis related - mantenance
class MaintenanceController extends Controller
{
    /**
     * Database real time user count sync to redis
     * ডাটাবেস থেকে রিয়েল কাউন্ট নিয়ে রেডিস আপডেট করা।
     */
    public function syncTotalCount()
    {
        $shards = config('database.shards'); // আপনার শার্ড লিস্ট
        $total = 0;

        foreach ($shards as $shard) {
            $total += DB::connection($shard)->table('users')->count();
        }

        // রেডিসে ফাইনাল ভ্যালু সেট করা
        Redis::set('total_users_count', $total);

        return response()->json(['message' => 'Redis counter synced!', 'total' => $total]);
    }

    /**
     * রেডিসের কাউন্টারটি পুরোপুরি ডিলিট বা রিসেট করা।
     */
    public function resetRedisCounter()
    {
        Redis::del('total_users_count'); // পুরোপুরি ডিলিট
        // অথবা Redis::set('total_users_count', 0); // জিরো সেট করা

        return response()->json(['message' => 'Redis counter reset to zero.']);
    }
}