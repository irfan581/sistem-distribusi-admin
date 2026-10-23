<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Hasil kerja driver di lapangan untuk satu rit, dilihat dari sisi admin.
 *
 * Dipisah dari DaftarMuatResource karena isinya berat — seluruh foto bukti
 * beserta koordinatnya — dan cuma dibutuhkan saat admin sengaja membuka
 * laporan satu rit, bukan setiap kali daftar rit dimuat.
 */
class LaporanDriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tujuan = $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
            'id'                 => $item->id,
            'urutan'             => (int) $item->urutan,
            'lokasi_pengiriman'  => $item->lokasi_pengiriman,
            'alamat_otomatis'    => $item->alamat_otomatis,
            'kode_area'          => $item->kode_area,
            'total_muatan_ton'   => (float) ($item->total_muatan_ton ?? 0),
            'status_bongkar'     => $item->status_bongkar ?? 'Menunggu',
            'tiba_at'            => $this->jam($item->tiba_at),
            'bongkar_selesai_at' => $this->jam($item->bongkar_selesai_at),
            'catatan_driver'     => $item->catatan_driver,
            'tanggapan'          => $item->tanggapanArray(),
            'fotos'              => $item->relationLoaded('bongkarFotos')
                ? $item->bongkarFotos->map(fn ($foto) => [
                    'id'            => $foto->id,
                    'jenis'         => $foto->jenis,
                    'foto_url'      => $foto->foto_url,
                    'catatan'       => $foto->catatan,
                    'alamat'        => $foto->alamat,
                    'latitude'      => $foto->latitude,
                    'longitude'     => $foto->longitude,
                    'accuracy'      => $foto->accuracy,
                    // Ditandai Android saat lokasinya berasal dari aplikasi
                    // pemalsu posisi. Dikirim apa adanya, admin yang menilai.
                    'is_mock'       => (bool) $foto->is_mock,
                    'diambil_pada'  => $this->jam($foto->diambil_pada),
                    'diterima_pada' => $this->jam($foto->created_at),
                    // "gagal" berarti berkasnya hilang di server: barisnya ada,
                    // gambarnya tidak. Admin perlu tahu sebelum menganggap
                    // tujuan ini sudah berbukti.
                    'status_proses' => $foto->status_proses,
                ])->values()
                : [],
        ])->values());

        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id'                => $this->id,
            'nomor_rit'         => (int) ($this->nomor_rit ?? 1),
            'tanggal_muatan'    => $this->tanggal_muatan,
            'status_perjalanan' => $this->status_perjalanan ?? 'Menunggu',
            'berangkat_at'      => $this->jam($this->berangkat_at),
            'selesai_at'        => $this->jam($this->selesai_at),

            'driver'            => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id'          => $this->driver->id,
                'nama_driver' => $this->driver->nama_driver,
                'no_telepon'  => $this->driver->no_telepon,
            ] : null),

            'ringkasan'         => [
                'total'      => $items->count(),
                'selesai'    => $items->where('status_bongkar', 'Selesai')->count(),
                // "Gagal" ikut dihitung di sini demi baris lama yang dibuat
                // sebelum status Bermasalah ada.
                'bermasalah' => $items->whereIn('status_bongkar', ['Bermasalah', 'Gagal'])->count(),
                'belum'      => $items->whereIn('status_bongkar', ['Menunggu', 'Tiba'])->count(),
                'belum_ditanggapi' => $items->filter(fn ($i) => $i->perlu_tanggapan && $i->tanggapan_status === null
                    && in_array($i->status_bongkar, \App\Models\DaftarMuatItem::STATUS_TUNTAS, true))->count(),
                'foto'       => $items->sum(fn ($i) => $i->relationLoaded('bongkarFotos') ? $i->bongkarFotos->count() : 0),
            ],

            'tujuan'            => $tujuan,
        ];
    }

    private function jam($waktu): ?string
    {
        return $waktu?->timezone('Asia/Jakarta')->format('d/m/Y H:i');
    }
}
