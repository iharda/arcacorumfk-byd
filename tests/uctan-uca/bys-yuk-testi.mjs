/**
 * BYS — turnike doğrulama ucunun yük testi (Aşama 06).
 *
 * Plan v1.0 md.11 "1.000 istek/sn" yazıyor. Bu sayı basın akreditasyonu için
 * fazlasıyla yüksek (bkz. rapor), ama ucun gerçekte ne yaptığını ÖLÇMEDEN
 * konuşmanın anlamı yok. Bu betik onu ölçer.
 *
 * ⚠️ ÜRETİME YAZAR: her istek bir geçiş kaydı oluşturur. Sonunda hepsi silinir.
 * ⚠️ Hız sınırı istemci başına 600/dk. Sınırın kendisini ölçmemek için istekler
 *    birden çok kapıya dağıtılır.
 *
 * node tests/uctan-uca/bys-yuk-testi.mjs [saniye] [eszamanlilik]
 */
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import https from 'node:https';
/*
 * 🪤 YOL NORMALLESTIRILIR (resolve). Ham `.../uctan-uca/../../../test-dosyalari`
 * yolunu Chrome'a verirsen dosya seciminde hata YOK ama form gonderilirken
 * POST `net::ERR_ACCESS_DENIED` ile duser: Chrome olusturucuya okuma iznini
 * COZULMUS yol icin verir, blob ise ham yolu tasir, ikisi tutmaz. Tarayici da
 * "site can't be reached" der; sunucuya istek HIC ulasmaz, erisim kaydinda iz
 * yoktur. Sunucu tasindiktan sonra bu testler bu yuzden kiriliyordu.
 */
const TEST_DOSYALARI = resolve(process.env.BYS_TEST_DOSYALARI ?? import.meta.dirname + '/../../../test-dosyalari');   // ornek evraklar; BYS_TEST_DOSYALARI ile degistirilebilir

const ALAN = process.env.BYS_ALAN || 'byd.ordolive.com';
const SURE = Number(process.argv[2]) || 15;
const ESZAMAN = Number(process.argv[3]) || 24;
const KAPI_SAYISI = 8;
const damga = Date.now();

