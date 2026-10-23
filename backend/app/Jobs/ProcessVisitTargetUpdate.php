<?php

namespace App\Jobs;

use App\Events\VisitTargetUpdated;
use App\Models\Sales;
use App\Models\SalesVisitTarget;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVisitTargetUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        private readonly int $visitTargetId,
        private readonly int $adminId,
    ) {}

    public function handle(): void
    {
        $target = SalesVisitTarget::with(['sales', 'setBy'])->find($this->visitTargetId);

        if (!$target) {
            Log::warning("ProcessVisitTargetUpdate: target #{$this->visitTargetId} not found");
            return;
        }

        // 1. Broadcast to Vue dashboard + Flutter via Reverb
        event(new VisitTargetUpdated($target));

        // 2. Send in-app notification to the affected sales
        $this->notifySales($target);

        // 3. Log to Laravel activity log
        $this->logActivity($target);
    }

    private function notifySales(SalesVisitTarget $target): void
    {
        $sales = $target->sales;
        if (!$sales) return;

        $admin  = User::find($this->adminId);
        $setter = $admin?->name ?? 'Admin';

        $sales->notify(new \App\Notifications\VisitTargetChangedNotification(
            target: $target->target_per_day,
            setBy: $setter,
        ));
    }

    private function logActivity(SalesVisitTarget $target): void
    {
        activity('visit_target')
            ->causedByAnonymous()
            ->performedOn($target)
            ->withProperties([
                'sales_id'    => $target->sales_id,
                'sales_nama'  => $target->sales?->nama,
                'target'      => $target->target_per_day,
                'set_by_id'   => $this->adminId,
            ])
            ->log('Target kunjungan diperbarui');
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ProcessVisitTargetUpdate gagal untuk target #{$this->visitTargetId}: {$e->getMessage()}");
    }
}
