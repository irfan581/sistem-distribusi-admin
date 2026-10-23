<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
use App\Models\Sales;

class ChatPollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $voterType = $user ? get_class($user) : null;
        $voterId   = $user?->id;

        return [
            'id'              => $this->id,
            'pertanyaan'      => $this->pertanyaan,
            'pilihan'         => $this->pilihan,
            'multiple_choice' => $this->multiple_choice,
            'is_closed'       => $this->is_closed,
            'tutup_pada'      => $this->tutup_pada?->toDateTimeString(),
            'total_votes'     => $this->votes()->count(),
            'hasil'           => $this->hasil,
            'sudah_vote'      => $voterType && $voterId
                ? $this->sudahVote($voterType, $voterId)
                : false,
            'votes_saya'      => $voterType && $voterId
                ? $this->votes()
                    ->where('voter_type', $voterType)
                    ->where('voter_id', $voterId)
                    ->pluck('pilihan_index')
                    ->toArray()
                : [],
            'creator'         => [
                'name' => $this->creator_type === 'App\\Models\\User'
                    ? User::find($this->creator_id)?->name
                    : Sales::find($this->creator_id)?->nama,
            ],
            'created_at'      => $this->created_at->toDateTimeString(),
        ];
    }
}
