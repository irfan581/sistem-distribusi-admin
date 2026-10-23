<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DaftarMuat extends Model
{
    use HasFactory;

    protected $fillable = [
        'kendaraan_id',
        'driver_id',
        'tanggal_muatan',
        'nomor_rit',
        'jenis_muatan',
        'total_muatan_ton',
        'checker_id',
        'status_perjalanan',
        'dimuat_at',
        'berangkat_at',
        'selesai_at',
    ];

    // tanggal_muatan sengaja tidak di-cast: admin sudah memakai bentuk string
    // mentahnya, dan mengubahnya jadi Carbon akan menggeser tampilan tanggal.
    protected $casts = [
        'dimuat_at'      => 'datetime',
        'berangkat_at'   => 'datetime',
        'selesai_at'     => 'datetime',
    ];

    /**
     * Urutan maju status rit. Driver hanya boleh bergerak ke kanan.
     */
    public const ALUR_STATUS = ['Menunggu', 'Dimuat', 'Berangkat', 'Selesai'];

    /**
     * Model yang baru dibuat belum memuat nilai default dari database, jadi
     * atribut kosong harus dibaca sebagai "Menunggu" — bukan sebagai rit yang
     * sudah terlanjur jalan.
     */
    public function statusPerjalanan(): string
    {
        return $this->status_perjalanan ?: 'Menunggu';
    }

    public function sudahJalan(): bool
    {
        return $this->statusPerjalanan() !== 'Menunggu';
    }

    public function scopeBolehDipegang($query, User $user)
    {
        return $query->whereIn('jenis_muatan', $user->jenisMuatanDiizinkan());
    }

    /**
     * Menyimpulkan status rit dari pekerjaan yang sudah dilaporkan driver.
     *
     * Driver tidak lagi menekan "mulai muat", "berangkat", dan "selesaikan rit"
     * satu per satu — tiga tap yang tidak mengubah apa pun di dunia nyata dan
     * cuma sering terlupa, sehingga rit tampak menggantung padahal barangnya
     * sudah turun semua. Sekarang statusnya diturunkan dari bukti: begitu ada
     * foto atau laporan pertama masuk, rit dianggap jalan; begitu semua tujuan
     * tuntas, rit menutup dirinya sendiri.
     *
     * Statusnya sengaja tetap disimpan, bukan dibuang, karena DaftarMuatService
     * memakai sudahJalan() untuk melarang admin mengedit daftar tujuan yang
     * sedang dikerjakan — tanpa itu bukti bongkar bisa berpindah tujuan.
     *
     * @return string|null Status baru bila berubah, null bila tidak.
     */
    public function selaraskanStatus(): ?string
    {
        // Rit yang dibatalkan admin tidak boleh dihidupkan lagi oleh laporan
        // yang terlanjur terkirim dari antrian offline driver.
        if ($this->statusPerjalanan() === 'Batal') {
            return null;
        }

        $sebelum = $this->statusPerjalanan();

        $total  = $this->items()->count();
        $tuntas = $this->items()->whereIn('status_bongkar', DaftarMuatItem::STATUS_TUNTAS)->count();

        if ($total > 0 && $tuntas >= $total) {
            $this->status_perjalanan = 'Selesai';
            $this->selesai_at ??= now();
        } elseif (in_array($sebelum, ['Menunggu', 'Dimuat'], true) && $this->adaBukti($tuntas)) {
            $this->status_perjalanan = 'Berangkat';
            $this->berangkat_at ??= now();
        }

        if ($this->statusPerjalanan() === $sebelum) {
            return null;
        }

        $this->save();

        return $this->status_perjalanan;
    }

    /**
     * Foto ikut dihitung sebagai tanda rit sudah jalan, bukan cuma tujuan yang
     * tuntas. Driver mengunggah bukti lebih dulu lalu menutup tujuannya; kalau
     * hanya menunggu tujuan tuntas, ada jeda saat admin masih boleh mengedit
     * daftar tujuan padahal fotonya sudah masuk.
     */
    private function adaBukti(int $tuntas): bool
    {
        return $tuntas > 0 || $this->bongkarFotos()->exists();
    }

    public function kendaraan()
    {
        return $this->belongsTo(kendaraans::class, 'kendaraan_id');
    }

    public function driver()
    {
        return $this->belongsTo(drivers::class, 'driver_id');
    }

    public function items()
    {
        return $this->hasMany(DaftarMuatItem::class)->orderBy('urutan');
    }

    public function logs()
    {
        return $this->hasMany(DaftarMuatLog::class)->orderBy('created_at');
    }

    public function checker()
    {
        return $this->belongsTo(Checker::class);
    }

    public function bongkarFotos()
    {
        return $this->hasMany(BongkarFoto::class)->orderBy('id');
    }

    public function getLokasiPengirimanAttribute(): string
    {
        return $this->items()->pluck('lokasi_pengiriman')->implode(', ');
    }
}
