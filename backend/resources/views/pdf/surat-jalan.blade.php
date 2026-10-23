<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e1b18; background: #fff; }

/* ── Header ──────────────────────────────────── */
.header {
  background: #ea580c;
  color: #fff;
  padding: 14px 20px;
}
.header-inner {
  width: 100%;
  border-collapse: collapse;
}
.header-inner td { vertical-align: middle; padding: 0; }
.co-name  { font-size: 14px; font-weight: bold; letter-spacing: 0.02em; }
.co-sub   { font-size: 7.5px; opacity: 0.85; margin-top: 1px; }
.doc-right { text-align: right; }
.doc-label { font-size: 7px; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.8; }
.doc-no    { font-size: 11px; font-weight: bold; letter-spacing: 0.03em; }

/* ── Rit badge ───────────────────────────────── */
.rit-badge {
  display: inline-block;
  background: rgba(255,255,255,0.25);
  border: 1px solid rgba(255,255,255,0.5);
  color: #fff;
  font-size: 7px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 10px;
  margin-top: 3px;
}

/* ── Info grid ───────────────────────────────── */
.info-section { padding: 8px 14px 0; }
.info-table { width: 100%; border-collapse: collapse; }
.info-table td { padding: 3px 6px 3px 0; font-size: 8.5px; vertical-align: top; }
.info-table .lbl { color: #6b7280; font-size: 8px; width: 72px; }
.info-table .val { color: #1e1b18; font-weight: 600; }
.info-table .divider { width: 1px; background: #f0e8e0; padding: 0 10px; }
.info-box {
  background: #fef9f6;
  border: 1px solid #fde8d8;
  border-radius: 5px;
  padding: 7px 10px;
  margin: 10px 20px 0;
}
.no-armada { color: #d97706; font-style: italic; font-size: 8px; }

/* ── Divider ─────────────────────────────────── */
.divider-line { height: 1px; background: #f0e8e0; margin: 10px 20px 0; }

/* ── Section title ───────────────────────────── */
.section-title {
  font-size: 8px;
  font-weight: 700;
  color: #ea580c;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  padding: 10px 20px 5px;
}

/* ── Items table ─────────────────────────────── */
.items-wrap { padding: 0 20px; }
table.items { width: 100%; border-collapse: collapse; }
table.items thead tr { background: #ea580c; }
table.items thead th {
  color: #fff;
  font-size: 7.5px;
  font-weight: 700;
  padding: 5px 6px;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
table.items tbody tr { border-bottom: 1px solid #f0e8e0; }
table.items tbody tr:nth-child(even) { background: #fef9f6; }
table.items tbody td { padding: 4.5px 6px; font-size: 8.5px; vertical-align: top; }
.lokasi-main { font-weight: 700; font-size: 9px; }
.lokasi-area { font-size: 7.5px; color: #6b7280; margin-top: 1px; }
.lokasi-catatan { font-size: 7.5px; color: #9a3412; margin-top: 2px; font-style: italic; }
.muatan-val  { text-align: right; font-weight: 700; color: #1e1b18; }
.muatan-unit { font-size: 7px; color: #6b7280; font-weight: 400; }

/* ── Total row ───────────────────────────────── */
.total-row { background: #fff7ed !important; border-top: 1.5px solid #ea580c !important; }
.total-row td { padding: 5px 6px; font-size: 8.5px; }
.total-label { font-weight: 700; color: #ea580c; text-transform: uppercase; font-size: 7.5px; letter-spacing: 0.05em; }
.total-val { text-align: right; font-weight: 700; font-size: 10px; color: #ea580c; }
.total-unit { font-size: 7px; font-weight: 400; color: #9a3412; }

/* ── Pengawas box ────────────────────────────── */
.pengawas-box {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 5px;
  padding: 7px 10px;
  margin: 8px 20px 0;
}
.pengawas-label { font-size: 7.5px; color: #6b7280; }
.pengawas-name  { font-size: 9px; font-weight: 700; color: #15803d; }
.pengawas-badge {
  display: inline-block;
  background: #dcfce7;
  color: #15803d;
  font-size: 6.5px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 8px;
  border: 1px solid #bbf7d0;
  margin-left: 4px;
  vertical-align: middle;
}
.no-pengawas { color: #9ca3af; font-style: italic; font-size: 8px; }

/* ── Footer ──────────────────────────────────── */
.footer {
  margin: 12px 20px 0;
  padding-top: 5px;
  border-top: 1px solid #f0e8e0;
  font-size: 7px;
  color: #9ca3af;
}
.footer-inner { width: 100%; border-collapse: collapse; }
.footer-inner td { padding: 0; }
</style>
</head>
<body>

{{-- ── HEADER ────────────────────────────────── --}}
<div class="header">
  <table class="header-inner">
    <tr>
      <td style="width:60%">
        <div class="co-name">CV. Tani Makmur Sejahtera</div>
      </td>
      <td class="doc-right">
        <div class="doc-label">Surat Jalan</div>
        <div class="doc-no">SJ-{{ str_pad($muat->id, 5, '0', STR_PAD_LEFT) }}/{{ date('Y') }}</div>
        <div class="rit-badge">Rit {{ $muat->nomor_rit ?? 1 }}</div>
      </td>
    </tr>
  </table>
</div>

{{-- ── INFO ARMADA ─────────────────────────────── --}}
<div class="info-box">
  <table class="info-table">
    <tr>
      <td class="lbl">Tanggal</td>
      <td class="val">{{ $tanggal }}</td>
      <td class="divider"></td>
      @if($muat->kendaraan)
      <td class="lbl">No. Polisi</td>
      <td class="val">{{ $muat->kendaraan->nomor_polisi }}</td>
      @else
      <td class="lbl">Kendaraan</td>
      <td class="val no-armada">Belum ditetapkan</td>
      @endif
    </tr>
    <tr>
      @if($muat->driver)
      <td class="lbl">Pengemudi</td>
      <td class="val">{{ $muat->driver->nama_panggilan ?? $muat->driver->nama_driver }}</td>
      @else
      <td class="lbl">Pengemudi</td>
      <td class="val no-armada">Belum ditetapkan</td>
      @endif
      <td class="divider"></td>
      @if($muat->kendaraan)
      <td class="lbl">Kendaraan</td>
      <td style="font-size:8px;color:#6b7280">{{ $muat->kendaraan->jenis_kendaraan }} · {{ number_format($muat->kendaraan->kapasitas_muatan_ton * 1000, 0) }} Kg kapasitas</td>
      @else
      <td class="lbl"></td><td></td>
      @endif
    </tr>
    @if($muat->driver && $muat->driver->no_telepon)
    <tr>
      <td class="lbl">No. Telepon</td>
      <td class="val" colspan="4">{{ $muat->driver->no_telepon }}</td>
    </tr>
    @endif
  </table>
</div>

{{-- ── PENGAWAS MUAT ─────────────────────────────── --}}
<div class="pengawas-box">
  <table class="info-table">
    <tr>
      <td class="lbl">Pengawas Muat</td>
      <td>
        @if($muat->checker)
          <span class="pengawas-name">{{ $muat->checker->nama }}</span>
          <span class="pengawas-badge">Ceker Gudang</span>
        @else
          <span class="no-pengawas">Belum ditugaskan</span>
        @endif
      </td>
    </tr>
  </table>
</div>

{{-- ── DAFTAR TUJUAN ────────────────────────────── --}}
<div class="section-title">Daftar Tujuan ({{ $muat->items->count() }} Toko)</div>

<div class="items-wrap">
  <table class="items">
    <thead>
      <tr>
        <th style="width:18px">#</th>
        <th>Toko / Lokasi</th>
      </tr>
    </thead>
    <tbody>
      @php
        $wilayahMap = [
          'BJN'=>'Bojonegoro','TRT'=>'Tuban','TTSJ'=>'Tuban Sel/Jenu',
          'NGJ'=>'Nganjuk','J86'=>'Jombang','KDR'=>'Kediri',
          'TLG'=>'Tulungagung','TRG'=>'Trenggalek','BLT'=>'Blitar',
          'MDN'=>'Madiun','MGT'=>'Magetan','NGW'=>'Ngawi',
          'PNR'=>'Ponorogo','WNG'=>'Wonogiri',
        ];
        $totalKg = round($muat->total_muatan_ton * 1000);
      @endphp
      @foreach($muat->items as $item)
      @php
        $kode = $item->kode_area ?? null;
        $wilayah = $kode ? ($wilayahMap[$kode] ?? $kode) : null;
      @endphp
      <tr>
        <td style="color:#9ca3af">{{ $item->urutan }}</td>
        <td>
          <div class="lokasi-main">{{ $item->lokasi_pengiriman }}</div>
          @if($wilayah)
          <div class="lokasi-area">{{ $wilayah }} ({{ $kode }})</div>
          @endif
          @if($item->catatan_admin)
          <div class="lokasi-catatan">Keterangan: {{ $item->catatan_admin }}</div>
          @endif
        </td>
      </tr>
      @endforeach

      <tr class="total-row">
        <td></td>
        <td class="total-label">Total Muatan &nbsp; <span class="total-val">{{ number_format($totalKg, 0, ',', '.') }} <span class="total-unit">Kg</span></span></td>
      </tr>
    </tbody>
  </table>
</div>

{{-- ── FOOTER ──────────────────────────────────── --}}
<div class="footer">
  <table class="footer-inner">
    <tr>
      <td>Dicetak: {{ $printed_at }} WIB</td>
      <td style="text-align:right">CV. Tani Makmur Sejahtera &nbsp;·&nbsp; SJ-{{ str_pad($muat->id, 5, '0', STR_PAD_LEFT) }}/{{ date('Y') }}</td>
    </tr>
  </table>
</div>

</body>
</html>
