<template>
  <div class="sc">
    <div class="sc-head">
      <div class="sc-head-icon sc-head-icon-blue"><i class="pi pi-palette" /></div>
      <div class="sc-head-text">
        <h3 class="sc-head-title">Tampilan</h3>
        <p class="sc-head-sub">Tema dan preferensi visual</p>
      </div>
    </div>

    <div class="sc-body theme-body">

      <!-- Two theme option cards side by side -->
      <div class="theme-options">

        <!-- Light mode option -->
        <div
          class="theme-option"
          :class="{ 'theme-option-active': !darkMode }"
          @click="darkMode && emit('toggleDark')"
        >
          <!-- Preview mockup -->
          <div class="theme-mockup theme-mockup-light">
            <div class="tm-sidebar">
              <div class="tm-sb-logo" />
              <div class="tm-sb-item" />
              <div class="tm-sb-item tm-sb-item-active" />
              <div class="tm-sb-item" />
              <div class="tm-sb-item" />
            </div>
            <div class="tm-main">
              <div class="tm-topbar">
                <div class="tm-tb-pill" />
                <div class="tm-tb-dot" />
              </div>
              <div class="tm-content">
                <div class="tm-card">
                  <div class="tm-bar" style="width:55%" />
                  <div class="tm-bar" style="width:80%" />
                  <div class="tm-bar tm-bar-short" />
                </div>
                <div class="tm-card">
                  <div class="tm-bar" style="width:70%" />
                  <div class="tm-bar tm-bar-short" style="width:45%" />
                </div>
              </div>
            </div>
          </div>

          <!-- Label + active indicator -->
          <div class="theme-option-foot">
            <div class="theme-option-info">
              <i class="pi pi-sun" />
              <span>Mode Terang</span>
            </div>
            <div class="theme-option-check" :class="{ active: !darkMode }">
              <i class="pi pi-check" />
            </div>
          </div>
        </div>

        <!-- Dark mode option -->
        <div
          class="theme-option"
          :class="{ 'theme-option-active': darkMode }"
          @click="!darkMode && emit('toggleDark')"
        >
          <!-- Preview mockup -->
          <div class="theme-mockup theme-mockup-dark">
            <div class="tm-sidebar">
              <div class="tm-sb-logo" />
              <div class="tm-sb-item" />
              <div class="tm-sb-item tm-sb-item-active" />
              <div class="tm-sb-item" />
              <div class="tm-sb-item" />
            </div>
            <div class="tm-main">
              <div class="tm-topbar">
                <div class="tm-tb-pill" />
                <div class="tm-tb-dot" />
              </div>
              <div class="tm-content">
                <div class="tm-card">
                  <div class="tm-bar" style="width:55%" />
                  <div class="tm-bar" style="width:80%" />
                  <div class="tm-bar tm-bar-short" />
                </div>
                <div class="tm-card">
                  <div class="tm-bar" style="width:70%" />
                  <div class="tm-bar tm-bar-short" style="width:45%" />
                </div>
              </div>
            </div>
          </div>

          <!-- Label + active indicator -->
          <div class="theme-option-foot">
            <div class="theme-option-info">
              <i class="pi pi-moon" />
              <span>Mode Gelap</span>
            </div>
            <div class="theme-option-check" :class="{ active: darkMode }">
              <i class="pi pi-check" />
            </div>
          </div>
        </div>

      </div>

      <!-- Current status + toggle row -->
      <div class="theme-toggle-row">
        <div class="theme-status-text">
          <i :class="darkMode ? 'pi pi-moon' : 'pi pi-sun'" class="theme-status-icon" />
          <span class="theme-status-label">
            {{ darkMode ? 'Mode Gelap aktif' : 'Mode Terang aktif' }}
          </span>
          <span class="theme-status-desc">
            {{ darkMode ? 'nyaman untuk malam hari' : 'cocok untuk siang hari' }}
          </span>
        </div>
        <ToggleSwitch :modelValue="darkMode" @update:modelValue="emit('toggleDark')" />
      </div>

    </div>
  </div>
</template>

<script setup>
defineProps({ darkMode: { type: Boolean, default: false } })
const emit = defineEmits(['toggleDark'])
</script>
