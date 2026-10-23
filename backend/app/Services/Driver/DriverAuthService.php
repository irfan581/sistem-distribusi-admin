<?php

namespace App\Services\Driver;

use App\Models\drivers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DriverAuthService
{
    /**
     * Karakter untuk password sementara. Tanpa 0/O/1/I/l karena password ini
     * dibacakan atau disalin admin ke driver lewat pesan singkat.
     */
    private const ALFABET_PASSWORD = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    /**
     * @return array{driver: drivers, token: string}
     *
     * @throws \App\Exceptions\DriverLoginException
     */
    public function login(string $kodeDriver, string $password, ?string $deviceId, ?string $fcmToken): array
    {
        $driver = drivers::where('kode_driver', strtoupper(trim($kodeDriver)))->first();

        if (!$driver || !$driver->password || !Hash::check($password, $driver->password)) {
            throw new \App\Exceptions\DriverLoginException('Kode driver atau password salah.', 'KREDENSIAL_SALAH', 401);
        }

        if ($driver->status_kerja !== 'Aktif') {
            throw new \App\Exceptions\DriverLoginException('Akun Anda sudah tidak aktif. Hubungi admin.', 'DRIVER_NONAKTIF', 403);
        }

        if (!$driver->mobile_aktif) {
            throw new \App\Exceptions\DriverLoginException('Akun aplikasi Anda belum diaktifkan admin.', 'AKUN_BELUM_AKTIF', 403);
        }

        // Satu akun satu HP, sama seperti aplikasi sales: token lama dicabut
        // supaya akun tidak dipakai bergantian di dua perangkat.
        $driver->tokens()->delete();

        $driver->device_id = $deviceId;
        if ($fcmToken) {
            $driver->fcm_token = $fcmToken;
        }
        $driver->last_login_at = now();
        $driver->save();

        return [
            'driver' => $driver->refresh(),
            'token'  => $driver->createToken('driver_auth_token')->plainTextToken,
        ];
    }

    /**
     * Nomor urut diambil dari kode tertinggi yang sudah ada, bukan dari id,
     * supaya penghapusan driver tidak membuat kode terpakai ulang.
     */
    public function generateKode(): string
    {
        return DB::transaction(function () {
            // Urutan dihitung di PHP, bukan lewat CAST/SUBSTRING di SQL, supaya
            // sama persis di MySQL produksi dan SQLite yang dipakai test.
            $tertinggi = drivers::lockForUpdate()
                ->where('kode_driver', 'like', 'DRV-%')
                ->pluck('kode_driver')
                ->map(fn (string $kode) => (int) substr($kode, 4))
                ->max();

            return 'DRV-' . str_pad((string) (($tertinggi ?? 0) + 1), 3, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Mengembalikan password polos SEKALI supaya admin bisa menyalinkannya ke
     * driver. Setelah ini hanya hash yang tersimpan.
     */
    public function resetPassword(drivers $driver, ?string $passwordPolos = null): string
    {
        $passwordPolos ??= $this->generatePassword();

        $driver->password = $passwordPolos;
        $driver->harus_ganti_password = true;
        $driver->save();

        // Password lama tidak boleh lagi dipakai lewat token yang masih hidup.
        $driver->tokens()->delete();

        return $passwordPolos;
    }

    public function gantiPassword(drivers $driver, string $passwordLama, string $passwordBaru): void
    {
        if (!Hash::check($passwordLama, $driver->password)) {
            throw new \App\Exceptions\DriverLoginException('Password lama salah.', 'PASSWORD_LAMA_SALAH', 422);
        }

        $driver->password = $passwordBaru;
        $driver->harus_ganti_password = false;
        $driver->save();
    }

    private function generatePassword(int $panjang = 8): string
    {
        $alfabet = self::ALFABET_PASSWORD;
        $maks = strlen($alfabet) - 1;
        $hasil = '';

        for ($i = 0; $i < $panjang; $i++) {
            $hasil .= $alfabet[random_int(0, $maks)];
        }

        return $hasil;
    }
}
