<template>
  <div class="sc">
    <div class="sc-head">
      <div class="sc-head-icon sc-head-icon-red"><i class="pi pi-shield" /></div>
      <div class="sc-head-text">
        <h3 class="sc-head-title">Keamanan</h3>
        <p class="sc-head-sub">Perbarui password akun Anda</p>
      </div>
    </div>

    <div class="sc-body">
      <form @submit.prevent="emit('save')" class="form-col" style="flex:1">

        <div class="sfield">
          <label class="sfield-label"><i class="pi pi-lock" /> Password Saat Ini</label>
          <Password
            :modelValue="form.current_password"
            @update:modelValue="emit('update:currentPassword', $event)"
            :feedback="false" toggleMask fluid
            placeholder="Masukkan password saat ini"
          />
        </div>

        <div class="sfield">
          <label class="sfield-label"><i class="pi pi-key" /> Password Baru</label>
          <Password
            :modelValue="form.new_password"
            @update:modelValue="emit('update:newPassword', $event)"
            toggleMask fluid
            placeholder="Minimal 6 karakter"
          />
        </div>

        <div class="sfield">
          <label class="sfield-label"><i class="pi pi-key" /> Konfirmasi Password</label>
          <Password
            :modelValue="form.new_password_confirmation"
            @update:modelValue="emit('update:confirmPassword', $event)"
            :feedback="false" toggleMask fluid
            placeholder="Ulangi password baru"
          />
        </div>

        <!-- Password tips — fills vertical space, keeps card balanced -->
        <div class="pw-tips">
          <p class="pw-tips-title"><i class="pi pi-info-circle" /> Tips keamanan</p>
          <ul class="pw-tips-list">
            <li>Minimal 8 karakter</li>
            <li>Kombinasi huruf besar &amp; kecil</li>
            <li>Sertakan angka atau simbol</li>
          </ul>
        </div>

        <div class="form-action-row">
          <button type="submit" class="sbtn sbtn-danger" :disabled="saving">
            <i v-if="saving" class="pi pi-spin pi-spinner" />
            <i v-else class="pi pi-refresh" />
            <span>{{ saving ? 'Menyimpan...' : 'Ubah Password' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
defineProps({ form: { type: Object, required: true }, saving: { type: Boolean, default: false } })
const emit = defineEmits(['save', 'update:currentPassword', 'update:newPassword', 'update:confirmPassword'])
</script>

<style scoped>
.pw-tips {
  margin-top: 0.15rem;
  padding: 0.7rem 0.85rem;
  background: var(--s-1);
  border: 1px solid var(--s-border);
  border-radius: var(--r-sm);
  flex: 1;
}
.pw-tips-title {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--t-secondary);
  margin: 0 0 0.4rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.pw-tips-title i { color: var(--c-brand-500); }
.pw-tips-list {
  list-style: none;
  padding: 0; margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.22rem;
}
.pw-tips-list li {
  font-size: 0.71rem;
  color: var(--t-muted);
  padding-left: 0.85rem;
  position: relative;
  line-height: 1.4;
}
.pw-tips-list li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: var(--c-brand-500);
  font-size: 0.65rem;
  top: 0.05em;
}

/* Dark mode */
.app-dark .pw-tips { background: var(--s-1); border-color: var(--s-border); }
.app-dark .pw-tips-title { color: var(--t-secondary); }
.app-dark .pw-tips-list li { color: var(--t-muted); }
</style>
