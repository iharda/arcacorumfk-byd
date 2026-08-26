/**
 * BYD — yeniden başvuru testi.
 *
 * Yusuf/IT 2026-08-23: "Reddedilen veya ayrılan kullanıcı yeniden
 * başvurabilmeli; şu an e-posta db ve request seviyesinde benzersiz kabul
 * edildiği için başvuramıyor."
 *
 * Ölçtüğü: kamuya açık form aynı e-postayı KABUL EDİYOR mu, süren başvurusu
 * olan gerçekten engelleniyor mu, ayrılan hesap yeniden başvurup onayla
 * birlikte tekrar etkinleşiyor mu — ve bütün bunlar olurken İKİNCİ BİR HESAP
 * açılmıyor mu.
 *
 * 🔑 Hesap ONAY anında açılır (Revizyon md.1): başvuru sırasında kullanıcı
 *    kaydı YOKTUR, bağ e-posta üzerinden kurulur.
 *
 * ⚠️ ÜRETİME YAZAR. Kendi kurum/hesap/başvuru kayıtlarını sonunda siler.
 * ⚠️ Başvuru gönderimi 10 dakikada 5 istek: test başlarken cache temizlenir.
 *
 * node /root/byd-yeniden-basvuru-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = process.env.BYD_ALAN || 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const D = (process.env.BYD_TEST_DOSYALARI ?? import.meta.dirname + '/../../../test-dosyalari');

const damga = Date.now();
const ADAY = `yeniden+${damga}@ornek.test`;
const UNVAN = `Yeniden Test Ajans ${damga}`;

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push(gecti); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const bekle = ms => new Promise(r => setTimeout(r, ms));
const artisan = kod => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 90000 });

/** Hız sınırı sayacı önbellekte: arka arkaya koşabilmek için sıfırla. */
execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'cache:clear'], { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..') });

const b = await puppeteer.launch({
  executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

/** Basın mensubu formunu evraklarıyla doldurup gönderir; dönüş: {url, govde}. */
async function basvuruGonder(adSoyad) {
  const baglam = await b.createBrowserContext();
  const s = await baglam.newPage();
  await s.setViewport({ width: 1400, height: 1000 });
  await s.goto(`${KOK}/basvuru/basin-mensubu`, { waitUntil: 'networkidle2' });
  await s.type('[name="ad_soyad"]', adSoyad);
  await s.type('[name="eposta"]', ADAY);
  await s.type('[name="telefon"]', '5354445566');
  await s.type('[name="adres"]', 'İnönü Cad. 12');
  // 🪤 İl/ilçe bağlı açılır liste (Revizyon md.5.1).
  await s.select('#il', 'Çorum');
  await bekle(500);
  await s.select('#ilce', 'Merkez');
  await s.type('[name="calisma_yili"]', '4');
  const secildi = await s.evaluate((unvan) => {
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
  if (!secildi) { await baglam.close(); return { url: '', govde: 'KURUM SEÇİLEMEDİ' }; }

  // Evrak artık aynı formda (Revizyon md.3.1).
  const girisler = await s.$$('input[type="file"]');
  const dosyalar = [`${D}/foto.jpg`, `${D}/kimlik.jpg`, `${D}/calisma-belgesi.jpg`];
  for (let i = 0; i < girisler.length; i++) {
    await girisler[i].uploadFile(dosyalar[i % dosyalar.length]);
  }

  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
  await bekle(500);
  const cikti = { url: s.url().replace(KOK, ''), govde: await s.evaluate(() => document.body.innerText) };
  await baglam.close();
  return cikti;
}

/** Adresin özeti: kaç hesap, kaç başvuru, son durum, ayrılış. */
const ozet = () => {
  const c = artisan(`
$u = App\\Models\\User::withTrashed()->where('email', '${ADAY}')->get();
$k = $u->first();
$bler = App\\Models\\Basvuru::withTrashed()
    ->where('basvuran_eposta', '${ADAY}')
    ->when($k, fn ($q) => $q->orWhere('kullanici_id', $k->id))
    ->get();
echo 'OZET:' . json_encode([
    'hesap' => $u->count(),
    'basvuru' => $bler->count(),
    'son' => optional($bler->sortByDesc('id')->first())->durum?->value,
    'ayrildi' => $k ? ($k->ayrildi_at !== null) : null,
    'aktif' => $k ? (bool) $k->aktif : null,
    'roller' => $k ? $k->getRoleNames()->all() : [],
]);`);
  return JSON.parse((c.match(/OZET:(\{.*\})/) || [, '{}'])[1]);
};

try {
  /* ═════ HAZIRLIK ═════ */
  artisan(`App\\Models\\Kurum::create(['resmi_unvan' => '${UNVAN}', 'akreditasyon_durumu' => 'akredite']); echo 'HAZIR';`);
  kontrol('Hazırlık: akredite kurum oluştu', true);

  /* ═════ 1) İLK BAŞVURU ═════ */
  let r = await basvuruGonder('Yeniden Aday');
  kontrol('İlk başvuru alındı', r.url.includes('gonderildi'), r.url || r.govde.slice(0, 90));

  let o = ozet();
  kontrol('Hesap YOK, tek başvuru var', o.hesap === 0 && o.basvuru === 1, JSON.stringify(o));

  /* ═════ 2) SÜREN BAŞVURU VARKEN ENGEL ═════ */
  r = await basvuruGonder('Yeniden Aday');
  kontrol('Süren başvuru varken yeni başvuru engelleniyor',
    !r.url.includes('gonderildi') && /devam eden bir başvurunuz/i.test(r.govde),
    r.url);
  kontrol('Engel ikinci bir başvuru AÇMADI', ozet().basvuru === 1);

  /* ═════ 3) REDDEDİLDİ → YENİDEN BAŞVURU ═════ */
  artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta', '${ADAY}')->latest('id')->firstOrFail();
app(App\\Servisler\\BasvuruAkisi::class)->reddet($b, 'Deneme reddi');
echo 'REDDEDILDI';`);

  r = await basvuruGonder('Yeniden Aday');
  kontrol('Reddedilen aday YENİDEN başvurabiliyor', r.url.includes('gonderildi'),
    r.url || r.govde.slice(0, 120));

  o = ozet();
  kontrol('Yeniden başvuruda hâlâ HESAP AÇILMIYOR', o.hesap === 0, JSON.stringify(o));
  kontrol('İkinci başvuru kaydedildi', o.basvuru === 2 && o.son === 'gonderildi', JSON.stringify(o));

  /* ═════ 4) ONAY → HESAP AÇILIR → AYRILIŞ ═════ */
  artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta', '${ADAY}')->latest('id')->firstOrFail();
// Kart üretimi işini kuyruğa atmadan hesabı ve akreditasyonu elde ediyoruz:
// ölçtüğümüz şey yeniden başvuru hakkı, kart değil.
[$u, $sifre] = app(App\\Servisler\\HesapAcici::class)->onaydanOlustur($b);
$b->forceFill(['durum' => App\\Enums\\BasvuruDurumu::Onaylandi, 'karar_at' => now()])->save();
$yil = (int) now()->year;
App\\Models\\Akreditasyon::create([
    'kart_no' => $yil . '-Z-' . random_int(9000, 9999), 'yil' => $yil, 'tur_kodu' => 'Z',
    'sira' => random_int(9000, 9999), 'kullanici_id' => $u->id, 'basvuru_id' => $b->id,
    'durum' => App\\Enums\\AkreditasyonDurumu::Aktif,
]);
app(App\\Servisler\\AkreditasyonAkisi::class)->kullaniciAyrildi($u, 'Test ayrılışı');
echo 'AYRILDI';`);

  o = ozet();
  kontrol('Onayda hesap açıldı, ayrılışta pasifleşti',
    o.hesap === 1 && o.ayrildi === true && o.aktif === false, JSON.stringify(o));

  const iptalMi = artisan(`
$u = App\\Models\\User::where('email', '${ADAY}')->firstOrFail();
echo 'IPTAL:' . ($u->akreditasyonlar()->where('durum', '!=', 'iptal')->count() === 0 ? 'evet' : 'hayir');`);
  kontrol('Ayrılışta akreditasyon iptal edildi', /IPTAL:evet/.test(iptalMi));

  /* ═════ 5) AYRILAN KİŞİ YENİDEN BAŞVURUR ═════ */
  r = await basvuruGonder('Yeniden Aday');
  kontrol('Ayrılan kişi YENİDEN başvurabiliyor', r.url.includes('gonderildi'),
    r.url || r.govde.slice(0, 120));

  o = ozet();
  kontrol('Hâlâ tek hesap, üç başvuru', o.hesap === 1 && o.basvuru === 3, JSON.stringify(o));
  kontrol('Onay gelmeden hesap etkinleşmiyor', o.ayrildi === true && o.aktif === false, JSON.stringify(o));

  /* ═════ 6) ONAY: MEVCUT hesap yeniden etkinleşir ═════ */
  artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta', '${ADAY}')->latest('id')->firstOrFail();
app(App\\Servisler\\HesapAcici::class)->onaydanOlustur($b);
echo 'YENIDEN_ETKIN';`);

  o = ozet();
  kontrol('Onayda mevcut hesap yeniden etkinleşti (ikinci hesap yok)',
    o.hesap === 1 && o.ayrildi === false && o.aktif === true, JSON.stringify(o));
  kontrol('Basın mensubu rolü duruyor', o.roller.includes('basin_mensubu'), JSON.stringify(o.roller));
} finally {
  await b.close();
  // Kuyruktaki iptal bildirimi kaydı okuyacak; silmeden önce işlensin diye
  // bekle. Yoksa her koşuda failed_jobs'a ModelNotFound çöpü düşer.
  await bekle(5000);
  artisan(`
$u = App\\Models\\User::withTrashed()->where('email', '${ADAY}')->first();
$bIds = App\\Models\\Basvuru::withTrashed()
    ->where('basvuran_eposta', '${ADAY}')
    ->when($u, fn ($q) => $q->orWhere('kullanici_id', $u->id))
    ->pluck('id');
foreach (App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $bIds)->get() as $e) {
    Illuminate\\Support\\Facades\\Storage::disk($e->disk)->delete($e->yol);
}
App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $bIds)->forceDelete();
App\\Models\\Akreditasyon::whereIn('basvuru_id', $bIds)->delete();
App\\Models\\BasvuruBileti::whereIn('basvuru_id', $bIds)->delete();
App\\Models\\Basvuru::withTrashed()->whereIn('id', $bIds)->forceDelete();
if ($u) {
    Illuminate\\Support\\Facades\\DB::table('model_has_roles')->where('model_id', $u->id)->delete();
    $u->forceDelete();
}
App\\Models\\Kurum::where('resmi_unvan', '${UNVAN}')->forceDelete();
echo 'TEMIZ';`);
}

const gecen = sonuc.filter(Boolean).length;
console.log(`\n${gecen === sonuc.length ? '\x1b[32m' : '\x1b[31m'}${gecen}/${sonuc.length} kontrol geçti\x1b[0m`);
process.exit(gecen === sonuc.length ? 0 : 1);
