/**
 * BYD — başvuru formu alanları (Başvuru akışı v2, Revizyon md.5).
 *
 * Ölçülenler:
 *   1. İl seçilmeden ilçe seçilemiyor; il seçilince ilçeler geliyor
 *   2. İle ait olmayan ilçe SUNUCUDA reddediliyor (istemci atlansa bile)
 *   3. Telefon maskesi TR'de çalışıyor, yabancı ülkede KALKIYOR
 *   4. Geçersiz cep numarası ve sağlaması tutmayan vergi no reddediliyor
 *   5. Aynı vergi numarasıyla ikinci kurum kaydı açılamıyor
 *   6. Çalışan sayısı açılır liste; serbest metin kabul edilmiyor
 *   7. Telefon E.164 (+905321234567) olarak saklanıyor
 *   8. Etiketler düzeldi (Resmi unvan · Web siteleri ve yayın adresleri)
 *
 * ⚠️ ÜRETİME YAZAR. Kendi kaydını oluşturur, sonunda siler.
 * ⚠️ Başvuru gönderimi 10 dakikada 5 istek: her gönderimden önce önbellek
 *    temizlenir (sayaç önbellekte, oturum ayrı veritabanında).
 *
 * node /root/byd-form-alanlari-testi.mjs
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
const EPOSTA = `form5+${damga}@ornek.test`;
const IKINCI = `form5b+${damga}@ornek.test`;
const UNVAN = `Form Alanları Testi ${damga}`;
// Sağlaması tutan, birbirinden farklı VKN'ler (birim testinde de doğrulanıyor).
const VKN = '4319521692';
const VKN2 = '5916897887';

const sonuc = [];
const kontrol = (ad, gecti, ek = '') => { sonuc.push(gecti); console.log(`${gecti ? '✅' : '❌'} ${ad}${ek ? '  → ' + ek : ''}`); };
const bekle = ms => new Promise(r => setTimeout(r, ms));
const artisan = kod => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 90000 });
const sinirSifirla = () => execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'cache:clear'],
  { cwd: (process.env.BYD_KOK ?? import.meta.dirname + '/../..') });

const b = await puppeteer.launch({
  executablePath: CHROME, headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${ALAN} 127.0.0.1`, '--ignore-certificate-errors'],
});

/** Formu doldurmadan, doğrudan alan listesiyle gönderir; dönen gövdeyi verir. */
async function hamGonder(sayfa, degisiklikler) {
  sinirSifirla();

  return sayfa.evaluate(async (kok, ek) => {
    const g = new FormData();
    g.append('_token', document.querySelector('input[name=_token]').value);
    for (const [k, v] of Object.entries(ek)) g.append(k, v);
    const c = await fetch(`${kok}/basvuru/kurum`, { method: 'POST', body: g, redirect: 'follow' });

    return { url: c.url, govde: await c.text() };
  }, KOK, degisiklikler);
}

/** Geçerli bir kurum başvurusunun tüm alanları. */
const alanlar = (eposta, vergiNo) => ({
  resmi_unvan: UNVAN,
  adres: 'Gazi Caddesi No 1',
  il: 'Çorum',
  ilce: 'Merkez',
  kurum_telefon_ulke: '+90',
  kurum_telefon: '364 213 45 67',
  kurum_eposta: eposta,
  vergi_dairesi: 'Çorum',
  vergi_no: vergiNo,
  calisan_araligi: '6-10',
  'yayin_platformlari[0][ad]': 'Form Testi Haber',
  'yayin_platformlari[0][url]': 'https://ornek.com.tr',
  yetkili_ad: 'Form Testi Yetkilisi',
  yetkili_eposta: eposta,
  yetkili_telefon_ulke: '+90',
  yetkili_telefon: '532 123 45 67',
  kvkk_aydinlatma: '1',
  kvkk_riza: '1',
});

