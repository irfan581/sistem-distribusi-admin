<template>
  <div class="error-page">
    <div class="error-content">
      <div class="error-code">404</div>
      <h1>Halaman Tidak Ditemukan</h1>
      <p>Halaman yang Anda cari tidak tersedia atau telah dipindahkan.</p>
      <Button label="Kembali ke Dashboard" icon="pi pi-home" @click="$router.push('/')" />
    </div>
  </div>
</template>

<script setup></script>

<style scoped>
.error-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--s-1);
}

.error-content {
  text-align: center;
  padding: 2rem;
}

.error-code {
  font-size: 8rem;
  font-weight: 900;
  color: var(--p-primary-500);
  line-height: 1;
  margin-bottom: 1rem;
}

h1 {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

p {
  color: var(--p-text-muted-color);
  margin-bottom: 2rem;
}
</style>
