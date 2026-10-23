<?php

namespace App\Console\Commands;

use App\Models\BongkarFoto;
use App\Models\ChatMessage;
use App\Models\Izin;
use App\Models\KunjunganToko;
use App\Models\Presensi;
use App\Models\Sales;
use App\Models\User;
use App\Support\Berkas;
use App\Support\BerkasChat;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Buktikan setiap foto masih tampil sesudah pindah ke route berpenjaga.
 *
 * Ini langkah 4 dari rencana amankan foto, dan pagar pengaman sebelum satu
 * berkas pun dipindahkan. Dibuat sebagai perintah, bukan daftar centang untuk
 * diklik, karena yang perlu dibuktikan ada 8.000 berkas di sembilan folder —
 * membuka delapan halaman dashboard hanya membuktikan delapan baris teratas.
 *
 * Yang diuji BUKAN isi database, melainkan rantai yang sebenarnya dipakai
 * dashboard dan aplikasi: accessor model membentuk alamat, alamat itu diminta
 * lewat kernel HTTP seperti browser meminta, dan jawabannya dihitung. Jadi
 * salah satu dari accessor, route, penjaga hak akses, atau pencarian disk yang
 * meleset akan kelihatan di sini.
 *
 *   php artisan berkas:periksa
 *   php artisan berkas:periksa --jenis=kunjungan --jumlah=500
 *   php artisan berkas:periksa --semua
 */
class PeriksaBerkas extends Command
{
    protected $signature = 'berkas:periksa
                            {--jenis=* : Batasi ke jenis tertentu (bongkar, kunjungan, presensi, izin, avatar-sales, avatar-pengguna, chat)}
                            {--jumlah=25 : Berapa baris diperiksa per jenis}
                            {--semua : Periksa seluruh baris, abaikan --jumlah}
                            {--rinci : Tampilkan setiap alamat yang gagal}';

    protected $description = 'Periksa semua foto masih terbuka lewat route berpenjaga';

    /** Jawaban yang dihitung gagal dikelompokkan per status, supaya sebabnya kebaca. */
    private array $rekap = [];

    /** Alamat pertama yang terbukti sehat, dipakai menguji servernya sendiri. */
    private ?string $penanda = null;

