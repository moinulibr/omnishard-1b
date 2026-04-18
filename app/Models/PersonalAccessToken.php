<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Class PersonalAccessToken
 * Custom model to override Sanctum's default behavior and support sharding.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The table associated with the model.
     * Ensure this table exists in all your shards.
     */
    protected $table = 'personal_access_tokens';

    /**
     * Note: We don't fix the $connection here. 
     * It will inherit the connection from the User model (the shard).
     */
}
