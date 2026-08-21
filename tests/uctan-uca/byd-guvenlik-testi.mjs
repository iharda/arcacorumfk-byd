/**
 * BYD — yetki ve yükleme güvenliği testi (Aşama 02).
 *
 * Yetkinin VAR olduğunu değil, OLMAYAN yetkinin GERÇEKTEN kapalı olduğunu ölçer.
 * Kendi oluşturduğu iki kurumu sonunda siler; başka kayda dokunmaz.
 *
 * node /root/byd-guvenlik-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { readdirSync, writeFileSync, unlinkSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const SIFRE = 'Kirmizi-Kartal-2026-x9';
const damga = Date.now();
const A = `guvA+${damga}@ornek.test`;
const B = `guvB+${damga}@ornek.test`;

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push({ ad, gecti, ek }); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const bekle = ms => new Promise(r => setTimeout(r, ms));
const artisan = kod => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: '/home/byd.ordolive.com/laravel', encoding: 'utf8', timeout: 60000 });

// İki ayrı kurum + başvuru + birer evrak kur
const kurulum = artisan(`
foreach ([['${A}','A'], ['${B}','B']] as [$mail, $ad]) {
    $k = App\\Models\\Kurum::create(['resmi_unvan' => "Guvenlik {$ad} ${damga}", 'akreditasyon_durumu' => 'beklemede']);
    $u = App\\Models\\User::create(['name' => "Guvenlik {$ad}", 'email' => $mail, 'password' => bcrypt('${SIFRE}'),
        'kurum_id' => $k->id, 'aktif' => true, 'email_verified_at' => now()]);
    $u->assignRole(App\\Models\\User::ROL_KURUM);
    $b = App\\Models\\Basvuru::create(['tur' => App\\Enums\\BasvuruTuru::Kurum,
        'durum' => App\\Enums\\BasvuruDurumu::Taslak, 'kullanici_id' => $u->id, 'kurum_id' => $k->id]);
    $t = App\\Models\\EvrakTuru::where('kod','ticaret_sicil_gazetesi')->first();
    app(App\\Servisler\\EvrakYukleyici::class)->yukle($b, $t,
        new Illuminate\\Http\\UploadedFile('/root/byd-test-dosyalari/ticaret-sicil.pdf', 'ticaret-sicil.pdf', 'application/pdf', null, true));
    echo strtoupper($ad) . '_EVRAK:' . $b->evraklar()->first()->ulid . ' ' . strtoupper($ad) . '_BASVURU:' . $b->ulid . ' ';
}`);

const bul = (etiket) => (kurulum.match(new RegExp(etiket + ':([0-9A-Z]+)')) || [])[1];
const aEvrak = bul('A_EVRAK'), bEvrak = bul('B_EVRAK'), bBasvuru = bul('B_BASVURU');
kontrol('Kurulum: iki kurum ve evrak oluştu', !!aEvrak && !!bEvrak && !!bBasvuru);

const b = await puppeteer.launch({ executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'] });

const durumu = async (sayfa, yol) => {
  const y = await sayfa.goto(KOK + yol, { waitUntil: 'domcontentloaded', timeout: 30000 });
  return { kod: y.status(), url: sayfa.url().replace(KOK, '') };
};

try {
  /* 1) OTURUMSUZ */
  const anon = await (await b.createBrowserContext()).newPage();
  let r = await durumu(anon, `/evrak/${aEvrak}`);
  kontrol('Oturumsuz evrak indirilemiyor', [401, 403].includes(r.kod), `${r.kod} ${r.url}`);
  r = await durumu(anon, '/yonetim');
  kontrol('Oturumsuz yönetim paneli kapalı', r.url.includes('login'), r.url);

  /* 2) KURUM A olarak giriş */
  const ctxA = await b.createBrowserContext();
  const a = await ctxA.newPage();
  await a.goto(`${KOK}/kurum/login`, { waitUntil: 'networkidle2' });
  await a.type('#form\\.email', A);
  await a.type('#form\\.password', SIFRE);
  await Promise.all([a.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}), a.click('button[type="submit"]')]);
  await bekle(800);
  kontrol('Kurum A giriş yaptı', a.url().includes('/kurum') && !a.url().includes('login'), a.url().replace(KOK, ''));

  r = await durumu(a, `/evrak/${aEvrak}`);
  kontrol('Kurum A KENDİ evrakını görebiliyor', r.kod === 200, String(r.kod));

  // 🔑 IDOR: başka kurumun evrakı
  r = await durumu(a, `/evrak/${bEvrak}`);
  kontrol('Kurum A BAŞKA kurumun evrakını göremiyor', r.kod === 403, String(r.kod));

  r = await durumu(a, '/yonetim');
  kontrol('Kurum A yönetim paneline giremiyor', r.kod === 403 || r.url.includes('login'), `${r.kod} ${r.url}`);

  r = await durumu(a, `/yonetim/basvurular/${bBasvuru}/inceleme`);
  kontrol('Kurum A inceleme ekranına giremiyor', r.kod === 403 || r.url.includes('login'), `${r.kod} ${r.url}`);

  r = await durumu(a, '/panel');
  kontrol('Kurum A üye paneline giremiyor', r.kod === 403 || r.url.includes('login'), `${r.kod} ${r.url}`);

  /* 3) KVKK onayı olmadan başvuru kabul edilmemeli */
  const form = await (await b.createBrowserContext()).newPage();
  await form.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2' });
  const doldur = async (ad, d) => form.type(`[name="${ad}"]`, d);
  await doldur('resmi_unvan', `KVKK Test ${damga}`); await doldur('adres', 'Adres');
  await doldur('il', 'Çorum'); await doldur('ilce', 'Merkez');
  await doldur('kurum_telefon', '0364 111 11 11'); await doldur('kurum_eposta', `kvkk+${damga}@ornek.test`);
  await doldur('vergi_dairesi', 'VD'); await doldur('vergi_no', '1234567890');
  await doldur('calisan_sayisi', '3');
  await doldur('yayin_platformlari[0][ad]', 'X'); await doldur('yayin_platformlari[0][url]', 'https://ornek.test');
  await doldur('yetkili_ad', 'Ad Soyad'); await doldur('yetkili_eposta', `kvkkyetkili+${damga}@ornek.test`);
  await doldur('yetkili_telefon', '0532 000 00 00');
  // KVKK kutuları BİLEREK işaretlenmiyor
  await form.evaluate(() => document.querySelectorAll('[required]').forEach(e => e.removeAttribute('required')));
  await Promise.all([form.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}), form.click('button[type="submit"]')]);
  await bekle(600);
  const formMetni = await form.evaluate(() => document.body.innerText);
  kontrol('KVKK onayı olmadan başvuru reddediliyor',
    !form.url().includes('gonderildi') && /açık rıza|aydınlatma/i.test(formMetni),
    form.url().replace(KOK, ''));

  /* ═══ Aşama 03 yüzeyleri ═══ */

  /* 5) Davet bağlantısı */
  const anon2 = await (await b.createBrowserContext()).newPage();
  r = await durumu(anon2, '/davet/' + 'x'.repeat(48));
  kontrol('Geçersiz davet bağlantısı reddediliyor', r.kod === 410, String(r.kod));

  const suresiGecmis = artisan(`
$k = App\\Models\\Kurum::where('resmi_unvan', 'like', 'Guvenlik A ${damga}')->first();
$token = 'SURESIGECMIS${damga}';
$d = App\\Models\\Davet::create(['kurum_id' => $k->id, 'ad_soyad' => 'Eski Davet',
    'eposta' => 'eski+${damga}@ornek.test', 'token_hash' => App\\Models\\Davet::tokenHash($token),
    'gecerlilik_bitis' => now()->subDay()]);
echo 'TOKEN:' . $token;`);
  const eskiToken = (suresiGecmis.match(/TOKEN:(\S+)/) || [])[1];
  r = await durumu(anon2, '/davet/' + eskiToken);
  kontrol('Süresi dolmuş davet reddediliyor', r.kod === 410, String(r.kod));

  /* 6) Akredite OLMAYAN kurum seçilemez */
  const formB = await (await b.createBrowserContext()).newPage();
  await formB.goto(KOK + '/basvuru/basin-mensubu', { waitUntil: 'networkidle2' });
  const listede = await formB.evaluate((unvan) => {
    const sec = document.querySelector('select[name="kurum_ulid"]');
    return sec ? [...sec.options].some(o => o.text.includes(unvan)) : false;
  }, `Guvenlik A ${damga}`);
  kontrol('Akredite olmayan kurum listede YOK', listede === false);

  // Listede olmasa da elle gönderilirse sunucu reddetmeli.
  const kurumUlid = (artisan(`echo 'ULID:' . App\\Models\\Kurum::where('resmi_unvan','Guvenlik A ${damga}')->value('ulid');`)
    .match(/ULID:(\w+)/) || [])[1];
  await formB.evaluate((ulid) => {
    const sec = document.querySelector('select[name="kurum_ulid"]');
    const o = document.createElement('option');
    o.value = ulid; o.selected = true; sec.appendChild(o);
    document.querySelectorAll('[required]').forEach(e => e.removeAttribute('required'));
  }, kurumUlid);
  const yaz = async (ad, d) => formB.type(`[name="${ad}"]`, d);
  await yaz('ad_soyad', 'Sızma Deneme'); await yaz('eposta', `sizma+${damga}@ornek.test`);
  await yaz('telefon', '0530 000 00 00'); await yaz('adres', 'Adres');
  await yaz('il', 'Çorum'); await yaz('ilce', 'Merkez'); await yaz('calisma_yili', '2');
  await formB.evaluate(() => {
    document.querySelector('input[name="sigorta_212_var"][value="1"]').click();
    document.querySelector('input[name="basin_karti_var"][value="0"]').click();
    document.querySelector('input[name="kvkk_aydinlatma"]').click();
    document.querySelector('input[name="kvkk_riza"]').click();
  });
  await Promise.all([formB.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}), formB.click('button[type="submit"]')]);
  await bekle(500);
  const formBMetni = await formB.evaluate(() => document.body.innerText);
  kontrol('Elle gönderilen akredite olmayan kurum sunucuda reddediliyor',
    !formB.url().includes('gonderildi') && /akredite değil/i.test(formBMetni),
    formB.url().replace(KOK, ''));

  /* 7) Akredite olmayan kurum davet gönderemez */
  const davetSonuc = artisan(`
$k = App\\Models\\Kurum::where('resmi_unvan','Guvenlik A ${damga}')->first();
try {
    app(App\\Servisler\\DavetAkisi::class)->olustur($k, 'Biri', 'biri+${damga}@ornek.test');
    echo 'IZIN_VERILDI';
} catch (Throwable $e) { echo 'ENGELLENDI'; }`);
  kontrol('Akredite olmayan kurum davet gönderemiyor', /ENGELLENDI/.test(davetSonuc),
    (davetSonuc.match(/IZIN_VERILDI|ENGELLENDI/) || ['?'])[0]);

  /* 8) Kurum A, Kurum B'nin çalışanını ayrıldı işaretleyemez */
  const capraz = artisan(`
$a = App\\Models\\User::where('email','${A}')->first();
$b = App\\Models\\User::where('email','${B}')->first();
Illuminate\\Support\\Facades\\Auth::login($a);
$sayfa = new App\\Filament\\Kurum\\Pages\\Calisanlar();
$m = new ReflectionMethod($sayfa, 'ayrilisBildir'); $m->setAccessible(true);
try { $m->invoke($sayfa, $b->ulid, null); echo 'IZIN_VERILDI'; }
catch (Throwable $e) { echo 'ENGELLENDI:' . class_basename($e); }`);
  kontrol('Kurum A, başka kurumun çalışanını ayrılmış işaretleyemiyor', /ENGELLENDI/.test(capraz),
    (capraz.match(/IZIN_VERILDI|ENGELLENDI:\w+/) || ['?'])[0]);

  await b.close();
} catch (e) {
  console.log('💥 ' + e.message);
  sonuc.push({ ad: 'Beklenmeyen hata', gecti: false, ek: e.message });
  try { await b.close(); } catch {}
}

