<?php

namespace App\Models;

use App\Models\Concerns\PunyaTanggapan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class KunjunganToko extends Model
{
    use HasFactory, LogsActivity, PunyaTanggapan;

    protected $table = 'kunjungan_tokos';

    protected $fillable = [
        'sales_id',
        'toko_id',
        'nama_toko',
        'location',
        'lat',
        'lng',
        'alamat',
        'accuracy',
        'foto_kunjungan',
        'keterangan',
        'is_suspicious',
        'suspicious_reason',
        'archived_at',
        'checkout_at',
        'created_at',
    ];

    protected $hidden = [];

    protected $casts = [
        'is_suspicious' => 'boolean',
        'lat'           => 'float',
        'lng'           => 'float',
        'accuracy'      => 'float',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'archived_at'   => 'datetime',
        'checkout_at'   => 'datetime',
    ];

    protected $appends = ['foto_kunjungan_url'];

    public function getActivitylogOptions(): LogOptions
    {
        return (new LogOptions())
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ===========================================================
    // ACCESSOR
    // ===========================================================

    public function getFotoKunjunganUrlAttribute(): ?string
    {
        if (empty($this->foto_kunjungan)) return null;
        if (str_starts_with($this->foto_kunjungan, 'http')) return $this->foto_kunjungan;

        // Lewat route terautentikasi, bukan URL /storage/ yang bisa dibuka
        // siapa saja. Sales hanya boleh melihat kunjungannya sendiri.
        return URL::temporarySignedRoute('berkas.kunjungan', now()->addHours(12), ['id' => $this->id]);
    }

    // ===========================================================
    // RELATIONSHIPS
    // ===========================================================

    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sales::class, 'sales_id');
    }

    // ===========================================================
    // SCOPES
    // ===========================================================

    /**
     * Hanya tampilkan kunjungan yang belum di-archive (< 24 jam)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Tampilkan kunjungan yang sudah di-archive (> 24 jam)
     */
    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Kunjungan hari ini (belum di-archive)
     */
    public function scopeHariIni($query)
    {
        return $query->whereDate('created_at', now('Asia/Jakarta')->toDateString())
                     ->whereNull('archived_at');
    }

    /**
     * Filter by sales
     */
    public function scopeBySales($query, $salesId)
    {
        return $query->where('sales_id', $salesId);
    }

    // ===========================================================
    // METHODS
    // ===========================================================

    /**
     * Archive kunjungan ini (soft delete visual)
     */
    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    /**
     * Un-archive kunjungan (restore ke active)
     */
    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
    }

    /**
     * Check apakah kunjungan sudah di-archive
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class, 'toko_id');
    }

    /**
     * Target kunjungan harian dianggap terpenuhi kalau sudah cukup jumlahnya,
     * ATAU sales sudah sekali memilih entri kantor resmi hari itu. Nama toko yang
     * diketik manual dan memuat kata "kantor" dihitung sebagai kunjungan biasa.
     */
    public static function targetTerpenuhi(int $salesId, ?string $tanggal = null): bool
    {
        $tanggal = $tanggal ?? now('Asia/Jakarta')->toDateString();

        $adaKunjunganKantor = static::where('sales_id', $salesId)
            ->whereDate('created_at', $tanggal)
            ->whereHas('toko', fn ($q) => $q->where('jenis', Toko::JENIS_KANTOR))
            ->exists();

        if ($adaKunjunganKantor) {
            return true;
        }

        $jumlah = static::where('sales_id', $salesId)
            ->whereDate('created_at', $tanggal)
            ->count();

        return $jumlah >= SalesVisitTarget::getActiveTargetForSales($salesId);
    }
}
