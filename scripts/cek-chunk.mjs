// Muat index.html dan semua chunk JS hasil build di Chrome sungguhan, lalu laporkan error.
// Pakai: node scripts/cek-chunk.mjs [folder-dist]   (bawaan: frontend/dist)
// Tanpa paket tambahan: memakai Chrome yang terpasang dan WebSocket bawaan Node 22+.
import { spawn } from 'node:child_process'
import fs from 'node:fs'
import http from 'node:http'
import os from 'node:os'
import path from 'node:path'

const dist = path.resolve(process.argv[2] || 'frontend/dist')
const chrome = process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
const tunggu = (ms) => new Promise((r) => setTimeout(r, ms))

const jenis = { '.js': 'text/javascript', '.css': 'text/css', '.html': 'text/html', '.json': 'application/json', '.webp': 'image/webp', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.webmanifest': 'application/manifest+json' }
const server = http.createServer((req, res) => {
  let f = path.join(dist, decodeURIComponent(req.url.split('?')[0]))
  // Rute SPA (/login, /employees, ...) dilayani index.html, sama seperti nginx
  if (!f.startsWith(dist) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) f = path.join(dist, 'index.html')
  res.setHeader('content-type', jenis[path.extname(f)] || 'application/octet-stream')
  res.end(fs.readFileSync(f))
}).listen(0)
const port = server.address().port

const profil = fs.mkdtempSync(path.join(os.tmpdir(), 'cek-chunk-'))
const browser = spawn(chrome, ['--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profil}`, '--no-first-run', 'about:blank'], { stdio: ['ignore', 'ignore', 'pipe'] })
const wsUrl = await new Promise((resolve, reject) => {
  let buf = ''
  browser.stderr.on('data', (d) => { buf += d; const m = buf.match(/ws:\/\/\S+/); if (m) resolve(m[0]) })
  browser.on('exit', () => reject(new Error('Chrome berhenti sebelum siap')))
})

const ws = new WebSocket(wsUrl)
await new Promise((r) => ws.addEventListener('open', r))
let id = 0
const menunggu = new Map()
const errors = []
ws.addEventListener('message', ({ data }) => {
  const msg = JSON.parse(data)
  if (msg.id && menunggu.has(msg.id)) { menunggu.get(msg.id)(msg); menunggu.delete(msg.id) }
  if (msg.method === 'Runtime.exceptionThrown') errors.push(msg.params.exceptionDetails.exception?.description?.split('\n')[0] || msg.params.exceptionDetails.text)
})
const kirim = (method, params = {}, sessionId) => new Promise((r) => { const i = ++id; menunggu.set(i, r); ws.send(JSON.stringify({ id: i, method, params, sessionId })) })

const { result: { targetId } } = await kirim('Target.createTarget', { url: 'about:blank' })
const { result: { sessionId } } = await kirim('Target.attachToTarget', { targetId, flatten: true })
await kirim('Runtime.enable', {}, sessionId)
await kirim('Page.enable', {}, sessionId)
await kirim('Page.navigate', { url: `http://localhost:${port}/` }, sessionId)
await tunggu(4000)

const nilai = async (expression) => (await kirim('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true }, sessionId)).result.result.value
const vueJalan = await nilai(`!!document.querySelector('#app')?.__vue_app__`)
const chunks = fs.readdirSync(path.join(dist, 'assets')).filter((f) => f.endsWith('.js'))
const gagal = await nilai(`(async () => { const bad = []; for (const f of ${JSON.stringify(chunks)}) { try { await import('/assets/' + f) } catch (e) { bad.push(f + ': ' + e.message) } } return bad })()`)

ws.close(); server.close()
// Profil baru boleh dihapus setelah Chrome benar-benar berhenti menulis
await new Promise((r) => { browser.on('exit', r); browser.kill() })
fs.rmSync(profil, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 })

console.log(`Vue ter-mount : ${vueJalan ? 'ya' : 'TIDAK'}`)
console.log(`Chunk dimuat  : ${chunks.length}, gagal ${gagal.length}`)
gagal.slice(0, 10).forEach((g) => console.log(`  ✗ ${g}`))
console.log(`Error halaman : ${errors.length}`)
;[...new Set(errors)].slice(0, 5).forEach((e) => console.log(`  ✗ ${e}`))
process.exit(vueJalan && !gagal.length && !errors.length ? 0 : 1)
