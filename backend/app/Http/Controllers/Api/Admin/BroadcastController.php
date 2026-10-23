<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastNotification;
use App\Models\NotifikasiSales;
use App\Models\Sales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BroadcastController extends Controller
{
    /**
     * GET /api/admin/broadcast/areas
     * Daftar area unik + total sales aktif + jumlah sales tanpa FCM token.
     */
    public function areas(): JsonResponse
    {
        // Hitung jumlah sales per area sekaligus — 1 query
        $areaCounts = Sales::where('is_active', true)
            ->whereNotNull('area')
            ->select('area', DB::raw('COUNT(*) as count'))
            ->groupBy('area')
            ->orderBy('area')
            ->get()
            ->mapWithKeys(fn($row) => [$row->area => (int) $row->count]);

        $totalSales      = Sales::where('is_active', true)->count();
        $salesTanpaToken = Sales::where('is_active', true)->whereNull('fcm_token')->count();

        return response()->json([
            'success' => true,
            'data'    => $areaCounts->keys()->values(),   // array of area names
            'meta'    => [
                'total_sales'       => $totalSales,
                'sales_tanpa_token' => $salesTanpaToken,
                'area_counts'       => $areaCounts,       // { "Jakarta": 5, "Bandung": 3, ... }
            ],
        ]);
    }

    /**
     * GET /api/admin/broadcast/history
     * Riwayat broadcast yang pernah dikirim (group by batch_id).
     */
    public function history(): JsonResponse
    {
        // MySQL strict mode: hanya kolom aggregate yang boleh di-SELECT tanpa GROUP BY.
        // Gunakan MIN(created_at) dan subquery untuk ambil title/message per batch.
        $history = NotifikasiSales::select(
                'batch_id',
                DB::raw('MIN(title) as title'),
                DB::raw('MIN(message) as message'),
                DB::raw('MIN(created_at) as created_at'),
                DB::raw('COUNT(*) as total_penerima'),
                DB::raw('SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as total_dibaca')
            )
            ->whereNotNull('batch_id')
            ->groupBy('batch_id')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $history]);
    }

    /**
     * POST /api/admin/broadcast/send
     *
     * Payload:
     *   title       : string required
     *   message     : string required
     *   target      : 'all' | 'area'
     *   areas       : array of area strings (wajib jika target=area)
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'title'   => 'required|string|max:150',
            'message' => 'required|string|max:1000',
            'target'  => 'required|in:all,area',
            'areas'   => 'required_if:target,area|array|min:1',
            'areas.*' => 'string|max:100',
        ]);

        // Build query sesuai target
        $query = Sales::where('is_active', true);

        if ($request->target === 'area') {
            $query->whereIn('area', $request->areas);
        }

        // Ambil id + cek fcm_token
        $salesList = $query->select('id', 'fcm_token')->get();

        if ($salesList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada sales aktif yang sesuai target.',
            ], 422);
        }

        $batchId       = (string) Str::uuid();
        $title         = $request->title;
        $message       = $request->message;
        $now           = now();
        $salesIds      = $salesList->pluck('id')->toArray();
        $denganToken   = $salesList->whereNotNull('fcm_token')->count();
        $tanpaToken    = $salesList->whereNull('fcm_token')->count();

        // Bulk insert ke notifikasi_sales — instan, tidak tunggu FCM
        $inserts = array_map(fn($id) => [
            'sales_id'   => $id,
            'title'      => $title,
            'message'    => $message,
            'is_read'    => false,
            'batch_id'   => $batchId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $salesIds);

        NotifikasiSales::insert($inserts);

        // Dispatch ke queue — FCM dikirim di background, request langsung return
        SendBroadcastNotification::dispatch($salesIds, $title, $message, $batchId);

        Log::info('[Broadcast] Queued', [
            'batch_id'       => $batchId,
            'total_penerima' => count($salesIds),
            'dengan_token'   => $denganToken,
            'tanpa_token'    => $tanpaToken,
            'admin_id'       => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Broadcast dikirim ke {$salesList->count()} sales.",
            'data'    => [
                'batch_id'        => $batchId,
                'total_penerima'  => count($salesIds),
                'fcm_terkirim'    => $denganToken,   // estimasi — sales yang punya token
                'fcm_gagal'       => $tanpaToken,    // sales tanpa token
            ],
        ]);
    }
}
