<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\drivers;

use App\Models\Presensi;
use App\Models\KunjunganToko;
use App\Models\Izin;
use Spatie\Activitylog\Models\Activity;

use App\Policies\KunjunganTokoPolicy;
use App\Policies\PresensiPolicy;
use App\Policies\IzinPolicy;
use App\Policies\UserPolicy;
use App\Policies\VisitTargetPolicy;
use App\Models\SalesVisitTarget;

use App\Observers\PresensiObserver;
use App\Observers\KunjunganTokoObserver;
use App\Observers\IzinObserver;
use App\Observers\DaftarMuatObserver;
use App\Models\DaftarMuat;
use App\Policies\DaftarMuatPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Policies
        Gate::policy(KunjunganToko::class, KunjunganTokoPolicy::class);
        Gate::policy(Activity::class, \App\Policies\ActivityPolicy::class);
        Gate::policy(\App\Models\User::class, UserPolicy::class);
        Gate::policy(Presensi::class, PresensiPolicy::class);
        Gate::policy(Izin::class, IzinPolicy::class);
        Gate::policy(SalesVisitTarget::class, VisitTargetPolicy::class);
        Gate::policy(DaftarMuat::class, DaftarMuatPolicy::class);

        // Observers
        Presensi::observe(PresensiObserver::class);
        KunjunganToko::observe(KunjunganTokoObserver::class);
        Izin::observe(IzinObserver::class);
        DaftarMuat::observe(DaftarMuatObserver::class);

        $this->configureRateLimiting();

        // Sopir cukup login sekali: batas 12 jam di config/sanctum.php hanya untuk
        // sales. Token sopir tetap berakhir lewat logout, HP lain, reset, atau nonaktif.
        Sanctum::authenticateAccessTokensUsing(function ($token, bool $isValid): bool {
            if ($isValid || ! $token->tokenable instanceof drivers) {
                return $isValid;
            }

            return ! $token->expires_at || ! $token->expires_at->isPast();
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn() => response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak permintaan. Silakan coba lagi.',
                ], 429));
        });

        // Foto punya pembatas sendiri, dan jauh lebih longgar.
        //
        // Sebelum foto dipindah ke route berpenjaga, Nginx menyajikannya
        // langsung tanpa batas sama sekali. Sekarang semuanya lewat Laravel,
        // dan satu halaman daftar kunjungan saja memuat 30 petak foto
        // sekaligus — dengan batas 'api' yang 60 per menit, foto berhenti
        // muncul sebelum admin selesai menggulir satu halaman.
        //
        // Lebih parah lagi: batasnya per alamat IP, sedangkan seluruh admin di
        // kantor keluar lewat satu IP yang sama. Jatah 60 itu dibagi bersama.
        //
        // Melonggarkannya aman karena yang menjaga foto bukan pembatas ini,
        // melainkan tanda tangan berbatas waktu dan pemeriksaan pemilik di
        // dalam controller. Pembatas di sini hanya menahan penyedotan massal.
        RateLimiter::for('berkas', function (Request $request) {
            return Limit::perMinute(600)
                ->by($request->user()?->id ?? $request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak permintaan gambar. Muat ulang halaman sebentar lagi.',
                ], 429));
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())
                ->response(fn() => response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.',
                ], 429));
        });

        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?? $request->ip())
                ->response(fn() => response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak upload. Mohon tunggu sebentar.',
                ], 429));
        });
    }
}
