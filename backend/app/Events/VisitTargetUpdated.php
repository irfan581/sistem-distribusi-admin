<?php

namespace App\Events;

use App\Models\SalesVisitTarget;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VisitTargetUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(SalesVisitTarget $target)
    {
        $sales   = $target->sales;
        $setBy   = $target->setBy;

        $this->payload = [
            'sales_id'           => $target->sales_id,
            'sales_nama'         => $sales?->nama,
            'sales_area'         => $sales?->area,
            'sales_foto_url'     => $sales?->foto_profil_url,
            'target_per_day'     => $target->target_per_day,
            'period_type'        => $target->period_type,
            'effective_from'     => $target->effective_from?->toISOString(),
            'effective_from_wib' => $target->effective_from?->setTimezone('Asia/Jakarta')->format('d M Y H:i'),
            'set_by_name'        => $setBy?->name ?? 'Admin',
            'updated_at'         => now()->toISOString(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            // Admin dashboard listens on this channel
            new Channel('visit-targets'),
            // Sales Flutter app listens on their personal channel
            new Channel("sales.{$this->payload['sales_id']}.target"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'VisitTargetUpdated';
    }
}
