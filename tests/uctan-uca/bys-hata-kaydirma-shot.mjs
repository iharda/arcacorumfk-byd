/**
 * BYS — başvuru formlarında hatalı alana kaydırma: aynı hata sayfasının
 * ÖNCE/SONRA ekran görüntüsü. Hem kurum hem bireysel form için.
 *
 * Form geçerli doldurulur, yalnızca formun EN ALTINDAKİ KVKK onayları
 * işaretlenmez. Bu kutularda `required` yoktur (olamaz da: kullanıcı bilerek
 * onaylamalı), hatayı sunucu döndürür -- gerçek kullanıcının yaşadığı yol.
 *
 * 1-once.png : sayfanın tepesi -- eski davranışta kullanıcının gördüğü ekran
 * 2-sonra.png: kaydırma sonrası -- kırmızı alan ekranda ve odakta
 *
 * ⚠️ ÜRETİME YAZMAZ: gönderim doğrulamada düşer, kayıt oluşmaz.
 * ⚠️ Başvuru gönderimi 10 dakikada 5 istek: her gönderimden önce önbellek
 *    temizlenir.
 *
 * node tests/uctan-uca/bys-hata-kaydirma-shot.mjs
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
const CIKTI = process.env.BYS_CIKTI ?? '/root';

const bekle = ms => new Promise(r => setTimeout(r, ms));
const sinirSifirla = () => execFileSync('sudo', ['-u', 'bys', 'php', 'artisan', 'cache:clear'], { cwd: KURULUM });
const damga = Date.now();

// Evrak kutusu id'si (`evrak-{tür}`) → ekranda anlamlı duran örnek dosya.
const BELGELER = {
  1: 'ticaret-sicil.pdf',   // Ticaret Sicili Gazetesi
  2: 'vergi-levhasi.pdf',   // Vergi levhası
  3: 'foto.jpg',            // Biyometrik fotoğraf
  4: 'kimlik.jpg',          // Kimlik belgesi
  5: 'calisma-belgesi.pdf', // Çalışma belgesi
};

/** İl seç, ilçe listesi çizilsin, ilçeyi seç. */
async function ilIlce(s) {
  // 🪤 İlçe seçenekleri il seçildikten SONRA çizilir; arada beklemek şart.
  await s.select('#il', 'Çorum');
  await bekle(500);
  await s.select('#ilce', 'Merkez');
}

async function belgeleriYukle(s) {
  for (const giris of await s.$$('input[type="file"]')) {
    const tur = (await giris.evaluate(e => e.id)).replace('evrak-', '');
    if (BELGELER[tur]) await giris.uploadFile(`${D}/${BELGELER[tur]}`);
  }
}

const FORMLAR = [
  {
    ad: 'kurum',
    yol: '/basvuru/kurum',
    doldur: async (s, eposta) => {
      await s.type('[name="resmi_unvan"]', 'Çorum Haber Ajansı');
      await s.type('[name="adres"]', 'Gazi Caddesi No 1');
      await ilIlce(s);
      await s.type('#kurum_telefon', '3642134567');
      await s.type('[name="kurum_eposta"]', eposta);
      await s.type('[name="vergi_dairesi"]', 'Çorum');
      await s.type('[name="vergi_no"]', '4319521692');
      await s.select('#calisan_araligi', '6-10');
      await s.type('[name="yayin_platformlari[0][ad]"]', 'Çorum Haber');
      await s.type('[name="yayin_platformlari[0][url]"]', 'https://corumhaber.example');
      await s.type('[name="yetkili_ad"]', 'Kaydırma Gösterimi');
      await s.type('[name="yetkili_eposta"]', eposta);
      await s.type('#yetkili_telefon', '5321234567');
      await belgeleriYukle(s);
    },
  },
  {
    ad: 'bireysel',
    yol: '/basvuru/icerik-ureticisi',
    doldur: async (s, eposta) => {
      await s.type('[name="ad_soyad"]', 'Kaydırma Gösterimi');
      await s.type('[name="eposta"]', eposta);
      await s.type('#telefon', '5321234567');
      await s.type('[name="adres"]', 'Gazi Caddesi No 1');
      await ilIlce(s);
      const sm = await s.$$('input[name^="sosyal_medya"]');
      await sm[0].type('https://x.com/kaydirma');
      // Zorunlu radyo: işaretlenmezse tarayıcı formu hiç göndermez.
      await s.click('[name="basin_karti_var"][value="0"]');
      await belgeleriYukle(s);
    },
  },
];

const b = await puppeteer.launch({
  executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

try {
  for (const form of FORMLAR) {
    const c = await b.createBrowserContext();
    const s = await c.newPage();
    await s.setViewport({ width: 1280, height: 900 });
    await s.goto(`${KOK}${form.yol}`, { waitUntil: 'networkidle2' });

    await form.doldur(s, `kaydirma+${form.ad}-${damga}@ornek.test`);
    // KVKK kutuları BİLEREK boş: hatayı sunucu döndürsün.

    sinirSifirla();
    await Promise.all([
      s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
      s.click('button[type="submit"]'),
    ]);
    await bekle(1200);

    const durum = await s.evaluate(() => ({
      yollar: document.body.dataset.hataAlanlari ?? null,
      odak: document.activeElement?.name ?? null,
      y: Math.round(window.scrollY),
    }));

    if (! durum.odak) throw new Error(`${form.ad}: hatalı alan bulunamadı (${durum.yollar})`);

    // "Önce": aynı sayfa, kaydırma yapılmamış hâli.
    await s.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await bekle(300);
    await s.screenshot({ path: `${CIKTI}/bys-hata-kaydirma-${form.ad}-1-once.png` });

    // "Sonra": kaydırmanın bıraktığı yer.
    await s.evaluate(y => window.scrollTo({ top: y, behavior: 'instant' }), durum.y);
    await bekle(300);
    await s.screenshot({ path: `${CIKTI}/bys-hata-kaydirma-${form.ad}-2-sonra.png` });

    console.log(`✅ ${form.ad.padEnd(9)} yollar=${durum.yollar} odak=${durum.odak} kaydırma=${durum.y}px`);
    console.log(`   ${CIKTI}/bys-hata-kaydirma-${form.ad}-{1-once,2-sonra}.png`);
    await c.close();
  }
} finally {
  await b.close();
  sinirSifirla();
}
