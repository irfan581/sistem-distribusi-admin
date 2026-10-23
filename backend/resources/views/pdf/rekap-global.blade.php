<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Aktivitas: {{ $sales_label }}</title>
    <style>
    @page { margin: 1cm; }
    body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; margin: 0; padding: 0; }

    .header-wrapper { border-bottom: 2px solid #EA580C; padding-bottom: 14px; margin-bottom: 16px; }
    .header-content h1 { margin: 0; color: #7c2d12; font-size: 17px; letter-spacing: 0.5px; text-transform: uppercase; }
    .header-content p { margin: 2px 0 0; color: #EA580C; font-weight: bold; font-size: 12px; }

    .info-bar { margin-bottom: 14px; padding: 10px 12px; background-color: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; }
    .label-text { font-weight: bold; color: #334155; width: 110px; }

    .stat-card { background-color: #f8fafc; border-left: 4px solid #EA580C; padding: 10px; border-radius: 4px; vertical-align: top; }
    .stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; margin-bottom: 3px; display: block; }
    .stat-value { font-size: 14px; font-weight: bold; color: #0f172a; }

    .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .data-table th { background-color: #EA580C; color: #ffffff; text-align: left; padding: 9px 8px; font-size: 9px; text-transform: uppercase; border: 1px solid #c2410c; }
    .data-table td { padding: 7px 8px; border: 1px solid #e2e8f0; vertical-align: middle; }
    .data-table tr:nth-child(even) { background-color: #f8fafc; }

    .badge { display: inline-block; padding: 3px 8px; border-radius: 9999px; color: white; font-weight: bold; font-size: 8px; text-align: center; text-transform: uppercase; }
    .bg-absen { background-color: #16a34a; }
    .bg-toko  { background-color: #d97706; }
    .bg-izin  { background-color: #7c3aed; }

    .location-text { color: #334155; font-size: 8.5px; font-family: 'Courier New', Courier, monospace; }

    .footer { position: fixed; bottom: -20px; left: 0; right: 0; height: 20px; text-align: right; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
    </style>
</head>
<body>

    <script type="text/php">
        if (isset($pdf)) {
            $x = 760; $y = 565;
            $text = "Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            $font = $fontMetrics->get_font("helvetica", "normal");
            $size = 8; $color = [0.5, 0.5, 0.5];
            $pdf->page_text($x, $y, $text, $font, $size, $color, 0.0, 0.0, 0.0);
        }
    </script>

    <div class="header-wrapper">
        <div class="header-content">
            <h1>Laporan Rekap Aktivitas Karyawan</h1>
            <p>CV. TANI MAKMUR MANDIRI</p>
        </div>
    </div>

    <div class="info-bar">
        <table style="width: 100%;">
            <tr>
                <td class="label-text">Sales / Filter</td>
                <td style="width: 40%">: <strong style="color:#EA580C;">{{ $sales_label }}</strong></td>
                <td class="label-text" style="text-align:right;">Periode</td>
                <td style="text-align:right;">: {{ $date_range }}</td>
            </tr>
            <tr>
                <td class="label-text">Dicetak Oleh</td>
                <td>: {{ $printed_by }}</td>
                <td class="label-text" style="text-align:right;">Dicetak Pada</td>
                <td style="text-align:right;">: {{ now()->translatedFormat('d F Y, H:i') }}</td>
            </tr>
        </table>
    </div>

    <table style="width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-left: -12px; margin-bottom: 16px;">
        <tr>
            <td width="33%">
                <div class="stat-card">
                    <span class="stat-label">Total Kehadiran</span>
                    <span class="stat-value">{{ $total_hadir }} HARI</span>
                </div>
            </td>
            <td width="33%">
                <div class="stat-card" style="border-left-color:#d97706;">
                    <span class="stat-label">Total Kunjungan Toko</span>
                    <span class="stat-value">{{ $total_kunjungan }} TOKO</span>
                </div>
            </td>
            <td width="34%">
                <div class="stat-card" style="border-left-color:#7c3aed;">
                    <span class="stat-label">Total Izin Disetujui</span>
                    <span class="stat-value">{{ $total_izin }} IZIN</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="10%">Tanggal</th>
                <th width="7%">Waktu</th>
                <th width="14%">Nama Sales</th>
                <th width="8%">Area</th>
                <th width="8%">Kategori</th>
                <th width="22%">Aktivitas / Detail</th>
                <th width="16%">Lokasi GPS</th>
                <th width="10%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($activities as $i => $item)
            <tr>
                <td style="text-align:center;">{{ $i + 1 }}</td>
                <td style="text-align:center;">{{ \Carbon\Carbon::parse($item['waktu'])->format('d/m/Y') }}</td>
                <td style="text-align:center;font-weight:bold;">{{ \Carbon\Carbon::parse($item['waktu'])->format('H:i') }}</td>
                <td>
                    <div style="font-weight:bold;">{{ $item['sales_nama'] }}</div>
                    <div style="font-size:8px;color:#64748b;">{{ $item['sales_nik'] }}</div>
                </td>
                <td>{{ $item['sales_area'] }}</td>
                <td>
                    @php
                    $cls = match($item['type']) { 'Absen' => 'bg-absen', 'Toko' => 'bg-toko', 'Izin' => 'bg-izin', default => 'bg-absen' };
                    @endphp
                    <span class="badge {{ $cls }}">{{ $item['type'] }}</span>
                </td>
                <td><div style="font-weight:bold;color:#1e293b;">{{ $item['detail'] }}</div></td>
                <td>
                    @if(!empty($item['location']) && $item['location'] !== '-')
                        <div class="location-text">{{ $item['location'] }}</div>
                    @else
                        <span style="color:#94a3b8;font-style:italic;">-</span>
                    @endif
                </td>
                <td><div style="color:#475569;">{{ $item['keterangan'] ?? '-' }}</div></td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align:center; padding:28px; color:#94a3b8;">
                    Tidak ada data aktivitas pada periode ini.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dicetak otomatis oleh Sistem Monitoring Sales, CV. TANI MAKMUR MANDIRI
    </div>
</body>
</html>
