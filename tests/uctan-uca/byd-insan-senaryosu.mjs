/**
 * BYD — SİSTEM GENELİ İNSAN SENARYOSU
 *
 * Gerçek bir kullanıcının tarayıcıda yapacağı sırayla, insan hızında:
 *   Perde 1  Kurum başvurusu (Kızılırmak Medya) — evrak formda, yanlış dosya
 *            denemesi dahil
 *   Perde 2  İkinci kurum başvurusu (reddedilecek)
 *   Perde 3  Yetkili: eksik evrak → PANELSİZ düzeltme → ONAY  ·  diğerini RED
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
 * 🔑 Hesap ONAY anında açılır (Revizyon md.1): başvuran onaya kadar sisteme
 *    hiç girmez, evrakını formda verir, eksiğini geçici bağlantıdan tamamlar.
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
const ALAN = process.env.BYD_ALAN || 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const D = (process.env.BYD_TEST_DOSYALARI ?? import.meta.dirname + '/../../../test-dosyalari');
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
// Yetki matrisinde "başka kurumun yetkilisi" rolü. Reddedilen başvurana artık
// hesap açılmadığı için (Revizyon md.3.2) yabancıyı ayrıca kuruyoruz.
const YABANCI = { unvan: `Yabancı Ajans ${damga}`, eposta: `yabanci+${damga}@ornek.test` };

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
  { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 180000 });
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
async function kurumBasvurusu(s, kisi, adres, vergiNo, kvkkGez, evraklar) {
  await s.goto(`${KOK}/`, { waitUntil: 'networkidle2' });
  await dusun(700, 1400);
  await s.evaluate(() => [...document.querySelectorAll('a')]
    .find(a => /Kurum/i.test(a.innerText) && /basvuru\/kurum/.test(a.href))?.click());
  await s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {});
  if (!s.url().includes('/basvuru/kurum')) await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2' });

  await yaz(s, '[name="resmi_unvan"]', kisi.unvan);
  await yaz(s, '[name="adres"]', adres);
  // 🪤 İl/ilçe bağlı açılır liste: ilçeler il seçildikten SONRA çizilir.
  await s.select('#il', 'Çorum');
  await bekle(500);
  await s.select('#ilce', 'Merkez');
  await dusun();
  // Telefon maskeli: yalnızca rakam yazılır, biçimi maske verir.
  await yaz(s, '[name="kurum_telefon"]', '3642134567');
  await yaz(s, '[name="kurum_eposta"]', kisi.kurumEposta);
  await yaz(s, '[name="vergi_dairesi"]', 'Çorum Vergi Dairesi');
  await yaz(s, '[name="vergi_no"]', vergiNo);
  await s.select('#calisan_araligi', '21-50');
  await yaz(s, '[name="yayin_platformlari[0][ad]"]', 'Kızılırmak Haber');
  await yaz(s, '[name="yayin_platformlari[0][url]"]', 'https://ornek.test/kizilirmak');
  await dusun();
  await yaz(s, '[name="yetkili_ad"]', kisi.yetkiliAd);
  await yaz(s, '[name="yetkili_eposta"]', kisi.yetkiliEposta);
  await yaz(s, '[name="yetkili_telefon"]', '5324112233');

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

  // Evrak AYNI formda isteniyor (Revizyon md.3.1).
  await evraklariSec(s, evraklar);

  await dusun(400, 800);
  await formuGonder(s);
}

/** Formdaki dosya kutularını sırayla doldurur; kutu sayısını döner. */
async function evraklariSec(s, dosyalar = []) {
  const girisler = await s.$$('input[type="file"]');
  for (let i = 0; i < girisler.length; i++) {
    if (dosyalar[i]) await girisler[i].uploadFile(dosyalar[i]);
  }
  return girisler.length;
}

async function formuGonder(s) {
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
}

