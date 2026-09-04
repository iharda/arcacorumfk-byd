/**
 * BYS — doğrulama hatasında ilk hatalı alana kaydırma.
 *
 * Kamu formları klasik POST; hata olunca sayfa baştan çizilir ve tarayıcı en
 * üste düşer. Başvuru formu uzun olduğu için kullanıcı yalnızca "N alan hatalı"
 * yazısını görüp kırmızı kutuyu elle ARAYARAK aşağı iniyordu.
 *
 * Ölçülenler:
 *   1. Hata yolları `<body data-hata-alanlari>` ile istemciye geçiyor
 *   2. Formun ALTINDAKİ hatalı alana kaydırılıyor ve odak oraya veriliyor
 *   3. Köşeli parantezli girdi adı (`yayin_platformlari[0][url]`) noktalı hata
 *      yoluyla eşleşiyor -- Alpine ile çizilen alanlar dahil
 *   4. Sıralama DOM'a göre: kural sırasında ÖNCE gelen kvkk yerine ekranda
 *      önce duran evrak alanına gidiliyor
 *
 * ⚠️ ÜRETİME YAZMAZ: her gönderim doğrulamada düşer, kayıt oluşmaz.
 * ⚠️ Başvuru gönderimi 10 dakikada 5 istek: her gönderimden önce önbellek
 *    temizlenir.
 *
 * node tests/uctan-uca/bys-hata-kaydirma-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { resolve } from 'node:path';
import { readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = process.env.BYS_ALAN || 'bys.corumfk.com.tr';
const KOK = `https://${ALAN}`;
const D = resolve(process.env.BYS_TEST_DOSYALARI ?? import.meta.dirname + '/../../../test-dosyalari');
const KURULUM = process.env.BYS_KOK ?? import.meta.dirname + '/../..';

const damga = Date.now();
const VKN = '4319521692';

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push(gecti); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const bekle = ms => new Promise(r => setTimeout(r, ms));
const sinirSifirla = () => execFileSync('sudo', ['-u', 'bys', 'php', 'artisan', 'cache:clear'], { cwd: KURULUM });

const b = await puppeteer.launch({
  executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

/**
 * Kurum formunu geçerli değerlerle doldurur; `bozuk` içindeki alanlar üste
 * yazılır. `evraksiz` verilirse belgeler yüklenmez.
 */
async function formuDoldur(s, { bozuk = {}, evraksiz = false } = {}) {
  const eposta = bozuk.yetkili_eposta ?? `kaydirma+${damga}-${Math.random().toString(36).slice(2, 8)}@ornek.test`;

  await s.type('[name="resmi_unvan"]', `Kaydırma Testi ${damga}`);
  await s.type('[name="adres"]', 'Gazi Caddesi No 1');
  // 🪤 İlçe seçenekleri il seçildikten SONRA çizilir; arada beklemek şart.
  await s.select('#il', 'Çorum');
  await bekle(500);
  await s.select('#ilce', 'Merkez');
  await s.type('#kurum_telefon', '3642134567');
  await s.type('[name="kurum_eposta"]', eposta);
  await s.type('[name="vergi_dairesi"]', 'Çorum');
  await s.type('[name="vergi_no"]', VKN);
  await s.select('#calisan_araligi', '6-10');
  await s.type('[name="yayin_platformlari[0][ad]"]', 'Kaydırma Testi Haber');
  await s.type('[name="yayin_platformlari[0][url]"]', bozuk['yayin_platformlari[0][url]'] ?? 'https://ornek.com.tr');
  await s.type('[name="yetkili_ad"]', 'Kaydırma Testi Yetkilisi');
  await s.type('[name="yetkili_eposta"]', eposta);
  await s.type('#yetkili_telefon', bozuk.yetkili_telefon ?? '5321234567');

  if (! evraksiz) {
    const girisler = await s.$$('input[type="file"]');
    const evraklar = [`${D}/ticaret-sicil.pdf`, `${D}/vergi-levhasi.pdf`];
    for (let i = 0; i < girisler.length; i++) await girisler[i].uploadFile(evraklar[i]);
  }

  if (! bozuk.kvkksiz) {
    await s.click('[name="kvkk_aydinlatma"]');
    await s.click('[name="kvkk_riza"]');
  }
}

/**
 * Formu gönderir ve hata sayfası çizilip kaydırma oturduktan sonraki durumu
 * verir.
 *
 * 🪤 `required` KALDIRILIR: tarayıcının kendi balonu formu hiç göndermez,
 * sunucu doğrulamasına -- yani ölçmek istediğimiz yola -- ulaşamayız.
 */
