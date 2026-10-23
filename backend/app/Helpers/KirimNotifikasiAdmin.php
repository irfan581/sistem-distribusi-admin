<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Helper untuk kirim database notification ke admin
 * tanpa bergantung pada Filament (yang tidak terpasang di project ini).
 *
 * Format data kompatibel dengan tabel `notifications` Laravel.
 */
class KirimNotifikasiAdmin
{
    /**
     * Kirim notifikasi ke satu atau banyak user.
     *
     * @param Collection|\Illuminate\Support\Collection|array $users
     * @param string $title
     * @param string $body
     * @param string $color  'success' | 'warning' | 'danger' | 'info'
     * @param string $type   kata kunci untuk icon Vue: 'izin' | 'presensi' | 'kunjungan' | dll
     */
    public static function kirim(
        $users,
        string $title,
        string $body,
        string $color = 'info',
        string $type  = '',
    ): void {
        if (empty($users)) return;

        $now  = now();
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                'id'              => (string) Str::uuid(),
                'type'            => 'App\Notifications\AdminNotification',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id'   => $user->id,
                // Format data kompatibel dengan NotificationsView.vue:
                // Vue membaca: notif.data.title, notif.data.message, notif.data.type
                'data'            => json_encode([
                    'title'   => $title,
                    'message' => $body,
                    'type'    => $type,   // 'izin' | 'presensi' | 'kunjungan'
                    'color'   => $color,
                ]),
                'read_at'    => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('notifications')->insert($rows);
        }
    }
}