/**
 * ONAY sonrası şifre belirleme. Hesap onayla birlikte açılır ve onay
 * e-postasındaki imzalı bağlantı buraya götürür (Revizyon md.3.2).
 */
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

  // İnsan hatası: ticaret sicil yerine PDF sandığı bozuk dosyayı seçer.
  await kurumBasvurusu(s1, K1, 'Gazi Caddesi No: 48/3', '6971435586', true,
    [`${D}/sahte-belge.pdf`, `${D}/vergi-levhasi.pdf`]);
  const hata1 = await govde(s1);
  kontrol('Sahte PDF (magic byte) formda reddedildi',
    s1.url().includes('/basvuru/kurum') && /kabul edilmiyor/i.test(hata1),
    (hata1.match(/[^\n]*kabul edilmiyor[^\n]*/) || ['-'])[0].slice(0, 60));
  kontrol('Yazdıkları kaybolmadı (form eski değerlerle doldu)',
    (await s1.$eval('[name="resmi_unvan"]', e => e.value)) === K1.unvan);
  await foto(s1, 'sahte-dosya-reddi');

  // Doğru dosyaları seçip yeniden gönderir: yalnızca dosyalar yeniden seçilir.
  await evraklariSec(s1, [`${D}/ticaret-sicil.pdf`, `${D}/vergi-levhasi.pdf`]);
  await dusun(400, 800);
  await formuGonder(s1);
  kontrol('Kurum başvurusu evraklarıyla tek adımda gönderildi',
    s1.url().includes('/basvuru/gonderildi'),
    s1.url().replace(KOK, '') + ((await govde(s1)).match(/429|Too Many/) ? ' (HIZ SINIRI)' : ''));
  await foto(s1, 'kurum-basvuru-gonderildi');
  if (!s1.url().includes('/basvuru/gonderildi')) throw new Error('K1 formu gönderilemedi');

  const k1Durum = artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta','${K1.yetkiliEposta}')->latest('id')->first();