async function gonderVeOlc(s) {
  await s.$$eval('[required]', ler => ler.forEach(e => e.removeAttribute('required')));
  sinirSifirla();

  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);

  // Yumuşak kaydırma otursun.
  await bekle(1200);

  return s.evaluate(() => {
    const odak = document.activeElement;
    const kutu = odak?.getBoundingClientRect?.();

    return {
      yollar: document.body.dataset.hataAlanlari ?? null,
      odakAdi: odak?.name ?? null,
      kaydirma: Math.round(window.scrollY),
      // Alan gerçekten görünür pencerede mi?
      goruntude: !! kutu && kutu.top >= 0 && kutu.bottom <= window.innerHeight,
      // Ekrandaki İLK hatalı alan (kırmızı çerçeve) hangisi?
      ilkKirmizi: document.querySelector('.border-kulup-600[name], .border-kulup-600 [name]')?.name ?? null,
    };
  });
}

try {
  /* ── 1. Formun altındaki tek hata: sayfa oraya kaydırılıyor ────── */
  let c = await b.createBrowserContext();
  let s = await c.newPage();
  await s.setViewport({ width: 1440, height: 900 });
  await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2' });
  // Sabit hat, cep zorunlu olan yetkili alanında reddedilir (formun altı).
  await formuDoldur(s, { bozuk: { yetkili_telefon: '3642134567' } });
  let o = await gonderVeOlc(s);

  kontrol('Hata yolları gövdeye yazılıyor', o.yollar === '["yetkili_telefon"]', o.yollar ?? 'yok');
  kontrol('Odak hatalı alanda', o.odakAdi === 'yetkili_telefon', o.odakAdi ?? 'odak yok');
  kontrol('Sayfa gerçekten aşağı kaydı', o.kaydirma > 300, `scrollY=${o.kaydirma}`);
  kontrol('Hatalı alan görüntüde', o.goruntude === true);
  await c.close();

  /* ── 2. Köşeli parantezli ad (Alpine ile çizilen alan) ──────────── */
  c = await b.createBrowserContext();
  s = await c.newPage();
  await s.setViewport({ width: 1440, height: 900 });
  await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2' });
  // 🪤 Şema `prepareForValidation`'da tamamlanıyor; boşluklu metin yine geçersiz.
  await formuDoldur(s, { bozuk: { 'yayin_platformlari[0][url]': 'gecerli olmayan adres' } });
  o = await gonderVeOlc(s);

  kontrol('Noktalı hata yolu köşeli girdi adıyla eşleşti',
    o.odakAdi === 'yayin_platformlari[0][url]', `${o.yollar} → ${o.odakAdi}`);
  kontrol('Kaydırma yapıldı (parantezli alan)', o.kaydirma > 100, `scrollY=${o.kaydirma}`);
  await c.close();

  /* ── 3. Sıra kurala göre değil, EKRANA göre ─────────────────────── */
  c = await b.createBrowserContext();
  s = await c.newPage();
  await s.setViewport({ width: 1440, height: 900 });
  await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2' });
  // Evrak yok + kvkk işaretsiz. Kural sırasında kvkk ÖNCE, ekranda evrak önce.
  await formuDoldur(s, { evraksiz: true, bozuk: { kvkksiz: true } });
  o = await gonderVeOlc(s);

  const yollar = JSON.parse(o.yollar ?? '[]');
  const kvkkSirasi = yollar.findIndex(y => y.startsWith('kvkk_'));
  const evrakSirasi = yollar.findIndex(y => y.startsWith('evraklar.'));

  kontrol('Kural sırasında kvkk, evraktan önce geliyor',
    kvkkSirasi !== -1 && evrakSirasi !== -1 && kvkkSirasi < evrakSirasi,
    yollar.join(', '));
  kontrol('Buna rağmen odak ekrandaki ilk hataya (evrak) gitti',
    (o.odakAdi ?? '').startsWith('evraklar['), o.odakAdi ?? 'odak yok');
  kontrol('Odaklanan alan, ekrandaki ilk kırmızı alanla aynı',
    o.odakAdi !== null && o.odakAdi === o.ilkKirmizi, `${o.ilkKirmizi} / ${o.odakAdi}`);
  await c.close();
} finally {
  await b.close();
  sinirSifirla();
}

const kaldi = sonuc.filter(x => ! x).length;
console.log(`\n─────────── ${sonuc.length - kaldi} geçti · ${kaldi} kaldı ───────────`);
process.exit(kaldi ? 1 : 0);
