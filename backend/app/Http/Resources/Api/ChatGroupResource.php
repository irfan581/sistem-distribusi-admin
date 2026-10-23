<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
use App\Models\Sales;

class ChatGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'nama'         => $this->nama,
            'deskripsi'    => $this->deskripsi,
            'is_broadcast' => $this->is_broadcast,
            'foto_url'     => $this->foto
                ? asset('storage/' . $this->foto)
                : null,
            'creator'      => [
                'id'   => $this->creator_id,
                'type' => $this->creator_type,
                'name' => $this->creator_type === 'App\\Models\\User'
                    ? User::find($this->creator_id)?->name
                    : Sales::find($this->creator_id)?->nama,
            ],
            'member_count' => $this->members()->count(),
            'members'      => $this->members->map(function ($member) {
                $model = $member->member_type === 'App\\Models\\User'
                    ? User::find($member->member_id)
                    : Sales::find($member->member_id);
                return [
                    'id'       => $member->member_id,
                    'type'     => $member->member_type,
                    'name'     => $member->member_type === 'App\\Models\\User'
                        ? $model?->name
                        : $model?->nama,
                    'is_admin' => $member->is_admin,
                ];
            }),
            'last_message' => $this->messages()
                ->latest()
                ->first()?->message,
            'created_at'   => $this->created_at->toDateTimeString(),
        ];
    }
}
