import http from './http'

export const checkerService = {
  getAll:  (params)    => http.get('/admin/pengiriman/checkers', { params }),
  store:   (data)      => http.post('/admin/pengiriman/checkers', data),
  update:  (id, data)  => http.put(`/admin/pengiriman/checkers/${id}`, data),
  destroy: (id)        => http.delete(`/admin/pengiriman/checkers/${id}`),
}

export const pengawasanMuatService = {
  getByTanggal: (tanggal) => http.get('/admin/pengiriman/pengawasan-muat', { params: { tanggal } }),
  assign:       (items)   => http.post('/admin/pengiriman/pengawasan-muat/assign', { items }),
}
