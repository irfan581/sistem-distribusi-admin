<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Muat subsidi dan non subsidi dipisah haknya karena diurus admin berbeda.
 * Siapa pun yang sekarang memegang view_pengiriman diberi keduanya, supaya
 * tidak ada yang kehilangan akses saat rilis; pemisahannya dilakukan dari
 * panel dengan mencabut salah satunya.
 */
return new class extends Migration
{
    private const HAK_BARU = ['muat_subsidi', 'muat_non_subsidi'];

    public function up(): void
    {
        $this->ubah('roles', fn (array $perms) => in_array('view_pengiriman', $perms)
            ? array_values(array_unique(array_merge($perms, self::HAK_BARU)))
            : $perms);
        $this->ubah('users', fn (array $perms) => in_array('view_pengiriman', $perms)
            ? array_values(array_unique(array_merge($perms, self::HAK_BARU)))
            : $perms);
    }

    public function down(): void
    {
        $this->ubah('roles', fn (array $perms) => array_values(array_diff($perms, self::HAK_BARU)));
        $this->ubah('users', fn (array $perms) => array_values(array_diff($perms, self::HAK_BARU)));
    }

    private function ubah(string $tabel, callable $fn): void
    {
        DB::table($tabel)->whereNotNull('permissions')->get(['id', 'permissions'])
            ->each(function ($row) use ($tabel, $fn) {
                $perms = json_decode($row->permissions, true);
                if (!is_array($perms)) return;

                $baru = $fn($perms);
                if ($baru !== $perms) {
                    DB::table($tabel)->where('id', $row->id)->update(['permissions' => json_encode($baru)]);
                }
            });
    }
};
