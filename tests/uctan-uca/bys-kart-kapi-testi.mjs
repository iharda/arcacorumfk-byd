/**
 * BYS — kart üretimi, QR imzası, doğrulama API'si ve kapı akışı (Aşama 04).
 *
 * ⚠️ ÜRETİME YAZAR. Kendi oluşturduğu kaydı ve dosyalarını siler.
 * node /root/bys-kart-kapi-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
const TEST_DOSYALARI = (process.env.BYS_TEST_DOSYALARI ?? import.meta.dirname + '/../../../test-dosyalari');   // ornek evraklar; BYS_TEST_DOSYALARI ile degistirilebilir

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = process.env.BYS_ALAN || 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const SIFRE = 'Kirmizi-Kartal-2026-x9';
const damga = Date.now();
const MAIL = `kart+${damga}@ornek.test`;
const UNVAN = `Kart Test Ajans ${damga}`;

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push({ ad, gecti, ek }); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const bekle = ms => new Promise(r => setTimeout(r, ms));
const artisan = kod => execFileSync('sudo', ['-u', 'bys', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: (process.env.BYS_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 180000 });
const cek = (metin, etiket) => (metin.match(new RegExp(etiket + ':(\\S+)')) || [])[1];

/** Doğrulama API'sine istek. --resolve ile origin'e doğrudan gider. */
function api(yol, anahtar, govde = null, ekBaslik = []) {
  const args = ['-s', '-k', '-o', '-', '-w', '\n__KOD__%{http_code}',
    '--resolve', `${ALAN}:443:127.0.0.1`,
    '-H', `X-Kapi-Anahtar: ${anahtar}`, '-H', 'Accept: application/json', ...ekBaslik];
  if (govde) { args.push('-X', 'POST', '-H', 'Content-Type: application/json', '-d', JSON.stringify(govde)); }
  args.push(`${KOK}${yol}`);
  const cikti = execFileSync('curl', args, { encoding: 'utf8', timeout: 30000 });
  const [gövde, kodSatiri] = cikti.split('\n__KOD__');
  let veri = {};
  try { veri = JSON.parse(gövde); } catch { /* json değil */ }
  return { kod: Number(kodSatiri), veri };
}

let anahtarA = '', anahtarB = '';

