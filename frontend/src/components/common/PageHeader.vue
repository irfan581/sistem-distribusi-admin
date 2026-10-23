<template>
  <div class="page-header" :class="{ 'page-header-compact': hideTitle }">
    <div v-if="!hideTitle" class="page-header-left">
      <h2 class="page-header-title">{{ title }}</h2>
      <p v-if="subtitle" class="page-header-subtitle">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="page-header-actions" :class="{ 'page-header-actions-only': hideTitle }">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  // Dipakai saat halaman ini dirender sebagai isi tab di halaman lain (mis.
  // MasterArmadaView) — tab-nya sendiri sudah punya label, jadi judul di
  // sini cuma jadi pengulangan. Tombol aksi (slot #actions) tetap tampil.
  hideTitle: { type: Boolean, default: false },
})
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

/* hideTitle: tidak ada judul yang perlu jarak besar di bawahnya — cuma
   tombol aksi sendirian, jadi marginnya dikecilkan supaya tidak menyisakan
   ruang kosong raksasa (mis. saat dipakai sebagai isi tab). */
.page-header-compact {
  margin-bottom: 0.5rem;
}

.page-header-title {
  font-size: 1.4rem;
  font-weight: 700;
  margin: 0;
  color: var(--p-text-color);
}

.page-header-subtitle {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
  margin: 0.25rem 0 0;
}

.page-header-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.page-header-actions-only {
  margin-left: auto;
}
</style>