const artisan = kod => execFileSync('sudo', ['-u', 'bys', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: (process.env.BYS_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 180000 });
const cek = (m, e) => (m.match(new RegExp(e + ':(\\S+)')) || [])[1];

// Origin'e doğrudan git: Cloudflare'i ölçmüyoruz, kendi sunucumuzu ölçüyoruz.
const ajan = new https.Agent({ keepAlive: true, maxSockets: ESZAMAN + 8, rejectUnauthorized: false });

function dogrula(anahtar, yuk) {
  return new Promise((cozumle) => {
    const govde = JSON.stringify({ yuk });
    const bas = process.hrtime.bigint();
    const istek = https.request({
      host: '127.0.0.1', port: 443, path: '/api/kapi/dogrula', method: 'POST', agent: ajan,
      servername: ALAN,
      headers: {
        'Host': ALAN, 'X-Kapi-Anahtar': anahtar, 'Content-Type': 'application/json',
        'Accept': 'application/json', 'Content-Length': Buffer.byteLength(govde),
      },
    }, (yanit) => {
      let boyut = 0;
      yanit.on('data', (p) => { boyut += p.length; });
      yanit.on('end', () => cozumle({
        kod: yanit.statusCode,
        ms: Number(process.hrtime.bigint() - bas) / 1e6,
        boyut,
      }));
    });
    istek.on('error', () => cozumle({ kod: 0, ms: Number(process.hrtime.bigint() - bas) / 1e6, boyut: 0 }));
    istek.write(govde);
    istek.end();
  });
}

console.log(`Hazırlık…`);
const kur = artisan(`
$k = App\\Models\\Kurum::create(['resmi_unvan' => 'Yuk Testi ${damga}', 'akreditasyon_durumu' => 'akredite']);
$u = App\\Models\\User::create(['name' => 'Yuk Testi', 'email' => 'yuk+${damga}@ornek.test',
    'password' => bcrypt('x'), 'kurum_id' => $k->id, 'aktif' => true]);
$b = App\\Models\\Basvuru::create(['tur' => App\\Enums\\BasvuruTuru::BasinMensubu,
    'durum' => App\\Enums\\BasvuruDurumu::Onaylandi, 'kullanici_id' => $u->id, 'kurum_id' => $k->id]);
$t = App\\Models\\EvrakTuru::where('kod','biyometrik_fotograf')->first();
app(App\\Servisler\\EvrakYukleyici::class)->yukle($b, $t,
    new Illuminate\\Http\\UploadedFile('${TEST_DOSYALARI}/foto.jpg', 'foto.jpg', 'image/jpeg', null, true));
$a = App\\Models\\Akreditasyon::create(['ulid' => (string) Illuminate\\Support\\Str::ulid(),
    'kart_no' => '2026-K-9500', 'yil' => 2026, 'tur_kodu' => 'K', 'sira' => 9500,
    'kullanici_id' => $u->id, 'basvuru_id' => $b->id, 'kurum_id' => $k->id,
    'durum' => App\\Enums\\AkreditasyonDurumu::Aktif]);
// Mükerrer okutma işaretini kapat: yük testinde her istek aynı kartı okutuyor.
App\\Models\\Ayar::yaz('mukerrer_okutma_saniye', 0);
$s = app(App\\Servisler\\KapiIstemcisiAkisi::class);
$anahtarlar = [];
for ($i = 1; $i <= ${KAPI_SAYISI}; $i++) {
    $anahtarlar[] = $s->olustur(['ad' => "Yuk Kapi {$i} ${damga}", 'kapi_kodu' => "YUK{$i}-${damga}"])['anahtar'];
}
echo 'YUK:' . app(App\\Servisler\\QrImzalayici::class)->yukUret($a);
echo ' ANAHTARLAR:' . implode(',', $anahtarlar);`);

const yuk = cek(kur, 'YUK');
const anahtarlar = (cek(kur, 'ANAHTARLAR') || '').split(',').filter(Boolean);
if (!yuk || anahtarlar.length < KAPI_SAYISI) { console.log('💥 hazırlık başarısız'); process.exit(1); }

console.log(`Ölçüm: ${SURE} sn · ${ESZAMAN} eşzamanlı bağlantı · ${KAPI_SAYISI} kapı\n`);

const olcum = [];
let dur = false;
setTimeout(() => { dur = true; }, SURE * 1000);

const baslangic = Date.now();
await Promise.all(Array.from({ length: ESZAMAN }, async (_, i) => {
  let n = i;
  while (!dur) {
    olcum.push(await dogrula(anahtarlar[n++ % anahtarlar.length], yuk));
  }
}));
const gecen = (Date.now() - baslangic) / 1000;

const basarili = olcum.filter(o => o.kod === 200);
const sinirli = olcum.filter(o => o.kod === 429);
const hatali = olcum.filter(o => o.kod !== 200 && o.kod !== 429);
const sureler = basarili.map(o => o.ms).sort((a, b) => a - b);
const y = (p) => sureler.length ? sureler[Math.min(sureler.length - 1, Math.floor(sureler.length * p))].toFixed(0) : '-';

console.log('── Sonuç ──');
console.log(`toplam istek       : ${olcum.length}   (${gecen.toFixed(1)} sn)`);
console.log(`başarılı (200)     : ${basarili.length}`);
console.log(`hız sınırı (429)   : ${sinirli.length}`);
console.log(`hata               : ${hatali.length}`);
console.log(`ISTEK/SN           : ${(basarili.length / gecen).toFixed(1)}`);
console.log(`gecikme  ortanca   : ${y(0.5)} ms`);
console.log(`         %95       : ${y(0.95)} ms`);
console.log(`         %99       : ${y(0.99)} ms`);
console.log(`         en kötü   : ${sureler.length ? sureler[sureler.length - 1].toFixed(0) : '-'} ms`);
console.log(`yanıt boyutu       : ${basarili.length ? (basarili[0].boyut / 1024).toFixed(1) : '-'} KB (fotoğraf dâhil)`);

const kayit = artisan(`echo 'KAYIT:' . App\\Models\\GecisKaydi::where('kapi_kodu','like','YUK%-${damga}')->count();`);
console.log(`yazılan geçiş kaydı: ${cek(kayit, 'KAYIT')}`);

console.log('\nTemizlik…');
artisan(`
App\\Models\\GecisKaydi::where('kapi_kodu','like','YUK%-${damga}')->delete();
App\\Models\\KapiIstemcisi::where('kapi_kodu','like','YUK%-${damga}')->forceDelete();
$u = App\\Models\\User::withTrashed()->where('email','yuk+${damga}@ornek.test')->first();
if ($u) {
    $bIds = App\\Models\\Basvuru::withTrashed()->where('kullanici_id',$u->id)->pluck('id');
    App\\Models\\Akreditasyon::whereIn('basvuru_id',$bIds)->get()->each(function ($a) {
        $a->kartlar()->get()->each->delete(); $a->gecisKayitlari()->delete(); $a->delete();
    });
    App\\Models\\Evrak::withTrashed()->whereIn('basvuru_id',$bIds)->get()->each->forceDelete();
    App\\Models\\Basvuru::withTrashed()->whereIn('id',$bIds)->forceDelete();
    $k = $u->kurum; $u->forceDelete(); $k?->forceDelete();
}
App\\Models\\Ayar::yaz('mukerrer_okutma_saniye', 30);
echo 'TEMIZ';`);
console.log('🧹 temizlendi, mükerrer okutma ayarı geri alındı');
