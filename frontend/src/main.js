import { createApp, defineAsyncComponent } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import { definePreset } from '@primevue/themes'

const TMKPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50:  '{orange.50}',
      100: '{orange.100}',
      200: '{orange.200}',
      300: '{orange.300}',
      400: '{orange.400}',
      500: '{orange.500}',
      600: '{orange.600}',
      700: '{orange.700}',
      800: '{orange.800}',
      900: '{orange.900}',
      950: '{orange.950}',
    },
    colorScheme: {
      light: {
        primary: {
          color: '{orange.500}',
          inverseColor: '#ffffff',
          hoverColor: '{orange.600}',
          activeColor: '{orange.700}',
        },
        highlight: {
          background: '{orange.50}',
          focusBackground: '{orange.100}',
          color: '{orange.700}',
          focusColor: '{orange.800}',
        },
      },
      dark: {
        primary: {
          color: '{orange.400}',
          inverseColor: '{surface.900}',
          hoverColor: '{orange.300}',
          activeColor: '{orange.200}',
        },
        highlight: {
          background: 'rgba(249,115,22,0.16)',
          focusBackground: 'rgba(249,115,22,0.24)',
          color: '{orange.300}',
          focusColor: '{orange.200}',
        },
      },
    },
  },
})
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import DialogService from 'primevue/dialogservice'

// ── PrimeVue: static (dibutuhkan di halaman login & semua halaman) ──────────
import Button       from 'primevue/button'
import InputText    from 'primevue/inputtext'
import Password     from 'primevue/password'
import Checkbox     from 'primevue/checkbox'
import Toast        from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import ConfirmPopup  from 'primevue/confirmpopup'
import Message      from 'primevue/message'
import Tooltip      from 'primevue/tooltip'
// DataTable & Column harus static — PrimeVue DataTable scan Column di slot saat setup
import DataTable    from 'primevue/datatable'
import Column       from 'primevue/column'

// ── PrimeVue: async (hanya load saat admin panel pertama dibuka) ─────────────
const Select        = defineAsyncComponent(() => import('primevue/select'))
const DatePicker    = defineAsyncComponent(() => import('primevue/datepicker'))
const Textarea      = defineAsyncComponent(() => import('primevue/textarea'))
const InputNumber   = defineAsyncComponent(() => import('primevue/inputnumber'))
const ToggleSwitch  = defineAsyncComponent(() => import('primevue/toggleswitch'))
const ToggleButton  = defineAsyncComponent(() => import('primevue/togglebutton'))
const SelectButton  = defineAsyncComponent(() => import('primevue/selectbutton'))
const Paginator     = defineAsyncComponent(() => import('primevue/paginator'))
const IconField     = defineAsyncComponent(() => import('primevue/iconfield'))
const InputIcon     = defineAsyncComponent(() => import('primevue/inputicon'))
const InputMask     = defineAsyncComponent(() => import('primevue/inputmask'))
const Card          = defineAsyncComponent(() => import('primevue/card'))
const Panel         = defineAsyncComponent(() => import('primevue/panel'))
const Toolbar       = defineAsyncComponent(() => import('primevue/toolbar'))
const Divider       = defineAsyncComponent(() => import('primevue/divider'))
const Splitter      = defineAsyncComponent(() => import('primevue/splitter'))
const SplitterPanel = defineAsyncComponent(() => import('primevue/splitterpanel'))
const ScrollPanel   = defineAsyncComponent(() => import('primevue/scrollpanel'))
// DataTable & Column sudah static import di atas
const Avatar        = defineAsyncComponent(() => import('primevue/avatar'))
const Badge         = defineAsyncComponent(() => import('primevue/badge'))
const Tag           = defineAsyncComponent(() => import('primevue/tag'))
const Skeleton      = defineAsyncComponent(() => import('primevue/skeleton'))
const ProgressSpinner = defineAsyncComponent(() => import('primevue/progressspinner'))
const ProgressBar   = defineAsyncComponent(() => import('primevue/progressbar'))
const Chip          = defineAsyncComponent(() => import('primevue/chip'))
const Menu          = defineAsyncComponent(() => import('primevue/menu'))
const Menubar       = defineAsyncComponent(() => import('primevue/menubar'))
const PanelMenu     = defineAsyncComponent(() => import('primevue/panelmenu'))
const Breadcrumb    = defineAsyncComponent(() => import('primevue/breadcrumb'))
const Dialog        = defineAsyncComponent(() => import('primevue/dialog'))
const Popover       = defineAsyncComponent(() => import('primevue/popover'))

import router from './router'
import App from './App.vue'
import SalesAvatar from '@/components/common/SalesAvatar.vue'

import 'primeicons/primeicons.css'
import '@/assets/styles/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
// Judul kolom ikut ke tiap sel sebagai data-label, dipakai tampilan kartu di ponsel (main.css)
const labelKolom = (opsi) => {
  const judul = opsi?.column?.props?.header ?? opsi?.props?.header
  return { 'data-label': typeof judul === 'string' ? judul : '' }
}

app.use(PrimeVue, {
  pt: { datatable: { column: { bodyCell: labelKolom } } },
  theme: {
    preset: TMKPreset,
    options: {
      prefix: 'p',
      darkModeSelector: '.app-dark',
      cssLayer: false,
    },
  },
})
app.use(ToastService)
app.use(ConfirmationService)
app.use(DialogService)

app.component('Button', Button)
app.component('InputText', InputText)
app.component('Password', Password)
app.component('Checkbox', Checkbox)
app.component('Select', Select)
app.component('DatePicker', DatePicker)
app.component('Textarea', Textarea)
app.component('InputNumber', InputNumber)
app.component('ToggleSwitch', ToggleSwitch)
app.component('ToggleButton', ToggleButton)
app.component('SelectButton', SelectButton)
app.component('Paginator', Paginator)
app.component('IconField', IconField)
app.component('InputIcon', InputIcon)
app.component('InputMask', InputMask)
app.component('Card', Card)
app.component('Panel', Panel)
app.component('Toolbar', Toolbar)
app.component('Divider', Divider)
app.component('Splitter', Splitter)
app.component('SplitterPanel', SplitterPanel)
app.component('ScrollPanel', ScrollPanel)
app.component('DataTable', DataTable)
app.component('Column', Column)
app.component('Avatar', Avatar)
app.component('Badge', Badge)
app.component('Tag', Tag)
app.component('Skeleton', Skeleton)
app.component('ProgressSpinner', ProgressSpinner)
app.component('ProgressBar', ProgressBar)
app.component('Message', Message)
app.component('Chip', Chip)
app.component('Menu', Menu)
app.component('Menubar', Menubar)
app.component('PanelMenu', PanelMenu)
app.component('Breadcrumb', Breadcrumb)
app.component('Dialog', Dialog)
app.component('Popover', Popover)
app.component('Toast', Toast)
app.component('ConfirmDialog', ConfirmDialog)
app.component('ConfirmPopup', ConfirmPopup)
app.component('SalesAvatar', SalesAvatar)
app.directive('tooltip', Tooltip)

// Sembunyikan console error di production — hanya tampil saat development
if (import.meta.env.PROD) {
  app.config.errorHandler = () => {}
  app.config.warnHandler  = () => {}
}

app.mount('#app')
