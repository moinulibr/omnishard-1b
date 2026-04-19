<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RedisCounterService;
use App\Services\ShardingConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class MaintenanceController
 * Dynamic controller to manage any Redis counter via specific service.
 */
class MaintenanceController extends Controller
{
    protected $shardConfig;
    protected $counterService;

    public function __construct(ShardingConfig $shardConfig, RedisCounterService $counterService)
    {
        $this->shardConfig = $shardConfig;
        $this->counterService = $counterService;
    }

    /**
     * FULL SYNC: Recount from all DB shards and SET in Redis.
     * Usage: /api/maintenance/sync?key=total_users_count&table=users
     */
    public function syncCount(Request $request)
    {
        $key = $request->query('key');
        $table = $request->query('table');

        if (!$key || !$table) {
            return response()->json(['error' => 'Key and Table are required.'], 400);
        }

        $shards = $this->shardConfig->getAllShards();
        $total = 0;

        foreach ($shards as $shard) {
            $total += DB::connection($shard)->table($table)->count();
        }

        $this->counterService->set($key, $total);

        return response()->json([
            'status' => 'success',
            'key' => $key,
            'synced_total' => $total
        ]);
    }

    /**
     * ADJUST: Single Increment or Decrement.
     * Usage: /api/maintenance/adjust?key=total_users_count&action=up|down
     */
    public function adjust(Request $request)
    {
        $key = $request->query('key');
        $action = $request->query('action');

        if (!$key) return response()->json(['error' => 'Key is required.'], 400);

        if ($action === 'up') {
            $value = $this->counterService->increment($key);
        } else {
            $value = $this->counterService->decrement($key);
        }

        return response()->json(['status' => 'success', 'key' => $key, 'new_value' => $value]);
    }

    /**
     * BULK ADD: Increment by a large specific amount.
     * Usage: /api/maintenance/add-bulk?key=total_users_count&amount=1000
     */
    public function addBulk(Request $request)
    {
        $key = $request->query('key');
        $amount = (int) $request->query('amount');

        if (!$key || $amount <= 0) {
            return response()->json(['error' => 'Valid Key and Amount are required.'], 400);
        }

        $value = $this->counterService->incrementBy($key, $amount);

        return response()->json(['status' => 'success', 'key' => $key, 'new_value' => $value]);
    }

    /**
     * RESET/DELETE: Completely remove the key.
     * Usage: /api/maintenance/reset?key=total_users_count
     */
    public function reset(Request $request)
    {
        $key = $request->query('key');

        if (!$key) return response()->json(['error' => 'Key is required.'], 400);

        $this->counterService->delete($key);

        return response()->json(['status' => 'success', 'message' => "Key $key deleted from Redis."]);
    }

    /**
     * GET CURRENT: Just check the value.
     * Usage: /api/maintenance/get?key=total_users_count
     */
    public function getStatus(Request $request)
    {
        $key = $request->query('key');
        if (!$key) return response()->json(['error' => 'Key is required.'], 400);

        return response()->json([
            'key' => $key,
            'value' => $this->counterService->get($key)
        ]);
    }
}