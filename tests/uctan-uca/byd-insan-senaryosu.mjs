/**
 * BYD — SİSTEM GENELİ İNSAN SENARYOSU
 *
 * Gerçek bir kullanıcının tarayıcıda yapacağı sırayla, insan hızında:
 *   Perde 1  Kurum başvurusu (Kızılırmak Medya) — yanlış dosya denemesi dahil
 *   Perde 2  İkinci kurum başvurusu (reddedilecek)
 *   Perde 3  Yetkili: eksik evrak → düzeltme → ONAY  ·  diğerini RED
 *   Perde 4  Basın mensubu başvurusu → kurum teyidi → onay → kart no
 *   Perde 5  Basın kartı + kapı (turnike) doğrulaması
 *   Perde 6  Duyuru yayını ve ayrılış → akreditasyon iptali
 *
 * ⚠️ ÜRETİME YAZAR. Oluşturduğu HER kaydı sonunda siler, `kurum_teyidi_istensin`
 *    ayarının eski değerini geri yazar. Başka kayda dokunmaz.
 *    `--birak` ile veriler panelde incelenmek üzere BIRAKILIR.
 *
 * Ekran görüntüleri: /root/byd-senaryo/NN-*.png
 *
 * node /root/byd-insan-senaryosu.mjs [--birak]
 */
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { totp } from './byd-totp.mjs';

const BIRAK = process.argv.includes('--birak');
const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const D = '/root/byd-test-dosyalari';
const SHOT = '/root/byd-senaryo';

const damga = Date.now();
const SIFRE = 'Cebeci-Kirmizi-2026-x7';

// ── Oyuncular ───────────────────────────────────────────────────────────────
const K1 = {
  unvan: `Kızılırmak Medya ve Yayıncılık Ltd. Şti. ${damga}`,
  yetkiliAd: 'Selim Aydoğan',
  yetkiliEposta: `selim+${damga}@ornek.test`,
  kurumEposta: `kizilirmak+${damga}@ornek.test`,
};
const K2 = {
  unvan: `Anadolu Kent Haber Ajansı ${damga}`,
  yetkiliAd: 'Meral Doğan',
  yetkiliEposta: `meral+${damga}@ornek.test`,
  kurumEposta: `anadolukent+${damga}@ornek.test`,
};
const P1 = { ad: 'Elif Karaman', eposta: `elif+${damga}@ornek.test` };

if (existsSync(SHOT)) rmSync(SHOT, { recursive: true });
mkdirSync(SHOT, { recursive: true });

const sonuc = [];
let kare = 0;
const kontrol = (ad, gecti, ek = '') => {
  sonuc.push({ ad, gecti, ek });
  console.log(`   ${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`);
};
const perde = ad => console.log(`\n\x1b[1m▶ ${ad}\x1b[0m`);
const bekle = ms => new Promise(r => setTimeout(r, ms));
/** İnsan okuma/düşünme molası. */
const dusun = (az = 500, cok = 1100) => bekle(az + Math.floor(Math.random() * (cok - az)));
const artisan = kod => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: '/home/byd.ordolive.com/laravel', encoding: 'utf8', timeout: 180000 });
const cek = (m, e) => (m.match(new RegExp(e + ':(\\S+)')) || [])[1];

async function foto(sayfa, ad) {
  kare++;
  await sayfa.screenshot({ path: `${SHOT}/${String(kare).padStart(2, '0')}-${ad}.png`, fullPage: true });
}

/** İnsan gibi yaz: alana tıkla, tuş tuş gir. */
async function yaz(sayfa, sec, metin, gecikme = 28) {
  await sayfa.click(sec).catch(() => {});
  await sayfa.type(sec, metin, { delay: gecikme });
}

const govde = s => s.evaluate(() => document.body.innerText);
const tikla = (s, metin) => s.evaluate(t => {
  const e = [...document.querySelectorAll('button, a')].find(x => x.innerText.trim() === t);
  e?.click();
  return !!e;
}, metin);
const kipTikla = (s, metin) => s.evaluate(t => {
  const e = [...document.querySelectorAll('button')]
    .find(x => x.innerText.trim() === t && x.closest('.fi-modal, [role="dialog"]'));
  e?.click();
  return !!e;
}, metin);

function aktivasyonBaglantisi(eposta) {
  const c = artisan(`
$u = App\\Models\\User::where('email', '${eposta}')->firstOrFail();
echo 'BAG:' . Illuminate\\Support\\Facades\\URL::temporarySignedRoute(
    'hesap.aktivasyon', now()->addHours(48), ['kullanici' => $u->ulid]);`);
  return cek(c, 'BAG');
}

