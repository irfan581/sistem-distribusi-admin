<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class VisitTargetChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int    $target,
        private readonly string $setBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'visit_target_changed',
            'title'   => 'Target Kunjungan Diperbarui',
            'body'    => "{$this->setBy} mengatur target kunjungan Anda menjadi {$this->target} toko/hari.",
            'target'  => $this->target,
            'set_by'  => $this->setBy,
            'icon'    => 'target',
        ];
    }
}
