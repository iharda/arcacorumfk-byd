/**
 * Duzeltme ekranini CANLIDA acar ve goruntuler.
 *
 * ⚠️ URETIME YAZAR: gecici bir basvuru olusturur, duzeltme talebi acar,
 * ekrani ceker ve SONUNDA olusturdugu her seyi siler. Var olan kayitlara
 * DOKUNMAZ.
 */
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { readdirSync, readFileSync } from 'node:fs';
import { totp } from './bys-totp.mjs';

const KOK_DIZIN = (process.env.BYS_KOK ?? import.meta.dirname + '/../..');
const KOK = 'https://' + (process.env.BYS_ALAN || 'byd.ordolive.com');
const tinker = (php) =>
  execFileSync('sudo', ['-u', 'bys', 'php', 'artisan', 'tinker', '--execute', php],
    { cwd: KOK_DIZIN, encoding: 'utf8' }).trim();

const oncesi = tinker(`echo DB::table('basvurular')->count().'|'.DB::table('basvuru_duzeltmeleri')->count();`);
console.log('oncesi (basvuru|duzeltme):', oncesi);

const kur = `
$b = App\\Models\\Basvuru::create([
  'tur' => App\\Enums\\BasvuruTuru::IcerikUreticisi,
  'durum' => App\\Enums\\BasvuruDurumu::Incelemede,
  'basvuran_ad' => 'GECICI TEST',
  'basvuran_eposta' => 'gecici-test@example.invalid',
  'basvuran_telefon' => '+905321112233',
  'form_verisi' => ['adres' => 'Eski adres 1', 'il' => 'Çorum', 'ilce' => 'Merkez'],
]);
$d = app(App\\Servisler\\BasvuruAkisi::class)->eksikEvrakIste($b, [
  'veri:telefon' => 'Numaraya ulasilamiyor',
  'veri:adres' => 'Adres eksik',
  'veri:il_ilce' => 'Evraktaki ile tutmuyor',
  'veri:eposta' => 'Adres hatali gorunuyor',
], 'Lutfen bilgileri guncelleyin.', [[
  'anahtar' => 'ek:1', 'etiket' => 'Yayin sozlesmesi', 'tip' => 'dosya',
  'aciklama' => 'Sozlesmenin ilk sayfasi yeterli',
]]);
echo $b->id.'|'.app(App\\Servisler\\BasvuruBiletiAkisi::class)->uret($b->fresh()).'|'.$b->ulid;
`;
const [basvuruId, token, ulid] = tinker(kur).split('|');
console.log('gecici basvuru id:', basvuruId);

