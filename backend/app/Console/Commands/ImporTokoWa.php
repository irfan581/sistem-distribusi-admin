<?php

namespace App\Console\Commands;

use App\Models\KunjunganToko;
use App\Models\Sales;
use App\Models\Toko;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Memasukkan hasil periksa toko dari grup WA "Maps Tani Makmur Madiun":
 * membuat toko baru dan memperbarui titik toko yang sudah ada.
 *
 * Titik dari WA ditandai 'manual' supaya tidak ditimpa GPS kunjungan berikutnya
 * (lihat Toko::isiLokasiDari). Bawaannya hanya simulasi; --simpan menulis ke
 * database dan mencadangkan nilai lama ke storage/app/impor-wa.
 */
class ImporTokoWa extends Command
{
    protected $signature = 'toko:impor-wa
        {berkas : JSON hasil periksa (daftar baris dengan aksi "buat" atau "titik")}
        {--simpan : Benar-benar menulis ke database}
        {--kembalikan= : Pulihkan dari berkas cadangan hasil --simpan sebelumnya}';

    protected $description = 'Membuat toko baru dan memperbarui titik toko dari hasil periksa grup WA';

    public function handle(): int
    {
        if ($cadangan = $this->option('kembalikan')) {
            return $this->kembalikan($cadangan);
        }

        $baris = json_decode((string) @file_get_contents($this->argument('berkas')), true);
        if (! is_array($baris)) {
            $this->error('Berkas tidak bisa dibaca sebagai JSON.');
            return self::FAILURE;
        }

        $simpan = (bool) $this->option('simpan');
        $hasil = [];
        $cadangan = ['dibuat' => [], 'diubah' => []];

        DB::beginTransaction();
        try {
            foreach ($baris as $b) {
                [$status, $ket] = match ($b['aksi'] ?? null) {
                    'buat'  => $this->buat($b, $cadangan),
                    'titik' => $this->titik($b, $cadangan),
                    default => ['lewati', 'aksi tidak dikenal'],
                };
                $hasil[] = [$b['kode'] ?? '-', $b['aksi'] ?? '-', $status, $ket];
            }

            if ($simpan) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['Kode', 'Aksi', 'Hasil', 'Keterangan'], $hasil);
        $ringkas = collect($hasil)->countBy(fn ($h) => $h[2]);
        $this->info(($simpan ? '' : '[SIMULASI, tidak disimpan] ') . $ringkas->map(fn ($n, $k) => "{$k}: {$n}")->join(' · '));

        if ($simpan && ($cadangan['dibuat'] || $cadangan['diubah'])) {
            $jalur = 'impor-wa/cadangan-' . now()->format('Ymd-His') . '.json';
            Storage::disk('local')->put($jalur, json_encode($cadangan, JSON_PRETTY_PRINT));
            $this->info('Cadangan: ' . Storage::disk('local')->path($jalur));
        }

        return self::SUCCESS;
    }

    private function buat(array $b, array &$cadangan): array
    {
        $nama = trim($b['nama_kios'] ?? '');
        $wilayah = trim($b['wilayah'] ?? '');
        if (mb_strlen($nama) < 3 || $wilayah === '') {
            return ['lewati', 'nama atau wilayah kosong'];
        }
        if (! Sales::whereKey($b['sales_id'] ?? 0)->exists()) {
            return ['lewati', 'sales tidak ditemukan'];
        }
        // Indeks unik (nama_kios, wilayah) tidak membedakan huruf besar/kecil.
        $ada = Toko::whereRaw('LOWER(nama_kios) = ? AND LOWER(wilayah) = ?', [mb_strtolower($nama), mb_strtolower($wilayah)])->first();
        if ($ada) {
            return ['lewati', "sudah ada: id {$ada->id}"];
        }

        $adaTitik = isset($b['lat'], $b['lng']);
        $toko = Toko::create([
            'sales_id'             => $b['sales_id'],
            'nama_kios'            => $nama,
            'jenis'                => Toko::JENIS_TOKO,
            'wilayah'              => $wilayah,
            'is_new'               => true,
            'alamat'               => $b['alamat'] ?? null,
            'alamat_manual'        => filled($b['alamat'] ?? null),
            'lat'                  => $adaTitik ? (float) $b['lat'] : null,
            'lng'                  => $adaTitik ? (float) $b['lng'] : null,
            'lokasi_sumber'        => $adaTitik ? 'manual' : null,
            'lokasi_diperbarui_at' => $adaTitik ? now() : null,
        ]);
        $cadangan['dibuat'][] = $toko->id;

        return ['dibuat', "id {$toko->id} · {$nama}"];
    }

    private function titik(array $b, array &$cadangan): array
    {
        $toko = Toko::find($b['toko_id'] ?? 0);
        if (! $toko || $toko->isKantor()) {
            return ['lewati', 'toko tidak ditemukan'];
        }
        if (! isset($b['lat'], $b['lng'])) {
            return ['lewati', 'titik kosong'];
        }

        $lama = $toko->only(['id', 'alamat', 'alamat_manual', 'lat', 'lng', 'lokasi_sumber', 'lokasi_diperbarui_at']);
        $toko->fill([
            'lat'                  => (float) $b['lat'],
            'lng'                  => (float) $b['lng'],
            'lokasi_sumber'        => 'manual',
            'lokasi_diperbarui_at' => now(),
        ]);
        // Alamat yang sudah ada dibiarkan; hanya diisi bila kosong.
        if (blank($toko->alamat) && filled($b['alamat'] ?? null)) {
            $toko->alamat = $b['alamat'];
            $toko->alamat_manual = true;
        }
        if (! $toko->isDirty(['lat', 'lng', 'alamat'])) {
            return ['tetap', "id {$toko->id} sudah sama"];
        }

        $isiAlamat = $toko->isDirty('alamat') ? ' + alamat' : '';
        $toko->save();
        $cadangan['diubah'][] = $lama;

        return ['diubah', "id {$toko->id} · titik{$isiAlamat}"];
    }

    private function kembalikan(string $jalur): int
    {
        $data = json_decode((string) @file_get_contents($jalur), true);
        if (! is_array($data)) {
            $this->error('Berkas cadangan tidak bisa dibaca.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($data) {
            foreach ($data['diubah'] ?? [] as $lama) {
                Toko::whereKey($lama['id'])->update(collect($lama)->except('id')->all());
            }
            foreach ($data['dibuat'] ?? [] as $id) {
                // Toko yang sudah dipakai kunjungan tidak dihapus agar riwayatnya tidak kehilangan toko.
                if (KunjunganToko::where('toko_id', $id)->exists()) {
                    $this->warn("id {$id} tidak dihapus: sudah ada kunjungan");
                    continue;
                }
                Toko::whereKey($id)->delete();
            }
        });

        $this->info('Dipulihkan: ' . count($data['diubah'] ?? []) . ' diubah, ' . count($data['dibuat'] ?? []) . ' dibuat.');

        return self::SUCCESS;
    }
}
