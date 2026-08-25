/**
 * BYD — kurumsal başvuru akışının uçtan uca testi (Başvuru akışı v2).
 *
 * Kapsam: kamuya açık form (EVRAK DAHİL, tek adım) → yetkili incelemesi →
 *         eksik evrak → PANELSİZ düzeltme bağlantısı → onay → hesabın onayda
 *         açılması.
 *
 * 🔑 Eski akıştaki "hesap aktivasyonu → panele gir → evrak yükle → gönder"
 *    adımları YOK: hesap onay anında açılır (Revizyon md.1).
 *
 * ⚠️ Bu test ÜRETİME YAZAR (kayıt oluşturur). Kendi oluşturduğu kaydı sonunda
 *    temizler; BAŞKA kayda dokunmaz. Süzgeç sayıları sabit yazılmaz.
 *
 * node /root/byd-basvuru-akisi-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { totp } from './byd-totp.mjs';

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const DOSYA = '/root/byd-test-dosyalari';

const damga = Date.now();
const EPOSTA = `bydtest+${damga}@ornek.test`;
const UNVAN = `BYD Test Medya A.Ş. ${damga}`;

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => {
  sonuc.push({ ad, gecti, ek });
  console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`);
};
const bekle = (ms) => new Promise(r => setTimeout(r, ms));

function artisan(kod) {
  return execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod], {
    cwd: '/home/byd.ordolive.com/laravel', encoding: 'utf8', timeout: 60000,
  });
}

/** Kuyruk bildirimi gerçekten işlendi mi? (Horizon günlüğünden) */
function bildirimIslendiMi(desen, saniye = 30) {
  for (let i = 0; i < saniye; i++) {
    const log = readFileSync('/var/log/byd-horizon.log', 'utf8').slice(-6000);
    if (desen.test(log)) return true;
    execFileSync('sleep', ['1']);
  }
  return false;
}

