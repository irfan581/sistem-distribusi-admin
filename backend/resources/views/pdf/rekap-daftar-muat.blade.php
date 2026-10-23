<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1e1b18; margin: 0; padding: 16px; }
  .header { border-bottom: 2px solid #ea580c; padding-bottom: 7px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: flex-end; }
  .title-left h2 { margin: 0 0 2px; font-size: 12px; color: #ea580c; }
  .title-left p  { margin: 0; font-size: 8px; color: #6b7280; }
  .title-right   { text-align: right; font-size: 8px; color: #6b7280; }

  .truk-block { margin-bottom: 10px; border: 1px solid #f0e8e0; border-radius: 4px; overflow: hidden; }
  .truk-header { background: #fff7ed; padding: 5px 8px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0e8e0; }
  .truk-info { font-weight: 700; font-size: 9px; color: #1e1b18; }
  .truk-sub  { font-size: 7.5px; color: #6b7280; }
  .badge-driver   { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5px; font-weight: 700; background: #fef3c7; color: #b45309; }
  .badge-pengawas { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.5px; font-weight: 700; background: #dcfce7; color: #15803d; }

  table { width: 100%; border-collapse: collapse; }
  thead tr { background: #ea580c; color: #fff; }
  thead th { padding: 4px 6px; font-size: 7.5px; text-align: left; }
  tbody tr:nth-child(even) { background: #fef9f6; }
  tbody td { padding: 3px 6px; border-bottom: 1px solid #f5ede8; font-size: 8px; vertical-align: top; }
  .total-row td { font-weight: 700; font-size: 8px; padding: 4px 6px; background: #fff7ed; }

  .summary { margin-top: 10px; padding: 7px 10px; background: #fff7ed; border: 1px solid #f0e8e0; border-radius: 4px; display: flex; gap: 20px; font-size: 8.5px; }
  .summary b { color: #ea580c; }
  .footer { margin-top: 8px; font-size: 7.5px; color: #9ca3af; text-align: right; }
  .no-data { color: #9ca3af; font-style: italic; }

  /* Ringkasan driver */
  .driver-summary { margin-top: 12px; display: flex; gap: 8px; }
  .driver-box { flex: 1; border-radius: 4px; overflow: hidden; border: 1px solid; }
  .driver-box--kirim  { border-color: #bbf7d0; }
  .driver-box--belum  { border-color: #fde68a; }
  .driver-box--izin   { border-color: #fecaca; }
  .driver-box-head { padding: 5px 8px; font-size: 8.5px; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
  .driver-box--kirim .driver-box-head  { background: #dcfce7; color: #15803d; }
  .driver-box--belum .driver-box-head  { background: #fef9c3; color: #b45309; }
  .driver-box--izin  .driver-box-head  { background: #fee2e2; color: #dc2626; }
  .driver-box-count { font-size: 10px; font-weight: 800; }
  .driver-box-body { padding: 5px 8px; }
  .driver-item { font-size: 7.5px; color: #374151; padding: 3px 0; border-bottom: 1px dotted #e5e7eb; }
  .driver-item:last-child { border-bottom: none; }
  .driver-item-name { font-weight: 700; }
  .driver-item-detail { color: #6b7280; font-size: 7px; margin-top: 1px; }
  .driver-item-toko { color: #ea580c; font-size: 7px; font-weight: 600; }
</style>
</head>
<body>
  <div class="header">
    <div class="title-left">
      <h2>Rekap Daftar Muat Harian</h2>
      <p>CV Tani Makmur &nbsp;|&nbsp; Tanggal: {{ $tanggal }}
        @if(!empty($jenis_muatan))
          &nbsp;|&nbsp;
          @if($jenis_muatan === 'subsidi')
            <span style="background:#dcfce7;color:#15803d;padding:1px 6px;border-radius:4px;font-weight:700;">SUBSIDI</span>
          @else
            <span style="background:#e0e7ff;color:#3730a3;padding:1px 6px;border-radius:4px;font-weight:700;">NON SUBSIDI</span>
          @endif
        @endif
      </p>
    </div>
    <div class="title-right">
      Dicetak oleh: {{ $printed_by }}<br>{{ $printed_at }} WIB
    </div>
  </div>

  @forelse($muats as $muat)
  <div class="truk-block">
    <div class="truk-header">
      <div>
        <span class="truk-info">
          {{ $muat->kendaraan?->nomor_polisi ?? 'Kendaraan Belum Ditentukan' }}
          @if($muat->kendaraan) ({{ $muat->kendaraan->jenis_kendaraan }}) @endif
        </span>
        <span class="truk-sub">
          &nbsp;|&nbsp; Sopir: {{ $muat->driver?->nama_driver ?? 'Belum ditentukan' }}
          @if($muat->driver) ({{ $muat->driver->no_telepon }}) @endif
          &nbsp;|&nbsp; {{ $muat->items->count() }} tujuan
          &nbsp;|&nbsp; Total: {{ number_format($muat->total_muatan_ton * 1000, 0) }} Kg
        </span>
      </div>
      <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
        @if(!$muat->driver_id)<span class="badge-driver">Belum ada driver</span>@endif
        <span class="badge-pengawas">Pengawas: {{ $muat->checker?->nama ?? 'Belum ditugaskan' }}</span>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th style="width:20px">No</th>
          <th>Toko / Lokasi Tujuan</th>
          <th style="width:60px">Jenis</th>
          <th style="width:100px">Pengawas Muat</th>
          <th>Keterangan</th>
        </tr>
      </thead>
      <tbody>
        @foreach($muat->items as $item)
        <tr>
          <td>{{ $item->urutan }}</td>
          <td><b>{{ $item->lokasi_pengiriman }}</b></td>
          <td>
            @if(($item->jenis_muatan ?? 'non_subsidi') === 'subsidi')
              <span style="background:#dcfce7;color:#15803d;padding:1px 5px;border-radius:4px;font-size:7px;font-weight:700;">SUBSIDI</span>
            @else
              <span style="background:#e0e7ff;color:#3730a3;padding:1px 5px;border-radius:4px;font-size:7px;font-weight:700;">NON-SUB</span>
            @endif
          </td>
          <td>{{ $loop->first ? ($muat->checker?->nama ?? '-') : '' }}</td>
          <td>{{ $item->catatan_admin ?? '-' }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
          <td colspan="2" style="text-align:right">TOTAL MUATAN</td>
          <td colspan="3">{{ number_format($muat->total_muatan_ton * 1000, 0) }} Kg</td>
        </tr>
      </tbody>
    </table>
  </div>
  @empty
  <p class="no-data" style="text-align:center; padding:20px">Tidak ada jadwal pengiriman pada tanggal ini.</p>
  @endforelse

  <div class="summary">
    <span>Total Trip: <b>{{ $muats->count() }}</b></span>
    <span>Sudah Ada Driver: <b>{{ $muats->filter(fn($m) => $m->driver_id)->count() }}</b></span>
    <span>Belum Ada Driver: <b>{{ $muats->filter(fn($m) => !$m->driver_id)->count() }}</b></span>
    <span>Total Tujuan: <b>{{ $muats->sum(fn($m) => $m->items->count()) }}</b> toko</span>
    <span>Total Muatan: <b>{{ number_format($muats->sum('total_muatan_ton') * 1000, 0) }} Kg</b></span>
  </div>

  @php
    $yangKirim  = $muats->filter(fn($m) => $m->driver_id);
  @endphp

  <div class="driver-summary">
    {{-- Kolom kiri: Yang Kirim --}}
    <div class="driver-box driver-box--kirim">
      <div class="driver-box-head">
        <span>Yang Kirim Hari Ini</span>
        <span class="driver-box-count">{{ $yangKirim->count() }} driver</span>
      </div>
      <div class="driver-box-body">
        @forelse($yangKirim as $m)
        <div class="driver-item">
          <div>
            <span class="driver-item-name">{{ $m->driver?->nama_driver ?? '-' }}</span>
            &nbsp;
            <span class="driver-item-toko">{{ $m->items->count() }} toko</span>
          </div>
          <div class="driver-item-detail">
            {{ $m->kendaraan?->nomor_polisi ?? '-' }}
            &nbsp;·&nbsp;
            {{ $m->items->pluck('lokasi_pengiriman')->take(4)->implode(', ') }}{{ $m->items->count() > 4 ? ' +'.($m->items->count()-4).' lagi' : '' }}
          </div>
        </div>
        @empty
        <div class="driver-item" style="color:#9ca3af;font-style:italic">Tidak ada jadwal kirim</div>
        @endforelse
      </div>
    </div>

    {{-- Kolom tengah: Tidak Jalan --}}
    <div class="driver-box driver-box--belum">
      <div class="driver-box-head">
        <span>Tidak Jalan Hari Ini</span>
        <span class="driver-box-count">{{ $tidakJalan->count() }} driver</span>
      </div>
      <div class="driver-box-body">
        @forelse($tidakJalan as $driver)
        <div class="driver-item">
          <span class="driver-item-name">{{ $driver->nama_driver }}</span>
          <div class="driver-item-detail">{{ $driver->no_telepon ?? '-' }}</div>
        </div>
        @empty
        <div class="driver-item" style="color:#9ca3af;font-style:italic">Semua driver aktif jalan hari ini</div>
        @endforelse
      </div>
    </div>

    {{-- Kolom kanan: Driver Izin --}}
    <div class="driver-box driver-box--izin">
      <div class="driver-box-head">
        <span>Driver Izin / Sakit</span>
        <span class="driver-box-count">{{ $driverIzin->count() }} driver</span>
      </div>
      <div class="driver-box-body">
        @forelse($driverIzin as $izin)
        <div class="driver-item">
          <span class="driver-item-name">
            {{ $izin->driver?->nama_driver ?? '-' }}
            <span style="font-weight:400;color:#dc2626">({{ $izin->keterangan }})</span>
          </span>
          @if($izin->catatan)
          <div class="driver-item-detail">{{ $izin->catatan }}</div>
          @endif
        </div>
        @empty
        <div class="driver-item" style="color:#9ca3af;font-style:italic">Tidak ada driver izin</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="footer">CV Tani Makmur &nbsp;|&nbsp; Rekap Muat {{ $tanggal }}</div>
</body>
</html>
