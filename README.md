# Sistem Distribusi: Backend API & Panel Admin

Sistem internal sebuah distributor pupuk: backend API yang melayani dua
aplikasi Android (sales dan sopir) dan panel admin web untuk kantor.

> **Catatan salinan portofolio.** Repo ini salinan dari proyek nyata yang saya
> kerjakan untuk sebuah distributor pupuk di Jawa Timur. Nama usaha, domain,
> alamat, nomor telepon, nama karyawan, foto, logo, kunci Firebase, dan
> konfigurasi server sudah diganti atau dihapus. "Tani Makmur" adalah nama
> samaran. Riwayat commit tidak disertakan.

Dikerjakan sendiri oleh **Irfan Fajar Hidayat**, dari backend, panel admin, dan
aplikasi Android sampai deploy ke VPS.

## Isi repo

| Folder | Isi |
|---|---|
| `backend/` | API Laravel 12 (PHP 8.3+), MySQL, Sanctum, Reverb untuk chat realtime, Firebase Cloud Messaging, antrean job, DomPDF |
| `frontend/` | Panel admin Vue 3 + PrimeVue 4 + Pinia, dibangun dengan Vite |
| `desktop/` | Pembungkus Electron yang membuka panel admin sebagai aplikasi desktop |
| `scripts/cek-chunk.mjs` | Memuat setiap chunk hasil build di Chrome headless untuk menangkap error urutan chunk yang lolos dari `npm run build` |

## Modul utama

- **SDM sales:** data karyawan, presensi dengan lokasi dan foto, izin, target kunjungan
- **Kunjungan toko:** riwayat kunjungan sales dengan foto bukti, deteksi lokasi
  mencurigakan (fake GPS, koordinat 0,0, di luar Indonesia), dan tanggapan admin
  diterima/ditolak. Foto dengan lokasi aman diterima otomatis
- **Data toko & peta:** master toko dengan titik koordinat, peta rute sales per hari
- **Pengiriman:** daftar muat, armada dan kendaraan, sopir, pengawasan muat,
  urutan tujuan otomatis (nearest-neighbor dari gudang), laporan bongkar per
  tujuan dari aplikasi sopir, statistik pengiriman
- **Komunikasi:** chat dan grup realtime (Reverb), broadcast, notifikasi push
- **Hak akses:** peran dan izin per admin, termasuk pemisahan muatan subsidi/non-subsidi

## Aplikasi lain yang terhubung

| Repo | Isi |
|---|---|
| [`aplikasi-sales`](https://github.com/irfan581/aplikasi-sales) | Flutter: presensi, kunjungan toko, izin, chat |
| [`aplikasi-driver`](https://github.com/irfan581/aplikasi-driver) | Flutter: kiriman, laporan bongkar dengan foto, riwayat |
| [`website-distributor`](https://github.com/irfan581/website-distributor) | Website perusahaan (Nuxt 4) |

## Menjalankan di komputer sendiri

Backend:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Chat realtime dan notifikasi juga butuh `php artisan reverb:start` dan
`php artisan queue:work` di terminal terpisah. Akun contoh dari seeder memakai
password `password` (lihat `backend/database/seeders/DatabaseSeeder.php`).

Frontend:

```bash
cd frontend
npm install
cp .env.example .env.local   # isi VITE_API_BASE_URL dan pengaturan Reverb
npm run dev
```

## Uji

```bash
cd backend
php artisan test
```

Uji memakai database MySQL `tmk_testing` (lihat `backend/phpunit.xml`).
GitHub Actions menjalankan uji backend dan build frontend di setiap push.
