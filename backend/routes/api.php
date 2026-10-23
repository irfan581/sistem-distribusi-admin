<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\KunjunganController;
use App\Http\Controllers\Api\IzinController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TokoController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatPollController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminChatController;
use App\Http\Controllers\Api\Admin\VisitTargetController;
use App\Http\Controllers\Api\Admin\SalesController as AdminSalesController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\TokoController as AdminTokoController;
use App\Http\Controllers\Api\Admin\BroadcastController;
use App\Http\Controllers\Api\Admin\TanggapanController;

/*
|--------------------------------------------------------------------------
| API TMK
|--------------------------------------------------------------------------
| Aplikasi sales & sopir (Flutter) dan dashboard admin (Vue),
| semuanya memakai token Sanctum.
*/

// ═══════════════════════════════════════════════════════════════
// PUBLIC — Tanpa Login
// ═══════════════════════════════════════════════════════════════

// Login mobile (Sales / Flutter)
Route::post('/login', [AttendanceController::class, 'login'])
    ->middleware('throttle:20,1')
    ->name('api.login');

// Login mobile (Driver / Flutter)
Route::post('/driver/login', [\App\Http\Controllers\Api\Driver\AuthController::class, 'login'])
    ->middleware('throttle:20,1')
    ->name('driver.login');

// Login admin (User / Vue Sakai)
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('admin.login');

// ═══════════════════════════════════════════════════════════════
// MOBILE APP DRIVER — Wajib Login (Driver Sanctum Token)
// ═══════════════════════════════════════════════════════════════

Route::prefix('driver')
    ->middleware(['auth:sanctum', \App\Http\Middleware\CheckDriverActive::class, 'throttle:api'])
    ->group(function () {

        Route::post('/logout', [\App\Http\Controllers\Api\Driver\AuthController::class, 'logout'])
            ->name('driver.logout');

        Route::prefix('profil')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\Driver\ProfilController::class, 'show'])
                ->name('driver.profil');
            Route::post('/ganti-password', [\App\Http\Controllers\Api\Driver\ProfilController::class, 'gantiPassword'])
                ->middleware('throttle:5,1')
                ->name('driver.profil.ganti-password');
            Route::post('/fcm-token', [\App\Http\Controllers\Api\Driver\ProfilController::class, 'simpanFcmToken'])
                ->name('driver.profil.fcm-token');
        });

        Route::prefix('tugas')->group(function () {
            Route::get('/hari-ini', [\App\Http\Controllers\Api\Driver\TugasController::class, 'hariIni'])
                ->name('driver.tugas.hari-ini');
            Route::get('/ringkasan', [\App\Http\Controllers\Api\Driver\TugasController::class, 'ringkasan'])
                ->name('driver.tugas.ringkasan');
            // Sebelum /{id} supaya urutannya jelas.
            Route::get('/riwayat', [\App\Http\Controllers\Api\Driver\TugasController::class, 'riwayat'])
                ->name('driver.tugas.riwayat');
            Route::get('/{id}', [\App\Http\Controllers\Api\Driver\TugasController::class, 'show'])
                ->whereNumber('id')->name('driver.tugas.show');
            // Status rit disimpulkan dari laporan (DaftarMuat::selaraskanStatus).
            Route::post('/{id}/item/{itemId}/laporan', [\App\Http\Controllers\Api\Driver\TugasController::class, 'simpanLaporan'])
                ->whereNumber('id')->whereNumber('itemId')->name('driver.tugas.item.laporan');

            // Foto bongkar diunggah terpisah agar bisa antre saat offline.
            // Minimal satu foto sebelum laporan diterima (FOTO_WAJIB).
            Route::post('/{id}/item/{itemId}/foto', [\App\Http\Controllers\Api\Driver\FotoBongkarController::class, 'simpan'])
                ->whereNumber('id')->whereNumber('itemId')->name('driver.tugas.item.foto.simpan');
            Route::get('/{id}/item/{itemId}/foto', [\App\Http\Controllers\Api\Driver\FotoBongkarController::class, 'index'])
                ->whereNumber('id')->whereNumber('itemId')->name('driver.tugas.item.foto.index');
            Route::delete('/{id}/item/{itemId}/foto/{fotoId}', [\App\Http\Controllers\Api\Driver\FotoBongkarController::class, 'hapus'])
                ->whereNumber('id')->whereNumber('itemId')->whereNumber('fotoId')->name('driver.tugas.item.foto.hapus');
        });

        // ── CHAT ──────────────────────────────────────────────
        // Logika sama dengan chat sales; sopir hanya punya chat grup.
        Route::prefix('chat')->middleware('throttle:60,1')->group(function () {
            Route::get('/grup', [\App\Http\Controllers\Api\Driver\ChatController::class, 'grup'])
                ->name('driver.chat.grup');
            Route::get('/grup/{groupId}/pesan', [\App\Http\Controllers\Api\Driver\ChatController::class, 'pesan'])
                ->whereNumber('groupId')->name('driver.chat.grup.pesan');
            Route::post('/kirim', [\App\Http\Controllers\Api\Driver\ChatController::class, 'kirim'])
                ->name('driver.chat.kirim');
            Route::get('/unread', [\App\Http\Controllers\Api\Driver\ChatController::class, 'unread'])
                ->name('driver.chat.unread');
            Route::post('/fcm-token', [\App\Http\Controllers\Api\Driver\ProfilController::class, 'simpanFcmToken'])
                ->name('driver.chat.fcm-token');
        });
    });

