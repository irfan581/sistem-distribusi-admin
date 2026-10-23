<template>
  <div class="dm-view">
    <PageHeader
      :title="mode === 'subsidi' ? 'Muat Subsidi' : 'Muat Non Subsidi'"
      :subtitle="mode === 'subsidi' ? 'Rencana pengiriman pupuk subsidi: input sore, assign driver besok pagi.' : 'Rencana pengiriman pupuk non subsidi: input sore, assign driver besok pagi.'"
    >
      <template #actions>
        <Button icon="pi pi-user-minus" label="Izin Driver" severity="warn" outlined @click="showIzinDialog = true" />
        <Button v-if="can('create_pengiriman')" icon="pi pi-plus" label="Buat Rencana" @click="openTambah" />
      </template>
    </PageHeader>

    <!-- Filter bar -->
    <Card class="filter-card">
      <template #content>
        <div class="filter-bar">
          <IconField class="search-field">
            <InputIcon class="pi pi-search" />
            <InputText v-model="filters.search" :placeholder="mode === 'subsidi' ? 'Cari nama pengirim...' : 'Cari lokasi tujuan...'" @input="onSearch" />
          </IconField>
          <Select
            v-model="filters.belumDriver"
            :options="driverFilterOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Semua"
            style="min-width: 170px"
            showClear
            @change="loadData"
          />
          <DatePicker
            v-model="filters.tanggal"
            placeholder="Filter tanggal"
            dateFormat="dd/mm/yy"
            style="min-width: 160px"
            showClear
            @date-select="loadData"
            @clear-click="loadData"
          />
          <Button icon="pi pi-refresh" text plain @click="resetFilters" v-tooltip="'Reset filter'" />
        </div>
        <div class="stats-row" v-if="!store.loading">
          <span class="stat"><b>{{ store.meta.total }}</b> rencana</span>
          <span class="stat belum"><i class="pi pi-user" /> {{ countBelumDriver }} belum ada driver</span>
          <span class="stat sudah"><i class="pi pi-check-circle" /> {{ store.list.filter(i=>i.driver).length }} sudah ada driver</span>
          <span class="stat">
            <i class="pi pi-map-marker" />
            {{ store.list.reduce((s, i) => s + (i.jumlah_tujuan ?? 0), 0) }} {{ mode === 'subsidi' ? 'pengirim' : 'tujuan' }}
          </span>
        </div>
      </template>
    </Card>

    <!-- Table (desktop) -->
    <Card class="desktop-only">
      <template #content>
        <DataTable
          :value="store.list"
          :loading="store.loading"
          stripedRows
          dataKey="id"
        >
          <Column header="No." style="width: 56px; text-align: center">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column header="Tanggal / Rit" style="min-width: 130px">
            <template #body="{ data }">
              <div class="tanggal-rit-cell">
                <span class="date-text">{{ formatDate(data.tanggal_muatan) }}</span>
                <span class="rit-badge" :class="data.nomor_rit === 2 ? 'rit-2' : data.nomor_rit === 3 ? 'rit-3' : 'rit-1'">
                  Rit {{ data.nomor_rit ?? 1 }}
                </span>
              </div>
            </template>
          </Column>

          <Column header="Kendaraan" style="min-width: 150px">
            <template #body="{ data }">
              <div v-if="data.kendaraan" class="kendaraan-cell">
                <span class="polisi">{{ data.kendaraan.nomor_polisi }}</span>
                <span class="jenis">{{ data.kendaraan.jenis_kendaraan }}</span>
              </div>
              <span v-else class="belum-tag"><i class="pi pi-truck" /> Belum ditentukan</span>
            </template>
          </Column>

          <Column header="Driver" style="min-width: 150px">
            <template #body="{ data }">
              <div v-if="data.driver" class="driver-cell">
                <span class="nama">{{ data.driver.nama_driver }}</span>
                <span class="telp">{{ data.driver.no_telepon }}</span>
              </div>
              <span v-else class="belum-driver-tag"><i class="pi pi-user" /> Belum ada driver</span>
            </template>
          </Column>

          <Column :header="mode === 'subsidi' ? 'Pengirim' : 'Tujuan'" style="min-width: 220px">
            <template #body="{ data }">
              <div class="tujuan-cell">
                <span class="tujuan-badge">{{ data.jumlah_tujuan }} {{ mode === 'subsidi' ? 'nama' : 'toko' }}</span>
                <span class="tujuan-list">{{ data.lokasi_pengiriman }}</span>
              </div>
            </template>
          </Column>

          <Column header="Total Muatan" style="min-width: 110px">
            <template #body="{ data }">
              <span class="tonase">{{ fmtKg(data.total_muatan_ton) }} Kg</span>
            </template>
          </Column>

          <Column header="Progres" style="min-width: 130px">
            <template #body="{ data }">
              <div class="progres-cell">
                <span class="progres-status" :class="'st-' + (data.status_perjalanan || 'Menunggu').toLowerCase()">
                  {{ data.status_perjalanan || 'Menunggu' }}
                </span>
                <span class="progres-angka">
                  {{ data.tujuan_tuntas ?? 0 }}/{{ data.jumlah_tujuan }} tujuan
                  <template v-if="data.tujuan_bermasalah > 0">
                    &middot; <span class="progres-masalah">{{ data.tujuan_bermasalah }} bermasalah</span>
                  </template>
                </span>
              </div>
            </template>
          </Column>

          <Column header="Aksi" style="min-width: 150px; text-align: center">
            <template #body="{ data }">
              <div class="action-btns">
                <Button
                  icon="pi pi-camera"
                  size="small"
                  text
                  :disabled="!data.driver"
                  v-tooltip="data.driver ? 'Laporan Driver' : 'Belum ada driver'"
                  @click="openLaporan(data)"
                />
                <Button v-if="can('edit_pengiriman')" icon="pi pi-pencil" size="small" text v-tooltip="'Edit / Assign Driver'" @click="openEdit(data)" />
                <Button v-if="can('delete_pengiriman') && (data.status_perjalanan || 'Menunggu') === 'Menunggu' && !data.tujuan_tuntas" icon="pi pi-trash" size="small" text severity="danger" v-tooltip="'Hapus'" @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="empty-state">
              <i class="pi pi-truck" />
              <span>{{ hasFilters ? 'Tidak ada rencana sesuai filter' : 'Belum ada rencana pengiriman' }}</span>
              <Button v-if="!hasFilters && can('create_pengiriman')" label="Buat Rencana Pertama" size="small" outlined @click="openTambah" />
            </div>
          </template>
        </DataTable>

        <!-- Pagination -->
        <div class="pagination-bar" v-if="store.meta.total > 0">
          <Paginator
            :rows="perPage"
            :totalRecords="store.meta.total"
            :first="(store.meta.current_page - 1) * perPage"
            @page="onPage"
            :rowsPerPageOptions="PAGINATION.ROWS_OPTIONS"
          />
        </div>
      </template>
    </Card>

    <!-- Card list (mobile) -->
    <div class="mobile-only">
      <div v-if="store.loading" class="mobile-loading">
        <i class="pi pi-spin pi-spinner" /> Memuat data...
      </div>
      <TransitionGroup v-else-if="store.list.length" name="card-list" tag="div">
        <div v-for="(item, i) in store.list" :key="item.id" :style="{'--i': i}" class="muat-card">
          <div class="muat-card-top">
            <div class="muat-card-date">
              <i class="pi pi-calendar" />
              {{ formatDate(item.tanggal_muatan) }}
              <span class="rit-badge" :class="item.nomor_rit === 2 ? 'rit-2' : item.nomor_rit === 3 ? 'rit-3' : 'rit-1'">Rit {{ item.nomor_rit ?? 1 }}</span>
            </div>
            <span class="tonase">{{ fmtKg(item.total_muatan_ton) }} Kg</span>
          </div>

          <div class="muat-card-body">
            <div class="muat-card-row">
              <i class="pi pi-truck" />
              <span v-if="item.kendaraan">
                <b>{{ item.kendaraan.nomor_polisi }}</b>
                <span class="muted"> ({{ item.kendaraan.jenis_kendaraan }})</span>
              </span>
              <span v-else class="belum-tag">Kendaraan belum ditentukan</span>
            </div>
            <div class="muat-card-row">
              <i class="pi pi-user" />
              <span v-if="item.driver">
                <b>{{ item.driver.nama_driver }}</b>
                <span class="muted"> ({{ item.driver.no_telepon }})</span>
              </span>
              <span v-else class="belum-driver-tag">Belum ada driver</span>
            </div>
            <div class="muat-card-row">
              <i class="pi pi-map-marker" />
              <span class="tujuan-list">
                <span class="tujuan-badge">{{ item.jumlah_tujuan }} {{ mode === 'subsidi' ? 'nama' : 'toko' }}</span>
                {{ item.lokasi_pengiriman }}
              </span>
            </div>
          </div>

          <div class="muat-card-actions">
            <Button v-if="can('edit_pengiriman')" icon="pi pi-pencil" label="Edit" size="small" text @click="openEdit(item)" />
            <Button v-if="can('delete_pengiriman')" icon="pi pi-trash" size="small" text severity="danger" @click="confirmDelete(item)" />
          </div>
        </div>
      </TransitionGroup>
      <div v-else class="empty-state">
        <i class="pi pi-truck" />
        <span>{{ hasFilters ? 'Tidak ada rencana sesuai filter' : 'Belum ada rencana pengiriman' }}</span>
        <Button v-if="!hasFilters && can('create_pengiriman')" label="Buat Rencana Pertama" size="small" outlined @click="openTambah" />
      </div>

      <div class="pagination-bar" v-if="store.meta.total > 0">
        <Paginator
          :rows="perPage"
          :totalRecords="store.meta.total"
          :first="(store.meta.current_page - 1) * perPage"
          @page="onPage"
          :rowsPerPageOptions="PAGINATION.ROWS_OPTIONS"
        />
      </div>
    </div>

    <!-- Dialog Tambah / Edit -->
    <Dialog v-model:visible="showDialog" :header="editId ? 'Edit Rencana Muat' : 'Buat Rencana Muat'" modal :style="{ width: 'min(700px, 95vw)' }" :closable="!store.saving">
      <div class="form-grid">

        <!-- Langkah 1: Tanggal + Rit -->
        <div class="form-section-title">
          <i class="pi pi-calendar" /> Jadwal
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Tanggal Kiriman <span class="req">*</span></label>
            <DatePicker v-model="form.tanggal_muatan" dateFormat="dd/mm/yy" class="full-width" />
          </div>
          <div class="form-group">
            <label>Nomor Rit <span class="req">*</span></label>
            <InputNumber
              v-model="form.nomor_rit"
              :min="1"
              :max="99"
              showButtons
              buttonLayout="horizontal"
              decrementButtonIcon="pi pi-minus"
              incrementButtonIcon="pi pi-plus"
              class="full-width"
            />
            <small class="hint">Terisi otomatis dari rit driver hari itu</small>
          </div>
        </div>

        <!-- Langkah 2: Penugasan Armada (opsional) -->
        <div class="form-section-title" style="margin-top: 0.25rem">
          <i class="pi pi-truck" /> Driver &amp; Armada
          <span class="optional-badge">opsional, bisa diisi besok</span>
        </div>

        <div class="form-row armada-row">
          <!-- Pilih Driver dulu -->
          <div class="form-group">
            <label>Driver</label>
            <Select
              v-model="form.driver_id"
              :options="driverOptionsUrut"
              optionLabel="label"
              optionValue="id"
              placeholder="Pilih driver..."
              :filterFields="['cari']"
              :filterMatchMode="ATURAN_SEMUA_KATA"
              filterPlaceholder="Ketik nama atau nomor HP..."
              filter showClear resetFilterOnHide class="full-width"
              @filter="e => (cariDriver = e.value)"
              @hide="cariDriver = ''"
            >
              <template #option="{ option }">
                <div class="driver-option">
                  <span>{{ option.label }}</span>
                  <small v-if="option.no_telepon">{{ option.no_telepon }}</small>
                </div>
              </template>
            </Select>
          </div>

          <!-- Kendaraan — auto dari driver atau pilih manual -->
          <div class="armada-kendaraan-wrap">
            <label>Kendaraan</label>
            <!-- Driver dipilih, ada kendaraan default, tidak override -->
            <template v-if="defaultKendaraan && !kendaraanOverride">
              <div class="armada-card">
                <div class="armada-card-icon"><i class="pi pi-truck" /></div>
                <div class="armada-card-body">
                  <span class="armada-polisi">
                    {{ defaultKendaraan.nomor_polisi }}
                    <span class="armada-kapasitas">· {{ fmtKg(defaultKendaraan.kapasitas_muatan_ton) }} Kg</span>
                  </span>
                  <span class="armada-detail" :title="defaultKendaraan.jenis_kendaraan">{{ defaultKendaraan.jenis_kendaraan }}</span>
                </div>
                <Button
                  label="Ganti"
                  icon="pi pi-pencil"
                  size="small" text severity="secondary"
                  @click="kendaraanOverride = true"
                  v-tooltip="'Truk sedang servis? Ganti ke kendaraan lain'"
                />
              </div>
            </template>

            <!-- Override: pilih kendaraan lain -->
            <template v-else>
              <div class="armada-override">
                <Select
                  v-model="form.kendaraan_id"
                  :options="kendaraanOptionsUrut"
                  optionLabel="label"
                  optionValue="id"
                  placeholder="Pilih kendaraan pengganti..."
                  :filterMatchMode="ATURAN_SEMUA_KATA"
                  filterPlaceholder="Ketik nomor polisi..."
                  filter showClear resetFilterOnHide class="full-width"
                  @filter="e => (cariKendaraan = e.value)"
                  @hide="cariKendaraan = ''"
                >
                  <!-- Nomor polisi, jenis, dan kapasitas dipecah jadi dua
                       baris. Sebagai satu baris panjang, "AD 2305 WE —
                       MITSUBISHI FUSO FN 527 (9000 Kg)" mentok tepi layar HP
                       dan nomor polisinya — satu-satunya bagian yang dicari
                       orang — tenggelam di antara keterangan lain. -->
                  <template #option="{ option }">
                    <div class="kendaraan-option">
                      <strong>{{ option.nomor_polisi }}</strong>
                      <small>{{ option.jenis_kendaraan }} · {{ fmtKg(option.kapasitas_muatan_ton) }} Kg</small>
                    </div>
                  </template>

                  <!-- Yang sudah terpilih cukup nomor polisi dan kapasitasnya.
                       Jenis kendaraan sudah tidak menentukan apa-apa begitu
                       pilihannya dijatuhkan, dan di kotak selebar layar HP ia
                       cuma memaksa teksnya terpotong. -->
                  <template #value="{ value, placeholder }">
                    <template v-if="value">
                      <span class="kendaraan-terpilih">
                        <strong>{{ pilihanKendaraan(value)?.nomor_polisi }}</strong>
                        <small>{{ fmtKg(pilihanKendaraan(value)?.kapasitas_muatan_ton) }} Kg</small>
                      </span>
                    </template>
                    <span v-else class="kendaraan-placeholder">{{ placeholder }}</span>
                  </template>
                </Select>
                <Button
                  v-if="defaultKendaraan"
                  icon="pi pi-arrow-left"
                  size="small" text
                  v-tooltip="'Kembali ke kendaraan tetap: ' + defaultKendaraan.nomor_polisi"
                  @click="resetKendaraan"
                />
              </div>
              <small v-if="defaultKendaraan" class="hint" style="color:#d97706">
                <i class="pi pi-info-circle" /> Tetap: <b>{{ defaultKendaraan.nomor_polisi }}</b>
              </small>
              <small v-else-if="!form.driver_id" class="hint">
                Pilih driver dulu, atau langsung pilih kendaraan
              </small>
              <small v-if="selectedKendaraan && kendaraanOverride" class="hint">
                Kapasitas: <b>{{ fmtKg(selectedKendaraan.kapasitas_muatan_ton) }} Kg</b>
              </small>
            </template>
          </div>
        </div>
        <small v-if="defaultKendaraan && !kendaraanOverride" class="hint">
          <i class="pi pi-info-circle" /> Kendaraan tetap driver ini otomatis terpilih. Klik "Ganti" kalau truk sedang servis.
        </small>

        <!-- Langkah 3: Daftar Tujuan -->
        <div class="form-section-title" style="margin-top: 0.25rem">
          <i class="pi pi-map-marker" /> {{ mode === 'subsidi' ? 'Daftar Nama' : 'Daftar Tujuan' }}
          <span class="tujuan-count">{{ form.items.length }} {{ mode === 'subsidi' ? 'nama' : 'toko' }}</span>
        </div>

        <div class="items-list">
          <div v-for="(item, idx) in form.items" :key="item._key" class="item-card">
            <div class="item-card-head">
              <span class="item-num-label">
                <i class="pi pi-map-marker" />
                {{ mode === 'subsidi' ? 'Nama' : 'Tujuan' }} {{ idx + 1 }}
              </span>
              <Button icon="pi pi-times" text severity="danger" size="small" v-tooltip="'Hapus'" :disabled="form.items.length === 1" @click="removeItem(idx)" />
            </div>

            <!-- Subsidi: hanya nama pengirim -->
            <template v-if="mode === 'subsidi'">
              <div class="form-group">
                <label>Nama <span class="req">*</span></label>
                <InputText v-model="item.lokasi_pengiriman" placeholder="Nama pengirim..." class="full-width" />
              </div>
            </template>

            <!-- Non-subsidi: toko dipilih dari Kunjungan Sales, bukan diketik -->
            <template v-else>
              <div class="form-group">
                <label>Toko Tujuan <span class="req">*</span></label>
                <AutoComplete
                  v-model="item._toko"
                  :inputId="`toko-${item._key}`"
                  :suggestions="item._cariToko"
                  optionLabel="nama_kios"
                  placeholder="Cari nama toko..."
                  fluid
                  @complete="(e) => cariToko(item, e)"
                  @item-select="(e) => pilihToko(item, e.value)"
                >
                  <template #option="{ option }">
                    <div class="toko-option">
                      <strong>{{ option.nama_kios }}</strong>
                      <small><i class="pi pi-map-marker" /> {{ option.wilayah }} · {{ option.alamat_terakhir || 'Belum ada alamat' }}</small>
                    </div>
                  </template>
                </AutoComplete>
                <small v-if="tokoBelumDipilih(item)" class="hint overload-hint">
                  <i class="pi pi-exclamation-triangle" /> Pilih toko dari daftar hasil pencarian, jangan hanya mengetik.
                </small>
              </div>
              <div v-if="item.toko_id && typeof item._toko === 'object' && item._toko" class="form-group">
                <label>Alamat</label>
                <!-- Alamat yang sudah ada hanya diubah lewat Data Toko, bukan dari sini. -->
                <div v-if="item._toko.alamat_terakhir" class="alamat-tetap">
                  <i class="pi pi-map-marker" /> {{ item._toko.alamat_terakhir }}
                </div>
                <template v-else>
                  <InputText v-model="item.alamat" maxlength="255" placeholder="Ketik alamat toko..." class="full-width" />
                  <small class="hint">Toko ini belum punya alamat. Alamat yang diketik ikut tersimpan ke Data Toko.</small>
                </template>
              </div>
              <div class="form-group">
                <label>Wilayah</label>
                <!-- Sudah cocok otomatis dari wilayah toko yang dipilih —
                     dropdown cuma muncul kalau admin sengaja klik "Ubah",
                     atau kalau pencocokan otomatis gagal (kode_area kosong). -->
                <div v-if="item.kode_area && !item._editWilayah" class="wilayah-auto">
                  <span>{{ labelWilayah(item.kode_area) }}</span>
                  <Button label="Ubah" text size="small" @click="item._editWilayah = true" />
                </div>
                <Select
                  v-else
                  v-model="item.kode_area"
                  :options="wilayahOptions"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Pilih wilayah..."
                  showClear filter
                  class="full-width"
                />
              </div>
            </template>

            <div class="form-group">
              <label>Keterangan</label>
              <InputText v-model="item.catatan_admin" maxlength="255" class="full-width" />
            </div>
          </div>
        </div>

        <div class="add-item-bar">
          <Button icon="pi pi-plus" :label="mode === 'subsidi' ? 'Tambah Nama' : 'Tambah Tujuan'" size="small" outlined @click="addItem" />
        </div>
        <small v-if="mode === 'non_subsidi' && editStatus === 'Menunggu'" class="hint">
          <i class="pi pi-info-circle" /> Tujuan diurutkan otomatis mulai dari yang terdekat dengan gudang. Toko yang belum punya lokasi ditaruh di akhir.
        </small>

        <!-- Langkah 4: Total Muatan (satu total untuk seluruh truk) -->
        <div class="form-section-title" style="margin-top: 0.25rem">
          <i class="pi pi-box" /> Total Muatan
        </div>
        <div class="form-group">
          <label>Total Muatan (Kg) <span class="req">*</span></label>
          <InputNumber
            :modelValue="form.total_muatan_ton != null ? Math.round(form.total_muatan_ton * 1000) : null"
            :min="1"
            :maxFractionDigits="0"
            suffix=" Kg"
            placeholder="Contoh: 8000"
            class="full-width"
            :class="{ 'input-overload': isOverload }"
            @input="(e) => { form.total_muatan_ton = e.value != null ? e.value / 1000 : null }"
          />
          <small class="hint" v-if="selectedKendaraan && !isOverload">
            <i class="pi pi-info-circle" /> Kapasitas {{ selectedKendaraan.nomor_polisi }}: <b>{{ fmtKg(selectedKendaraan.kapasitas_muatan_ton) }} Kg</b>
          </small>
          <small v-if="isOverload" class="hint overload-hint">
            <i class="pi pi-exclamation-triangle" /> Melebihi kapasitas! {{ fmtKg(totalTon) }} Kg / maks {{ fmtKg(selectedKendaraan?.kapasitas_muatan_ton) }} Kg
          </small>
        </div>

      </div>

      <template #footer>
        <Button label="Batal" severity="secondary" text @click="showDialog = false" :disabled="store.saving" />
        <span v-tooltip.top="isOverload ? 'Total muatan melebihi kapasitas kendaraan' : (adaTokoBelumDipilih ? 'Pilih toko dari daftar untuk setiap tujuan' : '')">
          <Button :label="editId ? 'Simpan Perubahan' : 'Simpan Rencana'" icon="pi pi-check" :loading="store.saving" :disabled="isOverload || adaTokoBelumDipilih" @click="doSave" />
        </span>
      </template>
    </Dialog>

    <!-- Laporan driver: hasil kerja di lapangan berikut foto buktinya -->
    <LaporanDriverDialog v-model:visible="showLaporanDialog" :rit-id="laporanRitId" />

    <!-- Dialog Izin Driver -->
    <Dialog v-model:visible="showIzinDialog" header="Izin Driver Hari Ini" modal :style="{ width: 'min(520px, 95vw)' }">
      <div class="izin-body">
        <div class="izin-form">
          <div class="form-group">
            <label>Driver <span class="req">*</span></label>
            <Select v-model="izinForm.driver_id" :options="izinDriverOptionsUrut" optionLabel="nama_driver" optionValue="id"
              placeholder="Pilih driver..." :filterMatchMode="ATURAN_SEMUA_KATA" filterPlaceholder="Ketik nama driver..."
              filter resetFilterOnHide class="full-width"
              @filter="e => (cariDriverIzin = e.value)" @hide="cariDriverIzin = ''" />
          </div>
          <div class="form-group">
            <label>Tanggal <span class="req">*</span></label>
            <DatePicker v-model="izinForm.tanggal" dateFormat="dd/mm/yy" class="full-width" />
          </div>
          <div class="form-group">
            <label>Keterangan <span class="req">*</span></label>
            <Select v-model="izinForm.keterangan" :options="['Sakit','Cuti','Izin','Lainnya']" placeholder="Pilih..." class="full-width" />
          </div>
          <div class="form-group">
            <label>Catatan</label>
            <InputText v-model="izinForm.catatan" placeholder="Opsional..." class="full-width" />
          </div>
          <Button label="Tambah Izin" icon="pi pi-plus" @click="tambahIzin" :loading="izinSaving" class="full-width" />
        </div>

        <div class="izin-list" v-if="izinList.length">
          <div class="izin-list-title">Izin pada tanggal ini</div>
          <div v-for="item in izinList" :key="item.id" class="izin-item">
            <div>
              <span class="izin-nama">{{ item.nama_driver }}</span>
              <Tag :value="item.keterangan" :severity="item.keterangan === 'Sakit' ? 'danger' : 'warn'" style="margin-left:6px;font-size:0.7rem" />
            </div>
            <div class="izin-catatan" v-if="item.catatan">{{ item.catatan }}</div>
            <Button icon="pi pi-trash" text severity="danger" size="small" @click="hapusIzin(item.id)" />
          </div>
        </div>
        <p v-else class="izin-empty">Belum ada driver izin pada tanggal yang dipilih.</p>
      </div>

      <template #footer>
        <Button label="Tutup" severity="secondary" text @click="showIzinDialog = false" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useStaleRefresh } from '@/composables/useStaleRefresh'

