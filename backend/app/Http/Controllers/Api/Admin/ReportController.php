<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\RekapExport;
use App\Exports\RekapSheet;
use App\Http\Controllers\Controller;
use App\Models\Izin;
use App\Models\KunjunganToko;
use App\Models\Presensi;
use App\Models\Sales;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Build the combined activity collection for one or all sales.
     */
    private function buildActivities(string $dari, string $sampai, ?int $salesId = null): array
    {
        // Specific sales_id: fetch regardless of active status (admin may download archived report)
        // All sales (no id): only fetch active sales
        $allSales = $salesId
            ? Sales::where('id', $salesId)->get()
            : Sales::where('is_active', true)->orderBy('nama')->get();

        $combined = collect();
        $totalHadir = 0;
        $totalKunjungan = 0;
        $totalIzin = 0;

        foreach ($allSales as $sales) {
            // Presensi
            $presensi = Presensi::where('sales_id', $sales->id)
                ->whereBetween('tanggal', [$dari, $sampai])
                ->get();

            $totalHadir += $presensi->count();

            foreach ($presensi as $p) {
                $tgl = Carbon::parse($p->tanggal)->toDateString();
                $combined->push([
                    'sales_id'   => $sales->id,
                    'sales_nama' => $sales->nama,
                    'sales_nik'  => $sales->nik,
                    'sales_area' => $sales->area ?? '-',
                    'waktu'      => $tgl . ' ' . ($p->jam_masuk ?? '00:00'),
                    'type'       => 'Absen',
                    'detail'     => 'MASUK ' . ($p->status === 'terlambat' ? '(Telat)' : '(On Time)'),
                    'location'   => $p->location_masuk ?? '-',
                    'keterangan' => $p->keterangan ?? '-',
                    'sort_key'   => Carbon::parse($tgl . ' ' . ($p->jam_masuk ?? '00:00')),
                ]);
                if ($p->jam_pulang) {
                    $combined->push([
                        'sales_id'   => $sales->id,
                        'sales_nama' => $sales->nama,
                        'sales_nik'  => $sales->nik,
                        'sales_area' => $sales->area ?? '-',
                        'waktu'      => $tgl . ' ' . $p->jam_pulang,
                        'type'       => 'Absen',
                        'detail'     => 'PULANG',
                        'location'   => $p->location_pulang ?? '-',
                        'keterangan' => 'Selesai Tugas',
                        'sort_key'   => Carbon::parse($tgl . ' ' . $p->jam_pulang),
                    ]);
                }
            }

            // Kunjungan Toko
            $kunjungan = KunjunganToko::where('sales_id', $sales->id)
                ->whereBetween('created_at', [
                    Carbon::parse($dari)->startOfDay(),
                    Carbon::parse($sampai)->endOfDay(),
                ])
                ->get();

            $totalKunjungan += $kunjungan->count();

            foreach ($kunjungan as $k) {
                $combined->push([
                    'sales_id'   => $sales->id,
                    'sales_nama' => $sales->nama,
                    'sales_nik'  => $sales->nik,
                    'sales_area' => $sales->area ?? '-',
                    'waktu'      => $k->created_at->format('Y-m-d H:i:s'),
                    'type'       => 'Toko',
                    'detail'     => 'Kunjungan: ' . ($k->nama_toko ?? 'Toko'),
                    'location'   => $k->location ?? '-',
                    'keterangan' => $k->keterangan ?? '-',
                    'sort_key'   => $k->created_at,
                ]);
            }

            // Izin
            Izin::where('sales_id', $sales->id)
                ->where('status', 'disetujui')
                ->whereBetween('tanggal', [$dari, $sampai])
                ->get()
                ->each(function ($i) use ($combined, $sales, &$totalIzin) {
                    $totalIzin++;
                    $combined->push([
                        'sales_id'   => $sales->id,
                        'sales_nama' => $sales->nama,
                        'sales_nik'  => $sales->nik,
                        'sales_area' => $sales->area ?? '-',
                        'waktu'      => Carbon::parse($i->tanggal)->format('Y-m-d 08:00'),
                        'type'       => 'Izin',
                        'detail'     => 'IZIN: ' . strtoupper($i->jenis_izin ?? 'IZIN'),
                        'location'   => '-',
                        'keterangan' => $i->keterangan ?? '-',
                        'sort_key'   => Carbon::parse($i->tanggal)->startOfDay(),
                    ]);
                });
        }

        return [
            'activities'      => $combined->sortBy('sort_key')->values(),
            'total_hadir'     => $totalHadir,
            'total_kunjungan' => $totalKunjungan,
            'total_izin'      => $totalIzin,
            'all_sales'       => $allSales,
        ];
    }

    public function downloadPdf(Request $request)
    {
        // Increase memory and execution time for PDF generation (development only)
        // If running in production, prefer optimizing the view or increasing PHP-FPM limits instead.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $request->validate([
            'dari'     => 'required|date',
            'sampai'   => 'required|date|after_or_equal:dari',
            'sales_id' => 'nullable|integer|exists:sales,id',
        ]);

        $dari    = Carbon::parse($request->dari)->toDateString();
        $sampai  = Carbon::parse($request->sampai)->toDateString();
        $salesId = $request->input('sales_id');

        $result = $this->buildActivities($dari, $sampai, $salesId ? (int) $salesId : null);

        $salesLabel = $salesId
            ? Sales::find($salesId)?->nama ?? 'Sales'
            : 'Semua Sales';

        $pdf = Pdf::loadView('pdf.rekap-global', [
            'activities'      => $result['activities'],
            'total_hadir'     => $result['total_hadir'],
            'total_kunjungan' => $result['total_kunjungan'],
            'total_izin'      => $result['total_izin'],
            'sales_label'     => $salesLabel,
            'date_range'      => Carbon::parse($dari)->format('d/m/Y') . ' sampai ' . Carbon::parse($sampai)->format('d/m/Y'),
            'printed_by'      => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        $slug     = str_replace(' ', '_', $salesLabel);
        $filename = "Rekap_{$slug}_" . date('Ymd') . ".pdf";

        return $pdf->download($filename);
    }

    public function downloadExcel(Request $request)
    {
        $request->validate([
            'dari'     => 'required|date',
            'sampai'   => 'required|date|after_or_equal:dari',
            'sales_id' => 'nullable|integer|exists:sales,id',
        ]);

        $dari    = Carbon::parse($request->dari)->toDateString();
        $sampai  = Carbon::parse($request->sampai)->toDateString();
        $salesId = $request->input('sales_id');

        $result = $this->buildActivities($dari, $sampai, $salesId ? (int) $salesId : null);

        $activities = $result['activities'];

        if ($salesId) {
            // Single sheet
            $rows = $activities->values()->map(function ($item, $idx) {
                return [
                    $idx + 1,
                    Carbon::parse($item['waktu'])->format('d/m/Y'),
                    Carbon::parse($item['waktu'])->format('H:i'),
                    $item['sales_nama'],
                    $item['sales_nik'],
                    $item['sales_area'],
                    $item['type'],
                    $item['detail'],
                    $item['location'],
                    $item['keterangan'],
                ];
            });

            $salesLabel = Sales::find($salesId)?->nama ?? 'Sales';
            $sheets     = [new RekapSheet($salesLabel, $rows)];
        } else {
            // One sheet per sales
            $grouped = $activities->groupBy('sales_id');
            $sheets  = [];

            foreach ($grouped as $sid => $items) {
                $first = $items->first();
                $rows  = $items->values()->map(function ($item, $idx) {
                    return [
                        $idx + 1,
                        Carbon::parse($item['waktu'])->format('d/m/Y'),
                        Carbon::parse($item['waktu'])->format('H:i'),
                        $item['sales_nama'],
                        $item['sales_nik'],
                        $item['sales_area'],
                        $item['type'],
                        $item['detail'],
                        $item['location'],
                        $item['keterangan'],
                    ];
                });

                $sheets[] = new RekapSheet($first['sales_nama'], $rows);
            }

            // Summary sheet first
            $summaryRows = collect();
            $no          = 1;
            foreach ($result['all_sales'] as $s) {
                $salesActivities = $activities->where('sales_id', $s->id);
                $summaryRows->push([
                    $no++,
                    $s->nama,
                    $s->nik,
                    $s->area ?? '-',
                    $salesActivities->where('type', 'Absen')->filter(fn($x) => str_contains($x['detail'], 'MASUK'))->count(),
                    $salesActivities->where('type', 'Toko')->count(),
                    $salesActivities->where('type', 'Izin')->count(),
                ]);
            }

            array_unshift($sheets, new class ($summaryRows) implements
                \Maatwebsite\Excel\Concerns\FromCollection,
                \Maatwebsite\Excel\Concerns\WithHeadings,
                \Maatwebsite\Excel\Concerns\WithStyles,
                \Maatwebsite\Excel\Concerns\WithTitle,
                \Maatwebsite\Excel\Concerns\ShouldAutoSize {
                public function __construct(private $rows) {}
                public function title(): string { return 'Ringkasan'; }
                public function collection() { return $this->rows; }
                public function headings(): array {
                    return ['No', 'Nama Sales', 'NIK', 'Area', 'Total Hadir', 'Total Kunjungan', 'Total Izin'];
                }
                public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array {
                    return [1 => [
                        'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                        'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEA580C']],
                        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                    ]];
                }
            });
        }

        $salesLabel = $salesId
            ? (Sales::find($salesId)?->nama ?? 'Sales')
            : 'Semua_Sales';

        $slug     = str_replace(' ', '_', $salesLabel);
        $filename = "Rekap_{$slug}_" . date('Ymd') . ".xlsx";

        return Excel::download(new RekapExport($sheets), $filename);
    }

    public function downloadIzinPdf(Request $request)
    {
        @ini_set('memory_limit', '512M');
        $request->validate([
            'dari'   => 'required|date',
            'sampai' => 'required|date|after_or_equal:dari',
        ]);

        $dari   = Carbon::parse($request->dari)->toDateString();
        $sampai = Carbon::parse($request->sampai)->toDateString();

        $salesList = Sales::where('is_active', true)->orderBy('nama')->get();

        $rows = collect();
        foreach ($salesList as $sales) {
            $izins = Izin::where('sales_id', $sales->id)
                ->whereBetween('tanggal', [$dari, $sampai])
                ->orderBy('tanggal')
                ->get();
            foreach ($izins as $izin) {
                $rows->push([
                    'nama'        => $sales->nama,
                    'area'        => $sales->area ?? '-',
                    'tanggal'     => Carbon::parse($izin->tanggal)->format('d/m/Y'),
                    'jenis'       => ucfirst($izin->jenis_izin ?? '-'),
                    'keterangan'  => $izin->keterangan ?? '-',
                    'status'      => ucfirst($izin->status),
                ]);
            }
        }

        $pdf = Pdf::loadView('pdf.rekap-izin', [
            'rows'       => $rows,
            'date_range' => Carbon::parse($dari)->format('d/m/Y') . ' sampai ' . Carbon::parse($sampai)->format('d/m/Y'),
            'printed_by' => $request->user()->name,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Rekap_Izin_" . date('Ymd') . ".pdf");
    }

    public function downloadIzinExcel(Request $request)
    {
        $request->validate([
            'dari'   => 'required|date',
            'sampai' => 'required|date|after_or_equal:dari',
        ]);

        $dari   = Carbon::parse($request->dari)->toDateString();
        $sampai = Carbon::parse($request->sampai)->toDateString();

        $salesList = Sales::where('is_active', true)->orderBy('nama')->get();

        $rows = collect();
        $no   = 1;
        foreach ($salesList as $sales) {
            $izins = Izin::where('sales_id', $sales->id)
                ->whereBetween('tanggal', [$dari, $sampai])
                ->orderBy('tanggal')
                ->get();
            foreach ($izins as $izin) {
                $rows->push([
                    $no++,
                    $sales->nama,
                    $sales->area ?? '-',
                    Carbon::parse($izin->tanggal)->format('d/m/Y'),
                    ucfirst($izin->jenis_izin ?? '-'),
                    $izin->keterangan ?? '-',
                    ucfirst($izin->status),
                ]);
            }
        }

        $sheet = new class ($rows) implements
            \Maatwebsite\Excel\Concerns\FromCollection,
            \Maatwebsite\Excel\Concerns\WithHeadings,
            \Maatwebsite\Excel\Concerns\WithStyles,
            \Maatwebsite\Excel\Concerns\WithTitle,
            \Maatwebsite\Excel\Concerns\ShouldAutoSize {
            public function __construct(private $rows) {}
            public function title(): string { return 'Rekap Izin'; }
            public function collection() { return $this->rows; }
            public function headings(): array {
                return ['No', 'Nama Sales', 'Area', 'Tanggal', 'Jenis', 'Keterangan', 'Status'];
            }
            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array {
                return [1 => [
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEA580C']],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                ]];
            }
        };

        $filename = "Rekap_Izin_" . date('Ymd') . ".xlsx";
        return Excel::download(new RekapExport([$sheet]), $filename);
    }
}
