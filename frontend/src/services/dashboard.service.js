import http from './http'

export const dashboardService = {
  async getStats() {
    const { data } = await http.get('/admin/dashboard/statistics')
    return data
  },

  async getAttendanceChart(days = 30) {
    const { data } = await http.get('/admin/dashboard/attendance-chart', { params: { days } })
    return data
  },

  async getAttendanceChartBulanan(months = 12) {
    const { data } = await http.get('/admin/dashboard/attendance-chart', {
      params: { group: 'bulan', months },
    })
    return data
  },

  async getVisitChart(bulan) {
    const params = bulan ? { bulan } : {}
    const { data } = await http.get('/admin/dashboard/visit-chart', { params })
    return data
  },

  async getRecentActivity(limit = 15) {
    const { data } = await http.get('/admin/dashboard/recent-activity', { params: { limit } })
    return data
  },

  async getMonthlySummary({ bulan, dari, sampai } = {}) {
    const params = {}
    if (dari && sampai) { params.dari = dari; params.sampai = sampai }
    else if (bulan) { params.bulan = bulan }
    const { data } = await http.get('/admin/dashboard/monthly-summary', { params })
    return data
  },

  async getPengirimanStats() {
    const { data } = await http.get('/admin/dashboard/pengiriman-stats')
    return data
  },

  async getMuatanTrend(days = 30) {
    const { data } = await http.get('/admin/dashboard/muatan-trend', { params: { days } })
    return data
  },

  async getMuatanTrendBulanan(months = 12) {
    const { data } = await http.get('/admin/dashboard/muatan-trend', {
      params: { group: 'bulan', months },
    })
    return data
  },
}
