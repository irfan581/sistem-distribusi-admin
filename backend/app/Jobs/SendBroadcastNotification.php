<?php

namespace App\Jobs;

use App\Models\Sales;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendBroadcastNotification implements ShouldQueue
{
    use Queueable;

    /** Retry maksimum 2× jika gagal */
    public int $tries = 2;

    /** Timeout 90 detik — cukup untuk 200+ FCM calls */
    public int $timeout = 90;

    public function __construct(
        private readonly array  $salesIds,
        private readonly string $title,
        private readonly string $message,
        private readonly string $batchId,
    ) {}

    public function handle(): void
    {
        // Bulk fetch tokens — 1 query saja
        $tokens = Sales::whereIn('id', $this->salesIds)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token', 'id');

        if ($tokens->isEmpty()) {
            Log::info('[Broadcast Job] No FCM tokens found', ['batch_id' => $this->batchId]);
            return;
        }

        $terkirim = 0;
        $gagal    = 0;

        foreach ($tokens as $token) {
            $ok = FcmService::sendNotification(
                $token,
                $this->title,
                $this->message,
                ['type' => 'admin_notif', 'batch_id' => $this->batchId]
            );
            $ok ? $terkirim++ : $gagal++;
        }

        Log::info('[Broadcast Job] Done', [
            'batch_id'     => $this->batchId,
            'fcm_terkirim' => $terkirim,
            'fcm_gagal'    => $gagal,
        ]);
    }
}
