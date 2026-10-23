<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotifikasiSales;
use App\Http\Resources\Api\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $sales = $request->user();

            $notifications = NotifikasiSales::where('sales_id', $sales->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar notifikasi berhasil diambil',
                'data'    => NotificationResource::collection($notifications),
            ]);
        } catch (\Exception $e) {
            Log::error('Notifikasi index error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil notifikasi.',
                'data'    => [],
            ], 500);
        }
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        try {
            $sales = $request->user();

            $notification = NotifikasiSales::where('id', $id)
                ->where('sales_id', $sales->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notifikasi tidak ditemukan.',
                ], 404);
            }

            $notification->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil ditandai sudah dibaca.',
            ]);
        } catch (\Exception $e) {
            Log::error('Notifikasi markAsRead error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal update status notifikasi.',
            ], 500);
        }
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $sales = $request->user();

            $updatedCount = NotifikasiSales::where('sales_id', $sales->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} notifikasi berhasil ditandai sudah dibaca.",
            ]);
        } catch (\Exception $e) {
            Log::error('Notifikasi markAllAsRead error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menandai semua notifikasi.',
            ], 500);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $sales = $request->user();

            $notification = NotifikasiSales::where('id', $id)
                ->where('sales_id', $sales->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notifikasi tidak ditemukan.',
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            Log::error('Notifikasi destroy error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus notifikasi.',
            ], 500);
        }
    }

    public function deleteAll(Request $request): JsonResponse
    {
        try {
            $sales = $request->user();

            $deletedCount = NotifikasiSales::where('sales_id', $sales->id)->delete();

            return response()->json([
                'success' => true,
                'message' => "Semua notifikasi ({$deletedCount}) berhasil dibersihkan.",
            ]);
        } catch (\Exception $e) {
            Log::error('Notifikasi deleteAll error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membersihkan notifikasi.',
            ], 500);
        }
    }

    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $sales = $request->user();

            $count = NotifikasiSales::where('sales_id', $sales->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success'      => true,
                'unread_count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('Notifikasi unreadCount error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success'      => false,
                'unread_count' => 0,
            ], 500);
        }
    }

    public function updateToken(Request $request): JsonResponse
    {
        try {
            $request->validate(['fcm_token' => 'required|string']);

            $request->user()->update(['fcm_token' => $request->fcm_token]);

            return response()->json([
                'success' => true,
                'message' => 'FCM Token berhasil diperbarui.',
            ]);
        } catch (\Exception $e) {
            Log::error('Notifikasi updateToken error', ['sales_id' => $request->user()?->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui token.',
            ], 500);
        }
    }
}
