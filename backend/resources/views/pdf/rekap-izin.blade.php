<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e1b18; margin: 0; padding: 20px; }
  h2 { font-size: 14px; margin: 0 0 2px; color: #ea580c; }
  .sub { font-size: 9px; color: #6b7280; margin-bottom: 14px; }
  table { width: 100%; border-collapse: collapse; }
  thead tr { background: #ea580c; color: #fff; }
  thead th { padding: 6px 8px; text-align: left; font-size: 9px; }
  tbody tr:nth-child(even) { background: #fef9f6; }
  tbody td { padding: 5px 8px; border-bottom: 1px solid #f0e8e0; font-size: 9px; }
  .badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 8px; font-weight: 600; }
  .badge-disetujui { background: #dcfce7; color: #16a34a; }
  .badge-ditolak   { background: #fee2e2; color: #dc2626; }
  .badge-pending   { background: #fef9c3; color: #ca8a04; }
  .footer { margin-top: 14px; font-size: 8px; color: #9ca3af; text-align: right; }
</style>
</head>
<body>
  <h2>Rekap Izin Karyawan</h2>
  <div class="sub">Periode: {{ $date_range }} &nbsp;|&nbsp; Dicetak oleh: {{ $printed_by }} &nbsp;|&nbsp; {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</div>

  <table>
    <thead>
      <tr>
        <th style="width:30px">No</th>
        <th>Nama Sales</th>
        <th>Area</th>
        <th>Tanggal</th>
        <th>Jenis</th>
        <th>Keterangan</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $i => $row)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $row['nama'] }}</td>
        <td>{{ $row['area'] }}</td>
        <td>{{ $row['tanggal'] }}</td>
        <td>{{ $row['jenis'] }}</td>
        <td>{{ $row['keterangan'] }}</td>
        <td>
          <span class="badge badge-{{ strtolower($row['status']) }}">{{ $row['status'] }}</span>
        </td>
      </tr>
      @empty
      <tr><td colspan="7" style="text-align:center; padding:20px; color:#9ca3af">Tidak ada data izin pada periode ini</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer">Total: {{ count($rows) }} data</div>
</body>
</html>
