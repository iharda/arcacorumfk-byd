// BYS kurulum dogrulamasi -- SALT OKUNUR (veri yazmaz, yalnizca giris yapar).
// node /root/bys-giris-testi.mjs
import puppeteer from 'puppeteer-core';
import { readFileSync, readdirSync } from 'node:fs';
import { totp } from './bys-totp.mjs';

const CHROME_KOK = '/root/.cache/puppeteer/chrome';
const surum = readdirSync(CHROME_KOK).sort().pop();
const CHROME = `${CHROME_KOK}/${surum}/chrome-linux64/chrome`;

const ALAN = process.env.BYS_ALAN || 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const PAROLA = readFileSync('/root/.bys-admin-pass', 'utf8').trim();

const b = await puppeteer.launch({
  executablePath: CHROME,
  headless: 'new',
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
         `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});
const s = await b.newPage();
await s.setViewport({ width: 1440, height: 900 });

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => sonuc.push({ ad, gecti, ek });

// 1) İki kapı açılıyor mu? Yetkilinin 2FA'lı kapısı ve TEK giriş sayfası.
for (const [yol, ad] of [['/yonetim/login', 'Yetkili'], ['/giris', 'Tek giriş']]) {
  const y = await s.goto(KOK + yol, { waitUntil: 'networkidle2', timeout: 60000 });
  kontrol(`${ad} ekranı`, y.status() === 200, `HTTP ${y.status()}`);
}

// Panellerin kendi giriş sayfaları KALDIRILDI (Revizyon md.4): eski adresler
// tek kapıya yönlenmeli, yer imleri kırılmasın.
for (const eskiAdres of ['/kurum/login', '/panel/login']) {
  await s.goto(KOK + eskiAdres, { waitUntil: 'networkidle2' });
  kontrol(`${eskiAdres} tek kapıya yönleniyor`, s.url() === `${KOK}/giris`, s.url().replace(KOK, ''));
}

// 2) Turkce ve marka
await s.goto(`${KOK}/yonetim/login`, { waitUntil: 'networkidle2' });
const govde0 = await s.evaluate(() => document.body.innerText);
kontrol('Arayüz Türkçe', /Giriş yap/i.test(govde0), govde0.split('\n').filter(Boolean)[0] ?? '');
kontrol('Marka adı görünüyor', (await s.content()).includes('ARCA Çorum FK'));
// 🪤 Arma bloğu sabit yükseklikli kutuya sığmalı; taşarsa başlığın üstüne biner.
const arma = await s.evaluate(() => {
  const i = document.querySelector('.fi-logo img');
  return i ? { y: Math.round(i.getBoundingClientRect().height), x: Math.round(i.getBoundingClientRect().width) } : null;
});
kontrol('Arma doğru boyutta (36–60px)', !!arma && arma.y >= 36 && arma.y <= 60, arma ? `${arma.x}×${arma.y}px` : 'arma yok');
await s.screenshot({ path: '/root/bys-01-giris.png' });

// 3) Gercek giris
await s.type('#form\\.email', 'admin@byd.ordolive.com');
await s.type('#form\\.password', PAROLA);
await Promise.all([
  s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
  s.click('button[type=submit]'),
]);
await new Promise(r => setTimeout(r, 1500));

// 🔒 Yöneticide iki adımlı doğrulama ZORUNLU: parola tek başına yetmez.
//    Kurulu değilse "kur" ekranı, kuruluysa AYNI adreste kod ekranı çıkar.
const araAdim = await s.evaluate(() => document.body.innerText);
kontrol('Parola tek başına yetmiyor (2FA isteniyor)',
  /Kimliğinizi doğrulayın|İki faktörlü/i.test(araAdim) || s.url().includes('multi-factor'),
  araAdim.split('\n').filter(Boolean)[2]?.slice(0, 50) ?? '');

const kutular = await s.$$('input[inputmode="numeric"]');
if (kutular.length >= 6) {
  await kutular[0].click();
  await s.keyboard.type(totp(readFileSync('/root/.bys-admin-totp', 'utf8').trim()), { delay: 60 });
  await new Promise(r => setTimeout(r, 900));
  await s.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => /Girişi doğrula/i.test(b.innerText))?.click());
  await new Promise(r => setTimeout(r, 3000));
}
const url = s.url();
const govde = await s.evaluate(() => document.body.innerText);
kontrol('Doğru kodla giriş tamamlandı', !url.includes('/login'), url.replace(KOK, ''));
// 🪤 URL'e bakmak YETMEZ: sayfa 500 dönerken de adres doğruydu. İçeriğe bak.
kontrol('Panel gerçekten açıldı (500 değil)',
  !/Sunucu Hatası|Server Error/m.test(govde) && govde.trim().length > 40,
  govde.replace(/\s+/g, ' ').slice(0, 60));
// 🔒 Dis kaynakli istek olmamali (avatar ui-avatars.com'a gitmemeli)
const disIstekler = [];
s.on('request', r => { const u = r.url(); if (!u.startsWith(KOK) && !u.startsWith('data:')) disIstekler.push(u); });
await s.reload({ waitUntil: 'networkidle2' });
kontrol('Dış kaynağa istek yok (KVKK)', disIstekler.length === 0, disIstekler.slice(0, 2).join(' '));
kontrol('Avatar yerel data: URI', await s.evaluate(() =>
  [...document.images].every(i => !i.src || i.src.startsWith('data:') || i.src.startsWith(location.origin))));
await s.screenshot({ path: '/root/bys-02-giris-sonrasi.png', fullPage: true });

// 4) Yetkisiz panele sizma denemesi (super rolu kurum panelinde OLMAMALI).
//    2026-08-22'den beri cikissiz 403 yerine KENDI paneline yonlendiriliyor;
//    onemli olan kurum paneline GIREMEMESI. Ayrintili senaryolar:
//    node /root/bys-panel-yonlendirme-testi.mjs
const k = await s.goto(`${KOK}/kurum`, { waitUntil: 'networkidle2' });
const kYol = s.url().replace(KOK, '');
kontrol('Yetkisiz panel kapalı (super → /kurum)',
  !/^\/kurum(\/|$)/.test(kYol) || kYol.startsWith('/kurum/login'),
  `HTTP ${k.status()} · ${kYol}`);
kontrol('Yetkisiz panelden kendi paneline dönüyor', kYol.startsWith('/yonetim'), kYol);

await b.close();

let hata = 0;
for (const r of sonuc) { if (!r.gecti) hata++; console.log(`${r.gecti ? '✅' : '❌'} ${r.ad}${r.ek ? '  → ' + r.ek : ''}`); }
console.log(`\n${sonuc.length - hata}/${sonuc.length} geçti`);
process.exit(hata ? 1 : 0);
