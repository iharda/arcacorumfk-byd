// Kamuya acik sayfalarin ekran goruntusu. SUNUCUYA YAZMAZ.
// node /root/byd-kamu-shot.mjs [genislik]
import puppeteer from 'puppeteer-core';
import { readdirSync } from 'node:fs';
const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = 'byd.ordolive.com';
const en = Number(process.argv[2]) || 1280;

const b = await puppeteer.launch({ executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox','--disable-dev-shm-usage',`--host-resolver-rules=MAP ${ALAN} 127.0.0.1`,'--ignore-certificate-errors'] });
const s = await b.newPage();
await s.setViewport({ width: en, height: 900 });

const dis = [];
s.on('request', r => { const u = r.url(); if (!u.startsWith(`https://${ALAN}`) && !u.startsWith('data:')) dis.push(u); });

for (const [yol, ad] of [['/', 'secim'], ['/basvuru/kurum', 'kurum-formu']]) {
  await s.goto(`https://${ALAN}${yol}`, { waitUntil: 'networkidle2', timeout: 60000 });
  await new Promise(r => setTimeout(r, 400));
  await s.screenshot({ path: `/root/byd-kamu-${ad}.png`, fullPage: true });
  console.log(`✅ ${yol} → /root/byd-kamu-${ad}.png`);
}
console.log(dis.length ? `❌ dış istek: ${dis.slice(0,3).join(' ')}` : '✅ dış kaynağa istek yok');
await b.close();
