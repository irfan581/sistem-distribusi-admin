<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  /*
   * Bentuknya mengikuti rekap tulis tangan yang sudah dipakai kantor: satu
   * blok per sopir, di dalamnya tanggal dan tonase memayungi beberapa baris
   * kios. Sengaja tidak dibuat satu tabel panjang, karena yang dicari orang
   * saat membacanya adalah "sopir ini bawa apa saja", bukan urutan waktu.
   */
  body {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 9px; color: #111; margin: 0; padding: 18px;
  }

  .judul { text-align: center; margin-bottom: 12px; }
  .judul h1 { margin: 0; font-size: 12px; font-weight: 700; letter-spacing: .2px; }
  .judul p  { margin: 2px 0 0; font-size: 10px; font-weight: 700; }

  .blok { margin-bottom: 12px; page-break-inside: avoid; }

  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #000; padding: 3px 5px; }

  thead th {
    background: #ffc000; font-size: 8.5px; font-weight: 700;
    text-align: center; text-transform: uppercase;
  }

  /* Lebar dipatok supaya tiap blok sopir sejajar dari atas ke bawah. Tanpa
     ini tiap tabel melebar sendiri mengikuti panjang nama kiosnya, dan
     halamannya terbaca seperti kumpulan tabel yang tidak berhubungan. */
  .k-sopir  { width: 24%; }
  .k-tgl    { width: 20%; }
  .k-kios   { width: 40%; }
  .k-tonase { width: 16%; }

  .sopir  { text-align: center; font-weight: 700; vertical-align: middle; }
  .tgl    { text-align: center; vertical-align: middle; }
  .tonase { text-align: center; vertical-align: middle; font-weight: 700; }
  .kios   { text-align: left; }

  .kosong { text-align: center; padding: 20px; color: #666; font-style: italic; }
  .kaki   { margin-top: 10px; font-size: 7.5px; color: #666; text-align: right; }
</style>
</head>
<body>

  <div class="judul">
    <h1>REKAP KIRIMAN SOPIR{{ $labelJenis ? ' ' . strtoupper($labelJenis) : '' }}</h1>
    <p>{{ strtoupper($periode) }}</p>
  </div>

  @forelse ($perSopir as $sopir)
    <div class="blok">
      <table>
        <thead>
          <tr>
            <th class="k-sopir">Nama Sopir</th>
            <th class="k-tgl">Tanggal</th>
            <th class="k-kios">Kios</th>
            <th class="k-tonase">Tonase (Kg)</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($sopir['rit'] as $iRit => $rit)
            @foreach ($rit['kios'] as $iKios => $kios)
              <tr>
                {{-- Nama sopir hanya ditulis sekali, memayungi seluruh barisnya. --}}
                @if ($iRit === 0 && $iKios === 0)
                  <td class="sopir" rowspan="{{ $sopir['baris'] }}">{{ $sopir['nama'] }}</td>
                @endif

                @if ($iKios === 0)
                  <td class="tgl" rowspan="{{ count($rit['kios']) }}">{{ $rit['tanggal'] }}</td>
                @endif

                <td class="kios">{{ $kios }}</td>

                @if ($iKios === 0)
                  <td class="tonase" rowspan="{{ count($rit['kios']) }}">{{ $rit['tonase'] }}</td>
                @endif
              </tr>
            @endforeach
          @endforeach
        </tbody>
      </table>
    </div>
  @empty
    <p class="kosong">Tidak ada kiriman pada periode ini.</p>
  @endforelse

  <div class="kaki">Dicetak {{ $dicetakPada }} oleh {{ $dicetakOleh }}</div>

</body>
</html>
