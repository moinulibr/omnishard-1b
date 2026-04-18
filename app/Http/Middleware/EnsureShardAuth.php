<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class EnsureShardAuth
{
    /**
     * intercepts the request to route DB connection to the correct shard.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($token) {
            // Step A: Extract plain token if it contains '|'
            $tokenValue = str_contains($token, '|') ? explode('|', $token)[1] : $token;

            // Step B: Hash it (Sanctum stores hashed versions in DB)
            $hashedToken = hash('sha256', $tokenValue);

            // Step C: Look up in Redis
            $shardKey = \Illuminate\Support\Facades\Redis::get("token_shard:{$hashedToken}");

            if ($shardKey) {
                // Set connection for the entire request lifecycle
                config(['database.default' => $shardKey]);
                \Illuminate\Support\Facades\DB::purge($shardKey);
            } else {
                // Log it for debugging (Optional)
                // \Log::info("Shard key not found for token hash: " . $hashedToken);
                return response()->json(['message' => 'Invalid or Expired Token Shard Map.'], 401);
            }
        }

        return $next($request);
    }
}