const props = defineProps({
  mode: { type: String, default: 'non_subsidi' },
})
const mode = computed(() => props.mode)
import { useDaftarMuatStore } from '@/stores/daftarMuat.store'
import { daftarMuatService } from '@/services/daftarMuat.service'
import LaporanDriverDialog from '@/components/pengiriman/LaporanDriverDialog.vue'
import { kendaraanService } from '@/services/kendaraan.service'
import { driverService } from '@/services/driver.service'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth.store'
import { usePermission } from '@/composables/usePermission'
import { debounce } from '@/utils/helpers'
import { urutkanTujuan } from '@/utils/urutanTujuan'
import { ATURAN_SEMUA_KATA, urutkanHasilCari, kataTerpanjang } from '@/utils/pencarian'
import { PAGINATION, safeRows } from '@/utils/constants'
import PageHeader from '@/components/common/PageHeader.vue'
import http from '@/services/http'
import AutoComplete from 'primevue/autocomplete'

const store     = useDaftarMuatStore()
const confirm   = useConfirm()
const toast     = useToast()
const authStore = useAuthStore()
const { can }   = usePermission()

const showDialog       = ref(false)
const editId           = ref(null)
const editStatus       = ref('Menunggu')
const kendaraanOptions = ref([])
const driverOptions    = ref([])
// Kata yang sedang diketik di kotak cari dropdown; dipakai untuk mendahulukan nama yang diawali kata itu.
const cariDriver       = ref('')
const cariKendaraan    = ref('')
const driverOptionsUrut    = computed(() => urutkanHasilCari(driverOptions.value, cariDriver.value, d => d.cari))
const kendaraanOptionsUrut = computed(() => urutkanHasilCari(kendaraanOptions.value, cariKendaraan.value, k => k.label))
const skipAutoDriver   = ref(false)
const kendaraanOverride = ref(false)
const currentPage      = ref(1)
const perPage          = ref(PAGINATION.DEFAULT_ROWS)

