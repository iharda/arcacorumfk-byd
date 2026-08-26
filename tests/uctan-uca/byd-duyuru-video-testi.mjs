/**
 * BYD — duyuru videosu: yönetim FORMUNDAN yükleme (Yusuf revizyonu md.6, duyuru ayağı).
 *
 * Neden ayrı test: alanın kodda durması yetmiyor. Yükleme zinciri nginx →
 * post_max_size → upload_max_filesize → Livewire → disk şeklinde ilerliyor ve
 * 💣 Livewire sınırı aşan dosyayı SESSİZCE reddediyor (aynı tuzak ValCert'te de
 * çıktı). Zinciri uçtan uca yürüten tek şey gerçek bir yükleme.
 *
 * ⚠️ ÜRETİME YAZAR; oluşturduğu duyuruyu ve dosyayı finally'de siler.
 * node /root/byd-duyuru-video-testi.mjs
 */
import puppeteer from 'puppeteer-core';
import { readdirSync, readFileSync, unlinkSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { totp } from './byd-totp.mjs';

const K = '/root/.cache/puppeteer/chrome';
const CHROME = `${K}/${readdirSync(K).sort().pop()}/chrome-linux64/chrome`;
const ALAN = process.env.BYD_ALAN || 'byd.ordolive.com', KOK = `https://${ALAN}`;
const damga = Date.now();
const BASLIK = `Video form denemesi ${damga}`;
const VIDEO = `/tmp/duyuru-form-video-${damga}.mp4`;
const bekle = ms => new Promise(r => setTimeout(r, ms));
const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push(gecti); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const artisan = kod => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 120000 });

execFileSync('ffmpeg', ['-y', '-f', 'lavfi', '-i', 'testsrc=size=320x240:rate=15:duration=2',
  '-pix_fmt', 'yuv420p', '-movflags', '+faststart', VIDEO], { stdio: 'ignore' });

const b = await puppeteer.launch({ executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'] });

try {
  const s = await b.newPage();
  await s.setViewport({ width: 1500, height: 1100 });
  await s.goto(`${KOK}/yonetim/login`, { waitUntil: 'networkidle2' });
  await s.type('#form\\.email', 'admin@byd.ordolive.com');
  await s.type('#form\\.password', readFileSync('/root/.byd-admin-pass', 'utf8').trim());
  await Promise.all([s.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {}), s.click('button[type="submit"]')]);
  await bekle(1200);
  const kutular = await s.$$('input[inputmode="numeric"]');
  if (kutular.length >= 6) {
    await kutular[0].click();
    await s.keyboard.type(totp(readFileSync('/root/.byd-admin-totp', 'utf8').trim()), { delay: 60 });
    await bekle(900);
    await s.evaluate(() => [...document.querySelectorAll('button')].find(b => /Girişi doğrula/i.test(b.innerText))?.click());
    await bekle(3000);
  }
  kontrol('Yönetim paneline girildi', s.url().includes('/yonetim'), s.url().replace(KOK, ''));

  await s.goto(`${KOK}/yonetim/duyurular`, { waitUntil: 'networkidle2' });
  await s.evaluate(() => [...document.querySelectorAll('button')].find(b => /Duyuru ekle/i.test(b.innerText))?.click());
  await bekle(2500);

  /* 🔑 Filament dosya alanının SARMALINA `id="mountedActionSchema0.<alan>"` verir;
        girdinin kendi id'si filepond'un rastgele adıdır, ona göre arama yapma. */
  const alanlar = await s.evaluate(() =>
    [...document.querySelectorAll('.fi-fo-file-upload[id]')].map(e => ({
      id: e.id,
      etiket: (e.closest('.fi-fo-field')?.innerText ?? '').trim().split('\n')[0],
      kabul: e.querySelector('input[type=file]')?.accept ?? null,
    })));
  kontrol('Formda hem Görsel hem Video alanı var',
    alanlar.some(a => a.id.endsWith('.gorsel_yolu')) && alanlar.some(a => a.id.endsWith('.video_yolu')),
    alanlar.map(a => `${a.etiket}(${a.id.split('.').pop()})`).join(' | '));

  const videoAlani = alanlar.find(a => a.id.endsWith('.video_yolu'));
  kontrol('Video alanı yalnızca mp4/webm kabul ediyor',
    videoAlani?.kabul === 'video/mp4,video/webm', videoAlani?.kabul ?? 'yok');

  await s.type('input[id$="baslik"]', BASLIK);

  const hedef = await s.$('[id="mountedActionSchema0.video_yolu"] input[type=file]');
  kontrol('Video dosya girdisi bulundu', !!hedef);
  await hedef.uploadFile(VIDEO);
  await bekle(8000);

  await s.screenshot({ path: '/root/byd-duyuru-video-form.png', fullPage: true });

  await s.evaluate(() => [...document.querySelectorAll('button')]
    .find(b => /^(Oluştur|Kaydet)$/i.test(b.innerText.trim()))?.click());
  await bekle(5000);

  const kayit = artisan(`
$d = App\\Models\\Duyuru::where('baslik', '${BASLIK}')->first();
echo 'VIDEO:' . ($d?->video_yolu ?? 'yok');`);
  const yol = (kayit.match(/VIDEO:(\S+)/) || [, 'yok'])[1];
  kontrol('Kayıtta video_yolu dolu', yol.startsWith('duyuru/'), yol);

  const diskte = artisan(`
echo 'VAR:' . (Illuminate\\Support\\Facades\\Storage::disk('icerik')->exists('${yol}') ? 'evet' : 'hayir');`);
  kontrol('Dosya içerik diskinde duruyor', /VAR:evet/.test(diskte));

  // 🔒 SVG/exe gibi liste dışı biçim reddedilmeli mi? Form beyaz listesi tarayıcı
  //    tarafında dosya seçiciyi kısıtlar; sunucu tarafını kontrol edelim.
  const mime = artisan(`
echo 'MIME:' . Illuminate\\Support\\Facades\\Storage::disk('icerik')->mimeType('${yol}');`);
  kontrol('Yüklenen dosya video/mp4 olarak duruyor', /MIME:video\/mp4/.test(mime), (mime.match(/MIME:(\S+)/) || [])[1]);

  await b.close();
} catch (e) {
  console.log('💥 ' + e.message);
  sonuc.push(false);
  try { await b.close(); } catch {}
} finally {
  try { unlinkSync(VIDEO); } catch {}
  try {
    const t = artisan(`
$d = App\\Models\\Duyuru::withTrashed()->where('baslik', '${BASLIK}')->first();
if ($d) {
    if ($d->video_yolu) { Illuminate\\Support\\Facades\\Storage::disk('icerik')->delete($d->video_yolu); }
    $d->forceDelete();
}
echo 'TEMIZ';`);
    console.log('🧹 ' + t.trim().split('\n').pop());
  } catch (e) { console.log('⚠️ temizlik: ' + e.message); }
}

const hata = sonuc.filter(r => !r).length;
console.log(`\n${sonuc.length - hata}/${sonuc.length} geçti`);
process.exit(hata ? 1 : 0);
