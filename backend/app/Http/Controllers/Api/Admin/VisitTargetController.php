<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesVisitTarget;
use App\Services\VisitTargetService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitTargetController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly VisitTargetService $service) {}

    // GET /admin/visit-targets
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesVisitTarget::class);

        $data = $this->service->getAllWithTargets([
            'search'    => $request->search,
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : null,
        ]);

        return $this->success($data, 'Daftar target kunjungan berhasil diambil');
    }

    // GET /admin/visit-targets/analytics
    public function analytics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SalesVisitTarget::class);

        return $this->success(
            $this->service->getAnalyticsSummary(),
            'Analitik target kunjungan berhasil diambil'
        );
    }

    // GET /admin/visit-targets/{salesId}
    public function show(int $salesId): JsonResponse
    {
        $this->authorize('viewAny', SalesVisitTarget::class);

        return $this->success(
            $this->service->getSalesTarget($salesId),
            'Detail target sales berhasil diambil'
        );
    }

    // GET /admin/visit-targets/{salesId}/history
    public function history(Request $request, int $salesId): JsonResponse
    {
        $this->authorize('viewAny', SalesVisitTarget::class);

        $limit = min((int) ($request->limit ?? 20), 100);

        return $this->success(
            $this->service->getChangeHistory($salesId, $limit),
            'Riwayat perubahan target berhasil diambil'
        );
    }

    // PUT /admin/visit-targets/{salesId}
    public function update(Request $request, int $salesId): JsonResponse
    {
        $this->authorize('create', SalesVisitTarget::class);

        $validated = $request->validate([
            'target'  => ['required', 'integer', 'min:1', 'max:50'],
            'reason'  => ['nullable', 'string', 'max:255'],
        ]);

        $target = $this->service->updateSingleTarget(
            salesId  : $salesId,
            newTarget: $validated['target'],
            admin    : $request->user(),
            reason   : $validated['reason'] ?? null,
        );

        return $this->success(
            $this->service->getSalesTarget($salesId),
            "Target kunjungan berhasil diperbarui menjadi {$validated['target']} toko/hari"
        );
    }

    // POST /admin/visit-targets/bulk
    public function bulkUpdate(Request $request): JsonResponse
    {
        $this->authorize('bulkUpdate', SalesVisitTarget::class);

        $validated = $request->validate([
            'updates'         => ['required', 'array', 'min:1', 'max:100'],
            'updates.*.sales_id' => ['required', 'integer', 'exists:sales,id'],
            'updates.*.target'   => ['required', 'integer', 'min:1', 'max:50'],
            'reason'          => ['nullable', 'string', 'max:255'],
        ]);

        $results = $this->service->bulkUpdateTargets(
            updates: $validated['updates'],
            admin  : $request->user(),
            reason : $validated['reason'] ?? null,
        );

        $updatedCount = count($results['updated']);
        $failedCount  = count($results['failed']);

        return $this->success($results,
            "{$updatedCount} target berhasil diperbarui" . ($failedCount ? ", {$failedCount} gagal" : "")
        );
    }

    // POST /admin/visit-targets/{salesId}/reset
    public function reset(Request $request, int $salesId): JsonResponse
    {
        $this->authorize('reset', SalesVisitTarget::class);

        $validated = $request->validate([
            'default' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $this->service->resetToDefault(
            salesId: $salesId,
            admin  : $request->user(),
            default: $validated['default'] ?? 5,
        );

        return $this->success(
            $this->service->getSalesTarget($salesId),
            'Target kunjungan berhasil direset ke default'
        );
    }
}
