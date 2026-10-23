<template>
  <div class="master-armada-view">
    <PageHeader title="Armada &amp; Sopir" />

    <!-- scrollable: di layar HP sempit, 3 label tab (terutama "Kendaraan")
         bisa lebih lebar dari viewport — tanpa ini tab-nya diam-diam
         terpotong/dorong halaman melebar ke samping alih-alih bisa digeser.

         Isi tiap tab dijaga sendiri lewat `sudahDibuka`, bukan lewat prop
         `lazy` milik PrimeVue. Tanpa keduanya, PrimeVue merender ISI KETIGA
         tab sekaligus dan cuma menyembunyikan yang tidak aktif dengan v-show
         (lihat TabPanel.vue: `v-if="$pcTabs?.lazy ? active : true"`), sehingga
         sekali membuka halaman ini tiga tabel dirender dan tiga permintaan
         dikirim padahal yang dilihat satu. Sedangkan `lazy` membuang isi tab
         yang ditinggalkan, jadi bolak-balik tab berarti memuat ulang terus.
         Cara ini memasang tab hanya saat pertama dibuka, lalu membiarkannya
         hidup. -->
    <Tabs v-model:value="activeTab" scrollable>
      <TabList>
        <Tab value="driver">Driver</Tab>
        <Tab value="kendaraan">Kendaraan</Tab>
        <Tab value="checker">Checker</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="driver">
          <DriverView v-if="sudahDibuka.driver" dalam-tab />
        </TabPanel>
        <TabPanel value="kendaraan">
          <KendaraanView v-if="sudahDibuka.kendaraan" dalam-tab />
        </TabPanel>
        <TabPanel value="checker">
          <CheckerView v-if="sudahDibuka.checker" dalam-tab />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import PageHeader from '@/components/common/PageHeader.vue'
import DriverView from './DriverView.vue'
import KendaraanView from './KendaraanView.vue'
import CheckerView from './CheckerView.vue'

const route = useRoute()
const router = useRouter()

const TAB_VALID = ['driver', 'kendaraan', 'checker']

// Tab aktif hidup di query string (bukan cuma state lokal) supaya link yang
// dibagikan staf lain, tombol back, dan redirect dari route lama
// (/pengiriman/driver dkk.) semuanya mendarat di tab yang benar.
const activeTab = computed({
  get: () => (TAB_VALID.includes(route.query.tab) ? route.query.tab : 'driver'),
  set: (tab) => {
    if (tab === activeTab.value) return
    router.replace({ query: { ...route.query, tab } })
  },
})

// Tab yang pernah dibuka, supaya isinya dipasang sekali lalu dibiarkan hidup.
const sudahDibuka = reactive({ driver: false, kendaraan: false, checker: false })

watch(activeTab, (tab) => { sudahDibuka[tab] = true }, { immediate: true })
</script>

<style scoped>
.master-armada-view :deep(.p-tabpanel) {
  padding-top: 0.75rem;
}
</style>
