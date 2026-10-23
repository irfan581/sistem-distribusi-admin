<?php

namespace Tests\Feature;

use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Muat subsidi dan non subsidi diurus admin berbeda, jadi masing-masing hanya
 * boleh melihat dan mengubah muatan jenisnya sendiri.
 */
class PisahMuatSubsidiTest extends TestCase
{
    use RefreshDatabase;

    private const HAK_DASAR = ['view_pengiriman', 'create_pengiriman', 'edit_pengiriman', 'delete_pengiriman'];

    private function admin(array $izin, ?Role $role = null): User
    {
        return User::create([
            'name' => 'Admin Uji', 'email' => uniqid() . '@uji.test', 'password' => 'rahasia123',
            'is_admin' => true, 'permissions' => $izin, 'role_id' => $role?->id,
        ]);
    }

    private function sebagai(User $pengguna): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $pengguna->createToken('admin')->plainTextToken);
    }

    private function buatRit(string $jenis): DaftarMuat
    {
        $muat = DaftarMuat::create([
            'tanggal_muatan'   => now()->toDateString(),
            'nomor_rit'        => 1,
            'jenis_muatan'     => $jenis,
            'total_muatan_ton' => 5,
        ]);

        DaftarMuatItem::create([
            'daftar_muat_id'    => $muat->id,
            'urutan'            => 1,
            'lokasi_pengiriman' => 'Tujuan ' . $jenis,
            'jenis_muatan'      => $jenis,
        ]);

        return $muat;
    }

    private function rencanaSubsidi(): array
    {
        return [
            'tanggal_muatan'   => now()->toDateString(),
            'jenis_muatan'     => 'subsidi',
            'total_muatan_ton' => 2,
            'items'            => [['lokasi_pengiriman' => 'Kelompok Tani Uji']],
        ];
    }

    public function test_admin_subsidi_hanya_melihat_muatan_subsidi(): void
    {
        $subsidi    = $this->buatRit('subsidi');
        $nonSubsidi = $this->buatRit('non_subsidi');
        $admin      = $this->admin([...self::HAK_DASAR, 'muat_subsidi']);

        $ids = collect($this->sebagai($admin)->getJson('/api/admin/pengiriman/daftar-muat')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$subsidi->id], $ids->all());

        $this->sebagai($admin)->getJson("/api/admin/pengiriman/daftar-muat/{$nonSubsidi->id}")->assertNotFound();
        $this->sebagai($admin)->getJson("/api/admin/pengiriman/daftar-muat/{$nonSubsidi->id}/laporan-driver")->assertNotFound();
        $this->sebagai($admin)->deleteJson("/api/admin/pengiriman/daftar-muat/{$nonSubsidi->id}")->assertNotFound();
        $this->assertModelExists($nonSubsidi);
    }

    public function test_admin_non_subsidi_tidak_bisa_membuat_muatan_subsidi(): void
    {
        $admin = $this->admin([...self::HAK_DASAR, 'muat_non_subsidi']);

        $this->sebagai($admin)->postJson('/api/admin/pengiriman/daftar-muat', $this->rencanaSubsidi())->assertForbidden();
        $this->assertDatabaseCount('daftar_muats', 0);
    }

    public function test_admin_subsidi_bisa_membuat_muatan_subsidi(): void
    {
        $admin = $this->admin([...self::HAK_DASAR, 'muat_subsidi']);

        $this->sebagai($admin)->postJson('/api/admin/pengiriman/daftar-muat', $this->rencanaSubsidi())->assertCreated();
    }

    public function test_muatan_tidak_bisa_dipindah_ke_jenis_yang_tidak_dipegang(): void
    {
        $muat  = $this->buatRit('non_subsidi');
        $admin = $this->admin([...self::HAK_DASAR, 'muat_non_subsidi']);

        $this->sebagai($admin)->putJson("/api/admin/pengiriman/daftar-muat/{$muat->id}", $this->rencanaSubsidi())->assertForbidden();
        $this->assertEquals('non_subsidi', $muat->fresh()->jenis_muatan);
    }

    public function test_penugasan_checker_khusus_admin_non_subsidi(): void
    {
        $this->sebagai($this->admin([...self::HAK_DASAR, 'muat_subsidi']))
            ->getJson('/api/admin/pengiriman/pengawasan-muat')->assertForbidden();

        $this->sebagai($this->admin([...self::HAK_DASAR, 'muat_non_subsidi']))
            ->getJson('/api/admin/pengiriman/pengawasan-muat')->assertOk();
    }

    public function test_hak_yang_dicabut_di_pengguna_tidak_lolos_lewat_role(): void
    {
        $role = Role::create([
            'nama' => 'admin_pengiriman', 'label' => 'Admin Pengiriman', 'level' => 1, 'is_active' => true,
            'permissions' => [...self::HAK_DASAR, 'muat_subsidi', 'muat_non_subsidi'],
        ]);
        $nonSubsidi = $this->buatRit('non_subsidi');
        $admin      = $this->admin([...self::HAK_DASAR, 'muat_subsidi'], $role);

        $this->sebagai($admin)->getJson("/api/admin/pengiriman/daftar-muat/{$nonSubsidi->id}")->assertNotFound();
    }

    public function test_migrasi_memberi_kedua_hak_ke_pemegang_view_pengiriman(): void
    {
        $role      = Role::create(['nama' => 'keuangan', 'label' => 'Keuangan', 'level' => 1, 'is_active' => true, 'permissions' => ['view_pengiriman']]);
        $roleLain  = Role::create(['nama' => 'sales_spv', 'label' => 'SPV Sales', 'level' => 1, 'is_active' => true, 'permissions' => ['view_sales']]);
        $pengguna  = $this->admin(['view_pengiriman', 'edit_pengiriman']);
        $migrasi   = require database_path('migrations/2026_10_19_100000_pisah_hak_muat_subsidi.php');

        $migrasi->up();

        $this->assertEquals(['view_pengiriman', 'muat_subsidi', 'muat_non_subsidi'], $role->fresh()->permissions);
        $this->assertEquals(['view_sales'], $roleLain->fresh()->permissions);
        $this->assertEquals(['view_pengiriman', 'edit_pengiriman', 'muat_subsidi', 'muat_non_subsidi'], $pengguna->fresh()->permissions);

        $migrasi->down();

        $this->assertEquals(['view_pengiriman'], $role->fresh()->permissions);
        $this->assertEquals(['view_pengiriman', 'edit_pengiriman'], $pengguna->fresh()->permissions);
    }
}
