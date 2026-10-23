<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Siapa yang melihat siapa di daftar pengguna admin.
 */
class DaftarPenggunaAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $developer;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner     = $this->buatPengguna('owner', 'Bu Pemilik', 'owner@uji.test');
        $this->developer = $this->buatPengguna('developer', 'Developer Uji', 'dev@uji.test');
        $this->admin     = $this->buatPengguna('admin', 'Admin Uji', 'admin@uji.test');
    }

    private function buatPengguna(string $peran, string $nama, string $email): User
    {
        $role = Role::firstOrCreate(
            ['nama' => $peran],
            ['label' => ucfirst($peran), 'level' => 1, 'permissions' => [], 'is_active' => true],
        );

        return User::create([
            'name'     => $nama,
            'email'    => $email,
            'password' => 'rahasia123',
            'role_id'  => $role->id,
            'is_admin' => true,
        ]);
    }

    private function sebagai(User $pengguna): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $pengguna->createToken('admin')->plainTextToken);
    }

    private function emailTerlihat(User $pengguna): array
    {
        return $this->sebagai($pengguna)
            ->getJson('/api/admin/admin-users')
            ->assertOk()
            ->collect('data')
            ->pluck('email')
            ->sort()
            ->values()
            ->all();
    }

    public function test_developer_melihat_akun_owner(): void
    {
        $this->assertSame(['admin@uji.test', 'owner@uji.test'], $this->emailTerlihat($this->developer));
    }

    public function test_owner_tidak_melihat_akun_owner_maupun_developer(): void
    {
        $this->assertSame(['admin@uji.test'], $this->emailTerlihat($this->owner));
    }

    public function test_developer_bisa_mengubah_nama_owner_tanpa_mengubah_perannya(): void
    {
        $this->sebagai($this->developer)
            ->putJson("/api/admin/admin-users/{$this->owner->id}", [
                'name'    => 'Bu Pemilik Baru',
                'email'   => 'owner@uji.test',
                'role_id' => $this->owner->role_id,
            ])
            ->assertOk();

        $this->assertSame('Bu Pemilik Baru', $this->owner->fresh()->name);
        $this->assertTrue($this->owner->fresh()->isOwner());
    }

    public function test_peran_owner_tidak_bisa_diganti(): void
    {
        $this->sebagai($this->developer)
            ->putJson("/api/admin/admin-users/{$this->owner->id}", [
                'name'    => 'Bu Pemilik',
                'email'   => 'owner@uji.test',
                'role_id' => $this->admin->role_id,
            ])
            ->assertStatus(422);

        $this->assertTrue($this->owner->fresh()->isOwner());
    }

    public function test_developer_bisa_mengganti_password_owner(): void
    {
        $this->sebagai($this->developer)
            ->putJson("/api/admin/admin-users/{$this->owner->id}/password", [
                'password'              => 'sandibaru123',
                'password_confirmation' => 'sandibaru123',
            ])
            ->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('sandibaru123', $this->owner->fresh()->password));
    }
}
