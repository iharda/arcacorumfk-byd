// Kulup kirmizisi gercekten uygulaniyor mu? Giris dugmesinin hesaplanan rengini olcer.
import puppeteer from 'puppeteer-core';
import { readdirSync } from 'node:fs';
const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = process.env.BYD_ALAN || 'byd.ordolive.com';
const b = await puppeteer.launch({ executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox','--disable-dev-shm-usage',`--host-resolver-rules=MAP ${ALAN} 127.0.0.1`,'--ignore-certificate-errors'] });
const s = await b.newPage();
await s.setViewport({ width: 1440, height: 900 });
await s.goto(`https://${ALAN}/yonetim/login`, { waitUntil: 'networkidle2' });
const renk = await s.evaluate(() => getComputedStyle(document.querySelector('button[type=submit]')).backgroundColor);
await s.screenshot({ path: '/root/byd-01-giris.png' });
await b.close();
// 🪤 Chrome hesaplanan degeri oklch() OLARAK dondurur, rgb'ye cevirmez.
// Kiyaslamak icin sRGB'ye kendimiz cevirmeliyiz.
function oklchToRgb(L, C, Hdeg) {
  const h = (Hdeg * Math.PI) / 180, a = C * Math.cos(h), bb = C * Math.sin(h);
  const l_ = L + 0.3963377774 * a + 0.2158037573 * bb;
  const m_ = L - 0.1055613458 * a - 0.0638541728 * bb;
  const s_ = L - 0.0894841775 * a - 1.2914855480 * bb;
  const l = l_ ** 3, m = m_ ** 3, s = s_ ** 3;
  const lin = [
    +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
    -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
    -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s,
  ];
  return lin.map(v => {
    const c = v <= 0.0031308 ? 12.92 * v : 1.055 * Math.pow(Math.max(v, 0), 1 / 2.4) - 0.055;
    return Math.round(Math.min(1, Math.max(0, c)) * 255);
  });
}
let olculen;
const ok = renk.match(/oklch\(([\d.]+)\s+([\d.]+)\s+([\d.]+)/);
if (ok) olculen = oklchToRgb(+ok[1], +ok[2], +ok[3]);
else olculen = renk.match(/\d+/g).map(Number).slice(0, 3);

const hedef = [193, 17, 25];   // #C11119
const fark = Math.max(...olculen.map((v, i) => Math.abs(v - hedef[i])));
console.log(`düğme rengi: ${renk}  →  rgb(${olculen.join(', ')})`);
console.log(`hedef #C11119 = rgb(193, 17, 25)  ·  en büyük sapma: ${fark}`);
console.log(fark <= 6 ? '✅ kulüp kırmızısı uygulanıyor' : '❌ renk tutmuyor');
process.exit(fark <= 6 ? 0 : 1);
