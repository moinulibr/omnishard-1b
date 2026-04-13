<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Class UserResource
 * Responsible for transforming the User data for API output.
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id'    => $this->id,
            'full_name'  => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'shard'      => $this->shard_key,
            'joined_at'  => $this->created_at ? Carbon::parse($this->created_at)->format('Y-m-d H:i:s') : null,
        ];
    }
}
