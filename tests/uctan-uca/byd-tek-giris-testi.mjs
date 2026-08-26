/**
 * BYD — TEK GİRİŞ KAPISI (Başvuru akışı v2, Revizyon md.4).
 *
 * Ölçülenler:
 *   1. `/kurum/login` ve `/panel/login` artık yok, `/giris`'e yönleniyor
 *   2. Oturumsuz panel isteği `/giris`'e; yönetim isteği KENDİ kapısına düşüyor
 *   3. Kurum ve basın mensubu tek kapıdan girip kendi paneline gidiyor
 *   4. Kulüp yetkilisi `/giris`'ten GİREMİYOR (2FA'lı kapısı ayrı) ve oturum
 *      açılmıyor
 *   5. Hatalı deneme denetim kaydına düşüyor; 5 denemeden sonra kilit
 *   6. Çift rollü kullanıcı panel seçim ekranı görüyor
 *   7. Girebileceği paneli olmayan hesap kapıda durduruluyor
 *   8. Şifremi unuttum → bağlantı → yeni şifreyle giriş
 *
 * ⚠️ ÜRETİME YAZAR. Kendi kullanıcılarını oluşturur, sonunda siler.
 *
 * node /root/byd-tek-giris-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = process.env.BYD_ALAN || 'byd.ordolive.com';
const KOK = `https://${ALAN}`;
const SIFRE = 'Kirmizi-Kartal-2026-x9';
const YENI_SIFRE = 'Yesil-Vadi-2026-q4';

const damga = Date.now();
const KURUM_U = `giris-kurum+${damga}@ornek.test`;
const UYE_U = `giris-uye+${damga}@ornek.test`;
const CIFT_U = `giris-cift+${damga}@ornek.test`;
const PASIF_U = `giris-pasif+${damga}@ornek.test`;
const UNVAN = `Giriş Testi Ajans ${damga}`;

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push(gecti); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const bekle = ms => new Promise(r => setTimeout(r, ms));
const artisan = kod => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 90000 });
const sinirSifirla = () => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'cache:clear'],
  { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..') });
const cek = (m, e) => (m.match(new RegExp(e + ':(\\S+)')) || [])[1];

const b = await puppeteer.launch({
  executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

const yol = s => s.url().replace(KOK, '') || '/';
const govde = s => s.evaluate(() => document.body.innerText);

async function yeniSekme() {
  const ctx = await b.createBrowserContext();
  const s = await ctx.newPage();
  await s.setViewport({ width: 1400, height: 1000 });
  return { ctx, s };
}

/** Tek giriş kapısından dener. */
async function gir(s, eposta, sifre = SIFRE) {
  sinirSifirla();
  await s.goto(`${KOK}/giris`, { waitUntil: 'networkidle2' });
  await s.type('[name="email"]', eposta);
  await s.type('[name="password"]', sifre);
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
  await bekle(600);
}