echo 'DURUM:' . ($b?->durum->value ?? 'yok') . ' EVRAK:' . ($b?->evraklar()->count() ?? 0)
   . ' HESAP:' . (App\\Models\\User::withTrashed()->where('email','${K1.yetkiliEposta}')->exists() ? 'var' : 'yok');`);
  kontrol('Başvuru iki evrakıyla kuyruğa düştü',
    /DURUM:gonderildi/.test(k1Durum) && /EVRAK:2/.test(k1Durum), cek(k1Durum, 'DURUM'));
  kontrol('Onaydan önce hesap AÇILMADI', /HESAP:yok/.test(k1Durum), cek(k1Durum, 'HESAP'));

  /* ═══════════ PERDE 2 — İkinci kurum (reddedilecek) ═══════════ */
  perde('PERDE 2 — İkinci kurum başvurusu: Anadolu Kent Haber Ajansı');
  const c2 = await b.createBrowserContext();
  const s2 = await c2.newPage();
  await s2.setViewport({ width: 1440, height: 1000 });
  await kurumBasvurusu(s2, K2, 'İnönü Caddesi No: 7', '1721541811', false,
    [`${D}/ticaret-sicil.pdf`, `${D}/vergi-levhasi.jpg`]);
  kontrol('İkinci kurum başvurusu gönderildi', s2.url().includes('/basvuru/gonderildi'),
    s2.url().replace(KOK, ''));
  const k2Durum = artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta','${K2.yetkiliEposta}')->latest('id')->first();
echo 'KUYRUKTA:' . ($b && App\\Models\\Basvuru::kuyrukta()->whereKey($b->id)->exists() ? 'evet' : 'hayir');`);
  kontrol('İkinci başvuru da kuyrukta', /KUYRUKTA:evet/.test(k2Durum), cek(k2Durum, 'KUYRUKTA'));

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
    /*
     * 🪤 DEĞER DEĞİL GÖRÜNEN AD ile seç. Alan anahtarları artık görünen ad
     * değil sabit kod (`evrak:vergi_levhasi`) -- yetkili evrak türünün adını
     * değiştirince yoldaki biletler bozuluyordu (Düzeltme listesi md.11).
     * Ada göre seçmek testi o şemadan bağımsız kılar.
     */
    const deger = await y.$$eval('select option', (ops) =>
      ops.find((o) => o.textContent.trim() === 'Vergi levhası')?.value ?? null);
    if (!deger) throw new Error('Açılır listede "Vergi levhası" yok');
    await y.select('select', deger);
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

  /*
   * Başvuranın HESABI YOK: düzeltme, e-postayla giden tek kullanımlık geçici
   * bağlantıdan yapılır (Revizyon md.3.4). Ham token yalnızca üretildiği anda
   * görünür; yetkilinin "yeniden gönder" eylemi yenisini üretir.
   */
  const duzToken = cek(artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta','${K1.yetkiliEposta}')->latest('id')->firstOrFail();
echo 'TOKEN:' . app(App\\Servisler\\BasvuruBiletiAkisi::class)->yenidenGonder($b);`), 'TOKEN');

  await s1.goto(`${KOK}/basvuru/duzelt/${duzToken}`, { waitUntil: 'networkidle2' });
  await dusun(800, 1400);
  const kur = await govde(s1);
  kontrol('Başvuran düzeltme bağlantısını HESAPSIZ açıyor',
    kur.includes('Başvurunuzu düzeltin') && kur.includes('Levha okunmuyor'));
  kontrol('Sayfada yalnızca istenen evrak açık', (await evraklariSec(s1, [`${D}/vergi-levhasi.jpg`])) === 1);
  await foto(s1, 'panelsiz-duzeltme');
  await formuGonder(s1);
  const kur2 = artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta','${K1.yetkiliEposta}')->latest('id')->firstOrFail();
echo 'DURUM:' . $b->durum->value . ' NOT:' . (blank($b->duzeltme_notlari) ? 'temiz' : 'duruyor');`);
  kontrol('Düzeltip yeniden gönderdi, notlar temizlendi',
    s1.url().includes('/basvuru/gonderildi') && /DURUM:gonderildi/.test(kur2) && /NOT:temiz/.test(kur2),
    cek(kur2, 'DURUM'));

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

  // HESAP ONAYLA BİRLİKTE AÇILDI: yetkili artık şifresini belirleyip girebilir.
  await sifreBelirle(s1, K1.yetkiliEposta);
  kontrol('Onaydan sonra yetkili şifresini belirleyip kurum paneline girdi',
    s1.url().includes('/kurum'), s1.url().replace(KOK, ''));
  await foto(s1, 'kurum-paneli-ilk-giris');

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

    // Reddedilene hesap AÇILMAZ (Revizyon md.3.2): gerekçe kayda geçer ve
    // e-postayla gider, panelde gösterilecek bir yer yoktur.
    const red = artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta','${K2.yetkiliEposta}')->latest('id')->firstOrFail();
echo 'GEREKCE:' . (str_contains((string) $b->karar_gerekcesi, 'güncel değil') ? 'var' : 'yok')
   . ' HESAP:' . (App\\Models\\User::withTrashed()->where('email','${K2.yetkiliEposta}')->exists() ? 'var' : 'yok');`);
    kontrol('Red gerekçesi başvuruya işlendi', /GEREKCE:var/.test(red), cek(red, 'GEREKCE'));
    kontrol('Reddedilen başvurana HESAP AÇILMADI', /HESAP:yok/.test(red), cek(red, 'HESAP'));
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
  await yaz(s3, '[name="telefon"]', '5352201144');
  await yaz(s3, '[name="adres"]', 'Bahçelievler Mah. 3. Sok. No: 9');
  await s3.select('#il', 'Çorum');
  await bekle(500);
  await s3.select('#ilce', 'Merkez');
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

  const uyeEvrakSayisi = await evraklariSec(s3,
    [`${D}/foto.jpg`, `${D}/kimlik.jpg`, `${D}/calisma-belgesi.pdf`]);
  kontrol('Üç kişisel evrak da formda isteniyor', uyeEvrakSayisi === 3, `${uyeEvrakSayisi} kutu`);
  await foto(s3, 'basin-mensubu-formu');
  await formuGonder(s3);
  kontrol('Basın mensubu başvurusu alındı', s3.url().includes('/basvuru/gonderildi'), s3.url().replace(KOK, ''));

  const kuy = artisan(`
