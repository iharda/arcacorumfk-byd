/**
 * BYD — kurumsal başvuru akışının uçtan uca testi (Aşama 02).
 *
 * Kapsam: kamuya açık form → hesap aktivasyonu → evrak yükleme → gönderim →
 *         yetkili incelemesi → eksik evrak → düzeltme → onay.
 *
 * ⚠️ Bu test ÜRETİME YAZAR (kayıt oluşturur). Kendi oluşturduğu kaydı sonunda
 *    temizler; BAŞKA kayda dokunmaz. Süzgeç sayıları sabit yazılmaz.
 *
 * node /root/byd-basvuru-akisi-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { totp } from './byd-totp.mjs';

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const LOG = '/home/byd.ordolive.com/laravel/storage/logs/laravel.log';
const DOSYA = '/root/byd-test-dosyalari';

const damga = Date.now();
const EPOSTA = `bydtest+${damga}@ornek.test`;
const UNVAN = `BYD Test Medya A.Ş. ${damga}`;
const SIFRE = 'Kirmizi-Kartal-2026-x9';

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

/** Kuyruk mailini logdan yakala (MAIL_MAILER=log). */
async function aktivasyonBaglantisiniBekle(baslangicBoyut, saniye = 40) {
  for (let i = 0; i < saniye; i++) {
    // 🪤 YALNIZCA bu testin başlamasından SONRA yazılanlara bak. Geriye doğru
    //    kaydırırsak önceki koşunun (kullanılmış, süresi geçmiş) bağlantısını
    //    bulup "geçti" sanırız.
    // 🪤 statSync BAYT verir, String.slice KARAKTER sayar. Logda Türkçe harf
    //    çoğaldıkça kayar ve yeni satırlar atlanır — Buffer'da dilimle.
    const bolum = readFileSync(LOG).subarray(baslangicBoyut).toString('utf8');
    const m = bolum.match(/https:\/\/byd\.ordolive\.com\/hesap\/aktivasyon\/[^\s"'<>\]]+/g);
    if (m) return m[m.length - 1].replace(/&amp;/g, '&');
    await bekle(1000);
  }
  return null;
}

const b = await puppeteer.launch({
  executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

let temizlenecekEposta = null;

try {
  /* ───────── 1) Kamuya açık başvuru formu ───────── */
  const s = await b.newPage();
  await s.setViewport({ width: 1440, height: 1000 });
  await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2', timeout: 60000 });

  const doldur = async (ad, deger) => { await s.type(`[name="${ad}"]`, deger); };
  await doldur('resmi_unvan', UNVAN);
  await doldur('adres', 'Gazi Caddesi No: 12');
  await doldur('il', 'Çorum');
  await doldur('ilce', 'Merkez');
  await doldur('kurum_telefon', '0364 213 45 67');
  await doldur('kurum_eposta', `kurum+${damga}@ornek.test`);
  await doldur('vergi_dairesi', 'Çorum Vergi Dairesi');
  await doldur('vergi_no', '1234567890');
  await doldur('calisan_sayisi', '24');
  await s.type('[name="yayin_platformlari[0][ad]"]', 'Test Haber');
  await s.type('[name="yayin_platformlari[0][url]"]', 'https://ornek.test/haber');
  await doldur('yetkili_ad', 'Deneme Yetkili');
  await doldur('yetkili_eposta', EPOSTA);
  await doldur('yetkili_telefon', '0532 111 22 33');
  await s.click('[name="kvkk_aydinlatma"]');
  await s.click('[name="kvkk_riza"]');
  temizlenecekEposta = EPOSTA;

  // ⏱️ Kuyruk hızlı; log işaretini GÖNDERİMDEN ÖNCE al, yoksa mail biz
  //    ölçmeden yazılır ve "log'da yok" deriz.
  const logIsareti = statSync(LOG).size;
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
  const gonderimMetni = await s.evaluate(() => document.body.innerText);
  kontrol('Başvuru formu kabul edildi', s.url().includes('/basvuru/gonderildi'),
    s.url().includes('/basvuru/gonderildi')
      ? ''
      : (/429|Too Many/i.test(gonderimMetni) ? 'HIZ SINIRI (429) — 10 dk bekle' : gonderimMetni.replace(/\s+/g, ' ').slice(0, 90)));
  if (!s.url().includes('/basvuru/gonderildi')) throw new Error('form gönderilemedi');

  /* ───────── 2) Aktivasyon e-postası ───────── */
  const baglanti = await aktivasyonBaglantisiniBekle(logIsareti);
  kontrol('Aktivasyon e-postası kuyruktan gönderildi', !!baglanti, baglanti ? 'bağlantı bulundu' : 'log’da yok');
  if (!baglanti) throw new Error('aktivasyon bağlantısı yok');

  /* ───────── 3) Şifre belirleme ───────── */
  await s.goto(baglanti, { waitUntil: 'networkidle2' });
  kontrol('Aktivasyon sayfası açıldı', (await s.content()).includes('Şifrenizi belirleyin'));
  await s.type('[name="sifre"]', SIFRE);
  await s.type('[name="sifre_confirmation"]', SIFRE);
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
  kontrol('Şifre kaydedildi ve kurum paneline girildi', s.url().includes('/kurum'), s.url().replace(KOK, ''));

  /* ───────── 4) Evrak yükleme ───────── */
  await s.goto(`${KOK}/kurum/basvurum`, { waitUntil: 'networkidle2' });
  const govde0 = await s.evaluate(() => document.body.innerText);
  kontrol('Başvurum sayfası açıldı', govde0.includes('Evraklar'), govde0.split('\n').find(Boolean));

  const girisler = await s.$$('input[type="file"]');
  kontrol('Evrak alanları listelendi', girisler.length >= 2, `${girisler.length} alan`);

  const dosyalar = [`${DOSYA}/ticaret-sicil.pdf`, `${DOSYA}/vergi-levhasi.jpg`];
  for (let i = 0; i < Math.min(2, girisler.length); i++) {
    await girisler[i].uploadFile(dosyalar[i]);
    await bekle(1800);                                  // Livewire yüklemesi
    const dugmeler = await s.$$('button');
    for (const d of dugmeler) {
      const t = (await s.evaluate(el => el.innerText.trim(), d));
      const wire = await s.evaluate(el => el.getAttribute('wire:click') || '', d);
      if (wire.startsWith('yukle(') && (t === 'Yükle' || t === 'Değiştir')) {
        const sira = Number(wire.match(/\d+/)[0]);
        if (i === 0 || sira) { /* sırayla ilerliyoruz */ }
      }
    }
    // i. satırın Yükle düğmesine bas
    await s.evaluate((idx) => {
      const btns = [...document.querySelectorAll('button[wire\\:click^="yukle("]')];
      btns[idx]?.click();
    }, i);
    await bekle(2500);
  }
  await s.reload({ waitUntil: 'networkidle2' });
  const govde1 = await s.evaluate(() => document.body.innerText);
  const yuklenenSayisi = (govde1.match(/Yüklendi/g) || []).length;
  kontrol('İki evrak da yüklendi', yuklenenSayisi >= 2, `${yuklenenSayisi} evrak`);

  /* ───────── 5) Başvuruyu gönder ───────── */
  await s.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => b.innerText.trim() === 'Başvuruyu gönder')?.click());
  await bekle(2500);
  await s.reload({ waitUntil: 'networkidle2' });
  const govde2 = await s.evaluate(() => document.body.innerText);
  kontrol('Başvuru "Gönderildi" durumuna geçti', govde2.includes('Gönderildi'),
    (govde2.match(/Taslak|Gönderildi|İncelemede|Eksik evrak|Onaylandı|Reddedildi/) || ['?'])[0]);

  /* ───────── 6) Yetkili girişi (2FA dahil) ───────── */
  // 🪤 AYRI tarayıcı bağlamı ŞART: aynı bağlamda kurum kullanıcısının oturum
  //    çerezi duruyor, /yonetim/login onu görüp panele yönlendiriyor ve giriş
  //    formu hiç render edilmiyor.
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
  // İlk kutu autocomplete="one-time-code", kalan beşi "off" — hepsi aynı bileşen.
  if (kutular.length >= 6) {
    await kutular[0].click();
    await y.keyboard.type(totp(gizli), { delay: 60 });
    await bekle(1200);
    await y.evaluate(() => [...document.querySelectorAll('button')]
      .find(b => /Girişi doğrula/i.test(b.innerText))?.click());
    await bekle(3000);
  }
  kontrol('Yetkili 2FA ile giriş yaptı', !y.url().includes('/login'), y.url().replace(KOK, ''));

  /* ───────── 7) Kuyrukta görünüyor mu ───────── */
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

  /* ───────── 8) İnceleme ekranı ───────── */
  await y.goto(acLink, { waitUntil: 'networkidle2' });
  await bekle(900);
  const inc = await y.evaluate(() => document.body.innerText);
  kontrol('İnceleme ekranı açıldı', inc.includes('Evraklar') && inc.includes(UNVAN.slice(0, 24)));
  kontrol('Evrak önizleme bölmesi var', await y.evaluate(() => !!document.querySelector('iframe, img[src*="/evrak/"]')));

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

  /* ───────── 9) İncelemeye al ───────── */
  await y.evaluate(() => [...document.querySelectorAll('button, a')]
    .find(e => e.innerText.trim() === 'İncelemeye al')?.click());
  await bekle(2500);
  const inc2 = await y.evaluate(() => document.body.innerText);
  kontrol('Durum "İncelemede" oldu', inc2.includes('İncelemede'));

  /* ───────── 10) Alan bazlı eksik evrak talebi ───────── */
  await y.evaluate(() => [...document.querySelectorAll('button, a')]
    .find(e => e.innerText.trim() === 'Eksik evrak iste')?.click());
  await bekle(2200);
  const secim = await y.$('select');
  kontrol('Eksik evrak kipi açıldı', !!secim);
  if (secim) {
    await y.select('select', 'Vergi levhası');
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

  /* ───────── 11) Kurum tarafı düzeltmeyi görüyor mu ───────── */
  await s.goto(`${KOK}/kurum/basvurum`, { waitUntil: 'networkidle2' });
  await bekle(800);
  const kur = await s.evaluate(() => document.body.innerText);
  kontrol('Kurum düzeltme talebini görüyor',
    kur.includes('Düzeltilmesi istenen') && kur.includes('Levha okunmuyor'));
  kontrol('Kurum yeniden gönderebiliyor', kur.includes('Başvuruyu gönder'));

  /* ───────── 12) Düzelt ve yeniden gönder ───────── */
  await s.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => b.innerText.trim() === 'Başvuruyu gönder')?.click());
  await bekle(2500);
  await s.reload({ waitUntil: 'networkidle2' });
  const kur2 = await s.evaluate(() => document.body.innerText);
  kontrol('Yeniden gönderildi ve düzeltme notları temizlendi',
    kur2.includes('Gönderildi') && !kur2.includes('Düzeltilmesi istenen'));

  /* ───────── 13) Onay ───────── */
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

  /* ───────── 14) Kurum akredite oldu mu (veritabanı) ───────── */
  const durum = artisan(`$u = App\\Models\\User::where('email','${EPOSTA}')->first();
    echo 'AKRED:' . ($u?->kurum?->akreditasyon_durumu ?? 'yok');`);
  kontrol('Kurum akredite edildi', /AKRED:akredite/.test(durum),
    (durum.match(/AKRED:\w+/) || ['?'])[0]);

  await b.close();
} catch (e) {
  console.log('💥 ' + e.message);
  sonuc.push({ ad: 'Beklenmeyen hata', gecti: false, ek: e.message });
  try { await b.close(); } catch {}
} finally {
  /* ───────── Temizlik: SADECE bu testin oluşturduğu kayıt ───────── */
  if (temizlenecekEposta) {
    const kod = `
$u = App\\Models\\User::where('email', '${temizlenecekEposta}')->first();
if ($u) {
    $k = $u->kurum;
    App\\Models\\Evrak::whereIn('basvuru_id', $u->basvurular()->pluck('id'))->get()->each->forceDelete();
    $u->basvurular()->forceDelete();
    $u->forceDelete();
    if ($k) { $k->forceDelete(); }
    echo 'TEMIZ';
} else { echo 'YOK'; }`;
    try { console.log('🧹 ' + artisan(kod).trim().split('\n').pop()); }
    catch (e) { console.log('⚠️ temizlik yapılamadı: ' + e.message); }
  }
}

const hata = sonuc.filter(r => !r.gecti).length;
console.log(`\n${sonuc.length - hata}/${sonuc.length} geçti`);
process.exit(hata ? 1 : 0);
