<?php

namespace App\Services;

use App\Events\VisitTargetUpdated;
use App\Jobs\ProcessVisitTargetUpdate;
use App\Models\Sales;
use App\Models\SalesVisitTarget;
use App\Models\TargetChangeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VisitTargetService
{
    // ── Read ─────────────────────────────────────────────────────

    public function getAllWithTargets(array $filters = []): Collection
    {
        $query = Sales::query()
            ->select('sales.*')
            ->with([
                'visitTarget' => fn($q) => $q->where('is_active', true)->latest('effective_from'),
            ])
            ->when(!empty($filters['search']), fn($q) =>
                $q->where(fn($inner) =>
                    $inner->where('nama', 'like', "%{$filters['search']}%")
                          ->orWhere('nik', 'like', "%{$filters['search']}%")
                          ->orWhere('area', 'like', "%{$filters['search']}%")
                )
            )
            ->when(isset($filters['is_active']), fn($q) =>
                $q->where('is_active', $filters['is_active'])
            )
            ->orderBy('nama');

        return $query->get()->map(fn(Sales $s) => $this->formatSalesWithTarget($s));
    }

    public function getSalesTarget(int $salesId): array
    {
        $sales = Sales::with([
            'visitTarget' => fn($q) => $q->where('is_active', true)->latest('effective_from'),
        ])->findOrFail($salesId);

        return $this->formatSalesWithTarget($sales);
    }

    public function getChangeHistory(int $salesId, int $limit = 20): Collection
    {
        return TargetChangeLog::where('sales_id', $salesId)
            ->with('changedBy:id,name')
            ->orderByDesc('changed_at')
            ->limit($limit)
            ->get()
            ->map(fn(TargetChangeLog $log) => [
                'id'             => $log->id,
                'old_target'     => $log->old_target,
                'new_target'     => $log->new_target,
                'action'         => $log->action,
                'reason'         => $log->reason,
                'changed_by'     => $log->changedBy?->name ?? 'System',
                'changed_at'     => $log->changed_at->toISOString(),
                'changed_at_wib' => $log->changed_at->setTimezone('Asia/Jakarta')->format('d M Y H:i'),
            ]);
    }

    public function getAnalyticsSummary(): array
    {
        $sales = Sales::where('is_active', true)
            ->with(['visitTarget' => fn($q) => $q->where('is_active', true)->latest('effective_from')])
            ->get();

        $targets = $sales->map(fn($s) => $this->resolveActiveTarget($s));

        return [
            'total_sales'         => $sales->count(),
            'avg_target'          => round($targets->avg(), 1),
            'min_target'          => $targets->min(),
            'max_target'          => $targets->max(),
            'target_distribution' => $targets->countBy()->sortKeys()->all(),
            'using_dedicated'     => $sales->filter(fn($s) => $s->visitTarget->isNotEmpty())->count(),
            'using_fallback'      => $sales->filter(fn($s) => $s->visitTarget->isEmpty())->count(),
        ];
    }

    // ── Write ────────────────────────────────────────────────────

    public function updateSingleTarget(int $salesId, int $newTarget, User $admin, ?string $reason = null): SalesVisitTarget
    {
        return DB::transaction(function () use ($salesId, $newTarget, $admin, $reason) {
            $sales = Sales::findOrFail($salesId);
            $oldTarget = SalesVisitTarget::getActiveTargetForSales($salesId);

            // Deactivate previous active target
            SalesVisitTarget::where('sales_id', $salesId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $target = SalesVisitTarget::create([
                'sales_id'      => $salesId,
                'target_per_day'=> $newTarget,
                'period_type'   => 'daily',
                'is_active'     => true,
                'set_by'        => $admin->id,
                'effective_from'=> now(),
            ]);

            // Mirror to legacy column for backward-compatible services
            $sales->update(['target_kunjungan' => $newTarget]);

            TargetChangeLog::create([
                'sales_id'       => $salesId,
                'visit_target_id'=> $target->id,
                'old_target'     => $oldTarget,
                'new_target'     => $newTarget,
                'changed_by_type'=> 'user',
                'changed_by_id'  => $admin->id,
                'action'         => 'update',
                'reason'         => $reason,
                'changed_at'     => now(),
            ]);

            // Dispatch broadcast + notifications via queue
            ProcessVisitTargetUpdate::dispatch($target->id, $admin->id);

            return $target;
        });
    }

    public function bulkUpdateTargets(array $updates, User $admin, ?string $reason = null): array
    {
        $results = ['updated' => [], 'failed' => []];
        $bulkOpId = (string) \Illuminate\Support\Str::uuid();

        DB::transaction(function () use ($updates, $admin, $reason, $bulkOpId, &$results) {
            foreach ($updates as $item) {
                try {
                    $salesId   = (int) $item['sales_id'];
                    $newTarget = max(1, min(50, (int) $item['target']));
                    $oldTarget = SalesVisitTarget::getActiveTargetForSales($salesId);

                    SalesVisitTarget::where('sales_id', $salesId)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);

                    $target = SalesVisitTarget::create([
                        'sales_id'       => $salesId,
                        'target_per_day' => $newTarget,
                        'period_type'    => 'daily',
                        'is_active'      => true,
                        'set_by'         => $admin->id,
                        'effective_from' => now(),
                    ]);

                    Sales::where('id', $salesId)
                        ->update(['target_kunjungan' => $newTarget]);

                    TargetChangeLog::create([
                        'sales_id'        => $salesId,
                        'visit_target_id' => $target->id,
                        'old_target'      => $oldTarget,
                        'new_target'      => $newTarget,
                        'changed_by_type' => 'user',
                        'changed_by_id'   => $admin->id,
                        'action'          => 'bulk_update',
                        'reason'          => $reason,
                        'metadata'        => ['bulk_op_id' => $bulkOpId],
                        'changed_at'      => now(),
                    ]);

                    ProcessVisitTargetUpdate::dispatch($target->id, $admin->id);
                    $results['updated'][] = $salesId;

                } catch (\Throwable $e) {
                    $results['failed'][] = ['sales_id' => $item['sales_id'], 'error' => $e->getMessage()];
                }
            }
        });

        return $results;
    }

    public function resetToDefault(int $salesId, User $admin, int $default = 5): SalesVisitTarget
    {
        return DB::transaction(function () use ($salesId, $default, $admin) {
            $oldTarget = SalesVisitTarget::getActiveTargetForSales($salesId);

            SalesVisitTarget::where('sales_id', $salesId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $target = SalesVisitTarget::create([
                'sales_id'       => $salesId,
                'target_per_day' => $default,
                'period_type'    => 'daily',
                'is_active'      => true,
                'set_by'         => $admin->id,
                'effective_from' => now(),
                'notes'          => 'Reset ke default sistem',
            ]);

            Sales::where('id', $salesId)->update(['target_kunjungan' => $default]);

            TargetChangeLog::create([
                'sales_id'        => $salesId,
                'visit_target_id' => $target->id,
                'old_target'      => $oldTarget,
                'new_target'      => $default,
                'changed_by_type' => 'user',
                'changed_by_id'   => $admin->id,
                'action'          => 'reset',
                'reason'          => 'Reset ke default sistem',
                'changed_at'      => now(),
            ]);

            ProcessVisitTargetUpdate::dispatch($target->id, $admin->id);

            return $target;
        });
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function formatSalesWithTarget(Sales $sales): array
    {
        $activeTarget = $sales->visitTarget->first();
        $resolvedTarget = $activeTarget?->target_per_day ?? $sales->target_kunjungan ?? 5;

        return [
            'id'                  => $sales->id,
            'nama'                => $sales->nama,
            'nik'                 => $sales->nik,
            'area'                => $sales->area,
            'is_active'           => $sales->is_active,
            'is_spv'              => $sales->is_spv,
            'foto_profil_url'     => $sales->foto_profil_url,
            'target_per_day'      => $resolvedTarget,
            'target_source'       => $activeTarget ? 'dedicated' : 'legacy',
            'target_set_by'       => $activeTarget?->setBy?->name,
            'target_updated_at'   => $activeTarget?->effective_from?->toISOString(),
            'target_updated_wib'  => $activeTarget?->effective_from?->setTimezone('Asia/Jakarta')->format('d M Y H:i'),
        ];
    }

    private function resolveActiveTarget(Sales $sales): int
    {
        return $sales->visitTarget->first()?->target_per_day
            ?? $sales->target_kunjungan
            ?? 5;
    }
}
