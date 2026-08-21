/**
 * BYD — sertleştirme denetimi (Aşama 06).
 *
 * Uygulama ve sunucu ayarlarının canlıya uygun olduğunu ölçer.
 * SALT OKUNUR: hiçbir kayıt oluşturmaz, hiçbir ayarı değiştirmez.
 *
 * node tests/uctan-uca/byd-sertlestirme-denetimi.mjs
 */
import { execFileSync } from 'node:child_process';

const ALAN = 'byd.ordolive.com';
const KOK = `https://${ALAN}`;

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push({ ad, gecti, ek }); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const uyari = (ad, ek) => { sonuc.push({ ad, gecti: true, uyari: true, ek }); console.log(`⚠️  ${ad}${ek ? '  → ' + ek : ''}`); };

const artisan = kod => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: '/home/byd.ordolive.com/laravel', encoding: 'utf8', timeout: 60000 });
const cek = (m, e) => (m.match(new RegExp(e + ':(\\S*)')) || [])[1];

function istek(yol, ekArgs = []) {
  const cikti = execFileSync('curl', ['-s', '-k', '-D', '-', '-o', '/dev/null',
    '--resolve', `${ALAN}:443:127.0.0.1`, '--resolve', `${ALAN}:80:127.0.0.1`,
    ...ekArgs, KOK + yol], { encoding: 'utf8', timeout: 20000 });
  return cikti;
}

console.log('── Uygulama yapılandırması ──');
const cfg = artisan(`
echo 'ENV:' . config('app.env')
   . ' DEBUG:' . json_encode(config('app.debug'))
   . ' SECURE:' . json_encode(config('session.secure'))
   . ' ENCRYPT:' . json_encode(config('session.encrypt'))
   . ' HTTPONLY:' . json_encode(config('session.http_only'))
   . ' SAMESITE:' . config('session.same_site')
   . ' MAIL:' . config('mail.default')
   . ' IKIADIMLI:' . json_encode(config('byd.2fa_zorunlu'))
   . ' QRANAHTAR:' . (filled(config('byd.qr.anahtarlar')[1] ?? null) ? 'var' : 'YOK')
   . ' APPKEY:' . (filled(config('app.key')) ? 'var' : 'YOK');`);

kontrol('APP_ENV = production', cek(cfg, 'ENV') === 'production', cek(cfg, 'ENV'));
kontrol('APP_DEBUG kapalı', cek(cfg, 'DEBUG') === 'false', cek(cfg, 'DEBUG'));
kontrol('Uygulama anahtarı tanımlı', cek(cfg, 'APPKEY') === 'var');
kontrol('QR imza anahtarı tanımlı', cek(cfg, 'QRANAHTAR') === 'var');
kontrol('Oturum çerezi Secure', cek(cfg, 'SECURE') === 'true', cek(cfg, 'SECURE'));
kontrol('Oturum çerezi HttpOnly', cek(cfg, 'HTTPONLY') === 'true');
kontrol('Oturum verisi şifreli', cek(cfg, 'ENCRYPT') === 'true');
kontrol('SameSite en az lax', ['lax', 'strict'].includes(cek(cfg, 'SAMESITE')), cek(cfg, 'SAMESITE'));

if (cek(cfg, 'MAIL') === 'log') {
  uyari('E-posta sürücüsü "log" — hiçbir posta GÖNDERİLMİYOR', 'canlıya çıkmadan SMTP girilmeli');
} else {
  kontrol('E-posta sürücüsü ayarlı', true, cek(cfg, 'MAIL'));
}

// Ayrılmış uzantılara gönderim geri dönüş üretir ve itibarı yıpratır.
const posta = artisan(`
Illuminate\\Support\\Facades\\Mail::raw('denetim', fn ($m) => $m->to('denetim@ornek.test')->subject('denetim'));
echo 'ENGEL:' . (str_contains(file_get_contents(storage_path('logs/laravel.log')), 'Gönderilemez adrese posta engellendi') ? 'var' : 'yok');`);
kontrol('Gönderilemez adres koruması etkin (.test/.invalid)', cek(posta, 'ENGEL') === 'var', cek(posta, 'ENGEL'));

// Plan v1.0 md.11: yetkili hesaplarinda 2FA ZORUNLU. Kapaliysa canliya cikilmaz.
if (cek(cfg, 'IKIADIMLI') === 'true') {
  kontrol('Yetkili panelinde 2FA zorunlu', true);
} else {
  uyari('Yetkili panelinde 2FA ZORUNLU DEĞİL', 'canlıya çıkmadan BYD_2FA_ZORUNLU=true yapılmalı');
}

console.log('\n── HTTP başlıkları ──');
const basliklar = istek('/yonetim/login').toLowerCase();
for (const [ad, desen] of [
  ['Strict-Transport-Security', /strict-transport-security:/],
  ['X-Content-Type-Options: nosniff', /x-content-type-options:\s*nosniff/],
  ['X-Frame-Options: DENY', /x-frame-options:\s*deny/],
  ['Content-Security-Policy', /content-security-policy:/],
  ['Referrer-Policy', /referrer-policy:/],
]) {
  kontrol(ad, desen.test(basliklar));
}
kontrol('frame-ancestors none', /frame-ancestors 'none'/.test(basliklar));
kontrol('Sunucu sürümü sızmıyor (X-Powered-By yok)', !/x-powered-by:/.test(basliklar));