/** Turnike ucuna istek (kapı cihazı gibi). */
function kapiApi(yol, anahtar, govde = null) {
  const args = ['-s', '-k', '-o', '-', '-w', '\n__KOD__%{http_code}',
    '--resolve', `${ALAN}:443:127.0.0.1`,
    '-H', `X-Kapi-Anahtar: ${anahtar}`, '-H', 'Accept: application/json'];
  if (govde) args.push('-X', 'POST', '-H', 'Content-Type: application/json', '-d', JSON.stringify(govde));
  args.push(`${KOK}${yol}`);
  const cikti = execFileSync('curl', args, { encoding: 'utf8', timeout: 30000 });
  const [g, kod] = cikti.split('\n__KOD__');
  let veri = {}; try { veri = JSON.parse(g); } catch {}
  return { kod: Number(kod), veri };
}

const b = await puppeteer.launch({
  executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`,
         '--ignore-certificate-errors'],
});

/** Kamuya açık kurum başvurusunu insan gibi doldurur. */
async function kurumBasvurusu(s, kisi, adres, vergiNo, kvkkGez) {
  await s.goto(`${KOK}/`, { waitUntil: 'networkidle2' });
  await dusun(700, 1400);
  await s.evaluate(() => [...document.querySelectorAll('a')]
    .find(a => /Kurum/i.test(a.innerText) && /basvuru\/kurum/.test(a.href))?.click());
  await s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {});
  if (!s.url().includes('/basvuru/kurum')) await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2' });

  await yaz(s, '[name="resmi_unvan"]', kisi.unvan);
  await yaz(s, '[name="adres"]', adres);
  await yaz(s, '[name="il"]', 'Çorum');
  await yaz(s, '[name="ilce"]', 'Merkez');
  await dusun();
  await yaz(s, '[name="kurum_telefon"]', '0364 213 45 67');
  await yaz(s, '[name="kurum_eposta"]', kisi.kurumEposta);
  await yaz(s, '[name="vergi_dairesi"]', 'Çorum Vergi Dairesi');
  await yaz(s, '[name="vergi_no"]', vergiNo);
  await yaz(s, '[name="calisan_sayisi"]', '24');
  await yaz(s, '[name="yayin_platformlari[0][ad]"]', 'Kızılırmak Haber');
  await yaz(s, '[name="yayin_platformlari[0][url]"]', 'https://ornek.test/kizilirmak');
  await dusun();
  await yaz(s, '[name="yetkili_ad"]', kisi.yetkiliAd);
  await yaz(s, '[name="yetkili_eposta"]', kisi.yetkiliEposta);
  await yaz(s, '[name="yetkili_telefon"]', '0532 411 22 33');

  if (kvkkGez) {
    // İnsan gibi: onay kutusunu işaretlemeden önce metni açıp okur.
    const yeni = new Promise(r => b.once('targetcreated', r));
    await s.evaluate(() => [...document.querySelectorAll('a')]
      .find(a => /Aydınlatma metnini/i.test(a.innerText))?.click());
    const hedef = await Promise.race([yeni, bekle(4000)]);
    const sekme = hedef && hedef.page ? await hedef.page() : null;
    if (sekme) {
      await sekme.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => {});
      await dusun(900, 1600);
      kontrol('KVKK metni yeni sekmede açıldı', /metin\//.test(sekme.url()), sekme.url().replace(KOK, ''));
      await sekme.close();
    }
    await s.bringToFront();
  }

  await s.click('[name="kvkk_aydinlatma"]');
  await s.click('[name="kvkk_riza"]');
  await dusun(400, 800);
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
}

/** Aktivasyon → şifre belirleme. */
async function sifreBelirle(s, eposta) {
  const bag = aktivasyonBaglantisi(eposta);
  await s.goto(bag, { waitUntil: 'networkidle2' });
  await dusun();
  await yaz(s, '[name="sifre"]', SIFRE);
  await yaz(s, '[name="sifre_confirmation"]', SIFRE);
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
}

/** i. evrak satırına dosya bırak ve Yükle'ye bas. */
async function evrakYukle(s, sira, dosya) {
  const girisler = await s.$$('input[type="file"]');
  if (!girisler[sira]) return false;
  await girisler[sira].uploadFile(dosya);
  await bekle(2000);
  await s.evaluate(i => [...document.querySelectorAll('button[wire\\:click^="yukle("]')][i]?.click(), sira);
  await bekle(2600);
  return true;
}

async function yetkiliGiris() {
  const ctx = await b.createBrowserContext();
  const y = await ctx.newPage();
  await y.setViewport({ width: 1600, height: 1000 });
  await y.goto(`${KOK}/yonetim/login`, { waitUntil: 'networkidle2' });
  await yaz(y, '#form\\.email', 'admin@byd.ordolive.com', 18);
  await yaz(y, '#form\\.password', readFileSync('/root/.byd-admin-pass', 'utf8').trim(), 12);
  await Promise.all([
    y.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    y.click('button[type="submit"]'),
  ]);
  await bekle(1500);
  const kutular = await y.$$('input[inputmode="numeric"]');
  if (kutular.length >= 6) {
    await kutular[0].click();
    await y.keyboard.type(totp(readFileSync('/root/.byd-admin-totp', 'utf8').trim()), { delay: 70 });
    await bekle(1200);
    await tikla(y, 'Girişi doğrula');
    await bekle(3000);
  }
  return { ctx, y };
}

/** Kuyruktan unvana göre inceleme bağlantısını bul. */
async function incelemeBaglantisi(y, unvanParca) {
  await y.goto(`${KOK}/yonetim/basvurular`, { waitUntil: 'networkidle2' });
  await bekle(1500);
  return y.evaluate(p => {
    const tr = [...document.querySelectorAll('tr')].find(t => t.innerText.includes(p));
    return tr?.querySelector('a[href*="/inceleme"]')?.href ?? null;
  }, unvanParca);
}

let eskiTeyitAyari = null;
let anahtarKapi = null;
const t0 = Date.now();

try {
  // Hız sınırı sayaçları temiz başlasın (3 form gönderimi var).
  artisan("Illuminate\\Support\\Facades\\Cache::flush(); echo 'TEMIZ';");
  eskiTeyitAyari = (artisan("echo 'ESKI:' . var_export(App\\Models\\Ayar::al('kurum_teyidi_istensin', false), true);")
    .match(/ESKI:(\w+)/) || [])[1];

  /* ═══════════ PERDE 1 — Kurum başvurusu (Kızılırmak Medya) ═══════════ */
  perde('PERDE 1 — Kurum başvurusu: Kızılırmak Medya');
  const c1 = await b.createBrowserContext();
  const s1 = await c1.newPage();
  await s1.setViewport({ width: 1440, height: 1000 });

  await kurumBasvurusu(s1, K1, 'Gazi Caddesi No: 48/3', '4820561973', true);
  kontrol('Kurum başvurusu gönderildi', s1.url().includes('/basvuru/gonderildi'),
    s1.url().replace(KOK, '') + ((await govde(s1)).match(/429|Too Many/) ? ' (HIZ SINIRI)' : ''));
  await foto(s1, 'kurum-basvuru-gonderildi');
  if (!s1.url().includes('/basvuru/gonderildi')) throw new Error('K1 formu gönderilemedi');

  await sifreBelirle(s1, K1.yetkiliEposta);
  kontrol('Aktivasyon + şifre ile kurum paneline girildi', s1.url().includes('/kurum'), s1.url().replace(KOK, ''));
  await foto(s1, 'kurum-paneli-ilk-giris');

  await s1.goto(`${KOK}/kurum/basvurum`, { waitUntil: 'networkidle2' });
  await dusun(800, 1500);
  const bs1 = await govde(s1);
  kontrol('Başvurum ekranı evrak listesini gösteriyor', bs1.includes('Evraklar'));

  // İnsan hatası: PDF sanıp bozuk dosya yükler.
  await evrakYukle(s1, 0, `${D}/sahte-belge.pdf`);
  const hata1 = await govde(s1);
  kontrol('Sahte PDF (magic byte) reddedildi',
    /kabul edilmiyor|geçersiz|hata|desteklenmiyor/i.test(hata1),
    (hata1.match(/[^\n]*kabul edilmiyor[^\n]*/) || ['-'])[0].slice(0, 60));
  await foto(s1, 'sahte-dosya-reddi');

  await s1.reload({ waitUntil: 'networkidle2' });
  await evrakYukle(s1, 0, `${D}/ticaret-sicil.pdf`);
  await evrakYukle(s1, 1, `${D}/vergi-levhasi.pdf`);
  await s1.reload({ waitUntil: 'networkidle2' });
  const bs2 = await govde(s1);
  kontrol('İki evrak da yüklendi', (bs2.match(/Yüklendi/g) || []).length >= 2,
    `${(bs2.match(/Yüklendi/g) || []).length} evrak`);
  await foto(s1, 'evraklar-yuklendi');

  await tikla(s1, 'Başvuruyu gönder');
  await bekle(2600);
  await s1.reload({ waitUntil: 'networkidle2' });
  kontrol('Başvuru gönderildi durumunda', (await govde(s1)).includes('Gönderildi'));

  /* ═══════════ PERDE 2 — İkinci kurum (reddedilecek) ═══════════ */
  perde('PERDE 2 — İkinci kurum başvurusu: Anadolu Kent Haber Ajansı');
  const c2 = await b.createBrowserContext();
  const s2 = await c2.newPage();
  await s2.setViewport({ width: 1440, height: 1000 });
  await kurumBasvurusu(s2, K2, 'İnönü Caddesi No: 7', '7391045628', false);
  kontrol('İkinci kurum başvurusu gönderildi', s2.url().includes('/basvuru/gonderildi'));
  await sifreBelirle(s2, K2.yetkiliEposta);
  await s2.goto(`${KOK}/kurum/basvurum`, { waitUntil: 'networkidle2' });
  await evrakYukle(s2, 0, `${D}/ticaret-sicil.pdf`);
  await evrakYukle(s2, 1, `${D}/vergi-levhasi.jpg`);
  await s2.reload({ waitUntil: 'networkidle2' });
  await tikla(s2, 'Başvuruyu gönder');
  await bekle(2600);
  await s2.reload({ waitUntil: 'networkidle2' });
  kontrol('İkinci başvuru da kuyrukta', (await govde(s2)).includes('Gönderildi'));

  /* ═══════════ PERDE 3 — Yetkili kararları ═══════════ */
  perde('PERDE 3 — Yetkili: eksik evrak → düzeltme → onay, diğerine red');
  const { ctx: yctx, y } = await yetkiliGiris();
  kontrol('Yetkili 2FA ile giriş yaptı', !y.url().includes('/login'), y.url().replace(KOK, ''));
  await foto(y, 'yetkili-panosu');

  const bag1 = await incelemeBaglantisi(y, K1.unvan.slice(0, 26));
  kontrol('Birinci başvuru kuyrukta görünüyor', !!bag1);
  if (!bag1) throw new Error('K1 kuyrukta yok');
  await foto(y, 'yetkili-kuyruk');

  await y.goto(bag1, { waitUntil: 'networkidle2' });
  await dusun(900, 1600);
  kontrol('İnceleme ekranı evrak önizlemesiyle açıldı',
    await y.evaluate(() => !!document.querySelector('iframe, img[src*="/evrak/"]')));
  const evrakYanit = await y.evaluate(async () => {
    const k = document.querySelector('iframe')?.src || document.querySelector('img[src*="/evrak/"]')?.src;
    if (!k) return null;
    const r = await fetch(k); const buf = await r.arrayBuffer();
    return { d: r.status, t: r.headers.get('content-type'), b: buf.byteLength };
  });
  kontrol('Yüklenen belge yetkiliye gerçekten iniyor',
    !!evrakYanit && evrakYanit.d === 200 && evrakYanit.b > 50000,
    evrakYanit ? `${evrakYanit.d} · ${evrakYanit.t} · ${Math.round(evrakYanit.b / 1024)} KB` : 'kaynak yok');
  await foto(y, 'inceleme-ekrani');

  await tikla(y, 'İncelemeye al');
  await bekle(2500);
  kontrol('Durum "İncelemede"', (await govde(y)).includes('İncelemede'));

  await tikla(y, 'Eksik evrak iste');
  await bekle(2400);
  const secim = await y.$('select');
  if (secim) {
    await y.select('select', 'Vergi levhası');
    const metin = await y.$('input[type="text"]:not([inputmode="numeric"])');
    if (metin) await metin.type('Levha okunmuyor, taramayı yenileyin.', { delay: 20 });
    await dusun(400, 700);
    await kipTikla(y, 'Talebi gönder');
    await bekle(3000);
  }
  const inc3 = await govde(y);
  kontrol('Alan bazlı eksik evrak talebi işlendi',
    inc3.includes('Eksik evrak') && inc3.includes('Levha okunmuyor'));
  await foto(y, 'eksik-evrak-talebi');

  // Kurum düzeltmeyi görüyor ve yeniden gönderiyor.
  await s1.goto(`${KOK}/kurum/basvurum`, { waitUntil: 'networkidle2' });
  await dusun(800, 1400);
  const kur = await govde(s1);
  kontrol('Kurum düzeltme talebini görüyor',
    kur.includes('Düzeltilmesi istenen') && kur.includes('Levha okunmuyor'));
  await foto(s1, 'kurum-duzeltme-notu');
  await evrakYukle(s1, 1, `${D}/vergi-levhasi.jpg`);
  await s1.reload({ waitUntil: 'networkidle2' });
  await tikla(s1, 'Başvuruyu gönder');
  await bekle(2600);
  await s1.reload({ waitUntil: 'networkidle2' });
  const kur2 = await govde(s1);
  kontrol('Düzeltip yeniden gönderdi, notlar temizlendi',
    kur2.includes('Gönderildi') && !kur2.includes('Düzeltilmesi istenen'));

  // Onay
  await y.goto(bag1, { waitUntil: 'networkidle2' });
  await bekle(1200);
  await tikla(y, 'İncelemeye al');
  await bekle(2500);
  await tikla(y, 'Onayla');
  await bekle(1600);
  await kipTikla(y, 'Onayla');
  await bekle(3500);
  kontrol('Birinci kurum ONAYLANDI', (await govde(y)).includes('Onaylandı'));
  const akr = artisan(`echo 'AKRED:' . (App\\Models\\Kurum::where('resmi_unvan','${K1.unvan}')->first()?->akreditasyon_durumu ?? 'yok');`);
  kontrol('Kurum akredite oldu (veritabanı)', /AKRED:akredite/.test(akr), cek(akr, 'AKRED'));
  await foto(y, 'kurum-onaylandi');

  // Red
  const bag2 = await incelemeBaglantisi(y, K2.unvan.slice(0, 24));
  kontrol('İkinci başvuru kuyrukta', !!bag2);
  if (bag2) {
    await y.goto(bag2, { waitUntil: 'networkidle2' });
    await bekle(1200);
    await tikla(y, 'İncelemeye al');
    await bekle(2500);
    await tikla(y, 'Reddet');
    await bekle(1800);
    const alan = await y.$('#mountedActionSchema0\\.gerekce, textarea');
    if (alan) await alan.type('Sunulan ticaret sicil kaydı güncel değil; başvuru bu hâliyle değerlendirilemedi.', { delay: 12 });
    await dusun(400, 700);
    await kipTikla(y, 'Reddet');
    await bekle(3500);
    kontrol('İkinci kurum REDDEDİLDİ', (await govde(y)).includes('Reddedildi'));
    await foto(y, 'kurum-reddedildi');

    await s2.goto(`${KOK}/kurum/basvurum`, { waitUntil: 'networkidle2' });
    await bekle(1200);
    const red = await govde(s2);
    kontrol('Reddedilen kurum gerekçeyi görüyor',
      red.includes('Reddedildi') && /güncel değil/.test(red),
      (red.match(/[^\n]*güncel değil[^\n]*/) || ['gerekçe yok'])[0].slice(0, 50));
    await foto(s2, 'kurum-red-gerekcesi');
  }

  /* ═══════════ PERDE 4 — Basın mensubu ═══════════ */
  perde('PERDE 4 — Basın mensubu başvurusu, kurum teyidi ve onay');

  // Panelden açmayı GERÇEKTEN sınamak için önce kapalı duruma çek.
  artisan("App\\Models\\Ayar::yaz('kurum_teyidi_istensin', false); echo 'KAPALI';");
  // Yetkili ayarlardan kurum teyidini açıyor (insan gibi, panelden).
  await y.goto(`${KOK}/yonetim/ayarlar`, { waitUntil: 'networkidle2' });
  await bekle(1500);
  const acildi = await y.evaluate(() => {
    const et = [...document.querySelectorAll('label, span')].find(e => /Kurum teyidi istensin/.test(e.innerText));
    const kutu = et?.closest('.fi-fo-field-wrp, div')?.querySelector('button[role="switch"], input[type="checkbox"]');
    if (!kutu) return 'alan yok';
    const acik = kutu.getAttribute('aria-checked') === 'true' || kutu.checked === true;
    if (!acik) kutu.click();
    return acik ? 'zaten açık' : 'açıldı';
  });
  await bekle(800);
  await tikla(y, 'Kaydet');
  await bekle(2500);
  const ayarDurum = artisan("echo 'AYAR:' . var_export(App\\Models\\Ayar::al('kurum_teyidi_istensin', false), true);");
  kontrol('Kurum teyidi ayarı panelden açıldı', /AYAR:true/.test(ayarDurum), `${acildi} · ${cek(ayarDurum, 'AYAR')}`);
  await foto(y, 'ayarlar-kurum-teyidi');

  const c3 = await b.createBrowserContext();
  const s3 = await c3.newPage();
  await s3.setViewport({ width: 1440, height: 1000 });
  await s3.goto(`${KOK}/basvuru/basin-mensubu`, { waitUntil: 'networkidle2' });
  await dusun(800, 1400);
  const listede = await s3.$$eval('#kurum_ulid option', o => o.map(x => x.textContent.trim()));
  kontrol('Yeni akredite kurum listede çıktı',
    listede.some(t => t.includes('Kızılırmak')), `${listede.length - 2} akredite kurum`);

  await yaz(s3, '[name="ad_soyad"]', P1.ad);
  await yaz(s3, '[name="eposta"]', P1.eposta);
  await yaz(s3, '[name="telefon"]', '0535 220 11 44');
  await yaz(s3, '[name="adres"]', 'Bahçelievler Mah. 3. Sok. No: 9');
  await yaz(s3, '[name="il"]', 'Çorum');
  await yaz(s3, '[name="ilce"]', 'Merkez');
  const k1Ulid = await s3.$$eval('#kurum_ulid option',
    (o, u) => o.find(x => x.textContent.includes(u))?.value, 'Kızılırmak');
  await s3.select('#kurum_ulid', k1Ulid);
  await dusun();
  await s3.evaluate(() => {
    document.querySelectorAll('[name="sigorta_212_var"]').forEach(r => { if (r.value === '1') r.click(); });
    document.querySelectorAll('[name="basin_karti_var"]').forEach(r => { if (r.value === '1') r.click(); });
  });
  await yaz(s3, '[name="calisma_yili"]', '5');
  await s3.click('[name="kvkk_aydinlatma"]');
  await s3.click('[name="kvkk_riza"]');
  await foto(s3, 'basin-mensubu-formu');
  await Promise.all([
    s3.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s3.click('button[type="submit"]'),
  ]);
  kontrol('Basın mensubu başvurusu alındı', s3.url().includes('/basvuru/gonderildi'), s3.url().replace(KOK, ''));

  await sifreBelirle(s3, P1.eposta);
  kontrol('Basın mensubu üye paneline girdi', s3.url().includes('/panel'), s3.url().replace(KOK, ''));
  await s3.goto(`${KOK}/panel/basvurum`, { waitUntil: 'networkidle2' });
  await evrakYukle(s3, 0, `${D}/foto.jpg`);
  await evrakYukle(s3, 1, `${D}/kimlik.jpg`);
  await evrakYukle(s3, 2, `${D}/calisma-belgesi.pdf`);
  await s3.reload({ waitUntil: 'networkidle2' });
  const uye = await govde(s3);
  kontrol('Üç kişisel evrak yüklendi', (uye.match(/Yüklendi/g) || []).length >= 3,
    `${(uye.match(/Yüklendi/g) || []).length} evrak`);
  await foto(s3, 'uye-evraklari');
  await tikla(s3, 'Başvuruyu gönder');
  await bekle(2600);
  await s3.reload({ waitUntil: 'networkidle2' });
  kontrol('Başvuru gönderildi', (await govde(s3)).includes('Gönderildi'));

  const kuy = artisan(`
$bv = App\\Models\\Basvuru::whereHas('kullanici', fn($q) => $q->where('email','${P1.eposta}'))->first();
echo 'KUYRUKTA:' . (App\\Models\\Basvuru::kuyrukta()->whereKey($bv->id)->exists() ? 'evet' : 'hayir');`);
  kontrol('Teyit beklerken yetkili kuyruğuna DÜŞMÜYOR', /KUYRUKTA:hayir/.test(kuy), cek(kuy, 'KUYRUKTA'));

  // Kurum yetkilisi teyit veriyor.
  await s1.goto(`${KOK}/kurum/calisanlar`, { waitUntil: 'networkidle2' });
  await bekle(1500);
  kontrol('Kurum teyit bekleyen kişiyi görüyor', (await govde(s1)).includes('Elif Karaman'));
  await foto(s1, 'kurum-teyit-bekleyen');
  await tikla(s1, 'Teyit et');
  await bekle(1600);
  await s1.evaluate(() => [...document.querySelectorAll('button')]
    .find(x => /^(Onayla|Teyit et|Evet)$/i.test(x.innerText.trim()) && x.closest('.fi-modal, [role="dialog"]'))?.click());
  await bekle(3000);
  const kuy2 = artisan(`
$bv = App\\Models\\Basvuru::whereHas('kullanici', fn($q) => $q->where('email','${P1.eposta}'))->first();
echo 'KUYRUKTA:' . (App\\Models\\Basvuru::kuyrukta()->whereKey($bv->id)->exists() ? 'evet' : 'hayir');`);
  kontrol('Teyit sonrası kuyruğa girdi', /KUYRUKTA:evet/.test(kuy2), cek(kuy2, 'KUYRUKTA'));

  const bag3 = await incelemeBaglantisi(y, 'Elif Karaman');
  // 💥 Bu satır gerçek bir hata yakaladı: kuyrukta bireysel başvuru da KURUM
  //    adıyla listeleniyordu, başvuranın kendi adı hiç görünmüyordu.
  kontrol('Kuyrukta başvuranın KENDİ adı yazıyor', !!bag3,
    bag3 ? 'Elif Karaman' : 'satır bulunamadı');
  if (bag3) {
    await y.goto(bag3, { waitUntil: 'networkidle2' });
    await bekle(1200);
    await tikla(y, 'İncelemeye al');
    await bekle(2500);
    await foto(y, 'basin-mensubu-inceleme');
    await tikla(y, 'Onayla');
    await bekle(1600);
    await kipTikla(y, 'Onayla');
    await bekle(4000);
    kontrol('Basın mensubu ONAYLANDI', (await govde(y)).includes('Onaylandı'));
  }

  const kart = artisan(`
$a = App\\Models\\Akreditasyon::whereHas('kullanici', fn($q)=>$q->where('email','${P1.eposta}'))->first();
echo 'KART:' . ($a?->kart_no ?? 'yok') . ' DURUM:' . ($a?->durum?->value ?? '-') . ' ULID:' . ($a?->ulid ?? '-');`);
  kontrol('Kart numarası üretildi', /KART:\d{4}-\w-\d{4}/.test(kart), cek(kart, 'KART'));
  const akrUlid = cek(kart, 'ULID');

  /* ═══════════ PERDE 5 — Kart ve kapı ═══════════ */
  perde('PERDE 5 — Basın kartı ve kapı (turnike) doğrulaması');
  // Kart PDF'i kuyrukta üretiliyor; bitmesini bekle.
  let kartDosya = null;
  for (let i = 0; i < 40; i++) {
    kartDosya = artisan(`
$a = App\\Models\\Akreditasyon::where('ulid','${akrUlid}')->first();
$k = $a?->kartlar()->latest('id')->first();
echo 'PDF:' . ($k && Illuminate\\Support\\Facades\\Storage::disk($k->disk)->exists($k->pdf_yolu)
    ? Illuminate\\Support\\Facades\\Storage::disk($k->disk)->size($k->pdf_yolu) : 0);`);
    if (Number(cek(kartDosya, 'PDF')) > 10000) break;
    await bekle(3000);
  }
  kontrol('Basın kartı PDF üretildi', Number(cek(kartDosya, 'PDF')) > 10000, `${cek(kartDosya, 'PDF')} bayt`);

  await s3.goto(`${KOK}/panel/kartim`, { waitUntil: 'networkidle2' });
  await bekle(1500);
  const kartSayfa = await govde(s3);
  kontrol('Üye kendi kartını panelde görüyor',
    /\d{4}-\w-\d{4}/.test(kartSayfa), (kartSayfa.match(/\d{4}-\w-\d{4}/) || ['-'])[0]);
  await foto(s3, 'uye-kartim');

  const kapiKur = artisan(`
$s = app(App\\Servisler\\KapiIstemcisiAkisi::class);
$k = $s->olustur(['ad' => 'Senaryo Kapı ${damga}', 'kapi_kodu' => 'SEN${damga}']);
echo 'ANAHTAR:' . $k['anahtar'];`);
  anahtarKapi = cek(kapiKur, 'ANAHTAR');
  const qr = artisan(`
$a = App\\Models\\Akreditasyon::where('ulid','${akrUlid}')->first();
echo 'YUK:' . app(App\\Servisler\\QrImzalayici::class)->yukUret($a);`);
  const yuk = cek(qr, 'YUK');

  let r = kapiApi('/api/kapi/dogrula', anahtarKapi, { yuk });
  kontrol('Kapıda kart okutuldu: İZİNLİ', r.kod === 200 && r.veri.izinli === true,
    `${r.kod} · ${r.veri.sonuc} · ${r.veri.kisi?.isim ?? ''} · ${r.veri.kisi?.kartNo ?? ''}`);
  // Görevli yüz kontrolü yapabilsin diye fotoğraf data: URI olarak dönüyor.
  kontrol('Görevliye fotoğraf da dönüyor',
    typeof r.veri.kisi?.foto === 'string' && r.veri.kisi.foto.startsWith('data:image'),
    r.veri.kisi?.foto ? `${Math.round(r.veri.kisi.foto.length / 1024)} KB` : 'yok');
  kontrol('Yanıtta kurum adı da var', !!r.veri.kisi?.kurum, r.veri.kisi?.kurum ?? '-');

  r = kapiApi('/api/kapi/dogrula', anahtarKapi, { yuk });
  kontrol('Arka arkaya okutma "mükerrer" diyor', r.veri.sonuc === 'mukerrer_okutma', r.veri.sonuc);

  r = kapiApi('/api/kapi/dogrula', anahtarKapi, { yuk: yuk.slice(0, -1) + 'X' });
  kontrol('Kurcalanmış QR reddediliyor', r.veri.sonuc === 'imza_gecersiz', r.veri.sonuc);

  artisan(`
$a = App\\Models\\Akreditasyon::where('ulid','${akrUlid}')->first();
app(App\\Servisler\\AkreditasyonAkisi::class)->askiyaAl($a, 'Senaryo testi');
Illuminate\\Support\\Facades\\Cache::flush(); echo 'ASKIDA';`);
  r = kapiApi('/api/kapi/dogrula', anahtarKapi, { yuk });
  kontrol('Askıya alınan kart kapıda geçmiyor', r.veri.sonuc === 'askida', r.veri.sonuc);
  artisan(`
$a = App\\Models\\Akreditasyon::where('ulid','${akrUlid}')->first();
app(App\\Servisler\\AkreditasyonAkisi::class)->yenidenAktiflestir($a);
Illuminate\\Support\\Facades\\Cache::flush(); echo 'AKTIF';`);

  await y.goto(`${KOK}/yonetim/gecis-kayitlari`, { waitUntil: 'networkidle2' });
  await bekle(1500);
  const gecis = await govde(y);
  kontrol('Her okutma geçiş kaydına düştü', /Senaryo Kapı|SEN\d+/.test(gecis) || gecis.includes('İzinli'));
  await foto(y, 'gecis-kayitlari');

  /* ═══════════ PERDE 6 — Duyuru ve ayrılış ═══════════ */
  perde('PERDE 6 — Duyuru yayını ve ayrılış');
  const duyuru = artisan(`
$d = App\\Models\\Duyuru::create(['baslik' => 'Senaryo duyurusu ${damga}',
    'ozet' => 'İnsan senaryosu testi', 'icerik' => '<p>Deneme</p>', 'yayinda' => false]);
app(App\\Servisler\\IcerikAkisi::class)->yayinla($d, 'duyuru');
echo 'DUYURU:' . $d->id;`);
  await s3.goto(`${KOK}/panel/duyurular`, { waitUntil: 'networkidle2' });
  await bekle(1500);
  kontrol('Akredite üye yayınlanan duyuruyu görüyor',
    (await govde(s3)).includes(`Senaryo duyurusu ${damga}`));
  await foto(s3, 'uye-duyurular');

  await s1.goto(`${KOK}/kurum/calisanlar`, { waitUntil: 'networkidle2' });
  await bekle(1500);
  await tikla(s1, 'Ayrıldı olarak işaretle');
  await bekle(1600);
  // 🪤 Kipin onay düğmesi "Onayla" değil "Ayrılışı bildir".
  await kipTikla(s1, 'Ayrılışı bildir');
  await bekle(3000);
  const ayril = artisan(`
$a = App\\Models\\Akreditasyon::where('ulid','${akrUlid}')->first();
echo 'DURUM:' . ($a?->durum?->value ?? '-');`);
  kontrol('Ayrılış bildirimi akreditasyonu iptal etti', /DURUM:iptal/.test(ayril), cek(ayril, 'DURUM'));

  Object.assign(globalThis, {}); // no-op
  artisan(`App\\Models\\Duyuru::where('baslik','Senaryo duyurusu ${damga}')->forceDelete(); echo 'OK';`);

  await foto(y, 'son-durum-yetkili');
  await b.close();
} catch (e) {
  console.log('\n💥 ' + e.message);
  sonuc.push({ ad: 'Beklenmeyen hata', gecti: false, ek: e.message });
  try { await b.close(); } catch {}
} finally {
  if (!BIRAK) {
    const kod = `
foreach (['${K1.yetkiliEposta}', '${K2.yetkiliEposta}', '${P1.eposta}'] as $e) {
    $u = App\\Models\\User::withTrashed()->where('email', $e)->first();
    if (! $u) continue;
    $ids = App\\Models\\Basvuru::withTrashed()->where('kullanici_id', $u->id)->pluck('id');
    App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $ids)->get()->each->forceDelete();
    // 🪤 Akreditasyon SoftDeletes KULLANMIYOR: withTrashed() burada patlar.
    App\\Models\\Akreditasyon::where('kullanici_id', $u->id)->get()->each(function ($a) {
        $a->kartlar()->get()->each->forceDelete(); $a->delete();
    });
    App\\Models\\Basvuru::withTrashed()->whereIn('id', $ids)->forceDelete();
    $k = $u->kurum; $u->forceDelete();
    if ($k) { $k->forceDelete(); }
}
App\\Models\\GecisKaydi::where('kapi_kodu', 'SEN${damga}')->delete();
App\\Models\\KapiIstemcisi::where('kapi_kodu', 'SEN${damga}')->forceDelete();
App\\Models\\Ayar::yaz('kurum_teyidi_istensin', ${eskiTeyitAyari === 'true' ? 'true' : 'false'});
echo 'TEMIZ';`;
    try { console.log('\n🧹 ' + artisan(kod).trim().split('\n').pop() + ' (ayar geri yazıldı)'); }
    catch (e) { console.log('⚠️ temizlik: ' + e.message); }
  } else {
    console.log('\n📌 --birak: veriler panelde duruyor, ayar AÇIK bırakıldı.');
  }
}

const hata = sonuc.filter(r => !r.gecti).length;
console.log(`\n${'─'.repeat(56)}`);
for (const r of sonuc.filter(x => !x.gecti)) console.log(`❌ ${r.ad}${r.ek ? '  → ' + r.ek : ''}`);
console.log(`${sonuc.length - hata}/${sonuc.length} kontrol geçti · ${Math.round((Date.now() - t0) / 1000)} sn · ekranlar: ${SHOT}`);
process.exit(hata ? 1 : 0);