$bv = App\\Models\\Basvuru::where('basvuran_eposta','${P1.eposta}')->latest('id')->firstOrFail();
echo 'EVRAK:' . $bv->evraklar()->count()
   . ' KUYRUKTA:' . (App\\Models\\Basvuru::kuyrukta()->whereKey($bv->id)->exists() ? 'evet' : 'hayir');`);
  kontrol('Üç evrak da başvuruya bağlandı', /EVRAK:3/.test(kuy), cek(kuy, 'EVRAK'));
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
$bv = App\\Models\\Basvuru::where('basvuran_eposta','${P1.eposta}')->latest('id')->firstOrFail();
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

  // Üyenin hesabı da onayla açıldı: şifresini belirleyip panele girer.
  await sifreBelirle(s3, P1.eposta);
  kontrol('Basın mensubu onaydan sonra üye paneline girdi',
    s3.url().includes('/panel'), s3.url().replace(KOK, ''));

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
  /*
   * 💥 Kart numarasının YAZMASI yetmez. Görsel ayrı bir uçtan geliyordu ve
   *    üyeye 403 dönüyordu: sayfada kart numarası vardı ama kartın kendisi
   *    boş bir kutuydu. Artık görselin GERÇEKTEN indiğini ölç.
   */
  const kartGorsel = await s3.evaluate(async () => {
    const g = [...document.images].find(i => /\/kart\/.+\/gorsel/.test(i.src));
    if (!g) return { yok: true };
    const r = await fetch(g.src);
    return { durum: r.status, tur: r.headers.get('content-type'),
             bayt: (await r.arrayBuffer()).byteLength,
             cizildi: g.complete && g.naturalWidth > 0 };
  });
  kontrol('Kart görseli gerçekten yükleniyor (403 değil)',
    kartGorsel.durum === 200 && kartGorsel.cizildi && kartGorsel.bayt > 20000,
    kartGorsel.yok ? 'görsel etiketi yok' : `${kartGorsel.durum} · ${kartGorsel.tur} · ${Math.round(kartGorsel.bayt / 1024)} KB`);
  await foto(s3, 'uye-kartim');

  /*
   * 🔐 "Kendi kaydını görmek için yetki gerekmez" kuralı — üç modelde birden.
   * Basın mensubu / içerik üreticisi rollerinde `basvuru.gor` ve
   * `akreditasyon.gor` YOK; policy'ler yetkiyi en başta sorunca kişi KENDİ
   * kaydına da giremiyordu. Ters yön de burada sınanır: başka kurumun
   * yetkilisi bu kayıtların hiçbirini göremez.
   */
  // Reddedilen başvurana hesap açılmadığı için "yabancı" yetkiliyi ayrıca
  // kuruyoruz: ölçülen şey kurumlar arası yalıtım.
  artisan(`
$k = App\\Models\\Kurum::create(['resmi_unvan' => '${YABANCI.unvan}', 'akreditasyon_durumu' => 'akredite']);
$u = App\\Models\\User::create(['name' => 'Yabancı Yetkili', 'email' => '${YABANCI.eposta}',
    'password' => bcrypt('${SIFRE}'), 'kurum_id' => $k->id, 'aktif' => true, 'email_verified_at' => now()]);
$u->assignRole(App\\Models\\User::ROL_KURUM);
echo 'YABANCI_HAZIR';`);

  const yetkiMatrisi = artisan(`