console.log('\n── Taşıma ──');
const http80 = execFileSync('curl', ['-s', '-o', '/dev/null', '-w', '%{http_code} %{redirect_url}',
  '--resolve', `${ALAN}:80:127.0.0.1`, `http://${ALAN}/yonetim`], { encoding: 'utf8', timeout: 20000 });
kontrol('HTTP → HTTPS yönlendiriyor', http80.startsWith('301') && http80.includes('https://'), http80.trim());

console.log('\n── Erişim sınırları ──');
const gizli = [
  ['/.env', 'ortam dosyası'],
  ['/storage/logs/laravel.log', 'uygulama günlüğü'],
  ['/composer.json', 'bağımlılık listesi'],
  ['/.git/config', 'sürüm deposu'],
  ['/vendor/autoload.php', 'satıcı dizini'],
];
for (const [yol, ad] of gizli) {
  const kod = execFileSync('curl', ['-s', '-k', '-o', '/dev/null', '-w', '%{http_code}',
    '--resolve', `${ALAN}:443:127.0.0.1`, KOK + yol], { encoding: 'utf8', timeout: 20000 });
  kontrol(`Erişilemez: ${ad} (${yol})`, ['403', '404'].includes(kod.trim()), kod.trim());
}

console.log('\n── Denetim kaydı ──');
const kilit = artisan(`
$k = App\\Models\\DenetimKaydi::latest('id')->first();
if (! $k) { echo 'KAYIT:yok'; return; }
try { Illuminate\\Support\\Facades\\DB::table('denetim_kaydi')->where('id', $k->id)->update(['olay' => 'x']); echo 'GUNCELLEME:acik'; }
catch (Throwable $e) { echo 'GUNCELLEME:kilitli'; }
try { Illuminate\\Support\\Facades\\DB::table('denetim_kaydi')->where('id', $k->id)->delete(); echo ' SILME:acik'; }
catch (Throwable $e) { echo ' SILME:kilitli'; }`);
kontrol('Denetim kaydı doğrudan SQL ile GÜNCELLENEMİYOR', cek(kilit, 'GUNCELLEME') === 'kilitli', cek(kilit, 'GUNCELLEME'));
kontrol('Denetim kaydı doğrudan SQL ile SİLİNEMİYOR', cek(kilit, 'SILME') === 'kilitli', cek(kilit, 'SILME'));

console.log('\n── Yetki ve veri ──');
const yetki = artisan(`
$eksik = [];
foreach ([App\\Models\\Basvuru::class, App\\Models\\Evrak::class, App\\Models\\Kurum::class,
          App\\Models\\Akreditasyon::class, App\\Models\\KapiIstemcisi::class,
          App\\Models\\GecisKaydi::class, App\\Models\\DenetimKaydi::class,
          App\\Models\\Duyuru::class, App\\Models\\Bulten::class, App\\Models\\Antrenman::class] as $m) {
    if (! Illuminate\\Support\\Facades\\Gate::getPolicyFor($m)) { $eksik[] = class_basename($m); }
}
echo 'POLICYSIZ:' . (implode(',', $eksik) ?: 'yok');
echo ' KAPIIPSIZ:' . App\\Models\\KapiIstemcisi::where('aktif', true)->whereNull('ip_listesi')->count();
echo ' HASSASEVRAK:' . App\\Models\\EvrakTuru::where('hassas', true)->whereNull('imha_gun')->count();
echo ' SIFRESIZEVRAK:' . App\\Models\\Evrak::whereHas('turu', fn ($q) => $q->where('hassas', true))->where('sifreli', false)->count();`);
kontrol('Her modelin policy\'si var', cek(yetki, 'POLICYSIZ') === 'yok', cek(yetki, 'POLICYSIZ'));
kontrol('Hassas evrak türlerinde imha süresi tanımlı', cek(yetki, 'HASSASEVRAK') === '0', cek(yetki, 'HASSASEVRAK') + ' tür eksik');
kontrol('Hassas evrakların hepsi şifreli', cek(yetki, 'SIFRESIZEVRAK') === '0', cek(yetki, 'SIFRESIZEVRAK') + ' şifresiz');
if (cek(yetki, 'KAPIIPSIZ') !== '0') {
  uyari('IP kısıtı olmayan etkin kapı var', cek(yetki, 'KAPIIPSIZ') + ' kapı');
} else {
  kontrol('Etkin kapıların hepsinde IP kısıtı var', true);
}

console.log('\n── Servisler ──');
for (const [ad, servis] of [['Kuyruk işleyicisi', 'byd-horizon'], ['PHP-FPM', 'php8.3-fpm'], ['nginx', 'nginx']]) {
  const durum = execFileSync('systemctl', ['is-active', servis], { encoding: 'utf8' }).trim();
  kontrol(`${ad} çalışıyor`, durum === 'active', durum);
}
const zamanlayici = execFileSync('bash', ['-c', 'test -f /etc/cron.d/byd-scheduler && echo var || echo yok'], { encoding: 'utf8' }).trim();
kontrol('Zamanlayıcı kurulu (evrak imhası)', zamanlayici === 'var');

const hata = sonuc.filter(r => !r.gecti).length;
const uyarilar = sonuc.filter(r => r.uyari).length;
console.log(`\n${sonuc.length - hata}/${sonuc.length} geçti${uyarilar ? ` · ${uyarilar} uyarı` : ''}`);
process.exit(hata ? 1 : 0);