// Laporan driver — isinya diambil komponen dialognya sendiri saat dibuka.
const showLaporanDialog = ref(false)
const laporanRitId      = ref(null)

// Izin driver
const showIzinDialog   = ref(false)
const izinSaving       = ref(false)
const izinList         = ref([])
const izinDriverOptions= ref([])
const cariDriverIzin    = ref('')
const izinDriverOptionsUrut = computed(() => urutkanHasilCari(izinDriverOptions.value, cariDriverIzin.value, d => d.nama_driver))
const izinForm         = reactive({ driver_id: null, tanggal: new Date(), keterangan: 'Izin', catatan: '' })

function emptyItem() {
  // toko_id/_toko/_cariToko dipakai mode non-subsidi (toko dipilih dari
  // Kunjungan Sales, bukan diketik). _toko & _cariToko murni state UI untuk
  // AutoComplete, tidak pernah dikirim ke backend (payload di doSave() dibangun
  // eksplisit per field).
  return { _key: kunciBaru(), lokasi_pengiriman: '', kode_area: '', catatan_admin: '', toko_id: null, alamat: '', _toko: null, _cariToko: [], _editWilayah: false }
}

// Kunci tetap per kartu tujuan, karena urutan kartu berubah saat diurutkan otomatis.
let nomorKunci = 0
function kunciBaru() { return ++nomorKunci }

