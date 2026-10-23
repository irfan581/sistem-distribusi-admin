<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nomor kantor untuk driver
    |--------------------------------------------------------------------------
    |
    | Ditampilkan sebagai tombol telepon di aplikasi driver saat ada tujuan
    | yang bermasalah. Keputusan apa yang dilakukan dengan barangnya — dibawa
    | pulang, dialihkan, atau ditunggu — selalu butuh orang, jadi aplikasinya
    | tidak mencoba mengurus itu; ia cuma mencatat kejadiannya dan
    | mendekatkan gagangnya.
    |
    | Dikosongkan berarti tombol teleponnya tidak muncul sama sekali.
    |
    */

    'nomor_admin' => env('DRIVER_NOMOR_ADMIN'),

    /*
    |--------------------------------------------------------------------------
    | Grup chat penerima laporan bongkar
    |--------------------------------------------------------------------------
    |
    | Id grup tempat setiap laporan tujuan otomatis diteruskan begitu driver
    | menekan kirim — yang selesai maupun yang bermasalah. Ini menggantikan
    | kebiasaan lama mengirim foto bongkar ke grup WhatsApp, supaya buktinya
    | tidak tenggelam di antara obrolan dan tetap terikat ke ritnya.
    |
    | Dikosongkan berarti penerusan dimatikan: laporan tetap tersimpan seperti
    | biasa, cuma tidak ada yang dikirim ke chat. Grupnya dibuat sekali lewat
    | `php artisan chat:grup-laporan`.
    |
    */

    'grup_laporan_id' => env('DRIVER_GRUP_LAPORAN_ID'),

    /*
    |--------------------------------------------------------------------------
    | Jendela rit yang tampil di beranda sopir
    |--------------------------------------------------------------------------
    |
    | Berapa hari ke belakang rit yang belum tuntas masih ikut ditampilkan.
    |
    | Beranda tidak lagi menyaring "tanggal hari ini" persis. Kantor menginput
    | rit sore hari untuk besok, jadi dengan saringan tanggal persis sopir baru
    | melihat tugasnya besok pagi — padahal ia sudah ditugaskan sejak semalam.
    | Sekarang penentunya nama sopir: begitu admin mengisinya, ritnya muncul.
    |
    | Jendela ke belakang ini untuk rit yang lupa ditutup sopir semalam, supaya
    | ia masih bisa membereskannya sendiri tanpa menelepon kantor. Dibatasi
    | karena tanpa batas, 6.920 catatan pembukuan lama yang tidak pernah
    | disentuh sopir akan ikut membanjiri berandanya — sebagian sopir sampai
    | 153 rit sekaligus.
    |
    | Nol berarti hanya hari ini dan ke depan.
    |
    */

    'jendela_rit_hari' => (int) env('DRIVER_JENDELA_RIT_HARI', 2),

];