// 🪤 Bu sunucuda `puppeteer` YOK, `puppeteer-core` var: Chrome yolu ELLE.
const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${readdirSync(K).sort().pop()}`.replace(/^/, `${K}/`) + '/chrome-linux64/chrome';

const tarayici = await puppeteer.launch({
  executablePath: CHROME,
  headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

try {
  const sayfa = await tarayici.newPage();
  await sayfa.setViewport({ width: 1280, height: 1400 });
  const yanit = await sayfa.goto(`${KOK}/basvuru/duzelt/${token}`,
    { waitUntil: 'networkidle0', timeout: 30000 });

  console.log('HTTP:', yanit.status());

  // <details> kapali gelir; goruntude ve metinde gorunsun diye aciyoruz.
  await sayfa.evaluate(() => document.querySelectorAll('details').forEach((d) => (d.open = true)));
  const metin = await sayfa.evaluate(() => document.body.innerText);
  const kontroller = [
    ['tur basligi', /Düzeltme talebi 01/],
    ['duzeltilecek bilgiler bolumu', /Düzeltilecek bilgiler/],
    ['mevcut deger gosterimi', /Şu anki değer/],
    ['eski adres gorunuyor', /Eski adres 1/],
    ['ek talep bolumu', /Ek talepler/],
    ['ek talep basligi', /Yayin sozlesmesi/],  // fixture ASCII yaziyor
    ['aciklama kutusu', /Açıklamanız/],
    ['ilk bilgiler girisi', /İlk bilgiler/],
    ['basvuru gecmisi bolumu', /Başvuru geçmişi/],
    ['duzeltilemeyen alan nota dustu', /E-posta/],
  ];
  let gecen = 0;
  for (const [ad, desen] of kontroller) {
    const ok = desen.test(metin);
    if (ok) gecen++;
    console.log(`${ok ? '✓' : '✗'} ${ad}`);
  }

  // Girdiler gercekten var mi
  const girdiler = await sayfa.evaluate(() =>
    [...document.querySelectorAll('[name^="alan["]')].map((e) => e.name));
  console.log('alan girdileri:', girdiler.join(', ') || '(YOK)');

  await sayfa.screenshot({ path: '/root/bys-duzeltme-ekrani.png', fullPage: true });
  console.log('goruntu: /root/bys-duzeltme-ekrani.png');

  /*
   * Aynı başvuruyu YETKİLİNİN gözünden de aç: düzeltme geçmişi bölümü
   * "İlk bilgiler · Düzeltme talebi 01" diye görünmeli (Yusuf md.4).
   */
  const y = await tarayici.newPage();
  await y.setViewport({ width: 1500, height: 1200 });
  await y.goto(`${KOK}/yonetim/login`, { waitUntil: 'networkidle2' });
  await y.type('#form\\.email', 'admin@byd.ordolive.com');
  await y.type('#form\\.password', readFileSync('/root/.bys-admin-pass', 'utf8').trim());
  await Promise.all([
    y.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}),
    y.click('button[type="submit"]'),
  ]);
  await new Promise((r) => setTimeout(r, 1200));

  const kutular = await y.$$('input[inputmode="numeric"]');
  if (kutular.length >= 6) {
    await kutular[0].click();
    await y.keyboard.type(totp(readFileSync('/root/.bys-admin-totp', 'utf8').trim()), { delay: 60 });
    await new Promise((r) => setTimeout(r, 900));
    await y.evaluate(() =>
      [...document.querySelectorAll('button')].find((b) => /Girişi doğrula/i.test(b.innerText))?.click());
    await new Promise((r) => setTimeout(r, 3000));
  }

  await y.goto(`${KOK}/yonetim/basvurular/${ulid}/inceleme`, { waitUntil: 'networkidle2' });
  await new Promise((r) => setTimeout(r, 1500));
  await y.evaluate(() => document.querySelectorAll('details').forEach((d) => (d.open = true)));
  await y.evaluate(() => [...document.querySelectorAll('button, [role="button"]')]
    .filter((b) => /Düzeltme geçmişi/i.test(b.innerText)).forEach((b) => b.click()));
  await new Promise((r) => setTimeout(r, 800));

  const yMetin = await y.evaluate(() => document.body.innerText);
  for (const [ad, desen] of [
    ['yetkili: düzeltme geçmişi', /Düzeltme geçmişi/],
    ['yetkili: ilk bilgiler', /İlk bilgiler/],
    ['yetkili: tur başlığı', /Düzeltme talebi 01/],
    ['yetkili: yanıt bekleniyor', /yanıt bekleniyor/],
  ]) {
    const ok = desen.test(yMetin);
    if (ok) gecen++;
    console.log(`${ok ? '✓' : '✗'} ${ad}`);
  }

  await y.screenshot({ path: '/root/bys-inceleme-gecmis.png', fullPage: true });
  console.log('goruntu: /root/bys-inceleme-gecmis.png');

  console.log(`kontrol: ${gecen}/${kontroller.length + 4}`);
} finally {
  /*
   * 💀 Özet buradaydı ve `gecen` bu kapsamda TANIMLI DEĞİLDİ: `finally`nin
   * İLK satırında patlayınca tarayıcı kapanmadı ve GEÇİCİ KAYIT SİLİNMEDİ.
   * `finally` yalnızca temizlik yapar; ölçüm/çıktı try içinde kalır.
   */
  await tarayici.close();
  // 🧹 TEMIZLIK: olusturdugumuz her sey gider, var olanlara dokunulmaz.
  tinker(`
    $b = App\\Models\\Basvuru::find(${basvuruId});
    if ($b) { $b->duzeltmeler()->forceDelete(); $b->biletler()->forceDelete(); $b->forceDelete(); }
  `);
  const sonrasi = tinker(`echo DB::table('basvurular')->count().'|'.DB::table('basvuru_duzeltmeleri')->count();`);
  console.log('sonrasi (basvuru|duzeltme):', sonrasi, oncesi === sonrasi ? '✓ ESIT' : '✗ ARTIK KALDI');
}