// Rit yang sudah jalan tidak diurutkan ulang: backend menolak perubahan urutan tujuannya.
function susunUlang() {
  if (mode.value === 'non_subsidi' && editStatus.value === 'Menunggu') form.items = urutkanTujuan(form.items)
}

// Server mencari satu potongan teks utuh, jadi "lia rus" tidak akan cocok dengan
// "Lia (UD RUSDELIMA)". Kirim kata terpanjang saja, sisanya disaring dan diurutkan di sini.
const cariTokoDebounced = debounce(async (item, query) => {
  try {
    const hasil = await daftarMuatService.cariTokoTujuan(kataTerpanjang(query))
    item._cariToko = urutkanHasilCari(hasil, query, t => t.nama_kios)
  } catch {
    item._cariToko = []
  }
}, 300)

function cariToko(item, event) {
  cariTokoDebounced(item, event.query)
}

function pilihToko(item, toko) {
  item.toko_id           = toko.id
  item.lokasi_pengiriman = toko.nama_kios
  item.alamat            = ''
  item._toko              = toko

  // Wilayah toko (nama bebas dari Master Toko, mis. "Nganjuk") dicocokkan
  // ke kode area (mis. "NGJ") lewat nama wilayahnya, bukan disamakan begitu
  // saja — dua daftar ini punya bentuk beda (kode singkat vs nama penuh).
  // Kalau tidak ketemu padanannya, dibiarkan kosong untuk dipilih manual.
  const cocok = wilayahList.find(
    w => w.nama.toLowerCase() === (toko.wilayah ?? '').trim().toLowerCase()
  )
  item.kode_area    = cocok ? cocok.kode : ''
  item._editWilayah = false
  susunUlang()

  // Nama toko yang panjang tampil dari awal, bukan ujungnya; di HP keyboard sekalian ditutup.
  // Dua kali setTimeout: AutoComplete memfokuskan ulang kolomnya di setTimeout(0) sesudah item-select.
  setTimeout(() => setTimeout(() => {
    const kolom = document.getElementById(`toko-${item._key}`)
    if (!kolom) return
    kolom.scrollLeft = 0
    if (matchMedia('(pointer: coarse)').matches) kolom.blur()
  }))
}