try {
  const c1 = await b.createBrowserContext();
  const s = await c1.newPage();
  await s.setViewport({ width: 1440, height: 1200 });
  await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2' });

  /* ── 1. Etiketler (md.5.5) ─────────────────────────────────────── */
  const govde = await s.evaluate(() => document.body.innerText);
  kontrol('"Resmi unvan" yazımı düzeldi',
    govde.includes('Resmi unvan') && ! govde.includes('Resmi ünvan'));
  kontrol('"Web siteleri ve yayın adresleri" başlığı',
    govde.includes('Web siteleri ve yayın adresleri') && ! govde.includes('Yayın platformları'));
  kontrol('"+ Adres ekle" düğmesi', govde.includes('Adres ekle') && ! govde.includes('Platform ekle'));

  /* ── 2. İl / ilçe bağlı liste ──────────────────────────────────── */
  kontrol('İl seçilmeden ilçe kutusu kapalı',
    await s.$eval('#ilce', e => e.disabled) === true);
  kontrol('İl listesi 81 il içeriyor',
    await s.$$eval('#il option', o => o.length) === 82, 'seçiniz + 81');

  await s.select('#il', 'Çorum');
  await bekle(400);
  const corum = await s.$$eval('#ilce option', o => o.map(x => x.textContent.trim()));
  kontrol('İl seçilince ilçeler geldi ve "Merkez" başta',
    corum.length === 15 && corum[1] === 'Merkez' && corum.includes('Sungurlu'),
    `${corum.length - 1} ilçe`);
  kontrol('İlçe kutusu açıldı', await s.$eval('#ilce', e => e.disabled) === false);

  await s.select('#ilce', 'Sungurlu');
  await s.select('#il', 'Ankara');
  await bekle(400);
  const ankara = await s.$$eval('#ilce option', o => o.map(x => x.textContent.trim()));
  kontrol('İl değişince ilçe listesi yenilendi ve seçim sıfırlandı',
    ankara.includes('Çankaya') && ! ankara.includes('Sungurlu')
    && (await s.$eval('#ilce', e => e.value)) === '');

  /* ── 3. Telefon maskesi ────────────────────────────────────────── */
  await s.type('#yetkili_telefon', '5321234567');
  await bekle(300);
  kontrol('TR maskesi rakamları gruplandırıyor',
    (await s.$eval('#yetkili_telefon', e => e.value)) === '532 123 45 67',
    await s.$eval('#yetkili_telefon', e => e.value));

  await s.$eval('#yetkili_telefon', e => { e.value = ''; });
  await s.select('[name="yetkili_telefon_ulke"]', '+49');
  await bekle(300);
  await s.type('#yetkili_telefon', '15112345678');
  await bekle(300);
  kontrol('Yabancı ülke seçilince maske kalkıyor',
    (await s.$eval('#yetkili_telefon', e => e.value)) === '15112345678',
    await s.$eval('#yetkili_telefon', e => e.value));

  /* ── 4. Sunucu doğrulaması: istemci atlansa bile ───────────────── */
  let r = await hamGonder(s, { ...alanlar(EPOSTA, VKN), il: 'Çorum', ilce: 'Kadıköy' });
  kontrol('İle ait olmayan ilçe sunucuda reddediliyor',
    /ilçe, ile ait değil/i.test(r.govde));

  r = await hamGonder(s, { ...alanlar(EPOSTA, '1111111111') });
  kontrol('Sağlaması tutmayan vergi no reddediliyor',
    /Vergi numarası geçersiz/i.test(r.govde));

  r = await hamGonder(s, { ...alanlar(EPOSTA, VKN), yetkili_telefon: '364 213 45 67' });
  kontrol('Cep olmayan numara yetkili alanında reddediliyor',
    /cep telefonu girin/i.test(r.govde));

  r = await hamGonder(s, { ...alanlar(EPOSTA, VKN), calisan_araligi: '17 kişi' });
  kontrol('Çalışan sayısında serbest metin kabul edilmiyor',
    ! r.url.includes('gonderildi') && /çalışan sayısı/i.test(r.govde));

  /* ── 5. Geçerli gönderim (insan gibi: seç, yaz, dosya bırak) ───── */
  await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2' });
  await s.type('[name="resmi_unvan"]', UNVAN);
  await s.type('[name="adres"]', 'Gazi Caddesi No 1');
  // 🪤 İlçe seçenekleri il seçildikten SONRA çizilir; arada beklemek şart.
  await s.select('#il', 'Çorum');
  await bekle(500);
  await s.select('#ilce', 'Merkez');
  await s.type('#kurum_telefon', '3642134567');
  await s.type('[name="kurum_eposta"]', EPOSTA);
  await s.type('[name="vergi_dairesi"]', 'Çorum');
  await s.type('[name="vergi_no"]', VKN);
  await s.select('#calisan_araligi', '6-10');
  await s.type('[name="yayin_platformlari[0][ad]"]', 'Form Testi Haber');
  await s.type('[name="yayin_platformlari[0][url]"]', 'https://ornek.com.tr');
  await s.type('[name="yetkili_ad"]', 'Form Testi Yetkilisi');
  await s.type('[name="yetkili_eposta"]', EPOSTA);
  await s.type('#yetkili_telefon', '5321234567');
  await s.click('[name="kvkk_aydinlatma"]');
  await s.click('[name="kvkk_riza"]');

  const girisler = await s.$$('input[type="file"]');
  const evraklar = [`${D}/ticaret-sicil.pdf`, `${D}/vergi-levhasi.pdf`];
  for (let i = 0; i < girisler.length; i++) await girisler[i].uploadFile(evraklar[i]);

  sinirSifirla();
  await Promise.all([
    s.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {}),
    s.click('button[type="submit"]'),
  ]);
  kontrol('Geçerli form gönderildi', s.url().includes('/basvuru/gonderildi'),
    s.url().includes('gonderildi')
      ? ''
      : (await s.evaluate(() => document.body.innerText)).replace(/\s+/g, ' ').slice(0, 160));

  const kayit = artisan(`
$b = App\\Models\\Basvuru::where('basvuran_eposta','${EPOSTA}')->latest('id')->first();
$k = $b?->kurum;
echo 'TEL:' . ($b?->basvuran_telefon ?? '-') . ' KTEL:' . ($k?->telefon ?? '-')
   . ' ARALIK:' . ($k?->calisan_araligi?->value ?? '-') . ' IL:' . ($k?->il ?? '-') . ' ILCE:' . ($k?->ilce ?? '-');`);
  kontrol('Telefon E.164 olarak saklandı', /TEL:\+905321234567/.test(kayit),
    (kayit.match(/TEL:\S+/) || ['?'])[0]);
  kontrol('Kurum telefonu da E.164', /KTEL:\+903642134567/.test(kayit),
    (kayit.match(/KTEL:\S+/) || ['?'])[0]);
  kontrol('Çalışan aralığı enum olarak saklandı', /ARALIK:6-10/.test(kayit),
    (kayit.match(/ARALIK:\S+/) || ['?'])[0]);
  kontrol('İl ve ilçe kaydedildi', /IL:Çorum/.test(kayit) && /ILCE:Merkez/.test(kayit),
    (kayit.match(/IL:\S+ ILCE:\S+/) || ['?'])[0]);

  /* ── 6. Aynı vergi numarasıyla ikinci kurum ────────────────────── */
  await s.goto(`${KOK}/basvuru/kurum`, { waitUntil: 'networkidle2' });
  r = await hamGonder(s, { ...alanlar(IKINCI, VKN) });
  kontrol('Aynı vergi numarasıyla ikinci kurum açılamıyor',
    /vergi numarasıyla kayıtlı bir kurum/i.test(r.govde));

  r = await hamGonder(s, { ...alanlar(IKINCI, VKN2) });
  kontrol('Farklı vergi numarası engele takılmıyor',
    ! /vergi numarasıyla kayıtlı bir kurum/i.test(r.govde));
} catch (e) {
  console.log('💥 ' + e.message);
  sonuc.push(false);
} finally {
  await b.close();
  try {
    artisan(`
foreach (['${EPOSTA}', '${IKINCI}'] as $mail) {
    $ids = App\\Models\\Basvuru::withTrashed()->where('basvuran_eposta', $mail)->pluck('id');
    $kurumlar = App\\Models\\Basvuru::withTrashed()->whereIn('id', $ids)->pluck('kurum_id')->filter();
    foreach (App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $ids)->get() as $e) {
        Illuminate\\Support\\Facades\\Storage::disk($e->disk)->delete($e->yol);
    }
    App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id', $ids)->forceDelete();
    App\\Models\\Basvuru::withTrashed()->whereIn('id', $ids)->forceDelete();
    App\\Models\\Kurum::withTrashed()->whereIn('id', $kurumlar)->forceDelete();
}
App\\Models\\Kurum::withTrashed()->where('resmi_unvan', '${UNVAN}')->forceDelete();
echo 'TEMIZ';`);
    console.log('🧹 temizlendi');
  } catch (e) { console.log('⚠️ temizlik: ' + e.message); }
}

const gecen = sonuc.filter(Boolean).length;
console.log(`\n${gecen === sonuc.length ? '\x1b[32m' : '\x1b[31m'}${gecen}/${sonuc.length} kontrol geçti\x1b[0m`);
process.exit(gecen === sonuc.length ? 0 : 1);