// ═══════════════════════════════════════════════════════════════
// BERKAS — Foto bukti, kunjungan, presensi, izin
// ═══════════════════════════════════════════════════════════════
// Semua foto lewat sini agar hak aksesnya diperiksa (admin, sopir, sales).
// Sengaja tanpa auth:sanctum: <img src> tidak mengirim token, jadi hak
// diperiksa di controller lewat tanda tangan berbatas waktu atau token.
Route::middleware(['throttle:berkas'])
    ->prefix('berkas')
    ->name('berkas.')
    ->group(function () {
        Route::get('/bongkar/{id}/{ukuran?}', [\App\Http\Controllers\Api\BerkasController::class, 'bongkar'])
            ->whereNumber('id')->whereIn('ukuran', ['penuh', 'kecil'])->name('bongkar');

        Route::get('/kunjungan/{id}', [\App\Http\Controllers\Api\BerkasController::class, 'kunjungan'])
            ->whereNumber('id')->name('kunjungan');

        Route::get('/presensi/{id}/{jenis?}', [\App\Http\Controllers\Api\BerkasController::class, 'presensi'])
            ->whereNumber('id')->whereIn('jenis', ['masuk', 'pulang'])->name('presensi');

        Route::get('/izin/{id}', [\App\Http\Controllers\Api\BerkasController::class, 'izin'])
            ->whereNumber('id')->name('izin');

        Route::get('/avatar-sales/{id}', [\App\Http\Controllers\Api\BerkasController::class, 'avatarSales'])
            ->whereNumber('id')->name('avatar-sales');

        Route::get('/avatar-pengguna/{id}', [\App\Http\Controllers\Api\BerkasController::class, 'avatarPengguna'])
            ->whereNumber('id')->name('avatar-pengguna');

        Route::get('/chat/{id}/{ukuran?}', [\App\Http\Controllers\Api\BerkasController::class, 'chat'])
            ->whereNumber('id')->whereIn('ukuran', ['penuh', 'kecil'])->name('chat');
    });

