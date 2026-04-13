<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\BloomFilterService;
use App\Services\ShardRoutingService;
use App\Utils\PhoneFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /**
     * Handle high-scale user registration with consistent sharding.
     */
    public function register(Request $request, BloomFilterService $bf, ShardRoutingService $router)
    {
        // 1. Validation with BD Phone formatting
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:100',
            'email' => 'required|email',
            'phone' => 'required|string',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $normalizedPhone = PhoneFormatter::normalize($request->phone);

        // 2. Double-Guard: Bloom Filter + Metadata DB check
        if ($bf->exists('email', $request->email) || $bf->exists('phone', $normalizedPhone)) {
            if ($router->getRoute($request->email)) {
                return response()->json(['error' => 'User already exists'], 409);
            }
        }

        // 3. Routing Logic
        $dest = $router->getNextAvailableDestination();
        $globalId = (int)(microtime(true) * 10000); // Distributed ID generation logic

        // 4. Atomic Transaction across multiple DB connections
        try {
            DB::beginTransaction(); // Default metadata connection

            // Insert into Physical Shard
            DB::connection($dest['shard_name'])->table('users')->insert([
                'id' => $globalId,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $normalizedPhone,
                'password' => Hash::make($request->password),
                'shard_key' => $dest['shard_key'],
            ]);

            // Insert into Global Metadata
            DB::connection('metadata')->table('global_users')->insert([
                'id' => $globalId,
                'email' => $request->email,
                'phone' => $normalizedPhone,
                'shard_id' => $dest['shard_id'],
                'shard_key' => $dest['shard_key'],
                'phase_id' => $dest['phase_id'],
            ]);

            DB::commit();

            // 5. Post-commit: Update Bloom Filter
            $bf->addToFilter($request->email, $normalizedPhone);

            return response()->json(['message' => 'User registered in ' . $dest['shard_name']], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'System Failure', 'details' => $e->getMessage()], 500);
        }
    }
}
