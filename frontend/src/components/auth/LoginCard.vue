<template>
  <div class="lp-card" :class="{ 'lp-card--entered': entered }">
    <header class="lp-hd">
      <p class="lp-hd-eyebrow"><span class="lp-hd-pip" />Administrator Portal</p>
      <h1 class="lp-hd-title">Selamat datang<span class="lp-hd-accent">.</span></h1>
      <p class="lp-hd-sub">Masuk untuk memantau tim, presensi, dan laporan sales.</p>
    </header>

    <LoginForm
      :form="form" :errors="errors"
      :form-id="formId"
      @submit="handleLogin"
      @update:email="onEmailInput($event)"
      @update:password="onPasswordInput($event)"
      @update:remember="form.remember = $event"
      @touchEmail="touchEmail"
      @touchPassword="touchPassword"
    />

    <p class="lp-serr" :class="{ 'lp-serr--visible': loginError }">
      <i class="pi pi-exclamation-circle" />{{ loginError || ' ' }}
    </p>

    <LoginButton
      :loading="authStore.loading"
      :transitioning="transitioning"
      :shake="shakeBtn"
      :form-id="formId"
    />
  </div>

  <Teleport to="body">
    <div v-if="transitioning" class="door-overlay">
      <div class="door-left"  :class="{ 'door-close': transitioning }" />
      <div class="door-right" :class="{ 'door-close': transitioning }" />
      <div class="door-logo"  :class="{ 'door-logo-show': logoVisible }">
        <picture>
          <source srcset="/logo-icon-96.webp" type="image/webp" />
          <img src="/logo-icon.png" alt="TMK" width="52" height="52" />
        </picture>
        <span>Tani Makmur</span>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick, useId } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { useAppStore }  from '@/stores/app.store'
import LoginForm   from '@/components/auth/LoginForm.vue'
import LoginButton from '@/components/auth/LoginButton.vue'

const router    = useRouter()
const route     = useRoute()
const authStore = useAuthStore()
const appStore  = useAppStore()

const loginError    = ref('')
const transitioning = ref(false)
const logoVisible   = ref(false)
const shakeBtn      = ref(false)

// Di app desktop (Electron), default "ingat saya" ke aktif — dipakai
// di satu laptop yang sama terus-menerus, jadi tidak perlu isi ulang
// email/password tiap kali token 12 jam yang biasa habis.
const isDesktopApp = navigator.userAgent.includes('Electron')
// Kartu ini dipasang dua kali di halaman yang sama, satu untuk layar HP dan
// satu untuk desktop, jadi id-nya harus berbeda per pasangan. Kalau makmur,
// tombol submit menempel ke form yang salah — yang kebetulan sedang
// disembunyikan CSS — dan Enter jadi tidak melakukan apa-apa.
const formId = `lp-form-${useId()}`

const form   = reactive({ email: '', password: '', remember: isDesktopApp })
const errors = reactive({ email: '', password: '' })

const entered = ref(false)
onMounted(() => { entered.value = true })

// Clear server error saat user mulai mengetik ulang
function onEmailInput(val) {
  form.email = val
  if (loginError.value) loginError.value = ''
}
function onPasswordInput(val) {
  form.password = val
  if (loginError.value) loginError.value = ''
}

function touchEmail()    { errors.email    = form.email    ? '' : 'Email wajib diisi.' }
function touchPassword() { errors.password = form.password ? '' : 'Password wajib diisi.' }
function validate()      { touchEmail(); touchPassword(); return !errors.email && !errors.password }

async function triggerShake() {
  shakeBtn.value = false
  // Tunggu DOM benar-benar lepas class-nya dulu, baru paksa reflow —
  // requestAnimationFrame saja tidak cukup karena render Vue di-batch,
  // jadi hilang-munculnya class bisa "digabung" jadi satu frame dan
  // animasi CSS-nya tidak restart kalau gagal login berturut-turut.
  await nextTick()
  void document.body.offsetHeight
  shakeBtn.value = true
  setTimeout(() => { shakeBtn.value = false }, 600)
}

function resolveLoginErrorMessage(err) {
  const status = err?.response?.status
  if (status === 401 || status === 422) return 'Email atau password kamu salah, tolong dicek kembali.'
  if (status === 429) return 'Terlalu banyak percobaan login, tunggu sebentar lalu coba lagi.'
  if (status >= 500) return 'Server sedang bermasalah, coba lagi beberapa saat lagi.'
  if (!err?.response) return 'Tidak bisa terhubung ke server, cek koneksi internet kamu.'
  return 'Login gagal, tolong coba lagi.'
}

async function handleLogin() {
  if (!validate()) return
  try {
    await authStore.login({ email: form.email, password: form.password, remember: form.remember })
    loginError.value = ''
    // Mulai door close — tunggu DOM flush dulu agar animasi tidak terpotong
    await new Promise(r => requestAnimationFrame(r))
    transitioning.value = true
    // Logo muncul setelah pintu hampir tertutup
    setTimeout(() => { logoVisible.value = true }, 380)
    // Navigasi setelah pintu benar-benar menutup + sedikit buffer
    setTimeout(() => {
      authStore.loading = false
      appStore.setFreshLogin()
      router.push(route.query.redirect || '/')
    }, 950)
  } catch (err) {
    loginError.value = resolveLoginErrorMessage(err)
    triggerShake()
  }
}
</script>
