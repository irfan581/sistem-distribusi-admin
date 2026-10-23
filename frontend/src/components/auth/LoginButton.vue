<template>
  <button
    type="submit"
    :form="formId"
    class="lp-btn"
    :class="{ 'lp-btn--shake': shake }"
    :disabled="loading || transitioning"
  >
    <span class="lp-btn-inner">
      <span v-show="!loading && !transitioning" class="lp-btn-idle">
        <i class="pi pi-sign-in" /><span>Masuk ke Dashboard</span>
      </span>
      <span v-show="loading || transitioning" class="lp-btn-busy">
        <span class="lp-spin" /><span>Memverifikasi…</span>
      </span>
    </span>
  </button>
</template>

<script setup>
defineProps({
  loading:       { type: Boolean, default: false },
  transitioning: { type: Boolean, default: false },
  shake:         { type: Boolean, default: false },
  // Tombolnya berada di luar <form> karena pesan galat menyela di antara
  // keduanya. Atribut `form` yang menyambungkannya kembali, dan itu bukan
  // sekadar demi kerapian: sebuah form hanya bisa dikirim dengan Enter kalau
  // ia punya tombol submit. Sebelum ini tidak punya, jadi menekan Enter di
  // kolom password tidak melakukan apa-apa.
  formId:        { type: String, required: true },
})
</script>