function labelWilayah(kode) {
  return wilayahList.find(w => w.kode === kode)?.nama ?? kode
}

function tokoBelumDipilih(item) {
  // Sebelum toko dipilih, PrimeVue AutoComplete mengisi v-model dengan teks
  // mentah yang diketik (string), bukan objek toko — itu penanda "sudah
  // mengetik tapi belum klik salah satu hasil pencarian".
  if (mode.value !== 'non_subsidi') return false
  const mengetikBelumPilih = typeof item._toko === 'string' && item._toko.trim() !== ''
  return mengetikBelumPilih || (!item.toko_id && !!item.lokasi_pengiriman?.trim())
}

const form = reactive({
  kendaraan_id:     null,
  driver_id:        null,
  tanggal_muatan:   null,
  nomor_rit:        1,
  total_muatan_ton: null,
  items:            [emptyItem()],
})

async function loadIzin() {
  const tanggal = izinForm.tanggal instanceof Date
    ? toLocalYMD(izinForm.tanggal)
    : izinForm.tanggal
  const res = await http.get('/admin/pengiriman/driver-izin', { params: { tanggal } })
  izinList.value = res.data.data
}

async function tambahIzin() {
  if (!izinForm.driver_id || !izinForm.keterangan) return toast.warn('Pilih driver dan keterangan')
  const tanggal = izinForm.tanggal instanceof Date
    ? toLocalYMD(izinForm.tanggal)
    : izinForm.tanggal
  izinSaving.value = true
  try {
    await http.post('/admin/pengiriman/driver-izin', { ...izinForm, tanggal })
    toast.success('Izin driver disimpan')
    await loadIzin()
  } catch { toast.error('Gagal menyimpan') } finally { izinSaving.value = false }
}

async function hapusIzin(id) {
  await http.delete(`/admin/pengiriman/driver-izin/${id}`)
  toast.success('Izin dihapus')
  await loadIzin()
}

watch(showIzinDialog, async (open) => {
  if (!open) return
  if (!izinDriverOptions.value.length) {
    const res = await http.get('/admin/pengiriman/driver-izin/drivers')
    izinDriverOptions.value = res.data.data
  }
  await loadIzin()
})

watch(() => izinForm.tanggal, () => { if (showIzinDialog.value) loadIzin() })

// Auto-detect nomor rit berikutnya saat driver atau tanggal berubah (hanya saat create baru)
watch([() => form.driver_id, () => form.tanggal_muatan], async () => {
  if (!editId.value) {
    form.nomor_rit = await nextRit(form.driver_id, form.tanggal_muatan)
  }
})

// Auto-fill kendaraan default driver saat driver dipilih (kecuali sedang edit atau override)
watch(() => form.driver_id, (driverId) => {
  if (skipAutoDriver.value || kendaraanOverride.value) return
  const kendaraan = kendaraanOptions.value.find(k => k.driver_id === driverId)
  form.kendaraan_id = kendaraan?.id ?? null
  kendaraanOverride.value = false
})

function resetKendaraan() {
  kendaraanOverride.value = false
  const kendaraan = kendaraanOptions.value.find(k => k.driver_id === form.driver_id)
  form.kendaraan_id = kendaraan?.id ?? null
}

const driverFilterOptions = [
  { label: 'Belum ada driver', value: 'belum' },
  { label: 'Sudah ada driver',  value: 'sudah' },
]

const wilayahList = [
  { kode: 'BJN',  nama: 'Bojonegoro' },
  { kode: 'TRT',  nama: 'Tuban' },
  { kode: 'TTSJ', nama: 'Tuban Selatan / Jenu' },
  { kode: 'NGJ',  nama: 'Nganjuk' },
  { kode: 'J86',  nama: 'Jombang' },
  { kode: 'KDR',  nama: 'Kediri' },
  { kode: 'TLG',  nama: 'Tulungagung' },
  { kode: 'TRG',  nama: 'Trenggalek' },
  { kode: 'BLT',  nama: 'Blitar' },
  { kode: 'MDN',  nama: 'Madiun' },
  { kode: 'MGT',  nama: 'Magetan' },
  { kode: 'NGW',  nama: 'Ngawi' },
  { kode: 'PNR',  nama: 'Ponorogo' },
  { kode: 'WNG',  nama: 'Wonogiri' },
]

const wilayahOptions = wilayahList.map(w => ({ label: `${w.nama} (${w.kode})`, value: w.kode }))

const filters = reactive({ search: '', tanggal: null, belumDriver: null })

const hasFilters        = computed(() => filters.search || filters.tanggal || filters.belumDriver)
const selectedKendaraan = computed(() => kendaraanOptions.value.find(k => k.id === form.kendaraan_id) ?? null)

/** Dipakai template #value milik Select, yang hanya menerima id-nya saja. */
function pilihanKendaraan(id) {
  return kendaraanOptions.value.find(k => k.id === id) ?? null
}
const defaultKendaraan  = computed(() => form.driver_id ? (kendaraanOptions.value.find(k => k.driver_id === form.driver_id) ?? null) : null)
const totalTon          = computed(() => parseFloat(form.total_muatan_ton) || 0)
const isOverload        = computed(() => {
  if (!selectedKendaraan.value || totalTon.value === 0) return false
  return totalTon.value > selectedKendaraan.value.kapasitas_muatan_ton
})
const countBelumDriver  = computed(() => store.list.filter(i => !i.driver).length)
const adaTokoBelumDipilih = computed(() => form.items.some(i => tokoBelumDipilih(i)))

function fmtTon(val) {
  const n = parseFloat(val) || 0
  return parseFloat(n.toFixed(3)).toString()
}

