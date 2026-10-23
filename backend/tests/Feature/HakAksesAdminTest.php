<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Centang hak akses di form admin harus benar-benar berpengaruh di server.
 */
class HakAksesAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $nama, array $izin): User
    {
        $role = Role::create([
            'nama' => $nama, 'label' => $nama, 'level' => 1,
            'permissions' => $izin, 'is_active' => true,
        ]);

        return User::create([
            'name' => $nama, 'email' => "{$nama}@uji.test", 'password' => 'rahasia123',
            'role_id' => $role->id, 'is_admin' => true,
        ]);
    }

    private function sebagai(User $pengguna): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $pengguna->createToken('admin')->plainTextToken);
    }

    public function test_pelacakan_sales_ditolak_tanpa_hak_peta(): void
    {
        $admin = $this->admin('tanpa_izin', []);

        foreach (['/api/admin/tracking/live', '/api/admin/tracking/sales-aktif', '/api/admin/tracking/rute/1'] as $uri) {
            $this->sebagai($admin)->getJson($uri)->assertForbidden();
        }
    }

    public function test_pelacakan_sales_boleh_dengan_hak_peta(): void
    {
        $admin = $this->admin('peta', ['view_maps_sales']);

        $this->sebagai($admin)->getJson('/api/admin/tracking/live')->assertOk();
        $this->sebagai($admin)->getJson('/api/admin/tracking/sales-aktif')->assertOk();
    }

    public function test_lihat_target_kunjungan_cukup_dengan_hak_lihat(): void
    {
        $admin = $this->admin('lihat_target', ['view_target_kunjungan']);

        $this->sebagai($admin)->getJson('/api/admin/visit-targets')->assertOk();
    }

    public function test_hak_lihat_target_saja_tidak_bisa_mengubah_massal(): void
    {
        $admin = $this->admin('lihat_target', ['view_target_kunjungan']);

        $this->sebagai($admin)->postJson('/api/admin/visit-targets/bulk', [])->assertForbidden();
    }

    public function test_kelola_target_boleh_mengubah_massal(): void
    {
        $admin = $this->admin('kelola_target', ['manage_target_kunjungan']);

        // Lolos pemeriksaan hak akses; isian kosong lalu ditolak validasi, bukan 403.
        $this->sebagai($admin)->postJson('/api/admin/visit-targets/bulk', [])->assertStatus(422);
    }

    public function test_target_kunjungan_ditolak_tanpa_hak_apa_pun(): void
    {
        $admin = $this->admin('tanpa_izin', []);

        $this->sebagai($admin)->getJson('/api/admin/visit-targets')->assertForbidden();
    }

    public function test_role_lama_dengan_manage_pengaturan_tetap_bisa_melihat_target(): void
    {
        $admin = $this->admin('pengaturan', ['manage_pengaturan']);

        $this->sebagai($admin)->getJson('/api/admin/visit-targets')->assertOk();
    }

    public function test_admin_pengiriman_tidak_bisa_membuka_chat_dan_notifikasi(): void
    {
        $admin = $this->admin('pengiriman', ['view_pengiriman', 'view_armada']);

        foreach (['/api/admin/chat/contacts', '/api/admin/chat/unread', '/api/admin/notifications', '/api/admin/notifications/unread-count'] as $uri) {
            $this->sebagai($admin)->getJson($uri)->assertForbidden();
        }
    }

    public function test_admin_sales_tetap_bisa_membuka_chat_dan_notifikasi(): void
    {
        $admin = $this->admin('izin', ['view_izin']);

        $this->sebagai($admin)->getJson('/api/admin/chat/unread')->assertOk();
        $this->sebagai($admin)->getJson('/api/admin/notifications/unread-count')->assertOk();
    }
}
