// BYS -- yetkili degerlendirmesi (1-5) uctan uca.
//   BYS_ALAN=<alanadi> node tests/uctan-uca/bys-degerlendirme-testi.mjs
//
// 🔒 Asil soru: puan ve not KULUP DISINA SIZIYOR MU? Yonetimde puan verilir,
// sonra kurum ve uye panellerinin SAYFA KAYNAGINDA notun gecmedigi dogrulanir.
// Blade'de @can sarmali yetmez; veriyi getiren sorgu da yonetimde olmali.
//
// 🪤 Hedef BASVURU TURUNE gore secilir: kurumsal basvuruda KURUM, bireysel
// basvuruda KISI puanlanir. Test ikisini de ayri ayri dener -- ilk surumu
// bireysel bir basvuruyu "kurum" sanarak yanlis yerde arıyordu.
//
// ⚠️ URETIME YAZAR: iki degerlendirme yazar ve sonunda ikisini de SILER.
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { totp } from './bys-totp.mjs';

const CHROME_KOK = '/root/.cache/puppeteer/chrome';
const CHROME = `${CHROME_KOK}/${readdirSync(CHROME_KOK).sort().pop()}/chrome-linux64/chrome`;

const ALAN = process.env.BYS_ALAN || 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const SIFRE = 'Pilot-Deneme-2026';
const UYE = 'muhabir+pilot@ornek.test';
const KURUM_YETKILISI = 'yetkili+pilot@ornek.test';
// Aranacak imza: bu metin kurum/uye panelinde HICBIR yerde gecmemeli.
const NOT = 'SIZINTI-KONTROL-' + process.pid;

// 🪤 execSync KABUKTAN geçer ve PHP'deki `$q` gibi değişkenleri BASH yer.
// execFileSync argümanı olduğu gibi verir; kaçış derdi yok.
const artisan = kod =>
  execFileSync('sudo', ['-u', 'bys', 'php', 'artisan', 'tinker', '--execute', kod],
    { cwd: '/home/bys/laravel', encoding: 'utf8' }).trim().split('\n').pop().trim();

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => sonuc.push({ ad, gecti, ek });
const bekle = ms => new Promise(r => setTimeout(r, ms));

/**
 * 🪤 Livewire eylemi ASENKRON: düğmeye basmak veritabanına yazıldığı anlamına
 * gelmez. Koşul gerçekleşene kadar denenir; sabit bekleme testi ağ hızına bağlar.
 */
async function tekrarDene(kosul, deneme = 12, ara = 500) {
  for (let i = 0; i < deneme; i++) {
    const cikti = await kosul();
    if (cikti) return cikti;
    await bekle(ara);
  }

  return null;
}

