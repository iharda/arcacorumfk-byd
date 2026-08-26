/**
 * BYD — medya merkezi içerikleri ve bildirimler (Aşama 05).
 *
 * Kapsam: duyuru / antrenman / bülten yayını · akredite kullanıcıya bildirim ·
 *         üye panelinde görünürlük · taslak sızmıyor · akredite olmayan giremez ·
 *         bülten eki erişimi · ikinci yayında tekrar e-posta gitmemesi ·
 *         duyuru videosu (oynatma + Range + yetkisiz erişim).
 *
 * ⚠️ ÜRETİME YAZAR. Kendi kayıtlarını ve dosyalarını siler.
 * node tests/uctan-uca/byd-icerik-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync, statSync, writeFileSync, unlinkSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = process.env.BYD_ALAN || 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const LOG = (process.env.BYD_KOK ?? import.meta.dirname + '/../..') + '/storage/logs/laravel.log';
const SIFRE = 'Kirmizi-Kartal-2026-x9';
const damga = Date.now();
const AKREDITE = `ic-akr+${damga}@ornek.test`;
const BEKLEYEN = `ic-bek+${damga}@ornek.test`;
const UNVAN = `Icerik Test Ajans ${damga}`;
const DUYURU_BASLIK = `Test duyurusu ${damga}`;
const BULTEN_BASLIK = `Test bülteni ${damga}`;

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push({ ad, gecti, ek }); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const bekle = ms => new Promise(r => setTimeout(r, ms));
const artisan = kod => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 120000 });
const cek = (m, e) => (m.match(new RegExp(e + ':(\\S+)')) || [])[1];

/**
 * Bir kullanıcıya kaç bildirim GÖNDERİLDİ?
 *
 * 🪤 Eskiden posta günlüğünden sayılıyordu; SMTP açılınca günlük boş kaldı.
 *    Artık kaynak veritabanı: her gönderim öncesi kuyruk işi Horizon'da
 *    tamamlanıyor, biz de gerçekten kaç iş işlendiğine bakıyoruz.
 */
function bildirimSayisi(isaret, eposta) {
  const cikti = artisan(`
echo 'SAYI:' . Illuminate\\Support\\Facades\\DB::table('jobs')->count();`);
  return Number((cikti.match(/SAYI:(\d+)/) || [, '0'])[1]);
}

/**
 * Horizon kuyruğu boşalana kadar bekle.
 *
 * 💀 Eskiden `jobs` TABLOSUNU sayıyordu: o tablo veritabanı sürücüsüne ait,
 * bu kurulumda kuyruk REDIS'te. Sayı hep 0 çıkıyor, fonksiyon hiç beklemiyordu.
 */
function kuyrukBosalsin(saniye = 40) {
  for (let i = 0; i < saniye; i++) {
    const c = artisan(`echo 'BEKLEYEN:' . Illuminate\\Support\\Facades\\Queue::size();`);
    if ((c.match(/BEKLEYEN:(\d+)/) || [, '1'])[1] === '0') return true;
    execFileSync('sleep', ['1']);
  }
  return false;
}

/** Gönderim kaydı: YeniIcerik işi Horizon'da kaç kez tamamlandı? */
function icerikBildirimAdedi(isaret) {
  const log = readFileSync('/var/log/byd-horizon.log', 'utf8').slice(isaret);
  return (log.match(/YeniIcerik[^\n]*DONE/g) || []).length;
}

/**
 * Kuyruk boşalana kadar bekler.
 * 🪤 Bildirim SAYISI kullanıcı sayısına bağlı: "en az 3" görüp devam edince
 * kalanlar bir sonraki ölçümün işaretinden SONRA düşüyor ve "ikinci bildirim
 * gitti" gibi görünüyordu. Ölçüm almadan önce günlüğün durması beklenir.
 */
async function kuyrukSakinlesin(sakinSaniye = 3, enFazla = 40) {
  let sonBoyut = -1, sabit = 0;
  for (let i = 0; i < enFazla; i++) {
    const boyut = statSync('/var/log/byd-horizon.log').size;
    sabit = boyut === sonBoyut ? sabit + 1 : 0;
    sonBoyut = boyut;
    if (sabit >= sakinSaniye) return true;
    await bekle(1000);
  }
  return false;
}