function fmtKg(val) {
  return Math.round((parseFloat(val) || 0) * 1000).toLocaleString('id-ID')
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function toLocalYMD(d) {
  const dt = new Date(d)
  return `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}-${String(dt.getDate()).padStart(2,'0')}`
}

async function loadData(page = currentPage.value) {
  currentPage.value = page
  const params = {
    search:        filters.search   || undefined,
    tanggal:       filters.tanggal  ? toLocalYMD(filters.tanggal) : undefined,
    jenis_muatan:  mode.value,
    belum_driver:  filters.belumDriver === 'belum' ? 1 : undefined,
    sudah_driver:  filters.belumDriver === 'sudah' ? 1 : undefined,
    page:          page,
    per_page:      perPage.value,
  }
  try { await store.fetchAll(params) }
  catch { toast.error('Gagal memuat data daftar muat') }
}

// Daftar kendaraan dan driver hanya dipakai di dalam dialog buat/edit, jadi
// tidak ikut ditarik saat halaman dibuka. Sebelumnya dua permintaan ini jalan
// di setiap kunjungan walau dialognya tidak pernah disentuh.
let optionsDimuat = false

async function pastikanOptions() {
  if (optionsDimuat) return
  optionsDimuat = true

  try {
    await loadOptions()
  } catch (err) {
    // Gagal sekali tidak boleh mengunci selamanya: dialog berikutnya harus
    // boleh mencoba lagi, kalau tidak isinya kosong terus tanpa penjelasan.
    optionsDimuat = false
    toast.error('Gagal memuat daftar kendaraan dan driver')
    throw err
  }
}

async function loadOptions() {
  const [kRes, dRes] = await Promise.all([
    kendaraanService.getAll(),
    driverService.getAll({ status: 'Aktif' }),
  ])
  kendaraanOptions.value = (kRes.data ?? []).map(k => ({
    id:                   k.id,
    label:                `${k.nomor_polisi}, ${k.jenis_kendaraan} (${Math.round(k.kapasitas_muatan_ton * 1000)} Kg)`,
    nomor_polisi:         k.nomor_polisi,
    jenis_kendaraan:      k.jenis_kendaraan,
    kapasitas_muatan_ton: k.kapasitas_muatan_ton,
    driver_id:            k.driver?.id ?? k.driver_id ?? null,
  }))
  // Yang tampil cukup nama; nomor HP hanya di daftar pilihan dan tetap bisa dicari lewat `cari`.
  driverOptions.value = (dRes.data ?? []).map(d => ({
    id: d.id, label: d.nama_driver, no_telepon: d.no_telepon, cari: `${d.nama_driver} ${d.no_telepon ?? ''}`,
  }))
}

const onSearch = debounce(() => loadData(1), 350)

function resetFilters() {
  filters.search = ''; filters.tanggal = null; filters.belumDriver = null
  loadData(1)
}

function onPage(event) {
  perPage.value = safeRows(event.rows)
  loadData(event.page + 1)
}

function addItem()         { form.items.push(emptyItem()) }
function removeItem(idx)   { if (form.items.length > 1) { form.items.splice(idx, 1); susunUlang() } }

async function nextRit(driverId, tanggal) {
  if (!driverId || !tanggal) return 1
  try {
    // Hitung di backend lintas jenis muatan (subsidi + non-subsidi) supaya nomor rit tidak bentrok
    return await daftarMuatService.nextRit(driverId, toLocalYMD(tanggal))
  } catch {
    return 1
  }
}

function openTambah() {
  pastikanOptions()
  editId.value = null
  editStatus.value = 'Menunggu'
  skipAutoDriver.value = false
  kendaraanOverride.value = false
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  Object.assign(form, {
    kendaraan_id: null, driver_id: null,
    tanggal_muatan: tomorrow,
    nomor_rit: 1,
    total_muatan_ton: null,
    items: [emptyItem()],
  })
  showDialog.value = true
}


function openLaporan(row) {
  laporanRitId.value      = row.id
  showLaporanDialog.value = true
}

function openEdit(item) {
  pastikanOptions()
  editId.value          = item.id
  editStatus.value      = item.status_perjalanan || 'Menunggu'
  skipAutoDriver.value  = true
  kendaraanOverride.value = item.kendaraan?.id ? true : false
  Object.assign(form, {
    kendaraan_id:   item.kendaraan?.id ?? null,
    driver_id:      item.driver?.id ?? null,
    tanggal_muatan: item.tanggal_muatan ? new Date(item.tanggal_muatan) : new Date(),
    nomor_rit:      item.nomor_rit ?? 1,
    total_muatan_ton: item.total_muatan_ton != null ? parseFloat(item.total_muatan_ton) : null,
    items: (item.items ?? []).length > 0
      ? item.items.map(i => ({
          _key:              kunciBaru(),
          lokasi_pengiriman: i.lokasi_pengiriman,
          kode_area:         i.kode_area ?? '',
          catatan_admin:     i.catatan_admin ?? '',
          toko_id:           i.toko_id ?? null,
          alamat:            '',
          // Preview alamat dibangun dari data yang sudah ada di respons ini
          // (tanpa panggilan API tambahan) supaya admin langsung lihat alamat
          // tersimpan saat membuka form edit.
          _toko: i.toko_id ? {
            id: i.toko_id,
            nama_kios: i.lokasi_pengiriman,
            wilayah: i.kode_area ?? '',
            alamat_terakhir: i.alamat_otomatis ?? null,
            lat: i.alamat_lat ?? null,
            lng: i.alamat_lng ?? null,
          } : null,
          _cariToko: [],
          _editWilayah: false,
        }))
      : [emptyItem()],
  })
  showDialog.value = true
  setTimeout(() => { skipAutoDriver.value = false }, 100)
}

async function doSave() {
  if (!form.tanggal_muatan) { toast.error('Pilih tanggal kiriman'); return }
  if (!form.total_muatan_ton || form.total_muatan_ton <= 0) { toast.error('Isi total muatan truk'); return }
  // Rit lama yang dibuka ulang belum tentu sudah urut.
  susunUlang()
  const filled = form.items.filter(i => i.lokasi_pengiriman)
  if (filled.length === 0) { toast.error('Isi minimal 1 tujuan pengiriman'); return }

  // Non-subsidi: tujuan wajib dipilih dari daftar toko (autocomplete), bukan
  // diketik bebas, supaya alamat/koordinat terisi otomatis dari Kunjungan Sales.
  if (mode.value === 'non_subsidi' && filled.some(i => !i.toko_id)) {
    toast.error('Pilih toko dari daftar untuk setiap tujuan (jangan hanya mengetik nama)')
    return
  }

  // Subsidi tidak punya wilayah — bersihkan kode_area supaya tidak mencemari statistik wilayah
  const items = filled.map(i => ({
    lokasi_pengiriman: i.lokasi_pengiriman,
    kode_area:         mode.value === 'subsidi' ? null : (i.kode_area || null),
    catatan_admin:     i.catatan_admin?.trim() || null,
    toko_id:           mode.value === 'subsidi' ? null : i.toko_id,
    alamat:            mode.value === 'subsidi' ? null : (i.alamat?.trim() || null),
  }))

  const payload = {
    kendaraan_id:     form.kendaraan_id || null,
    driver_id:        form.driver_id || null,
    tanggal_muatan:   toLocalYMD(form.tanggal_muatan),
    nomor_rit:        form.nomor_rit ?? 1,
    jenis_muatan:     mode.value,
    total_muatan_ton: form.total_muatan_ton,
    items,
  }
  try {
    if (editId.value) {
      await store.update(editId.value, payload)
      toast.success('Rencana muat berhasil diperbarui')
    } else {
      await store.store(payload)
      toast.success('Rencana muat berhasil dibuat')
    }
    showDialog.value = false
    loadOptions()
  } catch (err) {
    toast.error(err?.response?.data?.message ?? 'Gagal menyimpan rencana')
  }
}

async function downloadSuratJalan(item) {
  try {
    const res  = await fetch(daftarMuatService.suratJalanUrl(item.id), { headers: { Authorization: `Bearer ${authStore.token}` } })
    if (!res.ok) throw new Error()
    const blob = await res.blob()
    const a    = document.createElement('a')
    a.href     = URL.createObjectURL(blob)
    a.download = `SuratJalan_${item.id}.pdf`
    a.click()
    URL.revokeObjectURL(a.href)
  } catch { toast.error('Gagal mendownload surat jalan') }
}

function confirmDelete(item) {
  confirm.require({
    message: `Hapus rencana ini (${item.jumlah_tujuan} tujuan, ${fmtKg(item.total_muatan_ton)} Kg)?`,
    header: 'Hapus Rencana',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    acceptLabel: 'Ya, Hapus',
    rejectLabel: 'Batal',
    accept: async () => {
      try {
        await store.destroy(item.id)
        toast.success('Rencana berhasil dihapus')
        if (optionsDimuat) loadOptions()
      } catch (err) {
        toast.error(err?.response?.data?.message ?? 'Gagal menghapus rencana')
      }
    },
  })
}

useStaleRefresh(() => loadData())
</script>

<style scoped>
.dm-view { display: flex; flex-direction: column; gap: 1.25rem; animation: fadeUp var(--dur-base) var(--ease) both; }

.filter-card :deep(.p-card-body) { padding: 0.75rem 1rem; }
.filter-bar { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.search-field { flex: 1; min-width: 210px; }

.stats-row { margin-top: 0.5rem; display: flex; align-items: center; gap: 1.2rem; font-size: 0.8rem; color: var(--p-text-muted-color); flex-wrap: wrap; }
.stat { display: flex; align-items: center; gap: 0.3rem; }
.stat b { color: var(--p-text-color); }
.stat.belum { color: var(--c-warning); }
.stat.sudah { color: var(--c-success); }

.kendaraan-cell, .driver-cell { display: flex; flex-direction: column; gap: 1px; }
.polisi, .nama { font-weight: 600; font-size: 0.875rem; }
.jenis, .telp  { font-size: 0.73rem; color: var(--p-text-muted-color); }
.tanggal-rit-cell { display: flex; flex-direction: column; gap: 3px; }
.date-text     { font-size: 0.8rem; color: var(--p-text-muted-color); }
.rit-badge { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 1px 7px; border-radius: 8px; width: fit-content; }
.rit-1 { background: var(--c-success-soft); color: var(--c-success); }
.rit-2 { background: var(--c-warning-soft); color: var(--c-warning); }
.rit-3 { background: var(--c-danger-soft); color: var(--c-danger); }
.rit-warn { color: var(--c-warning); font-size: 0.75rem; display: flex; align-items: center; gap: 0.3rem; margin-top: 2px; }
.tonase        { font-weight: 600; font-size: 0.875rem; color: var(--c-brand-600); }
.belum-tag     { font-size: 0.75rem; color: var(--t-muted); display: flex; align-items: center; gap: 0.3rem; }
.belum-driver-tag { font-size: 0.75rem; color: var(--c-warning); background: var(--c-warning-soft); padding: 2px 8px; border-radius: 8px; display: inline-flex; align-items: center; gap: 0.3rem; font-weight: 600; }

.tujuan-cell  { display: flex; flex-direction: column; gap: 2px; }
.tujuan-badge { display: inline-block; background: var(--c-brand-50,var(--c-brand-soft)); color: var(--c-brand-600); font-size: 0.72rem; font-weight: 700; padding: 1px 7px; border-radius: 8px; width: fit-content; }
.tujuan-list  { font-size: 0.75rem; color: var(--p-text-muted-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }

.jenis-badge     { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 6px; }
.subsidi-badge   { background: var(--c-success-soft); color: var(--c-success); }
.nonsub-badge    { background: var(--c-nila-soft); color: var(--c-nila); }

.action-btns   { display: flex; justify-content: center; gap: 0.1rem; }
.empty-state   { display: flex; flex-direction: column; align-items: center; gap: 0.75rem; padding: 3rem; color: var(--p-text-muted-color); }
.empty-state i { font-size: 2.5rem; }
.pagination-bar { padding-top: 0.75rem; display: flex; justify-content: center; }

/* Form */
.form-grid { display: flex; flex-direction: column; gap: 0.85rem; }
.form-section-title { font-size: 0.85rem; font-weight: 700; color: var(--c-brand-600); display: flex; align-items: center; gap: 0.45rem; border-bottom: 1px solid var(--s-border); padding-bottom: 0.45rem; }
.input-overload :deep(.p-inputtext) { border-color: var(--c-danger) !important; }
.overload-hint { color: var(--c-danger); font-weight: 600; }
.tujuan-count { background: var(--c-brand-500); color: #fff; font-size: 0.7rem; padding: 1px 7px; border-radius: 8px; font-weight: 600; }
.overload-warn { color: var(--c-danger); font-size: 0.75rem; display: flex; align-items: center; gap: 0.3rem; margin-left: auto; }
.optional-badge { font-size: 0.68rem; font-weight: 400; color: var(--t-muted); background: var(--s-2); padding: 1px 8px; border-radius: 8px; margin-left: auto; }
/* minmax(0, …): nama driver yang panjang dipotong "…", bukan mendorong kolom keluar dialog */
.form-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
.form-row.single { grid-template-columns: minmax(0, 1fr); }
.form-group { display: flex; flex-direction: column; gap: 0.3rem; }
.form-group label { font-size: 0.82rem; font-weight: 600; color: var(--p-text-color); display: flex; align-items: center; }
.req { color: var(--c-brand-500); }
.full-width { width: 100%; }
.hint { font-size: 0.77rem; color: var(--p-text-muted-color); }
/* Lebar 0 + min-width 100%: alamat panjang tidak ikut melebarkan daftar, jadi daftarnya selebar kolom toko. */
.toko-option { display: flex; flex-direction: column; gap: 0.1rem; padding: 0.15rem 0; width: 0; min-width: 100%; }
/* Satu baris saja supaya beberapa toko muat sekaligus di layar HP; alamat lengkap tampil setelah dipilih. */
.toko-option small { color: var(--p-text-muted-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.alamat-tetap {
  padding: 0.55rem 0.75rem;
  border: 1px solid var(--p-content-border-color);
  border-radius: 6px;
  background: var(--p-content-hover-background);
  font-size: 0.875rem;
}
.wilayah-auto {
  display: flex; align-items: center; justify-content: space-between;
  border: 1px solid var(--s-border); border-radius: 8px;
  padding: 0.5rem 0.75rem; font-size: 0.9rem;
}

/* Armada kendaraan */
.armada-kendaraan-wrap { display: flex; flex-direction: column; gap: 0.3rem; }
.armada-kendaraan-wrap > label { font-size: 0.82rem; font-weight: 600; color: var(--p-text-color); }

.armada-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  /* Gaya sama dengan kotak Driver di sebelahnya (lihat .p-select di main.css) */
  background: var(--s-0);
  border: 1px solid var(--s-border);
  border-radius: var(--r-sm);
  padding: 0.25rem 0.4rem 0.25rem 0.6rem;
}
/* Kolom grid sama tinggi; kotak Driver mengisi sisa tingginya supaya persis setinggi kartu kendaraan. */
.armada-row:has(.armada-card) > .form-group > .p-select { flex: 1; }
/* Di layar sentuh kotak Driver lebih tinggi (huruf 16px), kartu ikut menyesuaikan. */
@media (hover: none) and (pointer: coarse) {
  .armada-card { padding-block: 0.3rem; }
  .armada-card-body { line-height: 1.25; }
}
.armada-card-icon {
  width: 30px; height: 30px; border-radius: 8px;
  background: var(--s-1); color: var(--t-muted);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 0.95rem;
}
.app-dark .armada-card      { background: var(--s-1); }
.app-dark .armada-card-icon { background: var(--s-2); }
/* Dua baris pendek supaya kartu setinggi kotak Driver di sebelahnya; jenis kendaraan
   yang panjang dipotong "…" (nama lengkapnya di tooltip), bukan turun memecah baris. */
.armada-card-body { flex: 1; display: flex; flex-direction: column; min-width: 0; line-height: 1.15; }
.armada-polisi  { font-weight: 700; font-size: 0.9rem; color: var(--p-text-color); white-space: nowrap; }
.armada-kapasitas { font-weight: 500; color: var(--p-text-muted-color); }
.armada-detail  { font-size: 0.73rem; color: var(--p-text-muted-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.driver-option { display: flex; flex-direction: column; gap: 0.05rem; }
.driver-option small { color: var(--p-text-muted-color); font-size: 0.78em; }

.kendaraan-option { display: flex; flex-direction: column; gap: 0.1rem; padding: 0.15rem 0; }
.kendaraan-option small { color: var(--p-text-muted-color); }

.kendaraan-terpilih { display: inline-flex; align-items: baseline; gap: 0.4rem; min-width: 0; }
.kendaraan-terpilih strong { font-weight: 600; }
.kendaraan-terpilih small { color: var(--p-text-muted-color); }
.kendaraan-placeholder { color: var(--p-text-muted-color); }

.armada-override { display: flex; align-items: center; gap: 0.5rem; }
.armada-override .full-width { flex: 1; }

/* Items list */
.items-list { display: flex; flex-direction: column; gap: 0.75rem; max-height: 360px; overflow-y: auto; padding-right: 2px; }

.item-card {
  background: var(--s-0);
  border: 1px solid var(--p-content-border-color);
  border-radius: 10px;
  padding: 0.875rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.item-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: -0.1rem;
}
.item-num-label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--c-brand-500);
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
/* index.html memaksa .p-inputtext setinggi 48px di ponsel (gaya pra-render login); di kartu tujuan disamakan dengan dropdown. */
.item-card :deep(.p-inputtext) { height: 38px; }
.item-card-row2 { display: flex; gap: 0.6rem; align-items: flex-end; }
.item-card-row2 :deep(.p-select),
.item-card-row2 :deep(.p-inputnumber-input),
.item-card-row2 :deep(.p-inputtext) {
  height: 42px;
}
.item-card-row2 :deep(.p-inputnumber) { width: 100%; }

.add-item-bar { display: flex; align-items: center; gap: 1rem; padding-top: 0.25rem; }
.total-ton    { font-size: 0.82rem; color: var(--p-text-muted-color); margin-left: auto; }
.total-ton b  { color: var(--c-brand-600); }
.total-kg     { font-size: 0.78rem; color: var(--p-text-muted-color); }
.kg-hint      { font-size: 0.72rem; color: var(--c-brand-500); margin-top: 2px; display: block; }

/* ── Responsive ─────────────────────────────────────────────────── */
.desktop-only { display: block; }
.mobile-only  { display: none; }

/* Mobile card stagger animation */
.card-list-enter-active { transition: opacity 0.3s ease, transform 0.3s ease; transition-delay: calc(var(--i, 0) * 0.045s); }
.card-list-enter-from   { opacity: 0; transform: translateY(14px); }
.card-list-leave-active { transition: opacity 0.18s ease; }
.card-list-leave-to     { opacity: 0; }
.card-list-move         { transition: transform 0.3s ease; }

/* Mobile card list */
.muat-card {
  background: var(--s-0);
  border: 1px solid var(--s-border);
  border-radius: 10px;
  padding: 0.9rem 1rem;
  margin-bottom: 0.6rem;
}
.muat-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.6rem;
}
.muat-card-date { font-size: 0.82rem; color: var(--p-text-muted-color); display: flex; align-items: center; gap: 0.35rem; }
.muat-card-body { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 0.7rem; }
.muat-card-row  { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.83rem; color: var(--p-text-color); }
.muat-card-row i { color: var(--p-text-muted-color); font-size: 0.8rem; margin-top: 2px; flex-shrink: 0; }
.muat-card-row .muted { color: var(--p-text-muted-color); }
.muat-card-actions { display: flex; gap: 0.3rem; padding-top: 0.5rem; border-top: 1px solid var(--s-border); }
.mobile-loading { text-align: center; padding: 2rem; color: var(--p-text-muted-color); display: flex; align-items: center; justify-content: center; gap: 0.5rem; }

@media (max-width: 768px) {
  .desktop-only { display: none; }
  .mobile-only  { display: block; }

  /* Filter bar stack */
  .filter-bar { flex-direction: column; align-items: stretch; }
  .search-field { min-width: unset; width: 100%; }
  .filter-bar :deep(.p-select),
  .filter-bar :deep(.p-datepicker) { width: 100% !important; min-width: unset !important; }

  /* Stats row wrap tighter */
  .stats-row { gap: 0.75rem; font-size: 0.75rem; }

  /* Form items stack vertically on mobile */
  .item-fields { flex-wrap: wrap; }
  .item-fields .form-group { flex: 1 1 100% !important; width: 100%; }

  /* item-card-row2: Wilayah + Kg stack vertically on mobile */
  .item-card-row2 { flex-direction: column; gap: 0.5rem; align-items: stretch; }

  /* Form row single column */
  .form-row { grid-template-columns: minmax(0, 1fr); }
  /* Di HP dialog sudah bergulir; daftar tujuan tidak perlu gulir sendiri di dalamnya. */
  .items-list { max-height: none; overflow: visible; padding-right: 0; }
}

/* ── Dark mode overrides ─────────────────────────────────────── */
:global(.app-dark) .rit-1 { background: rgba(34,197,94,.15);  color: var(--c-success); }
:global(.app-dark) .rit-2 { background: rgba(245,158,11,.15); color: var(--c-warning); }
:global(.app-dark) .rit-3 { background: rgba(239,68,68,.15);  color: var(--c-danger); }
:global(.app-dark) .belum-driver-tag   { background: rgba(245,158,11,.15); color: var(--c-warning); }
:global(.app-dark) .subsidi-badge      { background: rgba(34,197,94,.15);  color: var(--c-success); }
:global(.app-dark) .nonsub-badge       { background: rgba(99,102,241,.15); color: var(--c-nila); }
:global(.app-dark) .optional-badge     { background: rgba(255,255,255,.07); color: var(--p-text-muted-color); }
:global(.app-dark) .tujuan-badge       { background: rgba(249,115,22,.15);  color: var(--c-brand-400); }
:global(.app-dark) .izin-form          { background: rgba(255,255,255,.04); border-color: var(--p-content-border-color); }

/* Izin Dialog */
.izin-body { display: flex; flex-direction: column; gap: 1rem; }
.izin-form { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem; background: var(--surface-section, var(--s-2)); border-radius: 8px; border: 1px solid var(--surface-border); }
.izin-list-title { font-size: 0.8rem; font-weight: 600; color: var(--text-color-secondary); margin-bottom: 0.5rem; }
.izin-item { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--surface-border); gap: 0.5rem; }
.izin-item:last-child { border-bottom: none; }
.izin-nama { font-weight: 600; font-size: 0.875rem; }
.izin-catatan { font-size: 0.75rem; color: var(--text-color-secondary); margin-top: 2px; }
.izin-empty { font-size: 0.8rem; color: var(--text-color-secondary); font-style: italic; text-align: center; padding: 0.5rem 0; margin: 0; }

/* ── Progres driver di tabel ─────────────────────────────── */
.progres-cell    { display: flex; flex-direction: column; gap: 3px; }
.progres-status  { display: inline-block; font-size: 0.68rem; font-weight: 700; padding: 1px 7px; border-radius: 8px; width: fit-content; }
.st-menunggu     { background: var(--s-2); color: var(--t-secondary); }
.st-dimuat       { background: var(--c-warning-soft); color: var(--c-warning); }
.st-berangkat    { background: var(--c-info-soft); color: var(--c-info); }
.st-selesai      { background: var(--c-success-soft); color: var(--c-success); }
.st-batal        { background: var(--c-danger-soft); color: var(--c-danger); }
.progres-angka   { font-size: 0.73rem; color: var(--p-text-muted-color); }
.progres-masalah { color: var(--c-warning); font-weight: 600; }

</style>
