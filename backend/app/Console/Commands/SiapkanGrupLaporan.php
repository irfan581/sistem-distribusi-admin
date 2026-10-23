<?php

namespace App\Console\Commands;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\drivers;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Menyiapkan grup chat penerima laporan bongkar, sekaligus menyamakan
 * anggotanya dengan keadaan sekarang.
 *
 * Aman diulang: dijalankan lagi setiap ada sopir atau sales baru, dan yang
 * sudah jadi anggota tidak diduakan.
 */
class SiapkanGrupLaporan extends Command
{
    protected $signature = 'chat:grup-laporan
                            {--nama=Laporan Kiriman : Nama grup bila belum ada}
                            {--tanpa-sopir : Jangan masukkan sopir sebagai anggota}';

    protected $description = 'Membuat grup chat laporan bongkar dan menyamakan anggotanya';

    public function handle(): int
    {
        $grupId = config('driver.grup_laporan_id');
        $grup   = $grupId ? ChatGroup::find($grupId) : null;

        if (! $grup) {
            $grup = ChatGroup::create([
                'nama'         => $this->option('nama'),
                'creator_type' => User::class,
                // Pembuatnya dicatat sebagai admin pertama, bukan orang yang
                // kebetulan menjalankan perintah ini dari terminal.
                'creator_id'   => User::orderBy('id')->value('id'),
                'is_broadcast' => false,
            ]);

            $this->newLine();
            $this->info("Grup dibuat: #{$grup->id} \"{$grup->nama}\"");
            $this->warn("Tambahkan ke .env lalu jalankan ulang perintah ini:");
            $this->line("    DRIVER_GRUP_LAPORAN_ID={$grup->id}");
            $this->newLine();
        } else {
            $this->info("Grup dipakai: #{$grup->id} \"{$grup->nama}\"");
        }

        $anggota = [
            // Kantor jadi admin grup — merekalah yang menindaklanjuti laporan
            // bermasalah, jadi mereka yang boleh merapikan isinya.
            [User::class,    User::pluck('id'),                                    true],
            [Sales::class,   Sales::where('is_active', 1)->pluck('id'),            false],
        ];

        if (! $this->option('tanpa-sopir')) {
            // Hanya sopir yang akunnya sudah aktif: yang belum tidak punya cara
            // membuka aplikasi, jadi memasukkannya cuma membuat daftar anggota
            // ramai tanpa ada orangnya.
            $anggota[] = [drivers::class, drivers::where('mobile_aktif', 1)->pluck('id'), false];
        }

        $baru = 0;

        foreach ($anggota as [$tipe, $ids, $adminGrup]) {
            foreach ($ids as $id) {
                $ada = ChatGroupMember::where('group_id', $grup->id)
                    ->where('member_type', $tipe)
                    ->where('member_id', $id)
                    ->exists();

                if ($ada) {
                    continue;
                }

                ChatGroupMember::create([
                    'group_id'    => $grup->id,
                    'member_type' => $tipe,
                    'member_id'   => $id,
                    'is_admin'    => $adminGrup,
                ]);
                $baru++;
            }

            $this->line(sprintf('  %-24s %d orang', class_basename($tipe), $ids->count()));
        }

        $total = ChatGroupMember::where('group_id', $grup->id)->count();

        $this->newLine();
        $this->info("Anggota baru ditambahkan: {$baru}. Total sekarang: {$total}.");

        return self::SUCCESS;
    }
}
