<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DaftarMuatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
            'id'                => $item->id,
            'urutan'            => $item->urutan,
            'lokasi_pengiriman' => $item->lokasi_pengiriman,
            'toko_id'           => $item->toko_id,
            'alamat_otomatis'   => $item->alamat_otomatis,
            'alamat_lat'        => $item->alamat_lat === null ? null : (float) $item->alamat_lat,
            'alamat_lng'        => $item->alamat_lng === null ? null : (float) $item->alamat_lng,
            'jenis_muatan'      => $item->jenis_muatan ?? 'non_subsidi',
            'kode_area'         => $item->kode_area,
            'catatan_admin'     => $item->catatan_admin,
            'total_muatan_ton'  => $item->total_muatan_ton,
            'status_bongkar'    => $item->status_bongkar ?? 'Menunggu',
            'catatan_driver'    => $item->catatan_driver,
        ]));

        // Total muatan sekarang di level rencana (satu total per truk), bukan per toko.
        $totalTon   = (float) ($this->total_muatan_ton ?? 0);
        $lokasiList = $this->relationLoaded('items')
            ? $this->items->map(fn($i) => $i->kode_area ? "{$i->lokasi_pengiriman} ({$i->kode_area})" : $i->lokasi_pengiriman)
            : collect();

        return [
            'id'               => $this->id,
            'tanggal_muatan'   => $this->tanggal_muatan,
            'nomor_rit'        => (int) ($this->nomor_rit ?? 1),
            'jenis_muatan'     => $this->jenis_muatan ?? 'non_subsidi',
            'total_muatan_ton' => $totalTon,
            'status_perjalanan'    => $this->status_perjalanan ?? 'Menunggu',
            'berangkat_at'         => $this->berangkat_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'selesai_at'           => $this->selesai_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'jumlah_tujuan'        => $lokasiList->count(),

            // Progres driver, dipakai kolom ringkas di tabel admin supaya
            // kelihatan rit mana yang sudah dikerjakan tanpa membuka laporan.
            'tujuan_tuntas'        => $this->relationLoaded('items')
                ? $this->items->whereIn('status_bongkar', ['Selesai', 'Bermasalah', 'Gagal'])->count()
                : 0,
            'tujuan_bermasalah'    => $this->relationLoaded('items')
                ? $this->items->whereIn('status_bongkar', ['Bermasalah', 'Gagal'])->count()
                : 0,
            // Tujuan yang sudah dilaporkan driver tapi belum ditanggapi admin.
            'tujuan_belum_ditanggapi' => $this->relationLoaded('items')
                ? $this->items->filter(fn ($i) => $i->perlu_tanggapan && $i->tanggapan_status === null
                    && in_array($i->status_bongkar, \App\Models\DaftarMuatItem::STATUS_TUNTAS, true))->count()
                : 0,
            'lokasi_pengiriman'    => $lokasiList->implode(' → '),

            // ── Kategori kiriman ──────────────────────────────
            // Dua dari tiga tidak butuh data baru sama sekali.
            //
            // Ngecer: satu rit menyinggahi lebih dari satu toko. Di data
            // produksi 21% rit hanya satu toko, jadi ini pembeda nyata.
            'ngecer'               => $this->relationLoaded('items') && $this->items->count() > 1,

            'items'                => $items,
            'kendaraan'            => $this->whenLoaded('kendaraan', fn() => $this->kendaraan ? [
                'id'                   => $this->kendaraan->id,
                'nomor_polisi'         => $this->kendaraan->nomor_polisi,
                'jenis_kendaraan'      => $this->kendaraan->jenis_kendaraan,
                'kapasitas_muatan_ton' => $this->kendaraan->kapasitas_muatan_ton,
            ] : null),
            'driver'               => $this->whenLoaded('driver', fn() => $this->driver ? [
                'id'          => $this->driver->id,
                'nama_driver' => $this->driver->nama_driver,
                'no_telepon'  => $this->driver->no_telepon,
            ] : null),
            'logs'                 => $this->whenLoaded('logs', fn() => $this->logs->map(fn($log) => [
                'id'         => $log->id,
                'status'     => $log->status,
                'keterangan' => $log->keterangan,
                'user'       => $log->namaPelaku(),
                'aktor'      => $log->aktor ?? 'admin',
                'waktu'      => $log->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            ])),
            'created_at'           => $this->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
        ];
    }
}
