<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Imports\TokoImport;
use App\Models\KunjunganToko;
use App\Models\Sales;
use App\Models\Toko;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class TokoController extends Controller
{
    use ApiResponse;

    // ─── LIST ─────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Toko::with('sales:id,nama,area')
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('nama_kios', 'like', "%{$request->search}%")
                  ->orWhere('wilayah', 'like', "%{$request->search}%");
            }))
            ->when($request->sales_id, fn($q) => $q->where('sales_id', $request->sales_id))
            ->when($request->wilayah,  fn($q) => $q->where('wilayah', $request->wilayah))
            ->when(
                $request->filled('is_new'),
                fn($q) => $q->where('is_new', filter_var($request->is_new, FILTER_VALIDATE_BOOLEAN))
            )
            // punya_lokasi=1 hanya yang bertitik, punya_lokasi=0 hanya yang belum
            ->when(
                $request->filled('punya_lokasi'),
                fn($q) => filter_var($request->punya_lokasi, FILTER_VALIDATE_BOOLEAN)
                    ? $q->whereNotNull('lat')
                    : $q->whereNull('lat')
            )
            // Urut nama kios A-Z, apa pun filternya. Dulu diurutkan per wilayah
            // lebih dulu, sehingga toko tanpa wilayah naik ke atas dan nama
            // tidak berurutan bila tidak difilter per wilayah. `id` menjadi
            // pengurut kedua supaya nama makmur tidak berpindah halaman.
            ->orderBy('nama_kios')
            ->orderBy('id');

        $perPage = (int) ($request->per_page ?? 50);
        $data    = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return $this->success($data);
    }

    // ─── PENCARIAN UNTUK DAFTAR MUAT ──────────────────────────
    // Dipakai form "Buat/Edit Rencana Muat": admin cari toko lalu pilih.
    // Alamat dan koordinat diambil dari Data Toko; toko yang titiknya belum
    // terisi jatuh ke kunjungan sales terakhirnya.

    public function pencarianMuat(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('search', ''));

        $tokos = Toko::pelanggan()
            ->select('id', 'nama_kios', 'wilayah', 'alamat', 'lat', 'lng')
            ->when($search !== '', fn($q) => $q->where('nama_kios', 'like', "%{$search}%"))
            ->orderBy('nama_kios')
            ->limit(20)
            ->get();

        // Satu query untuk semua toko yang belum bertitik, bukan query per baris.
        $kunjunganTerakhir = KunjunganToko::whereIn('toko_id', $tokos->whereNull('lat')->pluck('id'))
            ->whereNotNull('alamat')
            ->orderByDesc('id')
            ->get(['toko_id', 'alamat', 'lat', 'lng'])
            ->unique('toko_id')
            ->keyBy('toko_id');

        $data = $tokos->map(function (Toko $toko) use ($kunjunganTerakhir) {
            $kunjungan = $kunjunganTerakhir->get($toko->id);

            return [
                'id'              => $toko->id,
                'nama_kios'       => $toko->nama_kios,
                'wilayah'         => $toko->wilayah,
                // Alamat ketikan admin bisa ada walau toko belum bertitik.
                'alamat_terakhir' => $toko->alamat ?: $kunjungan?->alamat,
                'lat'             => $toko->lat ?? $kunjungan?->lat,
                'lng'             => $toko->lng ?? $kunjungan?->lng,
            ];
        });

        return $this->success($data);
    }

    // ─── WILAYAH OPTIONS ──────────────────────────────────────

    public function wilayahList(): JsonResponse
    {
        $list = Toko::distinct()->orderBy('wilayah')->pluck('wilayah');
        return $this->success($list);
    }

    // ─── STORE ────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales_id'  => 'required|integer|exists:sales,id',
            'nama_kios' => ['required', 'string', 'min:3', 'max:255',
                Rule::unique('tokos')->where(fn($q) => $q->where('wilayah', $request->wilayah))],
            'wilayah'   => 'required|string|min:3|max:100',
        ], [
            'nama_kios.unique' => 'Nama kios sudah ada di wilayah yang sama.',
        ]);

        $toko = Toko::create($data);

        return $this->success($this->format($toko->load('sales')), 'Toko berhasil ditambahkan', 201);
    }

    // ─── UPDATE ───────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $toko = Toko::findOrFail($id);

        $data = $request->validate([
            'sales_id'  => 'required|integer|exists:sales,id',
            'nama_kios' => ['required', 'string', 'min:3', 'max:255',
                Rule::unique('tokos')->where(fn($q) => $q->where('wilayah', $request->wilayah))->ignore($id)],
            'wilayah'   => 'required|string|min:3|max:100',
            'alamat'    => 'nullable|string|max:255',
            'lat'       => 'nullable|numeric|between:-90,90|required_with:lng',
            'lng'       => 'nullable|numeric|between:-180,180|required_with:lat',
        ], [
            'nama_kios.unique' => 'Nama kios sudah ada di wilayah yang sama.',
        ]);

        $toko->fill($data);

        // Titik yang diubah admin ditandai manual; dikosongkan berarti kembali otomatis
        if ($toko->isDirty(['alamat', 'lat', 'lng'])) {
            $toko->lokasi_sumber = $toko->lat === null ? null : 'manual';
            $toko->lokasi_diperbarui_at = now();
        }

        $toko->save();

        return $this->success($this->format($toko->fresh('sales')), 'Toko berhasil diperbarui');
    }

    // ─── DESTROY ──────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        Toko::findOrFail($id)->delete();
        return $this->success(null, 'Toko berhasil dihapus');
    }

    // ─── IMPORT EXCEL ─────────────────────────────────────────

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ], [
            'file.mimes' => 'File harus berformat Excel (.xlsx atau .xls)',
            'file.max'   => 'Ukuran file maksimal 5 MB',
        ]);

        // Reset flag is_new sebelum import baru
        Toko::where('is_new', true)->update(['is_new' => false]);

        Excel::import(new TokoImport(), $request->file('file'));

        $newCount = Toko::where('is_new', true)->count();

        return $this->success(
            ['imported' => $newCount],
            "Import selesai. {$newCount} toko baru / diperbarui."
        );
    }

    // ─── SALES OPTIONS ────────────────────────────────────────

    public function salesOptions(): JsonResponse
    {
        $list = Sales::where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'area'])
            ->map(fn($s) => ['id' => $s->id, 'label' => "{$s->nama} ({$s->area})"])
            ;
        return $this->success($list);
    }

    // ─── FORMAT ───────────────────────────────────────────────

    private function format(Toko $t): array
    {
        return [
            'id'         => $t->id,
            'sales_id'   => $t->sales_id,
            'sales_nama' => $t->sales?->nama,
            'sales_area' => $t->sales?->area,
            'nama_kios'  => $t->nama_kios,
            'jenis'      => $t->jenis,
            'wilayah'    => $t->wilayah,
            'is_new'     => $t->is_new,
            'created_at' => $t->created_at?->toDateTimeString(),
            'alamat'     => $t->alamat,
            'lat'        => $t->lat,
            'lng'        => $t->lng,
            'lokasi_sumber' => $t->lokasi_sumber,
        ];
    }
}
