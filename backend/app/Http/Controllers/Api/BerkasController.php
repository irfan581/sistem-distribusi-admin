<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BongkarFoto;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\Izin;
use App\Models\KunjunganToko;
use App\Models\Presensi;
use App\Models\Sales;
use App\Models\User;
use App\Support\Berkas;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satu pintu untuk semua foto: bukti bongkar, kunjungan toko, presensi, izin.
 *
 * Sebelumnya foto disimpan di disk `public` dan disajikan Nginx lewat symlink
 * `public/storage`. Laravel tidak pernah dilibatkan, jadi siapa pun yang
 * memegang URL-nya bisa membukanya tanpa login — termasuk swafoto presensi
 * karyawan. Nama berkas yang berupa UUID acak hanya menunda, bukan menutup:
 * satu URL yang tersalin ke chat berlaku selamanya dan tidak bisa dicabut.
 *
 * Semua berkas kini lewat sini, dan tiap jenis punya aturan siapa yang berhak.
 */
class BerkasController extends Controller
{
    /**
     * Foto bukti bongkar.
     *
     * Sopir hanya boleh melihat foto ritnya sendiri; admin melihat semua.
     */
    public function bongkar(Request $request, int $id, string $ukuran = 'penuh'): StreamedResponse
    {
        $this->pastikanAdaBukti($request);

        $foto = BongkarFoto::with(['daftarMuat', 'item'])->findOrFail($id);

        $this->pastikanBerhak(
            $request,
            milikDriver: $foto->driver_id,
        );

        $path = $ukuran === 'kecil' && ! empty($foto->thumb)
            ? $foto->thumb
            : $foto->foto;

        return $this->alirkan($path, $this->namaUnduhan([
            $foto->lokasi_snapshot,
            optional($foto->created_at)->format('d-m-Y'),
        ], $path));
    }

    /** Foto kunjungan toko oleh sales. */
    public function kunjungan(Request $request, int $id): StreamedResponse
    {
        $this->pastikanAdaBukti($request);

        $kunjungan = KunjunganToko::findOrFail($id);

        $this->pastikanBerhak($request, milikSales: $kunjungan->sales_id);

        return $this->alirkan($kunjungan->foto_kunjungan, $this->namaUnduhan([
            $kunjungan->nama_toko,
            optional($kunjungan->created_at)->format('d-m-Y'),
        ], $kunjungan->foto_kunjungan));
    }

    /**
     * Foto presensi. [$jenis] memisahkan foto masuk dari foto pulang, karena
     * keduanya tersimpan di kolom berbeda pada baris yang sama.
     */
    public function presensi(Request $request, int $id, string $jenis = 'masuk'): StreamedResponse
    {
        $this->pastikanAdaBukti($request);

        $presensi = Presensi::with('sales')->findOrFail($id);

        $this->pastikanBerhak($request, milikSales: $presensi->sales_id);

        $path = $jenis === 'pulang' ? $presensi->foto_pulang : $presensi->foto_masuk;

        return $this->alirkan($path, $this->namaUnduhan([
            optional($presensi->sales)->nama,
            $jenis,
            optional($presensi->tanggal)->format('d-m-Y'),
        ], $path));
    }

    /** Lampiran bukti izin. */
    public function izin(Request $request, int $id): StreamedResponse
    {
        $this->pastikanAdaBukti($request);

        $izin = Izin::with('sales')->findOrFail($id);

        $this->pastikanBerhak($request, milikSales: $izin->sales_id);

        return $this->alirkan($izin->bukti_foto, $this->namaUnduhan([
            optional($izin->sales)->nama,
            'izin',
            optional($izin->tanggal)->format('d-m-Y'),
        ], $izin->bukti_foto));
    }

    /**
     * Foto profil sales.
     *
     * Ikut dipindah bukan karena isinya rahasia, tapi karena selama satu berkas
     * pun masih disajikan lewat `public/storage`, symlink itu tidak bisa dicabut
     * — dan selama symlink-nya ada, seluruh folder di belakangnya tetap terbuka.
     */
    public function avatarSales(Request $request, int $id): StreamedResponse
    {
        $this->pastikanAdaBukti($request);

        $sales = Sales::findOrFail($id);

        $this->pastikanBerhak($request, milikSales: $sales->id);

        return $this->alirkan($sales->foto_profil, $this->namaUnduhan(
            [$sales->nama, 'profil'],
            $sales->foto_profil,
        ));
    }