    public function handle(): int
    {
        $pilihan = $this->option('jenis') ?: array_keys($this->jenis());
        $jumlah  = $this->option('semua') ? null : max(1, (int) $this->option('jumlah'));

        $this->info($jumlah === null
            ? 'Memeriksa SELURUH baris. Ini bisa lama.'
            : "Memeriksa maksimal {$jumlah} baris per jenis.");
        $this->newLine();

        $baris = [];
        $gagalTotal = 0;
        $hilangTotal = 0;

        foreach ($pilihan as $nama) {
            $pemeriksa = $this->jenis()[$nama] ?? null;

            if ($pemeriksa === null) {
                $this->error("Jenis tidak dikenal: {$nama}");

                return self::FAILURE;
            }

            $hasil = $this->periksa($nama, $pemeriksa, $jumlah);

            $gagalTotal += $hasil['gagal'];
            $hilangTotal += $hasil['hilang'];

            $baris[] = [
                $nama,
                $hasil['diperiksa'],
                $hasil['tampil'],
                $hasil['gagal'] > 0 ? "<fg=red>{$hasil['gagal']}</>" : '0',
                $hasil['hilang'] > 0 ? "<fg=yellow>{$hasil['hilang']}</>" : '0',
                $hasil['kosong'],
            ];
        }

        $this->newLine();
        $this->table(
            ['Jenis', 'Diperiksa', 'Tampil', 'Gagal', 'Berkas hilang', 'Tanpa foto'],
            $baris,
        );

        if ($hilangTotal > 0) {
            $this->newLine();
            $this->warn("{$hilangTotal} berkas tercatat di database tapi tidak ada di disk mana pun.");
            $this->line('  Ini soal data, bukan kode, dan di Mac memang wajar, karena');
            $this->line('  foto produksi tidak semuanya pernah tersalin ke sini.');
            $this->line('  <fg=yellow>Kalau angka ini muncul di server, itu foto yang benar-benar hilang.</>');
        }

        if ($gagalTotal === 0) {
            $this->newLine();
            $this->info('Semua foto yang berkasnya ada berhasil tampil. Langkah 4 bersih untuk cakupan ini.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->error("{$gagalTotal} berkas tidak tampil. JANGAN pindahkan berkas apa pun dulu.");

        foreach ($this->rekap as $status => $contoh) {
            $this->line("  <fg=red>{$status}</>: {$contoh['jumlah']}x, contoh: {$contoh['contoh']}");
        }

        if (! $this->option('rinci')) {
            $this->newLine();
            $this->comment('Tambahkan --rinci untuk melihat setiap alamat yang gagal.');
        }

        return self::FAILURE;
    }

    /**
     * Tiap jenis menyebutkan query barisnya dan cara membentuk alamatnya.
     *
     * Alamatnya sengaja diambil dari accessor yang sama dengan yang dipakai
     * respons API, bukan dirakit ulang di sini — kalau dirakit ulang, yang
     * diuji cuma perintah ini, bukan yang sampai ke layar.
     */
    private function jenis(): array
    {
        return [
            'bongkar' => [
                fn () => BongkarFoto::query()->whereNotNull('foto'),
                fn (BongkarFoto $f) => [[$f->foto_url, $f->foto], [$f->thumb_url, $f->thumb]],
            ],
            'kunjungan' => [
                fn () => KunjunganToko::query()->whereNotNull('foto_kunjungan'),
                fn (KunjunganToko $k) => [[$k->foto_kunjungan_url, $k->foto_kunjungan]],
            ],
            'presensi' => [
                fn () => Presensi::query()->where(fn (Builder $q) => $q
                    ->whereNotNull('foto_masuk')
                    ->orWhereNotNull('foto_pulang')),
                fn (Presensi $p) => [[$p->foto_masuk_url, $p->foto_masuk], [$p->foto_pulang_url, $p->foto_pulang]],
            ],
            'izin' => [
                fn () => Izin::query()->whereNotNull('bukti_foto'),
                fn (Izin $i) => [[$i->bukti_foto_url, $i->bukti_foto]],
            ],
            'avatar-sales' => [
                fn () => Sales::query()->whereNotNull('foto_profil'),
                fn (Sales $s) => [[$s->foto_profil_url, $s->foto_profil]],
            ],
            'avatar-pengguna' => [
                fn () => User::query()->whereNotNull('avatar_url'),
                fn (User $u) => [[$u->avatar_url_full, $u->avatar_url]],
            ],
            'chat' => [
                fn () => ChatMessage::query()->where(fn (Builder $q) => $q
                    ->whereNotNull('file_path')
                    ->orWhereNotNull('file_url')),
                fn (ChatMessage $m) => [
                    [BerkasChat::url($m), $m->file_path],
                    [BerkasChat::thumb($m), $m->thumb_path ?: $m->file_path],
                ],
            ],
        ];
    }

    private function periksa(string $nama, array $pemeriksa, ?int $jumlah): array
    {
        [$query, $alamat] = $pemeriksa;

        $q = $query();

        $total = $q->clone()->count();
        $ambil = $jumlah === null ? $total : min($jumlah, $total);

        $this->line("<fg=cyan>{$nama}</>: {$total} baris berfoto, diperiksa {$ambil}");

        if ($ambil === 0) {
            return ['diperiksa' => 0, 'tampil' => 0, 'gagal' => 0, 'kosong' => 0, 'hilang' => 0];
        }

        $bar = $this->output->createProgressBar($ambil);
        $bar->start();

        $diperiksa = $tampil = $gagal = $kosong = $hilang = 0;

        $periksaSatuTumpuk = function ($rows) use ($alamat, $bar, &$diperiksa, &$tampil, &$gagal, &$kosong, &$hilang) {
                foreach ($rows as $row) {
                    $adaYangDiperiksa = false;

                    foreach ($alamat($row) as [$url, $path]) {
                        // Alamat luar (ui-avatars) bukan urusan kita.
                        if (blank($url) || ! str_contains($url, '/api/berkas/')) {
                            continue;
                        }

                        $adaYangDiperiksa = true;
                        $diperiksa++;

                        $status = $this->minta($url);

                        if ($status === 200) {
                            $tampil++;

                            continue;
                        }

                        // 404 punya dua sebab yang sangat berbeda, dan
                        // menggabungkannya membuat laporan ini tidak bisa
                        // dipakai mengambil keputusan: berkas yang memang
                        // tidak ada di mesin ini itu soal data, sedangkan
                        // route atau penjaga yang meleset itu soal kode.
                        // Yang menahan pemindahan berkas cuma yang kedua.
                        if ($status === 404 && ! Berkas::ada($path)) {
                            $hilang++;

                            continue;
                        }

                        $gagal++;
                        $this->catat($status, $url);
                    }

                    if (! $adaYangDiperiksa) {
                        $kosong++;
                    }

                    $bar->advance();
                }
        };

        if ($jumlah === null) {
            // Seluruh tabel: ditumpuk per 200 supaya 3.598 baris kunjungan
            // tidak dimuat sekaligus ke memori. chunkById memaksa urutannya
            // menaik dan mengabaikan orderBy apa pun di atasnya, jadi tidak
            // ada gunanya meminta urutan lain di sini.
            $q->chunkById(200, $periksaSatuTumpuk, 'id', 'id');
        } else {
            // Contoh secukupnya, dan yang terbaru dulu: kalau ada yang
            // meleset, biasanya di berkas baru — dan itu juga yang paling
            // sering dibuka orang. Tidak lewat chunkById, karena urutan menaik
            // yang dipaksakannya justru memberi contoh yang paling tua.
            $periksaSatuTumpuk($q->latest('id')->limit($jumlah)->get());
        }

        $bar->finish();
        $this->newLine();

        return compact('diperiksa', 'tampil', 'gagal', 'kosong', 'hilang');
    }

    /**
     * Diminta lewat HTTP sungguhan, bukan lewat kernel di proses ini.
     *
     * Dua alasan. Pertama, kernel yang dipanggil ratusan kali dalam satu
     * proses lama-lama menjawab 500 untuk alamat yang sebenarnya sehat — itu
     * batas cara memeriksanya, bukan cacat aplikasinya, dan kalau dibiarkan
     * laporan ini malah menakut-nakuti tanpa sebab. Kedua, lewat HTTP yang
     * ikut teruji bukan cuma Laravel, tapi juga Nginx dan PHP-FPM di
     * depannya — persis jalan yang dilalui `<img src>` di dashboard.
     *
     * Syaratnya aplikasinya harus bisa dihubungi di APP_URL. Di lokal berarti
     * `php artisan serve` jalan; di server sudah dengan sendirinya.
     */
    private function minta(string $url): int
    {
        try {
            $status = $this->ambilStatus($url);

            // Alamat pertama yang terbukti sehat disimpan sebagai pembanding.
            if ($status === 200 && $this->penanda === null) {
                $this->penanda = $url;
            }

            if ($status >= 500) {
                $this->pastikanServernyaMasihSehat();
            }

            return $status;
        } catch (ConnectionException $e) {
            $this->newLine();
            $this->error('Aplikasinya tidak bisa dihubungi di ' . config('app.url'));
            $this->line('  Di lokal: jalankan `php artisan serve` dulu, lalu ulangi.');

            exit(self::FAILURE);
        }
    }

    /**
     * Bedakan "satu foto bermasalah" dari "servernya yang sudah tidak sehat".
     *
     * `php artisan serve` cuma satu proses, dan sesudah beberapa ratus
     * permintaan beruntun ia mulai menjawab 500 untuk alamat apa pun — alamat
     * yang sama langsung benar lagi begitu servernya di-restart. Kalau itu
     * dibiarkan, laporan ini melaporkan ribuan kegagalan palsu dan justru
     * menahan pemindahan berkas tanpa sebab.
     *
     * Jadi begitu ada 5xx, alamat yang tadi terbukti sehat diminta ulang.
     * Kalau yang itu pun ikut gagal, yang rusak servernya, bukan fotonya —
     * dan lebih baik berhenti daripada meneruskan laporan yang menyesatkan.
     * Di server dengan PHP-FPM gejala ini tidak ada.
     */
    private function pastikanServernyaMasihSehat(): void
    {
        if ($this->penanda === null) {
            return;
        }

        usleep(200_000);

        if ($this->ambilStatus($this->penanda) === 200) {
            return;
        }

        $this->newLine(2);
        $this->error('Servernya berhenti sehat di tengah pemeriksaan.');
        $this->line('  Alamat yang tadi menjawab 200 sekarang ikut gagal, jadi yang');
        $this->line('  bermasalah servernya, bukan fotonya. Laporan sejauh ini tidak bisa dipakai.');
        $this->newLine();
        $this->line('  Ini batas <fg=yellow>php artisan serve</> yang cuma satu proses.');
        $this->line('  Restart servernya lalu ulangi dengan --jumlah lebih kecil,');
        $this->line('  atau jalankan langsung di server yang pakai PHP-FPM.');

        exit(self::FAILURE);
    }

    private function ambilStatus(string $url): int
    {
        return Http::withoutVerifying()->timeout(30)->get($url)->status();
    }

    private function catat(int $status, string $url): void
    {
        $kunci = match ($status) {
            401     => '401 ditolak, tanda tangan tidak diterima',
            403     => '403 ditolak, penjaga hak akses menolak',
            404     => '404 berkasnya ada di disk tapi route tidak menemukannya',
            default => "{$status} jawaban tak terduga",
        };

        $this->rekap[$kunci] ??= ['jumlah' => 0, 'contoh' => $url];
        $this->rekap[$kunci]['jumlah']++;

        if ($this->option('rinci')) {
            $this->newLine();
            $this->line("  <fg=red>{$status}</> {$url}");
        }
    }
}
