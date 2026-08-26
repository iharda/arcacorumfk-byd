/**
 * BYD — bireysel başvuru akışlarının uçtan uca testi (Başvuru akışı v2).
 *
 * Kapsam: içerik üreticisi · basın mensubu "Yol A" + kurum teyidi ·
 *         "Yol B" davet linki · ayrılış → akreditasyon otomatik iptal.
 *
 * 🔑 Evrak artık BAŞVURU FORMUNDA alınır ve hesap ONAY anında açılır
 *    (Revizyon md.1): aktivasyon + panelden yükleme adımları YOK.
 *
 * ⚠️ ÜRETİME YAZAR. Kendi oluşturduğu kayıtları siler, `kurum_teyidi_istensin`
 *    ayarının ESKİ DEĞERİNİ saklayıp finally'de aynen geri yazar.
 *
 * node /root/byd-bireysel-akis-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { totp } from './byd-totp.mjs';

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = process.env.BYD_ALAN || 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const D = (process.env.BYD_TEST_DOSYALARI ?? import.meta.dirname + '/../../../test-dosyalari');
const SIFRE = 'Kirmizi-Kartal-2026-x9';

const damga = Date.now();
const KURUM_YETKILI = `b3kurum+${damga}@ornek.test`;
const ICERIK = `b3icerik+${damga}@ornek.test`;
const BASIN = `b3basin+${damga}@ornek.test`;
const DAVETLI = `b3davetli+${damga}@ornek.test`;
const UNVAN = `B3 Test Ajans ${damga}`;

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push({ ad, gecti, ek }); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const bekle = ms => new Promise(r => setTimeout(r, ms));
const artisan = kod => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 90000 });

/** Başvurunun durumunu e-postasından okur (hesap YOK, bağ e-posta üzerinden). */
function basvuruDurumu(eposta) {
  return artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta', '${eposta}')->latest('id')->first();
echo 'DURUM:' . ($b?->durum->value ?? 'yok')
   . ' EVRAK:' . ($b?->evraklar()->count() ?? 0)
   . ' TEYIT:' . ($b?->kurumTeyidiBekliyorMu() ? 'bekliyor' : 'yok')
   . ' KUYRUKTA:' . ($b && App\\Models\\Basvuru::kuyrukta()->whereKey($b->id)->exists() ? 'evet' : 'hayir')
   . ' HESAP:' . (App\\Models\\User::withTrashed()->where('email','${eposta}')->exists() ? 'var' : 'yok');`);
}

function basvuruUlid(eposta) {
  return (artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta', '${eposta}')->latest('id')->first();
echo 'ULID:' . ($b?->ulid ?? 'yok');`).match(/ULID:(\w+)/) || [])[1];
}

async function girisYap(sayfa, yol, eposta, sifre) {
  // Tek giriş kapısı (Revizyon md.4): panel başına giriş sayfası YOK.
  await sayfa.goto(`${KOK}/giris`, { waitUntil: 'networkidle2' });
  await sayfa.type('[name="email"]', eposta);
  await sayfa.type('[name="password"]', sifre);
  await Promise.all([
    sayfa.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    sayfa.click('button[type="submit"]'),
  ]);
  await bekle(900);
}

/** Kamuya açık formdaki evrak kutularını sırayla doldurur; kutu sayısını döner. */
async function evraklariSec(sayfa, dosyalar) {
  const girisler = await sayfa.$$('input[type="file"]');
  for (let i = 0; i < girisler.length; i++) {
    await girisler[i].uploadFile(dosyalar[i % dosyalar.length]);
  }
  return girisler.length;
}