// ═══════════════════════════════════════════════════════════════
// MOBILE APP — Wajib Login (Sales Sanctum Token)
// ═══════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckSalesActive::class, 'throttle:api'])
    ->group(function () {

        // ── AUTH ──────────────────────────────────────────────
        Route::post('/logout', [AttendanceController::class, 'logout'])
            ->name('api.logout');

        // ── PROFILE ───────────────────────────────────────────
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'getProfile'])->name('profile.get');
            Route::post('/update', [ProfileController::class, 'updateProfile'])->name('profile.update');
            Route::post('/change-password', [ProfileController::class, 'changePassword'])->name('profile.change-password');
        });

        // ── PRESENSI ──────────────────────────────────────────
        Route::prefix('presensi')->group(function () {
            Route::get('/hari-ini', [AttendanceController::class, 'presensiHariIni'])
                ->middleware('throttle:40,1')
                ->name('presensi.hari-ini');
            Route::post('/masuk', [AttendanceController::class, 'absenMasuk'])
                ->middleware('throttle:10,1')
                ->name('presensi.masuk');
            Route::post('/pulang', [AttendanceController::class, 'absenPulang'])
                ->middleware('throttle:10,1')
                ->name('presensi.pulang');
            Route::get('/riwayat', [AttendanceController::class, 'riwayat'])
                ->name('presensi.riwayat');
        });

        // Alias endpoint lama (aplikasi versi lama)
        Route::get('/presensi-hari-ini', [AttendanceController::class, 'presensiHariIni']);
        Route::post('/absen-masuk', [AttendanceController::class, 'absenMasuk']);
        Route::post('/absen-pulang', [AttendanceController::class, 'absenPulang']);
        Route::get('/riwayat-presensi', [AttendanceController::class, 'riwayat']);

        // ── KUNJUNGAN ─────────────────────────────────────────
        Route::prefix('kunjungan')->group(function () {
            Route::post('/', [KunjunganController::class, 'store'])
                ->middleware('throttle:50,1')
                ->name('kunjungan.store');
            Route::get('/riwayat', [KunjunganController::class, 'history'])
                ->name('kunjungan.riwayat');
            Route::get('/cek-cooldown', [KunjunganController::class, 'cekCooldown'])
                ->name('kunjungan.cek-cooldown');
        });

        // Alias endpoint lama (aplikasi versi lama)
        Route::post('/kunjungan-toko', [KunjunganController::class, 'store']);
        Route::post('/kunjungan-toko/{id}/checkout', [KunjunganController::class, 'checkout']);
        Route::get('/riwayat-kunjungan', [KunjunganController::class, 'history']);

        // ── IZIN ──────────────────────────────────────────────
        Route::prefix('izin')->group(function () {
            Route::post('/ajukan', [IzinController::class, 'ajukanIzin'])
                ->middleware('throttle:10,1')
                ->name('izin.ajukan');
            Route::get('/riwayat', [IzinController::class, 'riwayat'])
                ->name('izin.riwayat');
            Route::get('/cek-hari-ini', [IzinController::class, 'cekIzinHariIni'])
                ->name('izin.cek');
            Route::delete('/{id}/batal', [IzinController::class, 'batalIzin'])
                ->name('izin.batal');
        });

        // ── TOKO ──────────────────────────────────────────────
        Route::prefix('tokos')->group(function () {
            Route::get('/', [TokoController::class, 'index'])->name('tokos.index');
            Route::get('/search', [TokoController::class, 'search'])->name('tokos.search');
        });

        // ── TRACKING GPS ─────────────────────────────────────
        Route::post('/tracking/lokasi', [\App\Http\Controllers\Api\TrackingController::class, 'kirimLokasi'])
            ->name('tracking.kirim-lokasi');

        // ── NOTIFIKASI ────────────────────────────────────────
        Route::prefix('notifications')->middleware('throttle:60,1')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
            Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])->name('notifications.unread-count');
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
            Route::post('/update-token', [NotificationController::class, 'updateToken'])->name('notifications.update-token');
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
            Route::delete('/delete-all', [NotificationController::class, 'deleteAll'])->name('notifications.delete-all');
            Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
        });

        // ── CHAT ──────────────────────────────────────────────
        Route::prefix('chat')->middleware('throttle:60,1')->group(function () {
            Route::get('/contacts', [ChatController::class, 'contacts'])->name('chat.contacts');
            Route::get('/groups', [ChatController::class, 'myGroups'])->name('chat.groups');
            Route::post('/groups', [ChatController::class, 'createGroup'])->name('chat.groups.create');
            Route::get('/groups/{groupId}/messages', [ChatController::class, 'groupMessages'])->name('chat.group-messages');
            Route::get('/dm', [ChatController::class, 'dmHistory'])->name('chat.dm');
            Route::post('/send', [ChatController::class, 'send'])->name('chat.send');
            Route::get('/unread', [ChatController::class, 'unreadCount'])->name('chat.unread');
            Route::put('/messages/{messageId}', [ChatController::class, 'editPesan'])->name('chat.edit');
            Route::delete('/messages/{messageId}', [ChatController::class, 'hapusPesan'])->name('chat.delete');
            Route::post('/typing', [ChatController::class, 'typing'])->name('chat.typing');
            Route::post('/heartbeat', [ChatController::class, 'heartbeat'])->name('chat.heartbeat');
        });

        // ── POLLS ─────────────────────────────────────────────
        Route::prefix('polls')->group(function () {
            Route::post('/', [ChatPollController::class, 'store'])->name('polls.store');
            Route::get('/{pollId}', [ChatPollController::class, 'show'])->name('polls.show');
            Route::post('/{pollId}/vote', [ChatPollController::class, 'vote'])->name('polls.vote');
            Route::post('/{pollId}/close', [ChatPollController::class, 'close'])->name('polls.close');
        });

    });

