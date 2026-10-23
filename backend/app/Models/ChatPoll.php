<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatPoll extends Model
{
    protected $fillable = [
        'creator_type', 'creator_id',
        'pertanyaan', 'pilihan',
        'multiple_choice', 'is_closed',
        'tutup_pada',
    ];

    protected $casts = [
        'pilihan'         => 'array',
        'multiple_choice' => 'boolean',
        'is_closed'       => 'boolean',
        'tutup_pada'      => 'datetime',
    ];

    public function votes()
    {
        return $this->hasMany(ChatPollVote::class, 'poll_id');
    }

    public function sudahVote(string $voterType, int $voterId): bool
    {
        return $this->votes()
            ->where('voter_type', $voterType)
            ->where('voter_id', $voterId)
            ->exists();
    }

    public function getHasilAttribute(): array
    {
        $total = $this->votes()->count();
        return collect($this->pilihan)->map(function ($text, $index) use ($total) {
            $count = $this->votes()->where('pilihan_index', $index)->count();
            return [
                'index'      => $index,
                'text'       => $text,
                'count'      => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100) : 0,
            ];
        })->toArray();
    }
}