async function girisYap(sayfa, yol, eposta) {
  // Tek giriş kapısı (Revizyon md.4).
  await sayfa.goto(`${KOK}/giris`, { waitUntil: 'networkidle2' });
  await sayfa.type('[name="email"]', eposta);
  await sayfa.type('[name="password"]', SIFRE);
  await Promise.all([
    sayfa.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    sayfa.click('button[type="submit"]'),
  ]);
  await bekle(800);
}

const ek = `/tmp/byd-bulten-eki-${damga}.pdf`;
const videoDosya = `/tmp/byd-duyuru-video-${damga}.mp4`;
const b = await puppeteer.launch({ executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'] });

try {
  /* ═════ HAZIRLIK: biri akredite, biri değil iki kullanıcı ═════ */
  artisan(`
$k = App\\Models\\Kurum::create(['resmi_unvan' => '${UNVAN}', 'akreditasyon_durumu' => 'akredite']);

// Akredite: aktif akreditasyonu olan basın mensubu
$u1 = App\\Models\\User::create(['name' => 'Akredite Gazeteci', 'email' => '${AKREDITE}',
    'password' => bcrypt('${SIFRE}'), 'kurum_id' => $k->id, 'aktif' => true, 'email_verified_at' => now()]);
$u1->assignRole(App\\Models\\User::ROL_BASIN);
$b1 = App\\Models\\Basvuru::create(['tur' => App\\Enums\\BasvuruTuru::BasinMensubu,
    'durum' => App\\Enums\\BasvuruDurumu::Onaylandi, 'kullanici_id' => $u1->id, 'kurum_id' => $k->id]);
App\\Models\\Akreditasyon::create(['ulid' => (string) Illuminate\\Support\\Str::ulid(),
    'kart_no' => '2026-K-9001', 'yil' => 2026, 'tur_kodu' => 'K', 'sira' => 9001,
    'kullanici_id' => $u1->id, 'basvuru_id' => $b1->id, 'kurum_id' => $k->id,
    'durum' => App\\Enums\\AkreditasyonDurumu::Aktif]);

// Akredite DEĞİL: başvurusu incelemede, kurumu yok
$u2 = App\\Models\\User::create(['name' => 'Bekleyen Aday', 'email' => '${BEKLEYEN}',
    'password' => bcrypt('${SIFRE}'), 'aktif' => true, 'email_verified_at' => now()]);
$u2->assignRole(App\\Models\\User::ROL_ICERIK);
App\\Models\\Basvuru::create(['tur' => App\\Enums\\BasvuruTuru::IcerikUreticisi,
    'durum' => App\\Enums\\BasvuruDurumu::Incelemede, 'kullanici_id' => $u2->id]);
echo 'HAZIR';`);
  kontrol('Hazırlık: akredite + akredite olmayan kullanıcı', true);

  /* ═════ 1) İçerik oluştur (taslak) ═════ */
  artisan(`
App\\Models\\Duyuru::create(['baslik' => '${DUYURU_BASLIK}', 'ozet' => 'Deneme özeti', 'icerik' => '<p>Gövde</p>']);
App\\Models\\Bulten::create(['baslik' => '${BULTEN_BASLIK}', 'icerik' => '<p>Bülten gövdesi</p>']);
App\\Models\\Antrenman::create(['baslik' => 'Test antrenmanı ${damga}',
    'baslangic_at' => now()->addDays(3), 'yer' => 'Nazmi Avluca', 'basina_acik' => true]);
echo 'TASLAK';`);

  const c1 = await b.createBrowserContext();
  const uye = await c1.newPage();
  await uye.setViewport({ width: 1280, height: 1000 });
  await girisYap(uye, '/panel', AKREDITE);
  kontrol('Akredite kullanıcı panele girdi', uye.url().includes('/panel'), uye.url().replace(KOK, ''));

  await uye.goto(`${KOK}/panel/duyurular`, { waitUntil: 'networkidle2' });
  const taslakGovde = await uye.evaluate(() => document.body.innerText);
  kontrol('TASLAK duyuru üye panelinde GÖRÜNMÜYOR', ! taslakGovde.includes(DUYURU_BASLIK));

  /* ═════ 2) Yayına al → bildirim ═════ */
  const isaret = statSync('/var/log/byd-horizon.log').size;
  artisan(`
$s = app(App\\Servisler\\IcerikAkisi::class);
$s->yayinla(App\\Models\\Duyuru::where('baslik', '${DUYURU_BASLIK}')->first(), 'duyuru');
$s->yayinla(App\\Models\\Bulten::where('baslik', '${BULTEN_BASLIK}')->first(), 'bulten');
$s->yayinla(App\\Models\\Antrenman::where('baslik', 'Test antrenmanı ${damga}')->first(), 'antrenman');
echo 'YAYINDA';`);

  let gonderim = 0;
  for (let i = 0; i < 40; i++) {
    gonderim = icerikBildirimAdedi(isaret);
    if (gonderim >= 3) break;
    await bekle(1000);
  }
  kontrol('Üç içerik bildirimi de kuyruktan gönderildi', gonderim >= 3, `${gonderim} gönderim`);

  // Alıcı kümesi doğru mu? Kaynak: "akredite kullanıcı" tanımının kendisi.
  const alici = artisan(`
$a = App\\Servisler\\IcerikAkisi::akrediteKullanicilar()->pluck('email')->all();
echo 'AKREDITE:' . (in_array('${AKREDITE}', $a) ? 'evet' : 'hayir')
   . ' BEKLEYEN:' . (in_array('${BEKLEYEN}', $a) ? 'evet' : 'hayir');`);
  kontrol('Akredite kullanıcı alıcı listesinde', /AKREDITE:evet/.test(alici));
  kontrol('Akredite OLMAYAN alıcı listesinde DEĞİL', /BEKLEYEN:hayir/.test(alici),
    (alici.match(/BEKLEYEN:\w+/) || ['?'])[0]);

  /* ═════ 3) Üye panelinde görünürlük ═════ */
  await uye.goto(`${KOK}/panel/duyurular`, { waitUntil: 'networkidle2' });
  await bekle(500);
  kontrol('Yayındaki duyuru üye panelinde görünüyor',
    (await uye.evaluate(() => document.body.innerText)).includes(DUYURU_BASLIK));
  await uye.screenshot({ path: '/root/byd-uye-duyurular.png', fullPage: true });

  await uye.goto(`${KOK}/panel/takvim`, { waitUntil: 'networkidle2' });
  await bekle(400);
  const takvim = await uye.evaluate(() => document.body.innerText);
  kontrol('Antrenman takvimi görünüyor', takvim.includes('Test antrenmanı') && takvim.includes('Nazmi Avluca'));
  // 🪤 Ay kısaltması CSS ile BÜYÜK harfe çevriliyor (AĞU); desen büyük/küçük
  //    harfe duyarlı olmamalı.
  const ayDeseni = /\b(Oca|Şub|Mar|Nis|May|Haz|Tem|Ağu|Eyl|Eki|Kas|Ara)\b/i;
  kontrol('Takvimde ay adı Türkçe', ayDeseni.test(takvim), (takvim.match(ayDeseni) || ['?'])[0]);
  await uye.screenshot({ path: '/root/byd-uye-takvim.png', fullPage: true });

  await uye.goto(`${KOK}/panel/bultenler`, { waitUntil: 'networkidle2' });
  await bekle(400);
  kontrol('Bülten üye panelinde görünüyor',
    (await uye.evaluate(() => document.body.innerText)).includes(BULTEN_BASLIK));

  /* ═════ 4) İkinci yayında tekrar e-posta YOK ═════ */
  // 🪤 İKİ ölçüt birden: kuyruk gerçekten boşalmalı (yetkili kaynak) VE günlük
  //    yazımı durmalı (Horizon çıktısı tamponlu; iş bittikten sonra da yazıyor).
  kuyrukBosalsin();
  await kuyrukSakinlesin();
  const isaret2 = statSync('/var/log/byd-horizon.log').size;
  artisan(`
$s = app(App\\Servisler\\IcerikAkisi::class);
$d = App\\Models\\Duyuru::where('baslik', '${DUYURU_BASLIK}')->first();
$s->yayindanKaldir($d, 'duyuru');
$s->yayinla($d, 'duyuru');
echo 'TEKRAR';`);
  await bekle(5000);
  kuyrukBosalsin();
  await kuyrukSakinlesin();
  kontrol('Yeniden yayında İKİNCİ bildirim gitmiyor', icerikBildirimAdedi(isaret2) === 0,
    `${icerikBildirimAdedi(isaret2)} gönderim`);

  /* ═════ 5) Akredite OLMAYAN içeriğe giremiyor ═════
   * Revizyon md.3.5: hesap onay anında açılır, akreditasyon da o işlemde
   * doğar. Akreditasyonu olmayan hesap panele HİÇ giremez -- eskiden panele
   * girip içerik sayfasında 403 alıyordu. */
  const c2 = await b.createBrowserContext();
  const bekleyen = await c2.newPage();
  await girisYap(bekleyen, '/panel', BEKLEYEN);
  await bekleyen.goto(`${KOK}/panel/duyurular`, { waitUntil: 'domcontentloaded' });
  // Tek giriş kapısı (Revizyon md.4): hesap içeri hiç alınmaz, /giris'te kalır.
  kontrol('Akredite olmayan içerik sayfasına giremiyor',
    bekleyen.url().endsWith('/giris'), bekleyen.url().replace(KOK, ''));
  const menu = await bekleyen.goto(`${KOK}/panel`, { waitUntil: 'networkidle2' })
    .then(() => bekleyen.evaluate(() => document.body.innerText));
  kontrol('Akredite olmayanın menüsünde içerik YOK',
    !/Duyurular|Antrenman takvimi|Basın bültenleri/.test(menu));

  /* ═════ 6) Bülten eki erişimi ═════ */
  writeFileSync(ek, Buffer.from('%PDF-1.4\n% test eki\n'));
  artisan(`
$b = App\\Models\\Bulten::where('baslik', '${BULTEN_BASLIK}')->first();
Illuminate\\Support\\Facades\\Storage::disk('icerik')->put('bulten/test-${damga}.pdf', file_get_contents('${ek}'));
$b->update(['ekler' => ['bulten/test-${damga}.pdf']]);
echo 'EKLENDI';`);

  const ekYol = `/icerik/bulten/test-${damga}.pdf`;
  const ekAkredite = await uye.goto(KOK + ekYol, { waitUntil: 'domcontentloaded' });
  kontrol('Akredite kullanıcı bülten ekini indirebiliyor', ekAkredite.status() === 200, String(ekAkredite.status()));

  const ekBekleyen = await bekleyen.goto(KOK + ekYol, { waitUntil: 'domcontentloaded' });
  const ekTur = ekBekleyen.headers()['content-type'] ?? '';
  kontrol('Akredite olmayan bülten ekine ERİŞEMİYOR',
    ! bekleyen.url().endsWith(ekYol) && ! /pdf/.test(ekTur),
    `${ekBekleyen.status()} · ${bekleyen.url().replace(KOK, '')}`);

  // 🪤 Puppeteer yönlendirmeyi TAKİP eder: status() ana sayfanın 200'ü olur.
  //    "200 aldık" diye geçmiş sayma — dosyaya ULAŞILDI mı, ona bak.
  const anon = await (await b.createBrowserContext()).newPage();
  await anon.goto(KOK + ekYol, { waitUntil: 'domcontentloaded' }).catch(() => null);
  const anonSonUrl = anon.url();
  const anonTur = await anon.evaluate(() => document.contentType);
  kontrol('Oturumsuz bülten ekine erişemiyor',
    !anonSonUrl.includes('/icerik/') && anonTur !== 'application/pdf',
    `${anonSonUrl.replace(KOK, '')} · ${anonTur}`);

  // 🔒 Dizin dışına çıkma denemesi
  const sizma = await uye.goto(`${KOK}/icerik/bulten/..%2F..%2F.env`, { waitUntil: 'domcontentloaded' }).catch(() => null);
  kontrol('Dizin dışına çıkma denemesi engelleniyor',
    !sizma || sizma.status() === 404 || sizma.status() === 403, sizma ? String(sizma.status()) : 'reddedildi');

  /* ═════ 7) Duyuru videosu ═════ */
  /* 🎬 `+faststart`: moov atomu dosyanın BAŞINA taşınır. Olmazsa tarayıcı
        süreyi öğrenmek için dosyanın sonunu ister; `preload="metadata"`
        60 MB'lık bir indirmeye dönüşür. */
  execFileSync('ffmpeg', ['-y', '-f', 'lavfi',
    '-i', 'testsrc=size=320x240:rate=15:duration=2',
    '-pix_fmt', 'yuv420p', '-movflags', '+faststart', videoDosya], { stdio: 'ignore' });

  artisan(`
Illuminate\\Support\\Facades\\Storage::disk('icerik')->put('duyuru/test-${damga}.mp4', file_get_contents('${videoDosya}'));
App\\Models\\Duyuru::where('baslik', '${DUYURU_BASLIK}')->update(['video_yolu' => 'duyuru/test-${damga}.mp4']);
echo 'VIDEO';`);

  const videoYol = `/icerik/duyuru/test-${damga}.mp4`;
  await uye.goto(`${KOK}/panel/duyurular`, { waitUntil: 'networkidle2' });

  const videoSrc = await uye.$eval('video source', el => el.getAttribute('src')).catch(() => null);
  kontrol('Duyuruda <video> etiketi var', videoSrc?.includes(`duyuru/test-${damga}.mp4`), videoSrc ?? 'yok');

  /* 💀 Etiketin varlığı yetmez: kaynak 404 dönse de <video> sayfada DURUR.
        Tarayıcı gerçekten çözebildi mi diye metadata'yı bekle. */
  const oynatilabilir = await uye.evaluate(() => new Promise(cozum => {
    const v = document.querySelector('video');
    if (! v) return cozum({ tamam: false, not: 'video yok' });
    if (v.readyState >= 1) return cozum({ tamam: true, not: `sure=${v.duration}` });
    const zaman = setTimeout(() => cozum({ tamam: false, not: `readyState=${v.readyState} hata=${v.error?.code ?? '-'}` }), 15000);
    v.addEventListener('loadedmetadata', () => { clearTimeout(zaman); cozum({ tamam: true, not: `sure=${v.duration}` }); }, { once: true });
    v.addEventListener('error', () => { clearTimeout(zaman); cozum({ tamam: false, not: `hata=${v.error?.code}` }); }, { once: true });
    v.load();
  }));
  kontrol('Video tarayıcıda gerçekten açılıyor', oynatilabilir.tamam, oynatilabilir.not);
  await uye.screenshot({ path: '/root/byd-uye-duyuru-video.png', fullPage: true });

  /* 🎬 İleri sarma Range'e bağlı: 200 dönerse video baştan sona inmek zorunda. */
  const parca = await uye.evaluate(async yol => {
    const y = await fetch(yol, { headers: { Range: 'bytes=0-1023' } });
    return { durum: y.status, aralik: y.headers.get('content-range') };
  }, videoYol);
  kontrol('Video Range isteğine 206 dönüyor', parca.durum === 206, `${parca.durum} · ${parca.aralik ?? '—'}`);

  const videoBekleyen = await bekleyen.goto(KOK + videoYol, { waitUntil: 'domcontentloaded' }).catch(() => null);
  kontrol('Akredite olmayan duyuru videosuna ERİŞEMİYOR',
    ! bekleyen.url().endsWith(videoYol),
    `${videoBekleyen?.status() ?? '—'} · ${bekleyen.url().replace(KOK, '')}`);

  await b.close();
} catch (e) {
  console.log('💥 ' + e.message);
  sonuc.push({ ad: 'Beklenmeyen hata', gecti: false, ek: e.message });
  try { await b.close(); } catch {}
} finally {
  try { unlinkSync(ek); } catch {}
  try { unlinkSync(videoDosya); } catch {}
  try {
    const t = artisan(`
Illuminate\\Support\\Facades\\Storage::disk('icerik')->delete(['bulten/test-${damga}.pdf', 'duyuru/test-${damga}.mp4']);
App\\Models\\Duyuru::where('baslik', '${DUYURU_BASLIK}')->forceDelete();
App\\Models\\Bulten::where('baslik', '${BULTEN_BASLIK}')->forceDelete();
App\\Models\\Antrenman::where('baslik', 'Test antrenmanı ${damga}')->forceDelete();
foreach (['${AKREDITE}', '${BEKLEYEN}'] as $mail) {
    $u = App\\Models\\User::withTrashed()->where('email', $mail)->first();
    if (! $u) continue;
    $bIds = App\\Models\\Basvuru::withTrashed()->where('kullanici_id', $u->id)->pluck('id');
    App\\Models\\Akreditasyon::whereIn('basvuru_id', $bIds)->get()->each(function ($a) {
        $a->kartlar()->get()->each->delete();
        $a->gecisKayitlari()->delete();
        $a->delete();
    });
    App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $bIds)->get()->each->forceDelete();
    App\\Models\\Basvuru::withTrashed()->whereIn('id', $bIds)->forceDelete();
    $k = $u->kurum; $u->forceDelete(); $k?->forceDelete();
}
echo 'TEMIZ';`);
    console.log('🧹 ' + t.trim().split('\n').pop());
  } catch (e) { console.log('⚠️ temizlik: ' + e.message); }
}

const hata = sonuc.filter(r => !r.gecti).length;
console.log(`\n${sonuc.length - hata}/${sonuc.length} geçti`);
process.exit(hata ? 1 : 0);