// ═══════════════════════════════════════════════════════════════
// ADMIN — Dashboard (Sanctum token)
// ═══════════════════════════════════════════════════════════════

Route::prefix('admin')
    ->middleware(['auth:sanctum', \App\Http\Middleware\EnsureIsAdmin::class, 'throttle:api'])
    ->group(function () {

        // ── AUTH ──────────────────────────────────────────────
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
        Route::get('/me', [AdminAuthController::class, 'me'])->name('admin.me');
        Route::put('/profile',  [AdminAuthController::class, 'updateProfile'])->name('admin.profile.update');
        Route::post('/profile', [AdminAuthController::class, 'updateProfile'])->name('admin.profile.update.post'); // multipart, dengan avatar
        Route::put('/profile/password', [AdminAuthController::class, 'changePassword'])->name('admin.profile.password');

        // ── DASHBOARD STATISTIK ───────────────────────────────
        Route::get('/dashboard/statistics', [DashboardController::class, 'statistics'])->name('admin.dashboard.stats');
        Route::get('/dashboard/attendance-chart', [DashboardController::class, 'attendanceChart'])->name('admin.dashboard.attendance-chart');
        Route::get('/dashboard/visit-chart', [DashboardController::class, 'visitChart'])->name('admin.dashboard.visit-chart');
        Route::get('/dashboard/recent-activity', [DashboardController::class, 'recentActivity'])->name('admin.dashboard.recent-activity');
        Route::get('/dashboard/monthly-summary', [DashboardController::class, 'monthlySummary'])->name('admin.dashboard.monthly-summary');
        Route::get('/dashboard/pengiriman-stats', [DashboardController::class, 'pengirimanStats'])->name('admin.dashboard.pengiriman-stats');
        Route::get('/dashboard/muatan-trend',     [DashboardController::class, 'muatanTrend'])->name('admin.dashboard.muatan-trend');

        // ── SALES MANAGEMENT ──────────────────────────────────
        Route::middleware('permission:view_sales')->group(function () {
            Route::get('/sales', [DashboardController::class, 'salesList'])->name('admin.sales.list');
            Route::get('/sales/{id}', [DashboardController::class, 'salesDetail'])->name('admin.sales.detail');
        });
        Route::post('/sales', [AdminSalesController::class, 'store'])->middleware('permission:create_sales')->name('admin.sales.store');
        Route::put('/sales/{id}',  [AdminSalesController::class, 'update'])->middleware('permission:edit_sales')->name('admin.sales.update');
        Route::post('/sales/{id}', [AdminSalesController::class, 'update'])->middleware('permission:edit_sales')->name('admin.sales.update.post');
        Route::put('/sales/{id}/reset-password', [AdminSalesController::class, 'resetPassword'])->middleware('permission:edit_sales')->name('admin.sales.reset-password');
        Route::put('/sales/{id}/reset-device', [AdminSalesController::class, 'resetDevice'])->middleware('permission:edit_sales')->name('admin.sales.reset-device');
        Route::get('/sales/{id}/report-pdf', [AdminSalesController::class, 'downloadReport'])->middleware('permission:export_pdf')->name('admin.sales.report-pdf');
        Route::delete('/sales/{id}', [AdminSalesController::class, 'destroy'])->middleware('permission:delete_sales')->name('admin.sales.destroy');

        // ── PRESENSI MANAGEMENT ───────────────────────────────
        Route::middleware('permission:view_presensi')->group(function () {
            Route::get('/presensi',      [DashboardController::class, 'presensiList'])->name('admin.presensi.list');
            Route::get('/presensi/{id}', [DashboardController::class, 'presensiDetail'])->name('admin.presensi.detail');
        });
        Route::put('/presensi/{id}',    [DashboardController::class, 'presensiUpdate'])->middleware('permission:edit_presensi')->name('admin.presensi.update');
        Route::delete('/presensi/{id}', [DashboardController::class, 'presensiDelete'])->middleware('permission:delete_presensi')->name('admin.presensi.delete');

        // ── KUNJUNGAN MANAGEMENT ──────────────────────────────
        Route::middleware('permission:view_kunjungan')->group(function () {
            Route::get('/kunjungan',      [DashboardController::class, 'kunjunganList'])->name('admin.kunjungan.list');
            Route::get('/kunjungan/sales', [DashboardController::class, 'kunjunganSalesOptions'])->name('admin.kunjungan.sales');
            Route::get('/kunjungan/{id}', [DashboardController::class, 'kunjunganDetail'])->name('admin.kunjungan.detail');
        });
        Route::post('/kunjungan',                      [DashboardController::class, 'kunjunganStore'])->middleware('permission:edit_kunjungan')->name('admin.kunjungan.store');
        Route::match(['POST','PUT'], '/kunjungan/{id}',[DashboardController::class, 'kunjunganUpdate'])->middleware('permission:edit_kunjungan')->name('admin.kunjungan.update');
        Route::delete('/kunjungan/{id}',               [DashboardController::class, 'kunjunganDelete'])->middleware('permission:delete_kunjungan')->name('admin.kunjungan.delete');
        Route::post('/kunjungan/{id}/tanggapan',       [TanggapanController::class, 'kunjungan'])->whereNumber('id')->middleware('permission:edit_kunjungan')->name('admin.kunjungan.tanggapan');

        // Jumlah foto lapangan yang belum ditanggapi, untuk penanda di menu samping.
        Route::get('/tanggapan/ringkasan', [TanggapanController::class, 'ringkasan'])->name('admin.tanggapan.ringkasan');

        // ── IZIN MANAGEMENT ───────────────────────────────────
        Route::middleware('permission:view_izin')->group(function () {
            Route::get('/izin',       [DashboardController::class, 'izinList'])->name('admin.izin.list');
            Route::get('/izin/recap', [DashboardController::class, 'izinRecap'])->name('admin.izin.recap');
        });
        Route::post('/izin/{id}/approve', [DashboardController::class, 'approveIzin'])->middleware('permission:approve_izin')->name('admin.izin.approve');
        Route::delete('/izin/{id}',       [DashboardController::class, 'deleteIzin'])->middleware('permission:delete_izin')->name('admin.izin.delete');

        // ── MASTER TOKO ───────────────────────────────────────
        Route::middleware('permission:view_toko')->group(function () {
            Route::get('/toko/wilayah', [AdminTokoController::class, 'wilayahList']);
            Route::get('/toko/sales',   [AdminTokoController::class, 'salesOptions']);
            Route::get('/toko',         [AdminTokoController::class, 'index']);
        });
        Route::post('/toko',        [AdminTokoController::class, 'store'])->middleware('permission:create_toko');
        Route::put('/toko/{id}',    [AdminTokoController::class, 'update'])->middleware('permission:edit_toko');
        Route::delete('/toko/{id}', [AdminTokoController::class, 'destroy'])->middleware('permission:delete_toko');
        Route::post('/toko/import', [AdminTokoController::class, 'import'])->middleware('permission:create_toko');

        // ── ADMIN USER MANAGEMENT — hanya owner & developer ───
        Route::middleware(\App\Http\Middleware\EnsureFullAccess::class)->group(function () {
            Route::get('/admin-users/roles',         [AdminUserController::class, 'roles']);
            Route::get('/admin-users',               [AdminUserController::class, 'index']);
            Route::post('/admin-users',              [AdminUserController::class, 'store']);
            Route::get('/admin-users/{id}',          [AdminUserController::class, 'show']);
            Route::match(['POST','PUT','PATCH'], '/admin-users/{id}', [AdminUserController::class, 'update']);
            Route::put('/admin-users/{id}/password', [AdminUserController::class, 'resetPassword']);
            Route::delete('/admin-users/{id}',       [AdminUserController::class, 'destroy']);
        });

        // ── LAPORAN / REKAP ───────────────────────────────────
        Route::get('/report/pdf',        [AdminReportController::class, 'downloadPdf'])->middleware('permission:export_pdf')->name('admin.report.pdf');
        Route::get('/report/excel',      [AdminReportController::class, 'downloadExcel'])->middleware('permission:export_excel')->name('admin.report.excel');
        Route::get('/report/izin-pdf',   [AdminReportController::class, 'downloadIzinPdf'])->middleware('permission:export_pdf')->name('admin.report.izin.pdf');
        Route::get('/report/izin-excel', [AdminReportController::class, 'downloadIzinExcel'])->middleware('permission:export_excel')->name('admin.report.izin.excel');

        // ── MAPS ──────────────────────────────────────────────
        Route::get('/maps/kunjungan', [DashboardController::class, 'mapsKunjungan'])->middleware('permission:view_maps_sales')->name('admin.maps.kunjungan');

        // ── TRACKING LIVE ─────────────────────────────────────
        // Posisi sales hanya untuk yang boleh membuka Peta Kunjungan.
        Route::prefix('tracking')->middleware('permission:view_maps_sales')->group(function () {
            Route::get('/live',              [\App\Http\Controllers\Api\TrackingController::class, 'live'])->name('admin.tracking.live');
            Route::get('/sales-aktif',       [\App\Http\Controllers\Api\TrackingController::class, 'salesAktif'])->name('admin.tracking.sales-aktif');
            Route::get('/rute/{userId}',     [\App\Http\Controllers\Api\TrackingController::class, 'rute'])->name('admin.tracking.rute');
        });

        // ── NOTIFIKASI ADMIN ──────────────────────────────────
        // Isinya izin, presensi, dan kunjungan sales — admin pengiriman tidak memakainya.
        Route::middleware('permission:view_sales,view_presensi,view_izin,view_kunjungan')->group(function () {
            Route::get('/notifications', [DashboardController::class, 'notificationList'])->name('admin.notifications.list');
            Route::get('/notifications/unread-count', [DashboardController::class, 'notificationUnreadCount'])->name('admin.notifications.unread-count');
            Route::post('/notifications/{id}/read', [DashboardController::class, 'notificationMarkRead'])->name('admin.notifications.read');
            Route::post('/notifications/read-all', [DashboardController::class, 'notificationMarkAllRead'])->name('admin.notifications.read-all');
        });

        // ── BROADCAST / KIRIM PESAN KE SALES ─────────────────
        Route::prefix('broadcast')->group(function () {
            Route::get('/areas',   [BroadcastController::class, 'areas'])->middleware('permission:send_broadcast');
            Route::get('/history', [BroadcastController::class, 'history'])->middleware('permission:send_broadcast');
            Route::post('/send',   [BroadcastController::class, 'send'])->middleware('permission:send_broadcast');
        });

        // ── TARGET KUNJUNGAN ──────────────────────────────────
        Route::prefix('visit-targets')->group(function () {
            Route::get('/',                  [VisitTargetController::class, 'index']);
            Route::get('/analytics',         [VisitTargetController::class, 'analytics']);
            Route::post('/bulk',             [VisitTargetController::class, 'bulkUpdate']);
            Route::get('/{salesId}',         [VisitTargetController::class, 'show']);
            Route::put('/{salesId}',         [VisitTargetController::class, 'update']);
            Route::get('/{salesId}/history', [VisitTargetController::class, 'history']);
            Route::post('/{salesId}/reset',  [VisitTargetController::class, 'reset']);
        });

        // ── CHAT (ADMIN) ──────────────────────────────────────
        Route::prefix('chat')->middleware('permission:view_sales,view_presensi,view_izin,view_kunjungan')->group(function () {
            Route::get('/contacts', [AdminChatController::class, 'contacts'])->name('admin.chat.contacts');
            Route::get('/dm/{userId}', [AdminChatController::class, 'dmMessages'])->name('admin.chat.dm');
            Route::post('/send', [AdminChatController::class, 'send'])->name('admin.chat.send');
            Route::get('/groups', [AdminChatController::class, 'groups'])->name('admin.chat.groups');
            Route::post('/groups', [AdminChatController::class, 'createGroup'])->name('admin.chat.groups.create');
            Route::get('/groups/{groupId}/messages', [AdminChatController::class, 'groupMessages'])->name('admin.chat.group-messages');
            Route::get('/groups/{groupId}/info',     [AdminChatController::class, 'groupInfo'])->name('admin.chat.group-info');
            Route::post('/groups/{groupId}/members', [AdminChatController::class, 'addMember'])->name('admin.chat.group-add-member');
            Route::delete('/groups/{groupId}/members/{memberId}', [AdminChatController::class, 'removeMember'])->name('admin.chat.group-remove-member');
            Route::put('/groups/{groupId}/rename',   [AdminChatController::class, 'renameGroup'])->name('admin.chat.group-rename');
            Route::get('/search',  [AdminChatController::class, 'searchMessages'])->name('admin.chat.search');
            Route::get('/starred',       [AdminChatController::class, 'starredMessages'])->name('admin.chat.starred');
            Route::post('/ping',         [AdminChatController::class, 'ping'])->name('admin.chat.ping');
            Route::get('/online-status', [AdminChatController::class, 'onlineStatus'])->name('admin.chat.online-status');
            Route::post('/messages/{id}/star',   [AdminChatController::class, 'starMessage'])->name('admin.chat.star');
            Route::delete('/messages/{id}/star', [AdminChatController::class, 'unstarMessage'])->name('admin.chat.unstar');
            Route::post('/typing', [AdminChatController::class, 'typing'])->name('admin.chat.typing');
            Route::get('/unread', [AdminChatController::class, 'unreadCount'])->name('admin.chat.unread');
            Route::post('/messages/{id}/read', [AdminChatController::class, 'markRead'])->name('admin.chat.read');
            Route::get('/pinned',                 [AdminChatController::class, 'getPinnedMessage'])->name('admin.chat.pinned');
            Route::post('/messages/{id}/pin',     [AdminChatController::class, 'pinMessage'])->name('admin.chat.pin');
            Route::delete('/messages/{id}/pin',   [AdminChatController::class, 'unpinMessage'])->name('admin.chat.unpin');
            Route::put('/messages/{id}',           [AdminChatController::class, 'editMessage'])->name('admin.chat.edit');
            Route::delete('/messages/{id}',        [AdminChatController::class, 'deleteMessage'])->name('admin.chat.delete');
            Route::post('/messages/{id}/react',   [AdminChatController::class, 'addReaction'])->name('admin.chat.react');
            Route::post('/messages/{id}/forward', [AdminChatController::class, 'forwardMessage'])->name('admin.chat.forward');
            Route::post('/fcm-token',   [AdminChatController::class, 'saveFcmToken'])->name('admin.chat.fcm-token');
            Route::get('/link-preview', [AdminChatController::class, 'linkPreview'])->name('admin.chat.link-preview');
        });

        // ── PENGIRIMAN & ARMADA ───────────────────────────────
        Route::prefix('pengiriman')->group(function () {

            // Daftar Muat — view
            Route::middleware('permission:view_pengiriman')->group(function () {
                Route::get('/daftar-muat/statistik',        [\App\Http\Controllers\Api\DaftarMuatController::class, 'statistik'])->name('pengiriman.statistik');
                Route::get('/daftar-muat/next-rit',         [\App\Http\Controllers\Api\DaftarMuatController::class, 'nextRit'])->name('pengiriman.next-rit');
                Route::get('/daftar-muat',                  [\App\Http\Controllers\Api\DaftarMuatController::class, 'index'])->name('pengiriman.daftar-muat.index');
                Route::get('/daftar-muat/{id}',             [\App\Http\Controllers\Api\DaftarMuatController::class, 'show'])->name('pengiriman.daftar-muat.show');
                Route::get('/daftar-muat/{id}/logs',        [\App\Http\Controllers\Api\DaftarMuatController::class, 'logs'])->name('pengiriman.daftar-muat.logs');
                Route::get('/daftar-muat/{id}/laporan-driver', [\App\Http\Controllers\Api\DaftarMuatController::class, 'laporanDriver'])->name('pengiriman.daftar-muat.laporan-driver');
                Route::get('/daftar-muat/{id}/surat-jalan', [\App\Http\Controllers\Api\DaftarMuatController::class, 'suratJalanPdf'])->name('pengiriman.daftar-muat.surat-jalan');
                Route::get('/daftar-muat/rekap-harian/pdf', [\App\Http\Controllers\Api\DaftarMuatController::class, 'rekapHarianPdf'])->name('pengiriman.daftar-muat.rekap-pdf');
                Route::get('/daftar-muat/rekap-periode/pdf', [\App\Http\Controllers\Api\DaftarMuatController::class, 'rekapPeriodePdf'])->name('pengiriman.daftar-muat.rekap-periode-pdf');

                // Cari toko tujuan muat (izin view_pengiriman, bukan view_toko).
                Route::get('/toko-tujuan', [AdminTokoController::class, 'pencarianMuat'])->name('pengiriman.toko-tujuan');
            });
            Route::post('/daftar-muat',    [\App\Http\Controllers\Api\DaftarMuatController::class, 'store'])->middleware('permission:create_pengiriman')->name('pengiriman.daftar-muat.store');
            Route::put('/daftar-muat/{id}',[\App\Http\Controllers\Api\DaftarMuatController::class, 'update'])->middleware('permission:edit_pengiriman')->name('pengiriman.daftar-muat.update');
            Route::delete('/daftar-muat/{id}',[\App\Http\Controllers\Api\DaftarMuatController::class, 'destroy'])->middleware('permission:delete_pengiriman')->name('pengiriman.daftar-muat.destroy');
            Route::post('/daftar-muat/{id}/item/{itemId}/tanggapan', [TanggapanController::class, 'laporan'])
                ->whereNumber('id')->whereNumber('itemId')->middleware('permission:edit_pengiriman')->name('pengiriman.daftar-muat.tanggapan');

            // Master Driver — view & manage
            Route::get('/drivers',        [\App\Http\Controllers\Api\DriverController::class, 'index'])->middleware('permission:view_armada')->name('pengiriman.drivers.index');
            Route::post('/drivers',       [\App\Http\Controllers\Api\DriverController::class, 'store'])->middleware('permission:manage_armada')->name('pengiriman.drivers.store');
            Route::put('/drivers/{id}',   [\App\Http\Controllers\Api\DriverController::class, 'update'])->middleware('permission:manage_armada')->name('pengiriman.drivers.update');
            Route::delete('/drivers/{id}',[\App\Http\Controllers\Api\DriverController::class, 'destroy'])->middleware('permission:manage_armada')->name('pengiriman.drivers.destroy');

            // Akun aplikasi driver (password sementara tampil sekali)
            Route::middleware('permission:manage_armada')->group(function () {
                Route::post('/drivers/{id}/akun/aktifkan',       [\App\Http\Controllers\Api\DriverController::class, 'aktifkanAkun'])->name('pengiriman.drivers.akun.aktifkan');
                Route::post('/drivers/{id}/akun/reset-password', [\App\Http\Controllers\Api\DriverController::class, 'resetPassword'])->name('pengiriman.drivers.akun.reset-password');
                Route::post('/drivers/{id}/akun/nonaktifkan',    [\App\Http\Controllers\Api\DriverController::class, 'nonaktifkanAkun'])->name('pengiriman.drivers.akun.nonaktifkan');
            });

            // Izin Driver — butuh view_armada
            Route::middleware('permission:view_armada')->group(function () {
                Route::get('/driver-izin',         [\App\Http\Controllers\Api\DriverIzinController::class, 'index']);
                Route::get('/driver-izin/drivers', [\App\Http\Controllers\Api\DriverIzinController::class, 'drivers']);
            });
            Route::post('/driver-izin',       [\App\Http\Controllers\Api\DriverIzinController::class, 'store'])->middleware('permission:manage_armada');
            Route::delete('/driver-izin/{id}',[\App\Http\Controllers\Api\DriverIzinController::class, 'destroy'])->middleware('permission:manage_armada');

            // Master Kendaraan — view & manage
            Route::get('/kendaraans',         [\App\Http\Controllers\Api\KendaraanController::class, 'index'])->middleware('permission:view_armada')->name('pengiriman.kendaraans.index');
            Route::post('/kendaraans',        [\App\Http\Controllers\Api\KendaraanController::class, 'store'])->middleware('permission:manage_armada')->name('pengiriman.kendaraans.store');
            Route::put('/kendaraans/{id}',    [\App\Http\Controllers\Api\KendaraanController::class, 'update'])->middleware('permission:manage_armada')->name('pengiriman.kendaraans.update');
            Route::delete('/kendaraans/{id}', [\App\Http\Controllers\Api\KendaraanController::class, 'destroy'])->middleware('permission:manage_armada')->name('pengiriman.kendaraans.destroy');

            // Master Checker
            Route::get('/checkers',        [\App\Http\Controllers\Api\CheckerController::class, 'index'])->middleware('permission:view_pengiriman');
            Route::post('/checkers',       [\App\Http\Controllers\Api\CheckerController::class, 'store'])->middleware('permission:manage_armada');
            Route::put('/checkers/{id}',   [\App\Http\Controllers\Api\CheckerController::class, 'update'])->middleware('permission:manage_armada');
            Route::delete('/checkers/{id}',[\App\Http\Controllers\Api\CheckerController::class, 'destroy'])->middleware('permission:manage_armada');

            // Pengawasan Muat (Ceker Gudang)
            Route::get('/pengawasan-muat',        [\App\Http\Controllers\Api\PengawasanMuatController::class, 'index'])->middleware('permission:view_pengiriman');
            Route::post('/pengawasan-muat/assign', [\App\Http\Controllers\Api\PengawasanMuatController::class, 'assign'])->middleware('permission:edit_pengiriman');
        });

    });
