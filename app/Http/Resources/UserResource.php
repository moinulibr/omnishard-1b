<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id'    => $this->id ?? null,
            'full_name'  => $this->name ?? null,
            'email'      => $this->email ?? null,
            'phone'      => $this->phone ?? null,
            'shard'      => $this->shard_key ?? null,
            'phase'      => $this->phase_id ?? null,
            'joined_at'  => isset($this->created_at)
                ? Carbon::parse($this->created_at)->toDateTimeString()
                : null,
        ];
    }
}