const b = await puppeteer.launch({
  executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

let temizlenecekEposta = null;

try {
  /* ───────── 1) Kamuya açık başvuru formu — evrak dahil ───────── */
  const s = await b.newPage();
  await s.setViewport({ width: 1440, height: 1000 });
  await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2', timeout: 60000 });

  const doldur = async (ad, deger) => { await s.type(`[name="${ad}"]`, deger); };
  await doldur('resmi_unvan', UNVAN);
  await doldur('adres', 'Gazi Caddesi No: 12');
  // 🪤 İl/ilçe artık AÇILIR LİSTE ve bağlı: ilçe seçenekleri il seçildikten
  //    sonra çizilir. Telefon alanı maskeli — yalnızca rakam yazılır.
  await s.select('#il', 'Çorum');
  await bekle(500);
  await s.select('#ilce', 'Merkez');
  await doldur('kurum_telefon', '3642134567');
  await doldur('kurum_eposta', `kurum+${damga}@ornek.test`);
  await doldur('vergi_dairesi', 'Çorum Vergi Dairesi');
  // Sağlaması tutan VKN (bkz. tests/Unit/VergiNumarasiTest).
  await doldur('vergi_no', '1234567890');
  await s.select('#calisan_araligi', '21-50');
  await s.type('[name="yayin_platformlari[0][ad]"]', 'Test Haber');
  await s.type('[name="yayin_platformlari[0][url]"]', 'https://ornek.test/haber');
  await doldur('yetkili_ad', 'Deneme Yetkili');
  await doldur('yetkili_eposta', EPOSTA);
  await doldur('yetkili_telefon', '5321112233');
  await s.click('[name="kvkk_aydinlatma"]');
  await s.click('[name="kvkk_riza"]');

  // Evrak artık AYNI formda: iki zorunlu evrak da burada seçilir.
  const girisler = await s.$$('input[type="file"]');
  kontrol('Evrak alanları başvuru formunda', girisler.length >= 2, `${girisler.length} alan`);
  await girisler[0]?.uploadFile(`${DOSYA}/ticaret-sicil.pdf`);
  await girisler[1]?.uploadFile(`${DOSYA}/vergi-levhasi.jpg`);
  temizlenecekEposta = EPOSTA;

  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
  const gonderimMetni = await s.evaluate(() => document.body.innerText);
  kontrol('Başvuru tek adımda kabul edildi', s.url().includes('/basvuru/gonderildi'),
    s.url().includes('/basvuru/gonderildi')
      ? ''
      : (/429|Too Many/i.test(gonderimMetni) ? 'HIZ SINIRI (429) — 10 dk bekle' : gonderimMetni.replace(/\s+/g, ' ').slice(0, 90)));
  if (!s.url().includes('/basvuru/gonderildi')) throw new Error('form gönderilemedi');

  /* ───────── 2) Hesap AÇILMADI, başvuru kuyrukta ───────── */
  const ilkDurum = artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta','${EPOSTA}')->latest('id')->first();
echo 'DURUM:' . ($b?->durum->value ?? 'yok')
   . ' EVRAK:' . ($b?->evraklar()->count() ?? 0)
   . ' HESAP:' . (App\\Models\\User::withTrashed()->where('email','${EPOSTA}')->exists() ? 'var' : 'yok');`);
  kontrol('Başvuru doğrudan "Gönderildi" ve iki evrakı var',
    /DURUM:gonderildi/.test(ilkDurum) && /EVRAK:2/.test(ilkDurum),
    (ilkDurum.match(/DURUM:\w+ EVRAK:\d+/) || ['?'])[0]);
  kontrol('Onaydan önce HESAP AÇILMADI', /HESAP:yok/.test(ilkDurum));
  kontrol('"Başvurunuz alındı" e-postası kuyrukta işlendi',
    bildirimIslendiMi(/BasvuruAlindi[^\n]*DONE/));

  /* ───────── 3) Yetkili girişi (2FA dahil) ───────── */
  const yBaglam = await b.createBrowserContext();
  const y = await yBaglam.newPage();
  await y.setViewport({ width: 1600, height: 1000 });
  await y.goto(`${KOK}/yonetim/login`, { waitUntil: 'networkidle2' });
  await y.type('#form\\.email', 'admin@byd.ordolive.com');
  await y.type('#form\\.password', readFileSync('/root/.byd-admin-pass', 'utf8').trim());
  await Promise.all([
    y.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    y.click('button[type="submit"]'),
  ]);
  await bekle(1200);
  // 🪤 Filament'in tek kullanımlık kod alanı AYNI SAYFADA açılıyor ve altı ayrı
  //    kutudan oluşuyor; asıl input gizli. Kutulara yazmak gerekiyor.
  const gizli = readFileSync('/root/.byd-admin-totp', 'utf8').trim();
  const kutular = await y.$$('input[inputmode="numeric"]');
  if (kutular.length >= 6) {
    await kutular[0].click();
    await y.keyboard.type(totp(gizli), { delay: 60 });
    await bekle(1200);
    await y.evaluate(() => [...document.querySelectorAll('button')]
      .find(b => /Girişi doğrula/i.test(b.innerText))?.click());
    await bekle(3000);
  }
  kontrol('Yetkili 2FA ile giriş yaptı', !y.url().includes('/login'), y.url().replace(KOK, ''));

  /* ───────── 4) Kuyrukta görünüyor mu ───────── */
  await y.goto(`${KOK}/yonetim/basvurular`, { waitUntil: 'networkidle2' });
  await bekle(1200);
  const kuyruk = await y.evaluate(() => document.body.innerText);
  kontrol('Başvuru yetkili kuyruğunda', kuyruk.includes(UNVAN.slice(0, 24)), 'kuyrukta');

  const acLink = await y.evaluate((unvanParca) => {
    const satir = [...document.querySelectorAll('tr')].find(tr => tr.innerText.includes(unvanParca));
    return satir?.querySelector('a[href*="/inceleme"]')?.href ?? null;
  }, UNVAN.slice(0, 24));
  kontrol('İnceleme bağlantısı var', !!acLink);
  if (!acLink) throw new Error('inceleme bağlantısı yok');

  /* ───────── 5) İnceleme ekranı ───────── */
  await y.goto(acLink, { waitUntil: 'networkidle2' });
  await bekle(900);
  const inc = await y.evaluate(() => document.body.innerText);
  kontrol('İnceleme ekranı açıldı', inc.includes('Evraklar') && inc.includes(UNVAN.slice(0, 24)));
  kontrol('Başvuranın adı ve e-postası ekranda',
    inc.includes('Deneme Yetkili') && inc.includes(EPOSTA));

  // 🔎 Görsel önizlemeye güvenmek yetmez (başsız Chrome PDF çizmez).
  //    Evrak ucunun GERÇEKTEN dosyayı döndürdüğünü ayrıca ölç.
  const evrakYanit = await y.evaluate(async () => {
    const kaynak = document.querySelector('iframe')?.src || document.querySelector('img[src*="/evrak/"]')?.src;
    if (!kaynak) return null;
    const r = await fetch(kaynak);
    const buf = await r.arrayBuffer();
    return { durum: r.status, tur: r.headers.get('content-type'), boyut: buf.byteLength };
  });
  kontrol('Evrak ucu dosyayı döndürüyor',
    !!evrakYanit && evrakYanit.durum === 200 && evrakYanit.boyut > 1000,
    evrakYanit ? `${evrakYanit.durum} · ${evrakYanit.tur} · ${evrakYanit.boyut} B` : 'kaynak yok');
  await y.screenshot({ path: '/root/byd-inceleme.png', fullPage: true });

  /* ───────── 6) İncelemeye al ───────── */
  await y.evaluate(() => [...document.querySelectorAll('button, a')]
    .find(e => e.innerText.trim() === 'İncelemeye al')?.click());
  await bekle(2500);
  const inc2 = await y.evaluate(() => document.body.innerText);
  kontrol('Durum "İncelemede" oldu', inc2.includes('İncelemede'));

  /* ───────── 7) Alan bazlı eksik evrak talebi ───────── */
  await y.evaluate(() => [...document.querySelectorAll('button, a')]
    .find(e => e.innerText.trim() === 'Eksik evrak iste')?.click());
  await bekle(2200);
  const secim = await y.$('select');
  kontrol('Eksik evrak kipi açıldı', !!secim);
  if (secim) {
    /*
     * 🪤 DEĞER DEĞİL GÖRÜNEN AD ile seç. Alan anahtarları artık görünen ad
     * değil sabit kod (`evrak:vergi_levhasi`) -- yetkili evrak türünün adını
     * değiştirince yoldaki biletler bozuluyordu (Düzeltme listesi md.11).
     */
    const deger = await y.$$eval('select option', (ops) =>
      ops.find((o) => o.textContent.trim() === 'Vergi levhası')?.value ?? null);
    if (!deger) throw new Error('Açılır listede "Vergi levhası" yok');
    await y.select('select', deger);
    const metinAlani = await y.$('input[type="text"]:not([inputmode="numeric"])');
    if (metinAlani) await metinAlani.type('Levha okunmuyor, yeniden yükleyin.');
    await bekle(400);
    await y.evaluate(() => [...document.querySelectorAll('button')]
      .find(b => b.innerText.trim() === 'Talebi gönder')?.click());
    await bekle(3000);
  }
  const inc3 = await y.evaluate(() => document.body.innerText);
  kontrol('Durum "Eksik evrak" oldu', inc3.includes('Eksik evrak'));
  kontrol('İstenen düzeltme kayda geçti', inc3.includes('Levha okunmuyor'));

  /* ───────── 8) PANELSİZ düzeltme: başvuranın hesabı yok ───────── */
  // Ham token yalnızca üretildiği anda görünür (sunucuda hash'i durur);
  // yetkilinin "yeniden gönder" eylemi yenisini üretir.
  const tokenCikti = artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta','${EPOSTA}')->latest('id')->first();
echo 'TOKEN:' . app(App\\Servisler\\BasvuruBiletiAkisi::class)->yenidenGonder($b);`);
  const token = (tokenCikti.match(/TOKEN:(\S+)/) || [])[1];
  kontrol('Düzeltme bileti üretildi', !!token);
  if (!token) throw new Error('düzeltme bileti yok');

  // 🪤 AYRI bağlam: bu sayfanın hiçbir oturuma ihtiyacı OLMAMALI.
  const dBaglam = await b.createBrowserContext();
  const d = await dBaglam.newPage();
  await d.goto(`${KOK}/basvuru/duzelt/${token}`, { waitUntil: 'networkidle2' });
  const duz = await d.evaluate(() => document.body.innerText);
  kontrol('Düzeltme sayfası hesap gerektirmeden açıldı',
    duz.includes('Başvurunuzu düzeltin') && duz.includes('Levha okunmuyor'));

  const duzGirisler = await d.$$('input[type="file"]');
  kontrol('Yalnızca işaretlenen evrak açıldı', duzGirisler.length === 1, `${duzGirisler.length} alan`);
  await duzGirisler[0]?.uploadFile(`${DOSYA}/vergi-levhasi.pdf`);
  await Promise.all([
    d.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    d.click('button[type="submit"]'),
  ]);
  kontrol('Düzeltme gönderildi', d.url().includes('/basvuru/gonderildi'), d.url().replace(KOK, ''));

  /* ───────── 9) Onay ───────── */
  await y.goto(acLink, { waitUntil: 'networkidle2' });
  await bekle(900);
  await y.evaluate(() => [...document.querySelectorAll('button, a')]
    .find(e => e.innerText.trim() === 'İncelemeye al')?.click());
  await bekle(2500);
  await y.evaluate(() => [...document.querySelectorAll('button, a')]
    .find(e => e.innerText.trim() === 'Onayla')?.click());
  await bekle(1500);
  await y.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => b.innerText.trim() === 'Onayla' && b.closest('[role="dialog"], .fi-modal'))?.click());
  await bekle(3000);
  const inc4 = await y.evaluate(() => document.body.innerText);
  kontrol('Durum "Onaylandı" oldu', inc4.includes('Onaylandı'),
    (inc4.match(/Taslak|Gönderildi|İncelemede|Eksik evrak|Onaylandı|Reddedildi/) || ['?'])[0]);
  await y.screenshot({ path: '/root/byd-inceleme.png', fullPage: true });

  /* ───────── 10) Hesap ONAYDA açıldı, kurum akredite ───────── */
  const sonDurum = artisan(`
$u = App\\Models\\User::where('email','${EPOSTA}')->first();
echo 'HESAP:' . ($u ? 'var' : 'yok')
   . ' ROL:' . ($u?->getRoleNames()->implode(',') ?: 'yok')
   . ' AKRED:' . ($u?->kurum?->akreditasyon_durumu ?? 'yok');`);
  kontrol('Hesap ONAY anında açıldı ve kurum rolü verildi',
    /HESAP:var/.test(sonDurum) && /ROL:[^ ]*kurum/.test(sonDurum),
    (sonDurum.match(/HESAP:\w+ ROL:\S+/) || ['?'])[0]);
  kontrol('Kurum akredite edildi', /AKRED:akredite/.test(sonDurum),
    (sonDurum.match(/AKRED:\w+/) || ['?'])[0]);

  await b.close();
} catch (e) {
  console.log('💥 ' + e.message);
  sonuc.push({ ad: 'Beklenmeyen hata', gecti: false, ek: e.message });
  try { await b.close(); } catch {}
} finally {
  /* ───────── Temizlik: SADECE bu testin oluşturduğu kayıt ───────── */
  if (temizlenecekEposta) {
    // 🪤 Hesap onaya kadar YOK: temizlik başvurudan yürür, kullanıcıdan değil.
    const kod = `
$bler = App\\Models\\Basvuru::withTrashed()->where('basvuran_eposta', '${temizlenecekEposta}')->get();
$kurumlar = $bler->pluck('kurum_id')->filter()->unique();
foreach (App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $bler->pluck('id'))->get() as $e) {
    Illuminate\\Support\\Facades\\Storage::disk($e->disk)->delete($e->yol);
}
App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $bler->pluck('id'))->forceDelete();
App\\Models\\BasvuruBileti::whereIn('basvuru_id', $bler->pluck('id'))->delete();
App\\Models\\Basvuru::withTrashed()->whereIn('id', $bler->pluck('id'))->forceDelete();
$u = App\\Models\\User::withTrashed()->where('email', '${temizlenecekEposta}')->first();
if ($u) {
    Illuminate\\Support\\Facades\\DB::table('model_has_roles')->where('model_id', $u->id)->delete();
    $u->forceDelete();
}
App\\Models\\Kurum::withTrashed()->whereIn('id', $kurumlar)->forceDelete();
echo 'TEMIZ';`;
    try { console.log('🧹 ' + artisan(kod).trim().split('\n').pop()); }
    catch (e) { console.log('⚠️ temizlik yapılamadı: ' + e.message); }
  }
}

const hata = sonuc.filter(r => !r.gecti).length;
console.log(`\n${sonuc.length - hata}/${sonuc.length} geçti`);
process.exit(hata ? 1 : 0);