const b = await puppeteer.launch({
  executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

// 🔁 Ayarın ESKİ değerini sakla — finally'de aynen geri yazılacak.
const eskiAyar = (artisan(`echo 'ESKI:' . json_encode(App\\Models\\Ayar::al('kurum_teyidi_istensin'));`)
  .match(/ESKI:(\S+)/) || [, 'null'])[1];

try {
  /* ═════ HAZIRLIK: akredite kurum + yetkilisi ═════ */
  artisan(`
$k = App\\Models\\Kurum::create(['resmi_unvan' => '${UNVAN}', 'akreditasyon_durumu' => 'akredite']);
$u = App\\Models\\User::create(['name' => 'B3 Kurum Yetkilisi', 'email' => '${KURUM_YETKILI}',
    'password' => bcrypt('${SIFRE}'), 'kurum_id' => $k->id, 'aktif' => true, 'email_verified_at' => now()]);
$u->assignRole(App\\Models\\User::ROL_KURUM);
App\\Models\\Ayar::yaz('kurum_teyidi_istensin', true);
echo 'HAZIR';`);
  kontrol('Hazırlık: akredite kurum + teyit ayarı açık', true);

  /* ═════ 0) "Kurumum listede yok" — çıkmaz sokak olmamalı ═════ */
  {
    const c0 = await b.createBrowserContext();
    const f = await c0.newPage();
    await f.goto(`${KOK}/basvuru/basin-mensubu`, { waitUntil: 'networkidle2' });

    const secenekler = await f.$$eval('#kurum_ulid option', o => o.map(x => x.value));
    kontrol('Kurum listesinde "listede yok" seçeneği var', secenekler.includes('yok'));

    const kutuGorunur = () => f.evaluate(() => {
      const d = [...document.querySelectorAll('div')]
        .find(x => x.querySelector('a') && /Kurum başvurusu yap/.test(x.innerText || ''));
      return d ? getComputedStyle(d).display !== 'none' && d.offsetHeight > 0 : false;
    });
    kontrol('Yönlendirme kutusu başlangıçta gizli', (await kutuGorunur()) === false);

    await f.select('#kurum_ulid', 'yok');
    await bekle(400);
    kontrol('"Listede yok" seçilince kurum başvurusuna yönlendiriliyor', await kutuGorunur());
    kontrol('Kurum seçilmeden gönderilemiyor', await f.$eval('button[type=submit]', d => d.disabled));

    // 🪤 Aynı sekmede açılırsa yarım kalan form kaybolur.
    const bag = await f.$$eval('a', a => a.filter(x => /Kurum başvurusu yap/.test(x.textContent))
      .map(x => ({ yol: new URL(x.href).pathname, hedef: x.target, rel: x.rel }))[0] ?? null);
    kontrol('Kurum başvurusu YENİ SEKMEDE açılıyor',
      bag?.yol === '/basvuru/kurum' && bag.hedef === '_blank' && /noopener/.test(bag.rel),
      JSON.stringify(bag));
    kontrol('Davet yoluyla devam edilebileceği yazıyor',
      /davet edebilir/.test(await f.evaluate(() => document.body.innerText)));
    // 🪤 Alpine ifadesi çift tırnak yüzünden metin olarak SIZMAMALI.
    const govde = await f.evaluate(() => document.body.innerText);
    kontrol('Sayfada çıplak JS metni yok', ! /x-show|x-data|kurum ===/.test(govde));

    // JS kapalı istemci "yok" gönderirse sunucu ne diyor?
    const yanit = await f.evaluate(async (kok) => {
      const jeton = document.querySelector('input[name=_token]').value;
      const g = new FormData();
      g.append('_token', jeton); g.append('kurum_ulid', 'yok');
      const c = await fetch(`${kok}/basvuru/basin-mensubu`, { method: 'POST', body: g, redirect: 'follow' });
      return (await c.text()).includes('akredite değil');
    }, KOK);
    kontrol('Sunucu "yok" değerini reddediyor', yanit);
    await c0.close();
  }

  /* ═════ 1) İÇERİK ÜRETİCİSİ — tek adım, evrak formda ═════ */
  const c1 = await b.createBrowserContext();
  const s1 = await c1.newPage();
  await s1.setViewport({ width: 1400, height: 1000 });
  await s1.goto(`${KOK}/basvuru/icerik-ureticisi`, { waitUntil: 'networkidle2' });
  await s1.type('[name="ad_soyad"]', 'Bağımsız Gazeteci');
  await s1.type('[name="eposta"]', ICERIK);
  await s1.type('[name="telefon"]', '5331112233');
  await s1.type('[name="adres"]', 'Yeni Mahalle 5');
  // 🪤 İl/ilçe bağlı açılır liste; ilçeler il seçilince çizilir.
  await s1.select('#il', 'Çorum');
  await bekle(500);
  await s1.select('#ilce', 'Merkez');
  await s1.type('[name="sosyal_medya[x]"]', 'https://ornek.test/x');
  await s1.evaluate(() => {
    document.querySelector('input[name="basin_karti_var"][value="0"]').click();
    document.querySelector('input[name="kvkk_aydinlatma"]').click();
    document.querySelector('input[name="kvkk_riza"]').click();
  });
  const evrak1 = await evraklariSec(s1, [`${D}/foto.jpg`, `${D}/kimlik.jpg`]);
  kontrol('İçerik üreticisi formunda evrak kutuları var', evrak1 === 2, `${evrak1} alan`);

  await Promise.all([s1.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}), s1.click('button[type="submit"]')]);
  kontrol('İçerik üreticisi başvurusu alındı', s1.url().includes('gonderildi'), s1.url().replace(KOK, ''));

  const d1 = basvuruDurumu(ICERIK);
  kontrol('Evraklarıyla birlikte kuyruğa düştü',
    /DURUM:gonderildi/.test(d1) && /EVRAK:2/.test(d1) && /KUYRUKTA:evet/.test(d1),
    (d1.match(/DURUM:\w+ EVRAK:\d+/) || ['?'])[0]);
  kontrol('İçerik üreticisine onaydan önce HESAP AÇILMADI', /HESAP:yok/.test(d1));

  /* ═════ 2) BASIN MENSUBU — Yol A (kurum teyidi açık) ═════ */
  const c2 = await b.createBrowserContext();
  const s2 = await c2.newPage();
  await s2.setViewport({ width: 1400, height: 1000 });
  await s2.goto(`${KOK}/basvuru/basin-mensubu`, { waitUntil: 'networkidle2' });
  await s2.type('[name="ad_soyad"]', 'Muhabir Aday');
  await s2.type('[name="eposta"]', BASIN);
  await s2.type('[name="telefon"]', '5342223344');
  await s2.type('[name="adres"]', 'Gazi Cad. 9');
  await s2.select('#il', 'Çorum');
  await bekle(500);
  await s2.select('#ilce', 'Merkez');
  await s2.type('[name="calisma_yili"]', '6');
  const kurumSecildi = await s2.evaluate((unvan) => {
    const sec = document.querySelector('select[name="kurum_ulid"]');
    const opt = [...sec.options].find(o => o.text.includes(unvan));
    if (!opt) return false;
    sec.value = opt.value;
    sec.dispatchEvent(new Event('change', { bubbles: true }));
    document.querySelector('input[name="sigorta_212_var"][value="1"]').click();
    document.querySelector('input[name="basin_karti_var"][value="1"]').click();
    document.querySelector('input[name="kvkk_aydinlatma"]').click();
    document.querySelector('input[name="kvkk_riza"]').click();
    return true;
  }, UNVAN);
  kontrol('Akredite kurum listede görünüyor', kurumSecildi);

  const evrak2 = await evraklariSec(s2, [`${D}/foto.jpg`, `${D}/kimlik.jpg`, `${D}/calisma-belgesi.jpg`]);
  kontrol('Basın mensubu formunda üç evrak kutusu var', evrak2 === 3, `${evrak2} alan`);

  await Promise.all([s2.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}), s2.click('button[type="submit"]')]);
  kontrol('Basın mensubu başvurusu alındı', s2.url().includes('gonderildi'), s2.url().replace(KOK, ''));

  const d2 = basvuruDurumu(BASIN);
  kontrol('Basın mensubu gönderildi, KURUM TEYİDİ bekliyor',
    /DURUM:gonderildi/.test(d2) && /EVRAK:3/.test(d2) && /TEYIT:bekliyor/.test(d2),
    (d2.match(/DURUM:\w+ EVRAK:\d+ TEYIT:\w+/) || ['?'])[0]);
  kontrol('Teyit bekleyen başvuru yetkili kuyruğunda YOK', /KUYRUKTA:hayir/.test(d2),
    (d2.match(/KUYRUKTA:\w+/) || ['?'])[0]);

  /* ═════ 3) KURUM: teyit ver + davet gönder ═════ */
  const c3 = await b.createBrowserContext();
  const s3 = await c3.newPage();
  await s3.setViewport({ width: 1500, height: 1000 });
  await girisYap(s3, '/kurum', KURUM_YETKILI, SIFRE);
  await s3.goto(`${KOK}/kurum/calisanlar`, { waitUntil: 'networkidle2' });
  await bekle(900);
  const cal = await s3.evaluate(() => document.body.innerText);
  kontrol('Kurum teyit bekleyen başvuruyu ADIYLA görüyor', cal.includes('Muhabir Aday'), '');
  await s3.screenshot({ path: '/root/byd-calisanlar.png', fullPage: true });

  await s3.evaluate(() => [...document.querySelectorAll('button')].find(b => b.innerText.trim() === 'Teyit et')?.click());
  await bekle(1500);
  await s3.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => /^(Onayla|Teyit et|Evet)$/i.test(b.innerText.trim()) && b.closest('.fi-modal, [role="dialog"]'))?.click());
  await bekle(2800);
  const d2b = basvuruDurumu(BASIN);
  kontrol('Teyit sonrası başvuru kuyruğa girdi', /KUYRUKTA:evet/.test(d2b),
    (d2b.match(/KUYRUKTA:\w+/) || ['?'])[0]);

  /* Davet gönder (Yol B) — kurum panelinden, gerçek arayüzle */
  await s3.reload({ waitUntil: 'networkidle2' });
  await s3.evaluate(() => [...document.querySelectorAll('button')].find(b => b.innerText.trim() === 'Çalışan davet et')?.click());
  await bekle(1600);
  // 🪤 Livewire alanına .value atamak YETMEZ; gerçek tuş vuruşu gerekiyor.
  //    Filament kip alanlarının id'si: mountedActionSchema0.<alan>
  await s3.type('#mountedActionSchema0\\.ad_soyad', 'Davetli Muhabir');
  await s3.type('#mountedActionSchema0\\.eposta', DAVETLI);
  await bekle(900);
  await s3.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => b.innerText.trim() === 'Daveti gönder')?.click());
  await bekle(3000);

  // 🪤 Ham token yalnızca üretildiği an var. Panel onu kalıcı bildirimde
  //    gösteriyor (e-posta gitmezse elden iletilebilsin diye).
  const davetBag = await s3.evaluate(() => {
    const m = document.body.innerText.match(/https:\/\/[^\s]+\/davet\/[A-Za-z0-9]+/);
    return m ? m[0] : null;
  });
  kontrol('Davet oluştu ve bağlantı panelde gösterildi', !!davetBag, davetBag ? 'bağlantı var' : 'yok');

  /* ═════ 4) Yol B: davetli formu doldurur ═════ */
  if (davetBag) {
    const c4 = await b.createBrowserContext();
    const s4 = await c4.newPage();
    await s4.setViewport({ width: 1400, height: 1000 });
    await s4.goto(davetBag, { waitUntil: 'networkidle2' });
    const dv = await s4.evaluate(() => document.body.innerText);
    kontrol('Davet formu kurumu ve kişiyi gösteriyor',
      dv.includes('Davetli Muhabir') && dv.includes(UNVAN.slice(0, 18)));
    kontrol('Davet formunda ad/e-posta alanı YOK (kurumdan gelir)',
      await s4.evaluate(() => !document.querySelector('[name="ad_soyad"]')));

    await s4.type('[name="telefon"]', '5353334455');
    await s4.type('[name="adres"]', 'İnönü Cad. 3');
    await s4.select('#il', 'Çorum');
    await bekle(500);
    await s4.select('#ilce', 'Merkez');
    await s4.type('[name="calisma_yili"]', '3');
    await s4.evaluate(() => {
      document.querySelector('input[name="sigorta_212_var"][value="1"]').click();
      document.querySelector('input[name="basin_karti_var"][value="0"]').click();
      document.querySelector('input[name="kvkk_aydinlatma"]').click();
      document.querySelector('input[name="kvkk_riza"]').click();
    });
    await evraklariSec(s4, [`${D}/foto.jpg`, `${D}/kimlik.jpg`, `${D}/calisma-belgesi.jpg`]);
    await Promise.all([s4.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}), s4.click('button[type="submit"]')]);
    kontrol('Davetli başvurusu alındı', s4.url().includes('gonderildi'), s4.url().replace(KOK, ''));

    // Yol B'de kurum zaten başlattı → İKİNCİ teyit istenmez.
    const d4 = basvuruDurumu(DAVETLI);
    kontrol('Davetli başvurusunda kurum teyidi İSTENMİYOR',
      /DURUM:gonderildi/.test(d4) && /TEYIT:yok/.test(d4) && /KUYRUKTA:evet/.test(d4),
      (d4.match(/TEYIT:\w+ KUYRUKTA:\w+/) || ['?'])[0]);
  }

  /* ═════ 5) YETKİLİ: içerik üreticisini onayla → kart no ═════ */
  const c5 = await b.createBrowserContext();
  const y = await c5.newPage();
  await y.setViewport({ width: 1600, height: 1000 });
  await y.goto(`${KOK}/yonetim/login`, { waitUntil: 'networkidle2' });
  await y.type('#form\\.email', 'admin@byd.ordolive.com');
  await y.type('#form\\.password', readFileSync('/root/.byd-admin-pass', 'utf8').trim());
  await Promise.all([y.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}), y.click('button[type="submit"]')]);
  await bekle(1200);
  const kutular = await y.$$('input[inputmode="numeric"]');
  if (kutular.length >= 6) {
    await kutular[0].click();
    await y.keyboard.type(totp(readFileSync('/root/.byd-admin-totp', 'utf8').trim()), { delay: 60 });
    await bekle(900);
    await y.evaluate(() => [...document.querySelectorAll('button')].find(b => /Girişi doğrula/i.test(b.innerText))?.click());
    await bekle(3000);
  }
  kontrol('Yetkili giriş yaptı', !y.url().includes('/login'), y.url().replace(KOK, ''));

  await y.goto(`${KOK}/yonetim/basvurular/${basvuruUlid(ICERIK)}/inceleme`, { waitUntil: 'networkidle2' });
  await bekle(800);
  const incIcerik = await y.evaluate(() => document.body.innerText);
  kontrol('Bireysel inceleme ekranı form verisini gösteriyor',
    incIcerik.includes('Başvuru bilgileri') && incIcerik.includes('Bağımsız Gazeteci'));

  await y.evaluate(() => [...document.querySelectorAll('button, a')].find(e => e.innerText.trim() === 'İncelemeye al')?.click());
  await bekle(2500);
  await y.evaluate(() => [...document.querySelectorAll('button, a')].find(e => e.innerText.trim() === 'Onayla')?.click());
  await bekle(1500);
  await y.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => b.innerText.trim() === 'Onayla' && b.closest('.fi-modal, [role="dialog"]'))?.click());
  await bekle(3000);

  const kart = artisan(`
$u = App\\Models\\User::where('email', '${ICERIK}')->first();
$a = $u?->akreditasyon;
echo 'KART:' . ($a?->kart_no ?? 'yok') . ' DURUM:' . ($a?->durum?->value ?? 'yok')
   . ' ROL:' . ($u?->getRoleNames()->implode(',') ?? 'yok') . ' IL:' . ($u?->il ?? 'yok');`);
  // Tür harfi artık AYARDAN geliyor; teste sabit harf yazmıyoruz.
  const icerikHarfi = (artisan(`echo 'HARF:' . App\\Servisler\\KartNoUretici::kod(App\\Enums\\BasvuruTuru::IcerikUreticisi);`)
    .match(/HARF:(\w)/) || [, '?'])[1];
  kontrol('Onayda hesap, akreditasyon ve kart no üretildi',
    new RegExp(`KART:\\d{4}-${icerikHarfi}-\\d{4}`).test(kart),
    (kart.match(/KART:\S+/) || ['?'])[0]);
  kontrol('Bireye içerik üreticisi rolü verildi', /ROL:icerik_ureticisi/.test(kart),
    (kart.match(/ROL:\S*/) || ['?'])[0]);
  kontrol('Form verisi (il) hesaba taşındı', /IL:Çorum/.test(kart), (kart.match(/IL:\S+/) || ['?'])[0]);

  /* ═════ 6) AYRILIŞ: kurum işaretler → akreditasyon iptal ═════ */
  await y.goto(`${KOK}/yonetim/basvurular/${basvuruUlid(BASIN)}/inceleme`, { waitUntil: 'networkidle2' });
  await bekle(800);
  await y.evaluate(() => [...document.querySelectorAll('button, a')].find(e => e.innerText.trim() === 'İncelemeye al')?.click());
  await bekle(2300);
  await y.evaluate(() => [...document.querySelectorAll('button, a')].find(e => e.innerText.trim() === 'Onayla')?.click());
  await bekle(1400);
  await y.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => b.innerText.trim() === 'Onayla' && b.closest('.fi-modal, [role="dialog"]'))?.click());
  await bekle(3000);

  const kart2 = artisan(`$u = App\\Models\\User::where('email','${BASIN}')->first(); echo 'KART:' . ($u?->akreditasyon?->kart_no ?? 'yok');`);
  const basinHarfi = (artisan(`echo 'HARF:' . App\\Servisler\\KartNoUretici::kod(App\\Enums\\BasvuruTuru::BasinMensubu);`)
    .match(/HARF:(\w)/) || [, '?'])[1];
  kontrol('Basın mensubuna kart no verildi',
    new RegExp(`KART:\\d{4}-${basinHarfi}-\\d{4}`).test(kart2),
    (kart2.match(/KART:\S+/) || ['?'])[0]);

  await s3.goto(`${KOK}/kurum/calisanlar`, { waitUntil: 'networkidle2' });
  await bekle(1000);
  await s3.evaluate(() => {
    const satir = [...document.querySelectorAll('div')].find(d => d.innerText?.startsWith('Muhabir Aday'));
    const kok = satir?.closest('div[style*="border"]') ?? document;
    [...kok.querySelectorAll('button')].find(b => /Ayrıldı olarak işaretle/.test(b.innerText))?.click();
  });
  await bekle(1600);
  await s3.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => b.innerText.trim() === 'Ayrılışı bildir')?.click());
  await bekle(3000);

  const ayrilis = artisan(`
$u = App\\Models\\User::where('email','${BASIN}')->first();
echo 'AYRILDI:' . ($u?->ayrildi_at ? 'evet' : 'hayir') . ' AKRDURUM:' . ($u?->akreditasyon?->durum?->value ?? 'yok');`);
  kontrol('Ayrılış kaydedildi', /AYRILDI:evet/.test(ayrilis), (ayrilis.match(/AYRILDI:\w+/) || ['?'])[0]);
  kontrol('Ayrılışta akreditasyon OTOMATİK iptal oldu', /AKRDURUM:iptal/.test(ayrilis),
    (ayrilis.match(/AKRDURUM:\w+/) || ['?'])[0]);

  await b.close();
} catch (e) {
  console.log('💥 ' + e.message);
  sonuc.push({ ad: 'Beklenmeyen hata', gecti: false, ek: e.message });
  try { await b.close(); } catch {}
} finally {
  /* ═════ Temizlik + ayarı ESKİ HÂLİNE getir ═════ */
  try {
    // 🪤 Hesap onaya kadar YOK: temizlik BAŞVURUDAN yürür, kullanıcıdan değil.
    const t = artisan(`
foreach (['${ICERIK}', '${BASIN}', '${DAVETLI}', '${KURUM_YETKILI}'] as $mail) {
    $u = App\\Models\\User::withTrashed()->where('email', $mail)->first();
    $bIds = App\\Models\\Basvuru::withTrashed()
        ->where('basvuran_eposta', $mail)
        ->when($u, fn ($q) => $q->orWhere('kullanici_id', $u->id))
        ->pluck('id');
    foreach (App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $bIds)->get() as $e) {
        Illuminate\\Support\\Facades\\Storage::disk($e->disk)->delete($e->yol);
    }
    App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $bIds)->forceDelete();
    // 🪤 Toplu delete model olayını tetiklemez → kart dosyaları diskte kalır.
    App\\Models\\Akreditasyon::whereIn('basvuru_id', $bIds)->get()->each(function ($a) {
        $a->kartlar()->get()->each->delete();
        $a->gecisKayitlari()->delete();
        $a->delete();
    });
    App\\Models\\BasvuruBileti::whereIn('basvuru_id', $bIds)->delete();
    App\\Models\\Davet::where('eposta', $mail)->delete();
    App\\Models\\Basvuru::withTrashed()->whereIn('id', $bIds)->forceDelete();
    if ($u) {
        Illuminate\\Support\\Facades\\DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
}
$k = App\\Models\\Kurum::withTrashed()->where('resmi_unvan', '${UNVAN}')->first();
if ($k) { App\\Models\\Davet::where('kurum_id', $k->id)->delete(); $k->forceDelete(); }
App\\Models\\Ayar::yaz('kurum_teyidi_istensin', json_decode('${eskiAyar}', true));
echo 'TEMIZ+AYAR_GERI';`);
    console.log('🧹 ' + t.trim().split('\n').pop());
  } catch (e) { console.log('⚠️ temizlik: ' + e.message); }
}

const hata = sonuc.filter(r => !r.gecti).length;
console.log(`\n${sonuc.length - hata}/${sonuc.length} geçti`);
process.exit(hata ? 1 : 0);
