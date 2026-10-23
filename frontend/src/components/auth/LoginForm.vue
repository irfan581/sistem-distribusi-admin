<template>
  <form :id="formId" @submit.prevent="emit('submit')" class="lp-form" novalidate autocomplete="on">

    <!-- Email -->
    <div class="ff" :class="{ 'ff--err': errors.email }">
      <label class="ff-label" :for="`${formId}-email`">Email</label>
      <div class="ff-wrap">
        <InputText
          :id="`${formId}-email`"
          :modelValue="form.email"
          @update:modelValue="emit('update:email', $event)"
          type="email"
          placeholder="email address"
          autocomplete="email"
          fluid
          :invalid="!!errors.email"
          @blur="emit('touchEmail')"
        />
      </div>
      <span class="ff-err-text">{{ errors.email }}&nbsp;</span>
    </div>

    <!-- Password -->
    <div class="ff" :class="{ 'ff--err': errors.password }">
      <label class="ff-label" :for="`${formId}-password`">Password</label>
      <div class="ff-wrap">
        <Password
          :inputId="`${formId}-password`"
          :modelValue="form.password"
          @update:modelValue="emit('update:password', $event)"
          placeholder="••••••••"
          :feedback="false"
          toggleMask
          autocomplete="current-password"
          fluid
          :invalid="!!errors.password"
          @blur="emit('touchPassword')"
        />
      </div>
      <span class="ff-err-text">{{ errors.password }}&nbsp;</span>
    </div>

    <!-- Remember -->
    <div class="lp-remember">
      <label class="rem-label">
        <Checkbox
          :modelValue="form.remember"
          @update:modelValue="emit('update:remember', $event)"
          binary
          :input-id="`${formId}-remember`"
        />
        <span class="rem-text">Ingat saya 30 hari</span>
      </label>
    </div>

  </form>
</template>

<script setup>
defineProps({
  form:   { type: Object, required: true },
  errors: { type: Object, required: true },
  // Kartu login ini dipasang dua kali di halaman yang sama, satu untuk layar
  // HP dan satu untuk desktop. Tanpa id yang berbeda per pasangan, kedua form
  // dan seluruh kolomnya bernama sama, dan browser selalu memilih yang
  // pertama ditemukan — yang kebetulan yang sedang disembunyikan CSS. Itu
  // sebabnya menekan label "Email" di desktop tidak memindahkan kursor ke
  // mana pun.
  formId: { type: String, required: true },
})

const emit = defineEmits([
  'submit',
  'update:email', 'update:password', 'update:remember',
  'touchEmail', 'touchPassword',
])
</script>
