<?php

namespace Tests\Feature\Driver;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\drivers;
use App\Support\BerkasChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Dua sisi aplikasi dulu menyimpan dan membaca kolom berkas yang berbeda, dan
 * masing-masing lolos saat diuji sendiri. Yang dijaga di sini: alamat berkas
 * sebuah pesan chat tidak lagi bergantung pada siapa yang mengirimnya.
 */
class BerkasChatTest extends TestCase
{
    use RefreshDatabase;

    private function pesan(array $override = []): ChatMessage
    {
        return ChatMessage::create(array_merge([
            'sender_type'  => (new drivers)->getMorphClass(),
            'sender_id'    => 1,
            'type'         => 'group',
            'group_id'     => 1,
            'message_type' => 'image',
            'message'      => 'Toko Makmur — Selesai',
        ], $override));
    }

    public function test_pesan_dari_aplikasi_memakai_file_path(): void
    {
        $pesan = $this->pesan(['file_path' => 'bongkar/foto-1.jpg']);

        $this->assertSame($this->tautan($pesan, 'penuh'), BerkasChat::url($pesan));
    }

    /**
     * Dashboard admin menulis alamat jadi ke kolom `file_url` dan tidak pernah
     * mengisi `file_path`. Pesan lama seperti itu tidak boleh jadi kosong —
     * alamatnya kini juga lewat route berpenjaga, dan path-nya diambil dari
     * bagian belakang alamat lama.
     */
    public function test_pesan_lama_dari_dashboard_masih_terbaca(): void
    {
        $pesan = $this->pesan(['file_url' => '/storage/chat-files/lama.jpg']);

        $this->assertSame($this->tautan($pesan, 'penuh'), BerkasChat::url($pesan));
    }

    public function test_thumbnail_dipakai_bila_ada(): void
    {
        $pesan = $this->pesan([
            'file_path'  => 'bongkar/foto-1.jpg',
            'thumb_path' => 'bongkar/thumb/foto-1.jpg',
        ]);

        $this->assertSame($this->tautan($pesan, 'kecil'), BerkasChat::thumb($pesan));
    }

    public function test_tanpa_thumbnail_jatuh_ke_berkas_penuh(): void
    {
        $pesan = $this->pesan(['file_path' => 'chat-files/biasa.jpg']);

        $this->assertSame(BerkasChat::url($pesan), BerkasChat::thumb($pesan));
    }

    public function test_pesan_tanpa_berkas_mengembalikan_null(): void
    {
        $pesan = $this->pesan(['message_type' => 'text']);

        $this->assertNull(BerkasChat::url($pesan));
        $this->assertNull(BerkasChat::thumb($pesan));
    }

    /**
     * Inti perbaikannya: siaran realtime dulu membaca kolom `file_url` yang
     * tidak pernah diisi jalur sopir, jadi foto laporan bongkar datang tanpa
     * gambar dan baru muncul setelah chat dibuka ulang.
     */
    public function test_siaran_realtime_membawa_alamat_foto(): void
    {
        $pesan = $this->pesan([
            'file_path'  => 'bongkar/foto-1.jpg',
            'thumb_path' => 'bongkar/thumb/foto-1.jpg',
        ]);

        $siaran = new ChatMessageSent($pesan);

        $this->assertSame($this->tautan($pesan, 'penuh'), $siaran->message['file_url']);
        $this->assertSame($this->tautan($pesan, 'kecil'), $siaran->message['thumb_url']);
    }

    /**
     * Lampiran chat tidak lagi disajikan langsung dari `public/storage`:
     * alamatnya berupa tautan bertanda tangan yang berlaku 12 jam dan hanya
     * terbuka untuk yang ikut percakapannya. Dijaga di sini supaya tidak ada
     * yang diam-diam mengembalikan alamat `/storage/...` yang berlaku
     * selamanya.
     */
    public function test_alamat_berkas_bertanda_tangan_bukan_storage_langsung(): void
    {
        $pesan = $this->pesan(['file_path' => 'chat-images/rahasia.jpg']);

        $alamat = BerkasChat::url($pesan);

        $this->assertStringContainsString('/api/berkas/chat/', $alamat);
        $this->assertStringContainsString('signature=', $alamat);
        $this->assertStringNotContainsString('/storage/', $alamat);
    }

    private function tautan(ChatMessage $pesan, string $ukuran): string
    {
        return URL::temporarySignedRoute('berkas.chat', now()->addHours(12), [
            'id' => $pesan->id, 'ukuran' => $ukuran,
        ]);
    }
}