try {
  /* ═════ HAZIRLIK ═════ */
  artisan(`
$k = App\\Models\\Kurum::create(['resmi_unvan' => '${UNVAN}', 'akreditasyon_durumu' => 'akredite']);
$yap = function (string $mail, string $ad, array $roller, ?int $kurumId, bool $akreditasyon) {
    $u = App\\Models\\User::create(['name' => $ad, 'email' => $mail, 'password' => bcrypt('${SIFRE}'),
        'kurum_id' => $kurumId, 'aktif' => true, 'email_verified_at' => now()]);
    foreach ($roller as $r) { $u->assignRole($r); }
    if ($akreditasyon) {
        // 🪤 akreditasyonlar.basvuru_id NOT NULL: onaylı bir başvuru şart.
        $bv = App\\Models\\Basvuru::create([
            'tur' => App\\Enums\\BasvuruTuru::BasinMensubu,
            'durum' => App\\Enums\\BasvuruDurumu::Onaylandi,
            'kullanici_id' => $u->id, 'kurum_id' => $kurumId,
            'basvuran_ad' => $ad, 'basvuran_eposta' => $mail,
            'gonderildi_at' => now()->subDay(), 'karar_at' => now(),
        ]);
        $yil = (int) now()->year;
        App\\Models\\Akreditasyon::create(['kart_no' => $yil . '-T-' . random_int(1000, 8999), 'yil' => $yil,
            'tur_kodu' => 'T', 'sira' => random_int(1000, 8999), 'kullanici_id' => $u->id,
            'basvuru_id' => $bv->id, 'durum' => App\\Enums\\AkreditasyonDurumu::Aktif]);
    }
    return $u;
};
$yap('${KURUM_U}', 'Giriş Kurum', [App\\Models\\User::ROL_KURUM], $k->id, false);
$yap('${UYE_U}', 'Giriş Üye', [App\\Models\\User::ROL_BASIN], $k->id, true);
$yap('${CIFT_U}', 'Giriş Çift Rollü', [App\\Models\\User::ROL_KURUM, App\\Models\\User::ROL_BASIN], $k->id, true);
// Akreditasyonu OLMAYAN basın mensubu: hiçbir panele giremez.
$yap('${PASIF_U}', 'Giriş Pasif', [App\\Models\\User::ROL_BASIN], $k->id, false);
echo 'HAZIR';`);
  kontrol('Hazırlık: dört hesap açıldı', true);

  /* ═════ 1. Eski giriş adresleri ═════ */
  {
    const { ctx, s } = await yeniSekme();
    await s.goto(`${KOK}/kurum/login`, { waitUntil: 'networkidle2' });
    kontrol('/kurum/login tek kapıya yönleniyor', yol(s) === '/giris', yol(s));
    await s.goto(`${KOK}/panel/login`, { waitUntil: 'networkidle2' });
    kontrol('/panel/login tek kapıya yönleniyor', yol(s) === '/giris', yol(s));

    await s.goto(`${KOK}/kurum`, { waitUntil: 'networkidle2' });
    kontrol('Oturumsuz kurum paneli tek kapıya düşüyor', yol(s) === '/giris', yol(s));
    await s.goto(`${KOK}/panel/duyurular`, { waitUntil: 'networkidle2' });
    kontrol('Oturumsuz üye paneli tek kapıya düşüyor', yol(s) === '/giris', yol(s));

    await s.goto(`${KOK}/yonetim/kapilar`, { waitUntil: 'networkidle2' });
    kontrol('Yönetim isteği KENDİ kapısına düşüyor', yol(s) === '/yonetim/login', yol(s));

    await s.goto(`${KOK}/panel-sec`, { waitUntil: 'networkidle2' });
    kontrol('Oturumsuz panel seçimi tek kapıya düşüyor', yol(s) === '/giris', yol(s));
    await ctx.close();
  }

  /* ═════ 2. Kurum ve üye tek kapıdan giriyor ═════ */
  {
    const { ctx, s } = await yeniSekme();
    await gir(s, KURUM_U);
    kontrol('Kurum yetkilisi tek kapıdan kendi paneline girdi', yol(s) === '/kurum', yol(s));
    await ctx.close();
  }
  {
    const { ctx, s } = await yeniSekme();
    await gir(s, UYE_U);
    kontrol('Basın mensubu tek kapıdan kendi paneline girdi', yol(s) === '/panel', yol(s));
    await ctx.close();
  }

  /* ═════ 3. Yetkili buradan giremez ═════ */
  {
    const { ctx, s } = await yeniSekme();
    await gir(s, 'admin@byd.ordolive.com',
      execFileSync('cat', ['/root/.byd-admin-pass'], { encoding: 'utf8' }).trim());
    kontrol('Yetkili tek kapıdan giremiyor, kendi kapısına yönleniyor',
      yol(s) === '/yonetim/login', yol(s));

    // 🔑 Asıl ölçüt "yönlendi mi" değil, OTURUM AÇILMADI mı: 2FA atlanmamalı.
    await s.goto(`${KOK}/yonetim`, { waitUntil: 'networkidle2' });
    kontrol('Yetkilinin oturumu AÇILMADI (2FA atlanmadı)', yol(s) === '/yonetim/login', yol(s));
    await ctx.close();
  }

  /* ═════ 4. Hatalı deneme ve kilit ═════ */
  {
    const { ctx, s } = await yeniSekme();
    const oncekiKayit = artisan(`echo 'SAYI:' . App\\Models\\DenetimKaydi::where('olay','oturum.basarisiz')->count();`);

    await gir(s, UYE_U, 'yanlis-sifre-123');
    kontrol('Yanlış şifre reddediliyor ve sebep sızdırılmıyor',
      yol(s) === '/giris' && /E-posta veya şifre hatalı/.test(await govde(s)),
      yol(s));

    const sonrakiKayit = artisan(`echo 'SAYI:' . App\\Models\\DenetimKaydi::where('olay','oturum.basarisiz')->count();`);
    kontrol('Başarısız deneme denetim kaydına düştü',
      Number(cek(sonrakiKayit, 'SAYI')) > Number(cek(oncekiKayit, 'SAYI')),
      `${cek(oncekiKayit, 'SAYI')} → ${cek(sonrakiKayit, 'SAYI')}`);

    // Aynı e-posta + IP için 5 hak: 6. denemede kilit.
    sinirSifirla();
    let kilitMetni = '';
    for (let i = 0; i < 6; i++) {
      await s.goto(`${KOK}/giris`, { waitUntil: 'networkidle2' });
      await s.type('[name="email"]', UYE_U);
      await s.type('[name="password"]', 'yanlis-sifre-123');
      await Promise.all([
        s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
        s.click('button[type="submit"]'),
      ]);
      kilitMetni = await govde(s);
    }
    kontrol('5 hatalı denemeden sonra kilit devreye giriyor',
      /dakika sonra tekrar deneyin/.test(kilitMetni),
      (kilitMetni.match(/[^\n]*tekrar deneyin[^\n]*/) || ['kilit yok'])[0].slice(0, 60));
    kontrol('Kilitlenme denetim kaydına düştü',
      /VAR/.test(artisan(`echo App\\Models\\DenetimKaydi::where('olay','oturum.kilitlendi')
        ->where('created_at','>', now()->subMinutes(5))->exists() ? 'VAR' : 'YOK';`)));

    // Kilit doğru şifreyi de kesmeli (kaba kuvvetin anlamı bu).
    await s.goto(`${KOK}/giris`, { waitUntil: 'networkidle2' });
    await s.type('[name="email"]', UYE_U);
    await s.type('[name="password"]', SIFRE);
    await Promise.all([
      s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
      s.click('button[type="submit"]'),
    ]);
    kontrol('Kilitliyken doğru şifre de girmiyor', yol(s) === '/giris', yol(s));
    sinirSifirla();
    await ctx.close();
  }

  /* ═════ 5. Çift rollü kullanıcı: panel seçimi ═════ */
  {
    const { ctx, s } = await yeniSekme();
    await gir(s, CIFT_U);
    kontrol('Çift rollü kullanıcı panel seçim ekranına gidiyor', yol(s) === '/panel-sec', yol(s));
    const secenekler = await s.$$eval('a[href="/kurum"], a[href="/panel"]', a => a.map(x => x.getAttribute('href')));
    kontrol('İki panel de seçenek olarak sunuluyor',
      secenekler.includes('/kurum') && secenekler.includes('/panel'), secenekler.join(', '));

    await Promise.all([
      s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
      s.click('a[href="/kurum"]'),
    ]);
    kontrol('Seçilen panele girebiliyor', yol(s) === '/kurum', yol(s));
    await ctx.close();
  }

  /* ═════ 6. Girebileceği paneli olmayan hesap ═════ */
  {
    const { ctx, s } = await yeniSekme();
    await gir(s, PASIF_U);
    kontrol('Akreditasyonsuz hesap kapıda durduruluyor',
      yol(s) === '/giris' && /etkin değil/.test(await govde(s)), yol(s));
    await s.goto(`${KOK}/panel`, { waitUntil: 'networkidle2' });
    kontrol('Oturumu da açılmamış', yol(s) === '/giris', yol(s));
    await ctx.close();
  }

  /* ═════ 7. Şifremi unuttum ═════ */
  {
    const { ctx, s } = await yeniSekme();
    sinirSifirla();
    await s.goto(`${KOK}/sifremi-unuttum`, { waitUntil: 'networkidle2' });
    await s.type('[name="email"]', UYE_U);
    await Promise.all([
      s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
      s.click('button[type="submit"]'),
    ]);
    kontrol('Şifre bağlantısı isteği alındı ve hesap varlığı sızdırılmıyor',
      /kayıtlıysa/.test(await govde(s)));

    // Bağlantıdaki jeton yalnızca üretildiği anda görünür; testte üretiyoruz.
    const jeton = cek(artisan(`
$u = App\\Models\\User::where('email','${UYE_U}')->firstOrFail();
echo 'TOKEN:' . Illuminate\\Support\\Facades\\Password::createToken($u);`), 'TOKEN');
    kontrol('Sıfırlama jetonu üretildi', !!jeton);

    await s.goto(`${KOK}/sifre-sifirla/${jeton}?email=${encodeURIComponent(UYE_U)}`, { waitUntil: 'networkidle2' });
    await s.type('[name="sifre"]', YENI_SIFRE);
    await s.type('[name="sifre_confirmation"]', YENI_SIFRE);
    await Promise.all([
      s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
      s.click('button[type="submit"]'),
    ]);
    kontrol('Şifre değişti ve kullanıcı kendi paneline alındı', yol(s) === '/panel', yol(s));
    await ctx.close();
  }
  {
    const { ctx, s } = await yeniSekme();
    await gir(s, UYE_U, YENI_SIFRE);
    kontrol('Yeni şifreyle giriş yapılabiliyor', yol(s) === '/panel', yol(s));
    await ctx.close();
  }
} catch (e) {
  console.log('💥 ' + e.message);
  sonuc.push(false);
} finally {
  await b.close();
  try {
    artisan(`
foreach (['${KURUM_U}', '${UYE_U}', '${CIFT_U}', '${PASIF_U}'] as $mail) {
    $u = App\\Models\\User::withTrashed()->where('email', $mail)->first();
    if (! $u) continue;
    App\\Models\\Akreditasyon::where('kullanici_id', $u->id)->get()->each(function ($a) {
        $a->kartlar()->get()->each->delete();
        $a->gecisKayitlari()->delete();
        $a->delete();
    });
    App\\Models\\Basvuru::withTrashed()->where('basvuran_eposta', $mail)->forceDelete();
    Illuminate\\Support\\Facades\\DB::table('model_has_roles')->where('model_id', $u->id)->delete();
    Illuminate\\Support\\Facades\\DB::table('password_reset_tokens')->where('email', $mail)->delete();
    $u->forceDelete();
}
App\\Models\\Kurum::withTrashed()->where('resmi_unvan', '${UNVAN}')->forceDelete();
echo 'TEMIZ';`);
    console.log('🧹 temizlendi');
  } catch (e) { console.log('⚠️ temizlik: ' + e.message); }
  sinirSifirla();
}

const gecen = sonuc.filter(Boolean).length;
console.log(`\n${gecen === sonuc.length ? '\x1b[32m' : '\x1b[31m'}${gecen}/${sonuc.length} kontrol geçti\x1b[0m`);
process.exit(gecen === sonuc.length ? 0 : 1);