    /** Foto profil pengguna admin. */
    public function avatarPengguna(Request $request, int $id): StreamedResponse
    {
        $this->pastikanAdaBukti($request);

        $pengguna = User::findOrFail($id);

        // Avatar admin hanya dilihat sesama admin di dashboard, jadi cukup
        // memastikan peminta memang admin — bukan pemilik avatarnya.
        abort_unless(
            $request->hasValidSignature() || auth('sanctum')->user() instanceof User,
            403,
            'Berkas ini bukan milik Anda.',
        );

        return $this->alirkan($pengguna->avatar_url, $this->namaUnduhan(
            [$pengguna->name, 'profil'],
            $pengguna->avatar_url,
        ));
    }

    /**
     * Lampiran pesan chat: foto, berkas, dan rekaman suara.
     *
     * Dijaga bukan karena isinya selalu rahasia, tapi karena grup laporan
     * kiriman memuat bukti bongkar — dan selama satu berkas pun masih
     * disajikan lewat `public/storage`, symlink itu tidak bisa dicabut.
     */
    public function chat(Request $request, int $id, string $ukuran = 'penuh'): StreamedResponse
    {
        $this->pastikanAdaBukti($request);

        $pesan = ChatMessage::findOrFail($id);

        $this->pastikanIkutPercakapan($request, $pesan);

        $path = $ukuran === 'kecil' && filled($pesan->thumb_path)
            ? $pesan->thumb_path
            : $this->pathChat($pesan);

        return $this->alirkan($path, $this->namaUnduhan([
            $pesan->file_name ? pathinfo($pesan->file_name, PATHINFO_FILENAME) : 'lampiran',
            optional($pesan->created_at)->format('d-m-Y'),
        ], $path));
    }

    /**
     * Pesan lama dari dashboard hanya menyimpan `file_url` berupa alamat penuh
     * `/storage/...`, bukan path. Diambil bagian belakangnya supaya lampiran
     * lama tidak jadi kosong setelah symlink-nya dicabut.
     */
    private function pathChat(ChatMessage $pesan): ?string
    {
        if (filled($pesan->file_path)) {
            return $pesan->file_path;
        }

        if (blank($pesan->file_url)) {
            return null;
        }

        $sesudah = Str::after(parse_url($pesan->file_url, PHP_URL_PATH) ?: '', '/storage/');

        return $sesudah !== '' ? $sesudah : null;
    }

    /**
     * Admin melihat semua. Selain itu harus benar-benar ikut percakapannya:
     * lawan bicara di pesan pribadi, atau anggota terdaftar di grup.
     *
     * Dibandingkan dengan pengirim dan penerima yang tersimpan di baris
     * pesannya, bukan dengan apa yang dikirim pemanggil.
     */
    private function pastikanIkutPercakapan(Request $request, ChatMessage $pesan): void
    {
        if ($this->tandaTanganSah($request)) {
            return;
        }

        $pengguna = auth('sanctum')->user();

        abort_if($pengguna === null, 401);

        if ($pengguna instanceof User) {
            return;
        }

        $kelas = get_class($pengguna);

        if ($pesan->group_id !== null) {
            $anggota = ChatGroupMember::where('group_id', $pesan->group_id)
                ->where('member_type', $kelas)
                ->where('member_id', $pengguna->id)
                ->exists();

            abort_unless($anggota, 403, 'Berkas ini bukan milik Anda.');

            return;
        }

        $ikut = ($pesan->sender_type === $kelas && $pesan->sender_id === $pengguna->id)
            || ($pesan->receiver_type === $kelas && $pesan->receiver_id === $pengguna->id);

        abort_unless($ikut, 403, 'Berkas ini bukan milik Anda.');
    }

    // ── Penjaga hak akses ─────────────────────────────────────────

    /**
     * Tolak sebelum database disentuh kalau tidak ada bukti apa pun.
     *
     * Tanpa ini, tamu tanpa token yang menebak nomor berkas dijawab "Data tidak
     * ditemukan" untuk nomor yang kosong dan 403 untuk nomor yang ada — dan dari
     * beda jawaban itu ia bisa memetakan nomor mana yang berisi, tanpa pernah
     * melihat satu foto pun.
     */
    private function pastikanAdaBukti(Request $request): void
    {
        if ($this->tandaTanganSah($request)) {
            return;
        }

        abort_if(auth('sanctum')->user() === null, 401);
    }

