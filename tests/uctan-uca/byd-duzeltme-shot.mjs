/**
 * Duzeltme ekranini CANLIDA acar ve goruntuler.
 *
 * ⚠️ URETIME YAZAR: gecici bir basvuru olusturur, duzeltme talebi acar,
 * ekrani ceker ve SONUNDA olusturdugu her seyi siler. Var olan kayitlara
 * DOKUNMAZ.
 */
import puppeteer from 'puppeteer-core';
import { execFileSync } from 'node:child_process';
import { readdirSync } from 'node:fs';

const KOK = '/home/byd.ordolive.com/laravel';
const tinker = (php) =>
  execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', php],
    { cwd: KOK, encoding: 'utf8' }).trim();

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
echo $b->id.'|'.app(App\\Servisler\\BasvuruBiletiAkisi::class)->uret($b->fresh());
`;
const [basvuruId, token] = tinker(kur).split('|');
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
  const yanit = await sayfa.goto(`https://byd.ordolive.com/basvuru/duzelt/${token}`,
    { waitUntil: 'networkidle0', timeout: 30000 });

  console.log('HTTP:', yanit.status());

  const metin = await sayfa.evaluate(() => document.body.innerText);
  const kontroller = [
    ['tur basligi', /Düzeltme talebi 01/],
    ['duzeltilecek bilgiler bolumu', /Düzeltilecek bilgiler/],
    ['mevcut deger gosterimi', /Şu anki değer/],
    ['eski adres gorunuyor', /Eski adres 1/],
    ['ek talep bolumu', /Ek talepler/],
    ['ek talep basligi', /Yayin sozlesmesi/],  // fixture ASCII yaziyor
    ['aciklama kutusu', /Açıklamanız/],
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
  console.log(`kontrol: ${gecen}/${kontroller.length}`);

  await sayfa.screenshot({ path: '/root/byd-duzeltme-ekrani.png', fullPage: true });
  console.log('goruntu: /root/byd-duzeltme-ekrani.png');
} finally {
  await tarayici.close();
  // 🧹 TEMIZLIK: olusturdugumuz her sey gider, var olanlara dokunulmaz.
  tinker(`
    $b = App\\Models\\Basvuru::find(${basvuruId});
    if ($b) { $b->duzeltmeler()->forceDelete(); $b->biletler()->forceDelete(); $b->forceDelete(); }
  `);
  const sonrasi = tinker(`echo DB::table('basvurular')->count().'|'.DB::table('basvuru_duzeltmeleri')->count();`);
  console.log('sonrasi (basvuru|duzeltme):', sonrasi, oncesi === sonrasi ? '✓ ESIT' : '✗ ARTIK KALDI');
}
