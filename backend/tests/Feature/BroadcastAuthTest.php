<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

/**
 * APK sales lama meminta auth channel tanpa socket_id; dulu jadi TypeError 500 ribuan kali sehari.
 */
class BroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Driver null di phpunit.xml tidak memeriksa socket_id, jadi pakai reverb seperti produksi.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'kunci-uji',
            'broadcasting.connections.reverb.secret' => 'rahasia-uji',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@uji.test', 'password' => 'rahasia123', 'is_admin' => true,
        ]);
        $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('admin')->plainTextToken);

        return $admin;
    }

    public function test_tanpa_socket_id_ditolak_422_bukan_500(): void
    {
        $admin = $this->admin();

        $this->postJson('/broadcasting/auth', ['channel_name' => "private-chat.user.{$admin->id}"])
            ->assertStatus(422);
    }

    public function test_dengan_socket_id_tetap_mendapat_tanda_tangan(): void
    {
        $admin = $this->admin();

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-chat.user.{$admin->id}",
        ])->assertOk()->assertJsonStructure(['auth']);
    }
}