/* 4) Magic byte: uzantısı pdf, içeriği düz metin */
try {
  const sahte = '/root/byd-test-dosyalari/sahte.pdf';
  writeFileSync(sahte, 'Bu bir PDF degil, duz metin. '.repeat(80));
  const cikti = artisan(`
$u = App\\Models\\User::where('email','${A}')->first();
$b = $u->basvurular()->first();
$t = App\\Models\\EvrakTuru::where('kod','vergi_levhasi')->first();
try {
    app(App\\Servisler\\EvrakYukleyici::class)->yukle($b, $t,
        new Illuminate\\Http\\UploadedFile('${sahte}', 'sahte.pdf', 'application/pdf', null, true));
    echo 'KABUL_EDILDI';
} catch (Throwable $e) { echo 'REDDEDILDI:' . $e->getMessage(); }`);
  unlinkSync(sahte);
  kontrol('Uzantısı sahte dosya reddediliyor (magic byte)', /REDDEDILDI/.test(cikti),
    (cikti.match(/REDDEDILDI:[^\n]{0,50}|KABUL_EDILDI/) || ['?'])[0]);
} catch (e) { kontrol('Magic byte kontrolü', false, e.message); }

/* Temizlik */
try {
  const t = artisan(`
foreach (['${A}', '${B}', 'sizma+${damga}@ornek.test'] as $mail) {
    $u = App\\Models\\User::where('email', $mail)->first();
    if (! $u) continue;
    $k = $u->kurum;
    App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $u->basvurular()->pluck('id'))->get()->each->forceDelete();
    $u->basvurular()->forceDelete();
    $u->forceDelete();
    // 🪤 Şablon dizesinde ters bölü ÇİFT yazılmalı; tek yazılınca JS onu
    //    yutuyor ve PHP'ye "AppModelsDavet" gidiyordu. Davet'te softDelete yok.
    if ($k) { App\\Models\\Davet::where('kurum_id', $k->id)->delete(); $k->forceDelete(); }
}
echo 'TEMIZ';`);
  console.log('🧹 ' + t.trim().split('\n').pop());
} catch (e) { console.log('⚠️ temizlik: ' + e.message); }

const hata = sonuc.filter(r => !r.gecti).length;
console.log(`\n${sonuc.length - hata}/${sonuc.length} geçti`);
process.exit(hata ? 1 : 0);