const b = await puppeteer.launch({
  executablePath: CHROME,
  headless: 'new',
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage',
         `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

async function yeniSekme() {
  const ctx = await b.createBrowserContext();
  const s = await ctx.newPage();
  await s.setViewport({ width: 1500, height: 1000 });
  return { ctx, s };
}

/** İnceleme ekranındaki değerlendirme modalını doldurup gönderir. */
async function puanVer(s, ulid, puanEtiketi, ilkMi) {
  await s.goto(`${KOK}/yonetim/basvurular/${ulid}/inceleme`, { waitUntil: 'networkidle2' });
  await bekle(900);

  if (ilkMi) {
    const govde = await s.evaluate(() => document.body.innerText);
    kontrol('İnceleme ekranında Değerlendirme bölümü var', /Değerlendirme/.test(govde));
    kontrol('Boş hâlde "Henüz değerlendirilmedi" yazıyor', /Henüz değerlendirilmedi/.test(govde));
    await s.screenshot({ path: '/root/bys-degerlendirme-bos.png', fullPage: true });
  }

  await s.evaluate(() => [...document.querySelectorAll('button')]
    .find(d => /^\s*Değerlendir\s*$/.test(d.innerText))?.click());
  await bekle(1200);

  const secildi = await s.evaluate(etiket => {
    const d = [...document.querySelectorAll('label, button')]
      .find(e => e.innerText.trim() === etiket);
    if (!d) return false;
    d.click();

    return true;
  }, puanEtiketi);

  await s.type('textarea', NOT);
  await bekle(300);
  await s.evaluate(() => [...document.querySelectorAll('.fi-modal button')]
    .find(d => /^\s*(Değerlendir|Kaydet|Gönder)\s*$/.test(d.innerText))?.click());

  return secildi;
}

try {
  /* ───────── Yönetim paneline gir ───────── */
  const { ctx: yctx, s: y } = await yeniSekme();

  await y.goto(`${KOK}/yonetim/login`, { waitUntil: 'networkidle2' });
  await y.type('#form\\.email', 'admin@byd.ordolive.com');
  await y.type('#form\\.password', readFileSync('/root/.bys-admin-pass', 'utf8').trim());
  await Promise.all([y.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
                     y.click('button[type="submit"]')]);
  await bekle(1200);
  const kutular = await y.$$('input[inputmode="numeric"]');
  if (kutular.length >= 6) {
    await kutular[0].click();
    await y.keyboard.type(totp(readFileSync('/root/.bys-admin-totp', 'utf8').trim()), { delay: 60 });
    await bekle(900);
    await y.evaluate(() => [...document.querySelectorAll('button')]
      .find(d => /Girişi doğrula/i.test(d.innerText))?.click());
    await bekle(3000);
  }

  /* ───────── 1) KURUMSAL başvuru → hedef KURUM ───────── */
  const kurumsalUlid = artisan(
    `echo App\\Models\\Basvuru::where('tur','kurum')->whereNotNull('kurum_id')->latest('id')->value('ulid');`);
  kontrol('Kurumsal başvuru bulundu', /^[0-9A-Z]{26}$/.test(kurumsalUlid), kurumsalUlid);

  kontrol('Puan düğmeleri 1-5 açıldı', await puanVer(y, kurumsalUlid, '2 · Olumsuz', true));

  const kurumPuani = await tekrarDene(() => {
    const p = artisan(
      `echo App\\Models\\Degerlendirme::where('hedef_tip','kurum')->latest('id')->first()?->puan?->value ?? 'YOK';`);

    return p === '2' ? p : null;
  });
  kontrol('Kurum puanı veritabanına yazıldı', kurumPuani === '2', `puan=${kurumPuani ?? 'YOK'}`);

  await y.reload({ waitUntil: 'networkidle2' });
  await bekle(900);
  let govde = await y.evaluate(() => document.body.innerText);
  kontrol('Şerit dolu hâliyle görünüyor', /2\s*·\s*Olumsuz/.test(govde));
  kontrol('Not incelemede yetkiliye görünüyor', govde.includes(NOT));
  await y.screenshot({ path: '/root/bys-degerlendirme-dolu.png', fullPage: true });

  await y.goto(`${KOK}/yonetim/kurumlar`, { waitUntil: 'networkidle2' });
  kontrol('Kurumlar tablosunda değerlendirme sütunu dolu',
    await tekrarDene(async () =>
      /2\s*·\s*Olumsuz/.test(await y.evaluate(() => document.body.innerText)) || null) === true);
  await y.screenshot({ path: '/root/bys-degerlendirme-kurumlar.png', fullPage: true });

  /* ───────── 2) BİREYSEL başvuru → hedef KİŞİ ───────── */
  const bireyselUlid = artisan(
    `echo App\\Models\\Basvuru::where('tur','!=','kurum')->where('basvuran_eposta','${UYE}')->latest('id')->value('ulid');`);
  kontrol('Bireysel başvuru bulundu', /^[0-9A-Z]{26}$/.test(bireyselUlid), bireyselUlid);

  await puanVer(y, bireyselUlid, '4 · Olumlu', false);

  const kisiPuani = await tekrarDene(() => {
    const p = artisan(
      `echo App\\Models\\Degerlendirme::where('hedef_tip','kisi')->where('eposta','${UYE}')->first()?->puan?->value ?? 'YOK';`);

    return p === '4' ? p : null;
  });
  kontrol('Kişi puanı e-posta anahtarıyla yazıldı', kisiPuani === '4', `puan=${kisiPuani ?? 'YOK'}`);

  /*
   * 🪤 SAYFALAMA: liste ada göre sıralı ve sayfa başına 10 satır. Kullanıcı
   * sayısı büyüdükçe aranan kişi 2. sayfaya kayıyor ve "sayfada metin var mı"
   * kontrolü VERİ YÜZÜNDEN kırılıyordu -- değerlendirme sütunuyla ilgisi yok.
   * Kişiyi arama kutusuyla süzüyoruz: kontrol satır sayısından bağımsız olsun.
   *
   * 🪤 `?tableSearch=` ADRESTEN ÇALIŞMAZ: Filament'te o özellik `#[Url]` ile
   * işaretli değil. Kutuya gerçekten yazmak gerekiyor ki Livewire tetiklensin.
   */
  await y.goto(`${KOK}/yonetim/kullanicilar`, { waitUntil: 'networkidle2' });

  const aramaKutusu = await y.evaluateHandle(() => [...document.querySelectorAll('input')]
    .find(i => [...i.attributes].some(a => a.name.startsWith('wire:model') && a.value.includes('tableSearch'))));

  if (aramaKutusu.asElement()) {
    await aramaKutusu.asElement().type(UYE, { delay: 20 });
    await bekle(1500);   // debounce (500 ms) + Livewire gidiş-dönüşü
  }
  kontrol('Kullanıcılar tablosunda değerlendirme sütunu dolu',
    await tekrarDene(async () =>
      /4\s*·\s*Olumlu/.test(await y.evaluate(() => document.body.innerText)) || null) === true);
  await y.screenshot({ path: '/root/bys-degerlendirme-kullanicilar.png', fullPage: true });

  // Denetim kaydı: iki olay da düşmüş olmalı.
  const olaySayisi = artisan(
    `echo App\\Models\\DenetimKaydi::where('olay','degerlendirme.verildi')->count();`);
  kontrol('Denetim kaydına düştü', Number(olaySayisi) >= 2, `${olaySayisi} kayıt`);

  await yctx.close();

  /* ───────── 3) Kulüp DIŞI paneller: sızıntı kontrolü ───────── */
  for (const [eposta, yollar, ad] of [
    [KURUM_YETKILISI, ['/kurum', '/kurum/calisanlar'], 'kurum'],
    // 🔑 Puanlanan KİŞİNİN KENDİSİ: kendi puanını hiçbir ekranda görmemeli.
    [UYE, ['/panel', '/panel/kartim', '/panel/basvurum'], 'üye'],
  ]) {
    const { ctx, s } = await yeniSekme();

    await s.goto(`${KOK}/giris`, { waitUntil: 'networkidle2' });
    await s.type('[name="email"]', eposta);
    await s.type('[name="password"]', SIFRE);
    await Promise.all([s.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
                       s.click('button[type="submit"]')]);
    await bekle(900);

    for (const yol of yollar) {
      const y2 = await s.goto(KOK + yol, { waitUntil: 'networkidle2' });
      await bekle(700);
      // 🔒 SAYFA KAYNAĞI: ekranda görünmemesi yetmez, HTML'de de geçmemeli.
      const kaynak = await s.content();
      const yazi = await s.evaluate(() => document.body.innerText);
      const sizdi = kaynak.includes(NOT) || /Olumsuz|Çok olumlu|Değerlendirme/.test(yazi);
      kontrol(`${ad} · ${yol} puan/not sızmıyor`, !sizdi, `HTTP ${y2.status()}`);
    }

    await ctx.close();
  }
} finally {
  await b.close();
  // 🧹 Test verisi kalmasın.
  execFileSync('sudo', ['-u', 'bys', 'php', 'artisan', 'tinker', '--execute',
    `App\\Models\\Degerlendirme::where('not','${NOT}')->delete();`],
    { cwd: '/home/bys/laravel', stdio: 'ignore' });
}

for (const r of sonuc) console.log(`${r.gecti ? '✅' : '❌'} ${r.ad}${r.ek ? '  — ' + r.ek : ''}`);
const kirik = sonuc.filter(r => !r.gecti).length;
console.log(`\n${sonuc.length - kirik}/${sonuc.length} kontrol geçti`);
process.exit(kirik ? 1 : 0);
