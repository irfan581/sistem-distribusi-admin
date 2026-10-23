<template>
  <div class="bento-cell bento-monthly">
    <div class="cell-head">
      <div class="cell-title-group">
        <span class="cell-title">Ringkasan Bulan Ini</span>
        <span class="cell-sub">{{ currentMonth }}, {{ stats.hari_kerja_bulan_ini ?? 0 }} hari kerja berjalan</span>
      </div>
    </div>
    <div class="cell-body">
      <div class="monthly-grid">
        <div v-for="item in items" :key="item.label" class="monthly-item">
          <span class="monthly-val" :class="item.tone">
            <span v-if="loading" class="skeleton" style="width:36px;height:22px;display:block" />
            <span v-else>{{ item.value }}</span>
          </span>
          <span class="monthly-label">{{ item.label }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  stats:        { type: Object,  required: true },
  loading:      { type: Boolean, default: false },
  currentMonth: { type: String,  required: true },
})

const nf = new Intl.NumberFormat('id-ID')

function angka(v) {
  return nf.format(parseInt(v) || 0)
}

/**
 * Semua isian di sini harus berskala BULAN — angka harian sudah tampil
 * di KPI strip, dan menaruhnya lagi di sini bikin orang ragu apakah
 * dua angka yang sama itu memang hal yang sama.
 */
const items = computed(() => {
  const s = props.stats

  const target   = parseInt(s.target_hadir_bulan_ini) || 0
  const hadir    = parseInt(s.total_absen_bulan_ini)  || 0
  const kunjung  = parseInt(s.kunjungan_bulan_ini)    || 0
  const hariKerja = parseInt(s.hari_kerja_bulan_ini)  || 0

  return [
    { label: 'Total Kehadiran', value: angka(hadir),                        tone: '' },
    { label: 'Tepat Waktu',     value: angka(s.tepat_waktu_bulan_ini),      tone: 'monthly-val-ok' },
    { label: 'Terlambat',       value: angka(s.terlambat_bulan_ini),        tone: 'monthly-val-warn' },
    {
      label: 'Ketepatan Waktu',
      value: `${parseInt(s.persen_tepat_waktu_bulan_ini) || 0}%`,
      tone: 'monthly-val-ok',
    },
    { label: 'Total Kunjungan', value: angka(kunjung),                      tone: 'monthly-val-brand' },
    {
      label: 'Kunjungan / Hari',
      value: hariKerja > 0 ? angka(Math.round(kunjung / hariKerja)) : '0',
      tone: 'monthly-val-brand',
    },
    { label: 'Izin Disetujui',  value: angka(s.izin_disetujui_bulan_ini),   tone: 'monthly-val-warn' },
    {
      label: 'Belum Absen',
      value: angka(Math.max(0, target - hadir)),
      tone: 'monthly-val-warn',
    },
  ]
})
</script>
