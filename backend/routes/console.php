<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Prune failed jobs — hapus failed jobs lebih dari 7 hari ──────────────
Schedule::command('queue:prune-failed --hours=168')
    ->dailyAt('03:00')
    ->timezone('Asia/Jakarta');

// ── Monitor disk — warning jika disk > 80% ───────────────────────────────
Schedule::call(function () {
    $bytes   = disk_free_space('/');
    $total   = disk_total_space('/');
    $usedPct = round((($total - $bytes) / $total) * 100, 1);

    if ($usedPct > 80) {
        \Illuminate\Support\Facades\Log::warning("DISK WARNING: penggunaan disk {$usedPct}%, segera cek storage.");
    }
})->dailyAt('07:00')->timezone('Asia/Jakarta');
