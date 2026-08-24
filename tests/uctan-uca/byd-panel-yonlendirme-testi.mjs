// BYD -- paneller arasi yonlendirme.  node /root/byd-panel-yonlendirme-testi.mjs
//
// Uc panel AYNI `web` oturumunu paylasir. Bu betik, yanlis panele dusen
// kullanicinin cikissiz bir "403 Yasak" sayfasinda kalmadigini dogrular.
// 💥 Gercek olay (2026-08-22): tarayicida once /yonetim/kapilar acilmis,
// `url.intended` orada kalmis; kurum kullanicisi /kurum/login'den girince
// Filament onu /yonetim/kapilar'a yollamis -> 403.
//
// SALT OKUNUR sayilir: veri degistirmez, yalnizca giris yapar
// (son_giris_at ve oturum denetim kaydi dogal olarak yazilir).
import puppeteer from 'puppeteer-core';
import { readdirSync } from 'node:fs';

const CHROME_KOK = '/root/.cache/puppeteer/chrome';
const surum = readdirSync(CHROME_KOK).sort().pop();
const CHROME = `${CHROME_KOK}/${surum}/chrome-linux64/chrome`;

const ALAN = 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const SIFRE = 'Pilot-Deneme-2026';
const KURUM = 'yetkili+pilot@ornek.test';
const AKREDITE_UYE = 'muhabir+pilot@ornek.test';
const ADAY_UYE = 'aday1+pilot@ornek.test';

const b = await puppeteer.launch({
  executablePath: CHROME,
  headless: 'new',
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
         `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => sonuc.push({ ad, gecti, ek });
const bekle = ms => new Promise(r => setTimeout(r, ms));
const yol = s => s.url().replace(KOK, '') || '/';
const metin = s => s.evaluate(() => document.body.innerText.replace(/\s+/g, ' ').trim());

/** Her senaryo temiz bir oturumla baslar. */
async function yeniSekme() {
  const ctx = await b.createBrowserContext();
  const s = await ctx.newPage();
  await s.setViewport({ width: 1440, height: 900 });
  return { ctx, s };
}

async function gir(s, panelYolu, eposta) {
  // Tek giriş kapısı (Revizyon md.4): `panelYolu` yalnızca senaryoyu okunur
  // kılmak için duruyor; giriş her hâlde /giris'ten yapılır.
  await s.goto(`${KOK}/giris`, { waitUntil: 'networkidle2' });
  await s.type('[name="email"]', eposta);
  await s.type('[name="password"]', SIFRE);
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type=submit]'),
  ]);
  await bekle(2000);
}

// 1) Misafir yetkili adresine giderse giris ekranini gormeli (403 degil).
{
  const { ctx, s } = await yeniSekme();
  const y = await s.goto(`${KOK}/yonetim/kapilar`, { waitUntil: 'networkidle2' });
  kontrol('Misafir yetkili adresinde giriş ekranı görüyor',
    y.status() === 200 && yol(s) === '/yonetim/login', `${y.status()} ${yol(s)}`);

  // 2) 💥 ASIL HATA: bekleyen /yonetim hedefi, kurum girişini 403'e düşürüyordu.
  await gir(s, '/kurum', KURUM);
  const g = await metin(s);
  kontrol('Başka panelin hedefi beklerken kurum girişi kendi paneline düşüyor',
    yol(s) === '/kurum', yol(s));
  kontrol('Giriş sonrası 403 yok', !/403|Yasak/.test(g), g.slice(0, 60));
  await ctx.close();
}

// 3) Aynı panel içindeki hedef KORUNMALI (özellik kaybolmasın).
{
  const { ctx, s } = await yeniSekme();
  await s.goto(`${KOK}/kurum/calisanlar`, { waitUntil: 'networkidle2' });
  await gir(s, '/kurum', KURUM);
  kontrol('Aynı panel içindeki hedef korunuyor', yol(s) === '/kurum/calisanlar', yol(s));

  // 4) Oturumluyken yetkili adresine giderse kendi paneline dönmeli.
  const y = await s.goto(`${KOK}/yonetim/kapilar`, { waitUntil: 'networkidle2' });
  const g = await metin(s);
  kontrol('Kurum kullanıcısı yetkili adresinde 403 sayfasında kalmıyor',
    yol(s) === '/kurum', `${y.status()} ${yol(s)}`);
  kontrol('Yönlendirme uyarısı gösteriliyor',
    /erişiminiz yok/i.test(g), g.slice(0, 70));

  // 5) Üye paneli de aynı şekilde.
  await s.goto(`${KOK}/panel/duyurular`, { waitUntil: 'networkidle2' });
  kontrol('Kurum kullanıcısı üye panelinden kendi paneline dönüyor', yol(s) === '/kurum', yol(s));
  await ctx.close();
}

// 6) SINIR: ONAYLANMAMIŞ hesap panele HİÇ giremez (Revizyon md.3.5).
//    Hesap onay anında açılır; rol ve akreditasyon aynı işlemde doğar. Elde
//    kalmış akreditasyonsuz bir hesap kapıda durdurulur -- yönlendirme onu
//    içeri almaz.
{
  const { ctx, s } = await yeniSekme();
  await gir(s, '/panel', ADAY_UYE);
  // Tek giriş kapısı hesabı içeri hiç almaz ve SEBEBİNİ yazar; eskiden
  // Filament'in giriş ekranında "kimlik bilgileri hatalı" diyordu.
  const g = await metin(s);
  kontrol('Akreditasyonsuz hesap üye paneline GİREMİYOR',
    yol(s) === '/giris' && /etkin değil/.test(g), yol(s));
  const y = await s.goto(`${KOK}/panel/duyurular`, { waitUntil: 'networkidle2' });
  kontrol('İçerik sayfası da açılmıyor, giriş ekranına düşüyor',
    yol(s) === '/giris', `${y.status()} ${yol(s)}`);
  await ctx.close();
}

// 7) Akredite üye içeriği görebilmeli (yönlendirme yanlış yere kapı açmasın).
{
  const { ctx, s } = await yeniSekme();
  await gir(s, '/panel', AKREDITE_UYE);
  const y = await s.goto(`${KOK}/panel/duyurular`, { waitUntil: 'networkidle2' });
  const g = await metin(s);
  kontrol('Akredite üye duyuruları görüyor',
    y.status() === 200 && yol(s) === '/panel/duyurular' && !/403|Yasak/.test(g),
    `${y.status()} ${yol(s)}`);
  await ctx.close();
}

await b.close();

let hata = 0;
for (const { ad, gecti, ek } of sonuc) {
  if (!gecti) hata++;
  console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? `  — ${ek}` : ''}`);
}
console.log(`\n${sonuc.length - hata}/${sonuc.length} kontrol geçti`);
process.exit(hata ? 1 : 0);