$sahip = App\\Models\\User::where('email','${P1.eposta}')->first();
$yabanci = App\\Models\\User::where('email','${YABANCI.eposta}')->first();
$bv = App\\Models\\Basvuru::where('kullanici_id', $sahip->id)->first();
$ev = App\\Models\\Evrak::where('basvuru_id', $bv->id)->first();
$ak = App\\Models\\Akreditasyon::where('kullanici_id', $sahip->id)->first();
$g = fn ($k, $m) => Illuminate\\Support\\Facades\\Gate::forUser($k)->allows('view', $m) ? 'E' : 'H';
echo 'SAHIP:' . $g($sahip,$bv) . $g($sahip,$ev) . $g($sahip,$ak)
   . ' YABANCI:' . $g($yabanci,$bv) . $g($yabanci,$ev) . $g($yabanci,$ak);`);
  kontrol('Üye kendi başvuru/evrak/kartını görebiliyor',
    cek(yetkiMatrisi, 'SAHIP') === 'EEE', 'başvuru·evrak·akreditasyon = ' + cek(yetkiMatrisi, 'SAHIP'));
  kontrol('Başka kurumun yetkilisi bunların hiçbirini göremiyor',
    cek(yetkiMatrisi, 'YABANCI') === 'HHH', cek(yetkiMatrisi, 'YABANCI'));

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

  // 🪤 Son karakteri sabit bir harfle değiştirmek KIRILGAN: harf zaten oysa yük
  //    hiç bozulmaz ve geçerli kart olarak okunur (bir koşuda "mukerrer_okutma"
  //    döndü). Farklı olduğu garanti bir karakterle değiştir.
  const bozukYuk = yuk.slice(0, -1) + (yuk.endsWith('X') ? 'Y' : 'X');
  r = kapiApi('/api/kapi/dogrula', anahtarKapi, { yuk: bozukYuk });
  kontrol('Kurcalanmış QR reddediliyor', r.veri.sonuc === 'imza_gecersiz',
    `${r.veri.sonuc} · yük değişti: ${bozukYuk !== yuk}`);
  kontrol('Kurcalanmış QR\'da kişi bilgisi SIZMIYOR', r.veri.kisi == null, String(r.veri.kisi));

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
    // 🪤 Hesap onaya kadar YOK (reddedilende hiç açılmaz): temizlik
    //    BAŞVURUDAN yürür, kullanıcıdan değil.
    const kod = `
foreach (['${K1.yetkiliEposta}', '${K2.yetkiliEposta}', '${P1.eposta}', '${YABANCI.eposta}'] as $e) {
    $u = App\\Models\\User::withTrashed()->where('email', $e)->first();
    $ids = App\\Models\\Basvuru::withTrashed()
        ->where('basvuran_eposta', $e)
        ->when($u, fn ($q) => $q->orWhere('kullanici_id', $u->id))
        ->pluck('id');
    $kurumlar = App\\Models\\Basvuru::withTrashed()->whereIn('id', $ids)->pluck('kurum_id')->filter();
    foreach (App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $ids)->get() as $ev) {
        Illuminate\\Support\\Facades\\Storage::disk($ev->disk)->delete($ev->yol);
        $ev->forceDelete();
    }
    // 🪤 Akreditasyon SoftDeletes KULLANMIYOR: withTrashed() burada patlar.
    App\\Models\\Akreditasyon::whereIn('basvuru_id', $ids)->get()->each(function ($a) {
        $a->kartlar()->get()->each->forceDelete();
        $a->gecisKayitlari()->delete();
        $a->delete();
    });
    App\\Models\\BasvuruBileti::whereIn('basvuru_id', $ids)->delete();
    App\\Models\\Basvuru::withTrashed()->whereIn('id', $ids)->forceDelete();
    if ($u) {
        $kurumlar = $kurumlar->merge([$u->kurum_id])->filter();
        Illuminate\\Support\\Facades\\DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    App\\Models\\Kurum::withTrashed()->whereIn('id', $kurumlar->unique())->forceDelete();
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
