// BYS -- uc panelin PANOSUNDAN ekran goruntusu (masaustu + 375px mobil).
//   node tests/uctan-uca/bys-pano-shot.mjs
//
// Gorsel gerileme kontrolu icin: her panoda bos kutu var mi, mobilde tasma
// var mi, menude "Dashboard" yazisi kaldi mi.
// SALT OKUNUR: veri yazmaz, yalnizca giris yapar.
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync } from 'node:fs';
import { totp } from './bys-totp.mjs';

const CHROME_KOK = '/root/.cache/puppeteer/chrome';
const CHROME = `${CHROME_KOK}/${readdirSync(CHROME_KOK).sort().pop()}/chrome-linux64/chrome`;

const ALAN = process.env.BYS_ALAN || 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const SIFRE = 'Pilot-Deneme-2026';

const b = await puppeteer.launch({
  executablePath: CHROME,
  headless: 'new',
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
         `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => sonuc.push({ ad, gecti, ek });
const bekle = ms => new Promise(r => setTimeout(r, ms));

async function yeniSekme(genislik = 1440) {
  const ctx = await b.createBrowserContext();
  const s = await ctx.newPage();
  await s.setViewport({ width: genislik, height: 1000 });
  return { ctx, s };
}

/** Tek giris kapisindan (Revizyon md.4) kurum/uye girisi. */
async function girisYap(s, eposta) {
  await s.goto(`${KOK}/giris`, { waitUntil: 'networkidle2' });
  await s.type('[name="email"]', eposta);
  await s.type('[name="password"]', SIFRE);
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
  await bekle(900);
}

/** Yonetim panelinin kendi 2FA'li kapisi. */
async function yonetimeGir(s) {
  await s.goto(`${KOK}/yonetim/login`, { waitUntil: 'networkidle2' });
  await s.type('#form\\.email', 'admin@byd.ordolive.com');
  await s.type('#form\\.password', readFileSync('/root/.bys-admin-pass', 'utf8').trim());
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
  await bekle(1200);
  const kutular = await s.$$('input[inputmode="numeric"]');
  if (kutular.length >= 6) {
    await kutular[0].click();
    await s.keyboard.type(totp(readFileSync('/root/.bys-admin-totp', 'utf8').trim()), { delay: 60 });
    await bekle(900);
    await s.evaluate(() => [...document.querySelectorAll('button')]
      .find(d => /Girişi doğrula/i.test(d.innerText))?.click());
    await bekle(3000);
  }
}

const panolar = [
  { ad: 'uye', yol: '/panel', giris: s => girisYap(s, 'muhabir+pilot@ornek.test') },
  { ad: 'kurum', yol: '/kurum', giris: s => girisYap(s, 'yetkili+pilot@ornek.test') },
  { ad: 'yonetim', yol: '/yonetim', giris: yonetimeGir },
];

for (const pano of panolar) {
  for (const [genislik, etiket] of [[1440, 'masaustu'], [375, 'mobil']]) {
    const { ctx, s } = await yeniSekme(genislik);
    try {
      await pano.giris(s);
      const y = await s.goto(KOK + pano.yol, { waitUntil: 'networkidle2' });
      await bekle(1500);

      const govde = await s.evaluate(() => document.body.innerText);
      const acildi = y.status() === 200 && !/Sunucu Hatası|Server Error/.test(govde);
      kontrol(`${pano.ad} panosu (${etiket})`, acildi, `HTTP ${y.status()}`);

      // 🔤 Menude Ingilizce "Dashboard" kalmamali.
      kontrol(`${pano.ad} menusunde "Genel bakış" (${etiket})`,
        /Genel bakış/.test(govde) && !/\bDashboard\b/.test(govde));

      // 📱 Yatay kaydirma olmamali: hicbir kutu tasmasin.
      const tasma = await s.evaluate(() =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth);
      kontrol(`${pano.ad} yatay tasma yok (${etiket})`, tasma <= 1, `${tasma}px`);

      await s.screenshot({ path: `/root/bys-pano-${pano.ad}-${etiket}.png`, fullPage: true });
    } catch (e) {
      kontrol(`${pano.ad} panosu (${etiket})`, false, String(e.message).slice(0, 80));
    } finally {
      await ctx.close();
    }
  }
}

await b.close();

for (const r of sonuc) console.log(`${r.gecti ? '✅' : '❌'} ${r.ad}${r.ek ? '  — ' + r.ek : ''}`);
const kirik = sonuc.filter(r => !r.gecti).length;
console.log(`\n${sonuc.length - kirik}/${sonuc.length} kontrol geçti`);
console.log('Ekran görüntüleri: /root/bys-pano-*.png');
process.exit(kirik ? 1 : 0);