    /**
     * Tanda tangan sah, dengan `v` sengaja diabaikan.
     *
     * Aplikasi sales menempelkan `?v=<waktu>` ke URL foto profil untuk memaksa
     * gambarnya dimuat ulang setelah diganti (layar_profil.dart:401). Laravel
     * menghitung SELURUH parameter saat memeriksa tanda tangan, jadi tambahan
     * itu akan membatalkannya dan foto profil sales jadi kosong.
     *
     * Diabaikan di sini, bukan dihapus dari aplikasinya, supaya APK sales yang
     * sudah terpasang di HP orang tidak perlu dibangun dan dibagikan ulang —
     * dan pemaksa segarnya tetap bekerja seperti sebelumnya.
     */
    private function tandaTanganSah(Request $request): bool
    {
        return $request->hasValidSignatureWhileIgnoring(['v']);
    }

    /**
     * Admin boleh melihat semua. Sopir dan sales hanya miliknya sendiri.
     *
     * Dibandingkan dengan id pemilik yang tersimpan di barisnya, bukan dengan
     * apa yang dikirim pemanggil — id di URL bisa ditebak, id pemilik tidak
     * bisa dipalsukan tanpa menembus autentikasi lebih dulu.
     */
    private function pastikanBerhak(
        Request $request,
        ?int $milikDriver = null,
        ?int $milikSales = null,
    ): void {
        // Tautan bertanda tangan lebih dulu. Dashboard menampilkan foto dengan
        // `<img src>`, dan tag itu TIDAK PERNAH mengirim header Authorization —
        // token Bearer hanya ditambahkan axios. Route berpenjaga token saja
        // akan mematikan semua gambar di dashboard.
        //
        // Tanda tangannya hanya bisa dibuat server, dan hanya diberikan kepada
        // pemakai yang sudah terbukti berhak lewat endpoint datanya. Masa
        // berlakunya terbatas, jadi URL yang bocor tidak berlaku selamanya
        // seperti /storage/ yang lama.
        if ($this->tandaTanganSah($request)) {
            return;
        }

        // Aplikasi sopir dan sales memanggil lewat Dio dan bisa mengirim
        // header, jadi jalur token tetap dilayani.
        $pengguna = auth('sanctum')->user();

        abort_if($pengguna === null, 401);

        // Admin web memakai model User; sopir dan sales punya modelnya sendiri.
        if ($pengguna instanceof \App\Models\User) {
            return;
        }

        if ($milikDriver !== null && $pengguna instanceof \App\Models\drivers) {
            abort_if($pengguna->id !== $milikDriver, 403, 'Berkas ini bukan milik Anda.');

            return;
        }

        if ($milikSales !== null && $pengguna instanceof \App\Models\Sales) {
            abort_if($pengguna->id !== $milikSales, 403, 'Berkas ini bukan milik Anda.');

            return;
        }

        abort(403, 'Berkas ini bukan milik Anda.');
    }

    // ── Pengaliran berkas ─────────────────────────────────────────

    /**
     * Cari berkasnya di disk privat dulu, baru disk lama.
     *
     * Urutan ini yang membuat perpindahan bisa dikerjakan bertahap per folder:
     * selama masa pindah, foto yang sudah pindah dan yang belum sama-sama
     * tampil lewat route yang sama. Cadangan ke `public` dibuang setelah
     * seluruh berkas selesai dipindah.
     */
    private function alirkan(?string $path, string $namaUnduhan): StreamedResponse
    {
        $disk = Berkas::disk($path);

        abort_if($disk === null, 404, 'Berkas tidak ada.');

        return $disk->response($path, $namaUnduhan, [
            // Foto bukti tidak boleh disimpan proxy bersama, dan browser boleh
            // menyimpannya sebentar karena isinya tidak pernah berubah setelah
            // diunggah.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Nama berkas yang terbaca manusia, dipakai saat admin mengunduh.
     *
     * Di disk namanya tetap UUID — nama orang dan toko bisa berubah, dan dua
     * toko bisa bernama sama. Yang dirapikan hanya nama yang diterima admin
     * saat menyimpan berkasnya, karena `33255636-ad4c-….jpg` tidak memberi
     * tahu apa pun setelah ada di folder Unduhan.
     */
    private function namaUnduhan(array $bagian, ?string $path): string
    {
        $bersih = collect($bagian)
            ->filter(fn ($b) => filled($b))
            ->map(fn ($b) => Str::slug((string) $b))
            ->filter()
            ->implode('-');

        $ekstensi = pathinfo((string) $path, PATHINFO_EXTENSION) ?: 'jpg';

        return ($bersih !== '' ? $bersih : 'berkas') . '.' . $ekstensi;
    }
}