try {
  /* ═════ HAZIRLIK: akredite kurum + onaylı akreditasyon + kart ═════ */
  const kur = artisan(`
$k = App\\Models\\Kurum::create(['resmi_unvan' => '${UNVAN}', 'akreditasyon_durumu' => 'akredite']);
$u = App\\Models\\User::create(['name' => 'Şükrü Ağaoğlu', 'email' => '${MAIL}',
    'password' => bcrypt('${SIFRE}'), 'kurum_id' => $k->id, 'aktif' => true, 'email_verified_at' => now()]);
$u->assignRole(App\\Models\\User::ROL_BASIN);
$b = App\\Models\\Basvuru::create(['tur' => App\\Enums\\BasvuruTuru::BasinMensubu,
    'durum' => App\\Enums\\BasvuruDurumu::Onaylandi, 'kullanici_id' => $u->id, 'kurum_id' => $k->id,
    'gonderildi_at' => now(), 'karar_at' => now()]);
$t = App\\Models\\EvrakTuru::where('kod', 'biyometrik_fotograf')->first();
app(App\\Servisler\\EvrakYukleyici::class)->yukle($b, $t,
    new Illuminate\\Http\\UploadedFile('${TEST_DOSYALARI}/foto.jpg', 'foto.jpg', 'image/jpeg', null, true));
$a = app(App\\Servisler\\AkreditasyonAkisi::class)->basvurudanOlustur($b);
$a->update(['bolge_yetkileri' => ['basin_locasi'], 'sezon' => '2026 / 2027']);
echo 'KART:' . $a->kart_no . ' ULID:' . $a->ulid;`);

  const kartNo = cek(kur, 'KART'), akrUlid = cek(kur, 'ULID');
  // Tür harfi ayardan geliyor; teste sabit harf yazmıyoruz.
  kontrol('Hazırlık: akreditasyon ve kart no üretildi', /^\d{4}-[A-Z]-\d{4}$/.test(kartNo || ''), kartNo);

  /* ═════ 1) Kart PDF + görsel üretimi (kuyruk) ═════ */
  let kartBilgi = '';
  for (let i = 0; i < 40; i++) {
    kartBilgi = artisan(`
$a = App\\Models\\Akreditasyon::where('ulid', '${akrUlid}')->first();
$kt = $a->guncelKart;
echo 'PDF:' . ($kt && Illuminate\\Support\\Facades\\Storage::disk($kt->disk)->exists($kt->pdf_yolu) ? Illuminate\\Support\\Facades\\Storage::disk($kt->disk)->size($kt->pdf_yolu) : 0)
   . ' PNG:' . ($kt && Illuminate\\Support\\Facades\\Storage::disk($kt->disk)->exists($kt->gorsel_yolu) ? Illuminate\\Support\\Facades\\Storage::disk($kt->disk)->size($kt->gorsel_yolu) : 0)
   . ' SURUM:' . ($kt?->surum ?? 0);`);
    if (Number(cek(kartBilgi, 'PDF')) > 10000) break;
    await bekle(1500);
  }
  kontrol('Kart PDF üretildi', Number(cek(kartBilgi, 'PDF')) > 10000, `${cek(kartBilgi, 'PDF')} bayt`);
  kontrol('Kart görseli üretildi', Number(cek(kartBilgi, 'PNG')) > 10000, `${cek(kartBilgi, 'PNG')} bayt`);

  /* ═════ 2) QR yükü ═════ */
  const qr = artisan(`
$a = App\\Models\\Akreditasyon::where('ulid', '${akrUlid}')->first();
echo 'YUK:' . app(App\\Servisler\\QrImzalayici::class)->yukUret($a);`);
  const yuk = cek(qr, 'YUK');
  kontrol('QR yükü imzalandı', /^BYS1\.\d+\.[0-9A-Z]{26}\.[\w-]{22}$/.test(yuk || ''), yuk);

  /* ═════ 3) Kapı istemcileri ═════ */
  const kapilar = artisan(`
$s = app(App\\Servisler\\KapiIstemcisiAkisi::class);
$a = $s->olustur(['ad' => 'Test Kapı A ${damga}', 'kapi_kodu' => 'TESTA${damga}']);
$b = $s->olustur(['ad' => 'Test Kapı B ${damga}', 'kapi_kodu' => 'TESTB${damga}', 'bolgeler' => ['saha_kenari']]);
echo 'ANAHTARA:' . $a['anahtar'] . ' ANAHTARB:' . $b['anahtar'];`);
  anahtarA = cek(kapilar, 'ANAHTARA'); anahtarB = cek(kapilar, 'ANAHTARB');
  kontrol('Kapı anahtarları üretildi', !!anahtarA && !!anahtarB, anahtarA?.slice(0, 14) + '…');

  /* ═════ 4) Doğrulama API ═════ */
  let y = api('/api/kapi/tanim', anahtarA);
  kontrol('Kapı tanımı alınıyor', y.kod === 200 && y.veri.kapiKodu?.startsWith('TESTA'), `${y.kod} ${y.veri.kapi ?? ''}`);

  y = api('/api/kapi/tanim', 'kapi_yanlisanahtaryanlisanahtaryanlis1234');
  kontrol('Yanlış anahtar reddediliyor', y.kod === 401, String(y.kod));

  y = api('/api/kapi/dogrula', anahtarA, { yuk });
  kontrol('Geçerli kart İZİNLİ', y.kod === 200 && y.veri.izinli === true, `${y.veri.sonuc} · ${y.veri.kisi?.isim ?? ''}`);
  kontrol('Yanıtta fotoğraf var (yüz kontrolü için)',
    typeof y.veri.kisi?.foto === 'string' && y.veri.kisi.foto.startsWith('data:image'),
    y.veri.kisi?.foto ? `${Math.round(y.veri.kisi.foto.length / 1024)} KB` : 'YOK');
  kontrol('Yanıtta kart no ve kurum var', y.veri.kisi?.kartNo === kartNo && !!y.veri.kisi?.kurum);

  /* Mükerrer okutma */
  y = api('/api/kapi/dogrula', anahtarA, { yuk });
  kontrol('Aynı kapıda hemen tekrar okutma MÜKERRER işaretleniyor',
    y.veri.sonuc === 'mukerrer_okutma', y.veri.sonuc);

  /* Bölge yetkisi */
  y = api('/api/kapi/dogrula', anahtarB, { yuk });
  kontrol('Yetkisi olmayan bölgede geçiş reddediliyor',
    y.veri.sonuc === 'bolge_yetkisi_yok' && y.veri.izinli === false, y.veri.sonuc);

  /* Bozuk imza */
  y = api('/api/kapi/dogrula', anahtarA, { yuk: yuk.slice(0, -1) + 'X' });
  kontrol('Bozuk imza reddediliyor', y.veri.sonuc === 'imza_gecersiz', y.veri.sonuc);
  kontrol('Bozuk imzada kişi bilgisi SIZMIYOR', y.veri.kisi === null);

  /* Bilinmeyen kart (imzası geçerli ama kayıt yok) */
  const sahte = artisan(`
$s = app(App\\Servisler\\QrImzalayici::class);
$x = new App\\Models\\Akreditasyon(); $x->ulid = '01M0J9999999999999999999ZZ';
echo 'YUK:' . $s->yukUret($x);`);
  y = api('/api/kapi/dogrula', anahtarA, { yuk: cek(sahte, 'YUK') });
  kontrol('Kaydı olmayan kart reddediliyor', y.veri.sonuc === 'bulunamadi', y.veri.sonuc);

  /* İptal edilen kart ANINDA geçersiz */
  artisan(`
$a = App\\Models\\Akreditasyon::where('ulid', '${akrUlid}')->first();
app(App\\Servisler\\AkreditasyonAkisi::class)->iptalEt($a, 'Test iptali');
echo 'IPTAL';`);
  y = api('/api/kapi/dogrula', anahtarA, { yuk });
  kontrol('İptal edilen kart ANINDA geçersiz', y.veri.sonuc === 'iptal' && y.veri.izinli === false, y.veri.sonuc);

  /* ═════ 5) IP kısıtı ═════ */
  artisan(`
App\\Models\\KapiIstemcisi::where('kapi_kodu', 'TESTA${damga}')->update(['ip_listesi' => ['203.0.113.7']]);
echo 'IPKISIT';`);
  y = api('/api/kapi/dogrula', anahtarA, { yuk });
  kontrol('IP kısıtı dışından erişim reddediliyor', y.kod === 403, String(y.kod));

  /* ═════ 6) Geçiş kayıtları ═════ */
  const kayitlar = artisan(`
$k = App\\Models\\GecisKaydi::whereIn('kapi_kodu', ['TESTA${damga}', 'TESTB${damga}'])->get();
echo 'TOPLAM:' . $k->count()
   . ' IZINLI:' . $k->where('sonuc', App\\Enums\\GecisSonucu::Izinli)->count()
   . ' BASARISIZ:' . $k->where('sonuc', '!=', App\\Enums\\GecisSonucu::Izinli)->count()
   . ' HAMYUK:' . ($k->contains(fn ($x) => str_contains((string) $x->okunan_referans, 'BYS1.')) ? 'VAR' : 'YOK')
   . ' PARMAKIZI:' . ($k->contains(fn ($x) => str_starts_with((string) $x->okunan_referans, 'sha256:')) ? 'VAR' : 'YOK');`);
  kontrol('Her okutma loglandı', Number(cek(kayitlar, 'TOPLAM')) >= 6, cek(kayitlar, 'TOPLAM'));
  kontrol('BAŞARISIZ okutmalar da loglandı', Number(cek(kayitlar, 'BASARISIZ')) >= 4, cek(kayitlar, 'BASARISIZ'));
  kontrol('Logda ham QR yükü YOK (yalnızca referans)', cek(kayitlar, 'HAMYUK') === 'YOK', cek(kayitlar, 'HAMYUK'));
  kontrol('Geçersiz kart parmak iziyle loglanıyor', cek(kayitlar, 'PARMAKIZI') === 'VAR', cek(kayitlar, 'PARMAKIZI'));

  /* ═════ 7) Kapı uygulaması (PWA) ═════ */
  const b = await puppeteer.launch({ executablePath: CHROME, headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'] });
  const s = await b.newPage();
  await s.setViewport({ width: 420, height: 860, deviceScaleFactor: 2 });
  const dis = [];
  s.on('request', r => { const u = r.url(); if (!u.startsWith(KOK) && !u.startsWith('data:')) dis.push(u); });
  const yanit = await s.goto(`${KOK}/kapi`, { waitUntil: 'networkidle2' });
  await bekle(1200);
  const govde = await s.evaluate(() => document.body.innerText);
  kontrol('Kapı uygulaması açılıyor', yanit.status() === 200 && /Cihazı tanıt/.test(govde), String(yanit.status()));
  kontrol('Anahtarsız cihaz yalnızca kurulum ekranı görüyor',
    !/İzinli|Kart no|Şükrü/.test(govde));
  // 🪤 `hidden` yeterli değil: display:flex onu ezebiliyor. Gerçekten kaç ekran
  //    çizilmiş, ölç.
  const gorunenEkran = await s.evaluate(() => [...document.querySelectorAll('[data-ekran]')]
    .filter(e => e.getBoundingClientRect().height > 0).map(e => e.dataset.ekran));
  kontrol('Aynı anda tek ekran görünüyor',
    gorunenEkran.length === 1 && gorunenEkran[0] === 'kurulum', gorunenEkran.join(', ') || 'hiçbiri');
  kontrol('Kapı uygulaması dış kaynağa istek atmıyor', dis.length === 0, dis.slice(0, 2).join(' '));
  await s.screenshot({ path: '/root/bys-kapi-kurulum.png', fullPage: true });

  const man = await s.goto(`${KOK}/kapi/manifest.json`, { waitUntil: 'domcontentloaded' });
  kontrol('PWA tanım dosyası geçerli', man.status() === 200 && /"scope":\s*"\\?\/kapi"/.test(await man.text()));
  await b.close();

} catch (e) {
  console.log('💥 ' + e.message);
  sonuc.push({ ad: 'Beklenmeyen hata', gecti: false, ek: e.message });
} finally {
  try {
    const t = artisan(`
$u = App\\Models\\User::withTrashed()->where('email', '${MAIL}')->first();
if ($u) {
    $bIds = App\\Models\\Basvuru::withTrashed()->where('kullanici_id', $u->id)->pluck('id');
    $akr = App\\Models\\Akreditasyon::whereIn('basvuru_id', $bIds)->get();
    foreach ($akr as $a) {
        foreach ($a->kartlar as $kt) {
            foreach ([$kt->pdf_yolu, $kt->gorsel_yolu] as $y) {
                if ($y) { Illuminate\\Support\\Facades\\Storage::disk($kt->disk)->delete($y); }
            }
        }
        App\\Models\\GecisKaydi::where('akreditasyon_id', $a->id)->delete();
        $a->kartlar()->delete();
        $a->delete();
    }
    App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $bIds)->get()->each->forceDelete();
    App\\Models\\Basvuru::withTrashed()->whereIn('id', $bIds)->forceDelete();
    $k = $u->kurum; $u->forceDelete(); $k?->forceDelete();
}
App\\Models\\GecisKaydi::whereIn('kapi_kodu', ['TESTA${damga}', 'TESTB${damga}'])->delete();
App\\Models\\KapiIstemcisi::whereIn('kapi_kodu', ['TESTA${damga}', 'TESTB${damga}'])->forceDelete();
echo 'TEMIZ';`);
    console.log('🧹 ' + t.trim().split('\n').pop());
  } catch (e) { console.log('⚠️ temizlik: ' + e.message); }
}

const hata = sonuc.filter(r => !r.gecti).length;
console.log(`\n${sonuc.length - hata}/${sonuc.length} geçti`);
process.exit(hata ? 1 : 0);
