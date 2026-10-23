<?php

namespace App\Services\Driver;

use App\Exceptions\TransisiStatusException;
use App\Jobs\ProsesFotoBongkar;
use App\Models\BongkarFoto;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\DaftarMuatLog;
use App\Models\drivers;
use App\Support\Berkas;
use App\Traits\UploadGambar;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BongkarFotoService
{
    use UploadGambar;

    /**
     * Batas foto per tujuan. Cukup untuk surat jalan, tumpukan barang, dan
     * beberapa sudut kendala; sekaligus menjaga kuota disk VPS dari driver
     * yang menahan tombol jepret.
     */
    private const BATAS_PER_TUJUAN = 10;

    /**
     * Simpan satu foto bukti untuk sebuah tujuan.
     *
     * Idempoten lewat client_uuid: antrian offline di HP boleh mengirim ulang
     * foto yang sama berkali-kali tanpa menghasilkan duplikat di server.
     */
    public function simpan(
        drivers $driver,
        int $daftarMuatId,
        int $itemId,
        UploadedFile $file,
        array $data,
    ): BongkarFoto {
        $muat = DaftarMuat::whereKey($daftarMuatId)->firstOrFail();

        $this->pastikanMilikDriver($muat, $driver);

        $item = DaftarMuatItem::whereKey($itemId)
            ->where('daftar_muat_id', $muat->id)
            ->firstOrFail();

        // Pengulangan kiriman dijawab dengan foto yang sudah ada. Diperiksa
        // sebelum aturan lain supaya retry tetap berhasil walaupun sementara
        // itu tujuannya sudah ditandai selesai.
        if ($sudahAda = $this->cariKiriman($driver, $item, $data['client_uuid'])) {
            return $sudahAda;
        }

        $this->pastikanBolehMenambah($muat, $item);

        // Disimpan ke temp dulu, dipindahkan job. Upload dari lapangan sering
        // putus, jadi respons harus kembali secepat mungkin ke HP.
        $path = $this->uploadBukti($file, 'temp-bongkar');

        $foto = DB::transaction(function () use ($muat, $item, $driver, $path, $data) {
            $foto = BongkarFoto::create([
                'daftar_muat_id'      => $muat->id,
                'daftar_muat_item_id' => $item->id,
                'driver_id'           => $driver->id,
                'urutan_snapshot'     => $item->urutan,
                'lokasi_snapshot'     => $item->lokasi_pengiriman,
                'jenis'               => $data['jenis'] ?? 'bongkar',
                'foto'                => $path,
                'latitude'            => $data['latitude'] ?? null,
                'longitude'           => $data['longitude'] ?? null,
                'accuracy'            => $data['accuracy'] ?? null,
                'is_mock'             => (bool) ($data['is_mock'] ?? false),
                'diambil_pada'        => $data['diambil_pada'] ?? null,
                'catatan'             => $data['catatan'] ?? null,
                'client_uuid'         => $data['client_uuid'],
                'status_proses'       => 'temp',
            ]);

            // Foto pertama menandai rit sudah dikerjakan. Bukan sekadar
            // kosmetik: DaftarMuatService memakai sudahJalan() untuk melarang
            // admin mengedit daftar tujuan, dan tanpa penanda ini ada jeda saat
            // bukti sudah masuk tapi tujuannya masih boleh diganti admin.
            // Dipanggil setelah foto tersimpan, karena penandanya justru
            // keberadaan foto itu sendiri.
            if ($statusBaru = $muat->selaraskanStatus()) {
                // Dicatat di sini juga, bukan cuma saat laporan masuk. Kalau
                // tidak, rit yang mulai jalan lewat foto pertama tidak punya
                // jejak "Berangkat" sama sekali di riwayat admin.
                DaftarMuatLog::create([
                    'daftar_muat_id' => $muat->id,
                    'status'         => $statusBaru,
                    'keterangan'     => 'Rit mulai dikerjakan, bukti pertama masuk',
                    'user_id'        => null,
                    'driver_id'      => $driver->id,
                    'aktor'          => 'driver',
                    'latitude'       => $data['latitude'] ?? null,
                    'longitude'      => $data['longitude'] ?? null,
                    'created_at'     => now(),
                ]);
            }

            return $foto;
        });

        ProsesFotoBongkar::dispatch($foto->id);

        // Kalau antriannya sync, job barusan sudah memindahkan berkas dan
        // menulis alamat. Tanpa refresh, respons memberi HP path temp yang
        // sudah tidak ada, dan foto_url-nya mati.
        return $foto->refresh();
    }

    /**
     * @return Collection<int, BongkarFoto>
     */
    public function daftar(drivers $driver, int $daftarMuatId, int $itemId): Collection
    {
        $muat = DaftarMuat::whereKey($daftarMuatId)->firstOrFail();

        $this->pastikanMilikDriver($muat, $driver);

        return BongkarFoto::where('daftar_muat_id', $muat->id)
            ->where('daftar_muat_item_id', $itemId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Hapus foto salah jepret. Hanya selama tujuannya belum tuntas — begitu
     * ditandai selesai, foto menjadi bukti dan bukan lagi milik driver.
     */
    public function hapus(drivers $driver, int $daftarMuatId, int $itemId, int $fotoId): void
    {
        $muat = DaftarMuat::whereKey($daftarMuatId)->firstOrFail();

        $this->pastikanMilikDriver($muat, $driver);

        $foto = BongkarFoto::whereKey($fotoId)
            ->where('daftar_muat_id', $muat->id)
            ->where('daftar_muat_item_id', $itemId)
            ->firstOrFail();

        if ($foto->driver_id !== $driver->id) {
            throw new TransisiStatusException(
                'Foto ini diunggah driver lain.',
                'BUKAN_FOTO_ANDA'
            );
        }

        $item = DaftarMuatItem::whereKey($itemId)->firstOrFail();

        if ($item->tuntas()) {
            throw new TransisiStatusException(
                "Tujuan ini sudah ditandai \"{$item->status_bongkar}\". Foto tidak bisa dihapus lagi.",
                'TUJUAN_SUDAH_TUNTAS'
            );
        }

        $path = $foto->foto;

        $foto->delete();

        Berkas::hapus($path);
    }

    private function cariKiriman(drivers $driver, DaftarMuatItem $item, string $clientUuid): ?BongkarFoto
    {
        $foto = BongkarFoto::where('client_uuid', $clientUuid)->first();

        if (!$foto) {
            return null;
        }

        // client_uuid unik satu tabel. Kalau tabrakan datang dari driver atau
        // tujuan lain, itu bug generator di HP — jangan diam-diam dianggap
        // kiriman ulang, karena hasilnya foto hilang tanpa jejak.
        if ($foto->driver_id !== $driver->id || $foto->daftar_muat_item_id !== $item->id) {
            throw new TransisiStatusException(
                'Kode foto ini sudah dipakai untuk foto lain. Ulangi pengambilan foto.',
                'CLIENT_UUID_BENTROK'
            );
        }

        return $foto;
    }

    private function pastikanBolehMenambah(DaftarMuat $muat, DaftarMuatItem $item): void
    {
        if ($muat->statusPerjalanan() === 'Batal') {
            throw new TransisiStatusException(
                'Rit ini dibatalkan admin. Hubungi kantor.',
                'RIT_DIBATALKAN'
            );
        }

        if ($item->tuntas()) {
            throw new TransisiStatusException(
                "Tujuan ini sudah ditandai \"{$item->status_bongkar}\". Foto tidak bisa ditambah lagi.",
                'TUJUAN_SUDAH_TUNTAS'
            );
        }

        $jumlah = BongkarFoto::where('daftar_muat_item_id', $item->id)->count();

        if ($jumlah >= self::BATAS_PER_TUJUAN) {
            throw new TransisiStatusException(
                'Foto untuk tujuan ini sudah ' . self::BATAS_PER_TUJUAN . '. Hapus dulu yang tidak terpakai.',
                'BATAS_FOTO'
            );
        }
    }

    private function pastikanMilikDriver(DaftarMuat $muat, drivers $driver): void
    {
        if ($muat->driver_id !== $driver->id) {
            throw new TransisiStatusException(
                'Rit ini bukan tugas Anda.',
                'BUKAN_TUGAS_ANDA'
            );
        }
    }
}
