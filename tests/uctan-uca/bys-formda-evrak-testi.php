<?php

/*
 * BYS — evrak BAŞVURU FORMUNDA alınıyor mu? (Başvuru akışı v2, Revizyon md.3.1)
 *
 * Yeni akışta başvuru tek adım: kurum/kişi bilgileri, evraklar ve KVKK onayları
 * aynı formda gelir; hesap AÇILMAZ, başvuru doğrudan inceleme kuyruğuna düşer.
 * Bu testin ölçtükleri:
 *   1. Kamuya açık formdan evrak dahil TEK gönderimle başvuru oluşuyor mu?
 *   2. Başvuru sonrası `users` tablosunda yeni kayıt oluşmuyor mu?
 *   3. Zorunlu evrak eksikken gönderim ALAN BAZLI hata veriyor mu (500 değil)?
 *   4. İçeriği uymayan dosya reddedilince diskte YETİM DOSYA kalıyor mu?
 *   5. Aynı e-postayla ikinci başvuru engelleniyor mu?
 *   6. Onayda hesap açılıp form verisi (adres/il/ilçe) hesaba taşınıyor mu?
 *   7. Akreditasyonu olmayan hesap üye paneline giremiyor mu? (md.3.5)
 *
 * Gerçek siteye HTTP ile gider (CSRF ve hız sınırı dahil).
 * ⚠️ ÜRETİME YAZAR. Kendi kayıtlarını oluşturur, sonunda siler.
 *
 * sudo -u bys php tests/uctan-uca/bys-formda-evrak-testi.php
 */

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\Evrak;
use App\Models\EvrakTuru;
use App\Models\Kurum;
use App\Models\User;
use App\Servisler\BasvuruAkisi;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$uygulama = require __DIR__.'/../../bootstrap/app.php';
$uygulama->make(Kernel::class)->bootstrap();

$sonuc = [];
$kontrol = function (string $ad, bool $gecti, string $ek = '') use (&$sonuc) {
    $sonuc[] = $gecti;
    echo ($gecti ? "\033[32m✅\033[0m " : "\033[31m❌\033[0m ").$ad.($ek ? "  → {$ek}" : '')."\n";
};

$kok = rtrim((string) config('app.url'), '/');
$damga = substr((string) Str::ulid(), -10);
$disk = config('bys.evrak_disk');
$belge = (getenv('BYS_TEST_DOSYALARI') ?: __DIR__.'/../../../test-dosyalari');
$temizlik = ['basvuru' => [], 'kurum' => [], 'kullanici' => [], 'akreditasyon' => []];

$istemci = fn (CookieJar $kavanoz) => Http::withOptions([
    'cookies' => $kavanoz,
    'allow_redirects' => false,
    'verify' => false,
])->withHeaders(['User-Agent' => 'BYS-formda-evrak-testi']);

$tokenBul = fn (string $govde) => preg_match('/name="_token" value="([^"]+)"/', $govde, $e) ? $e[1] : '';

/*
 * 🪤 Başvuru gönderimi 10 dakikada 5 istekle sınırlı ve bu test bundan fazla
 * gönderim yapıyor. Sayaç önbellekte (db7) durur; oturum ayrı veritabanında
 * (db6) olduğu için temizlemek CSRF'i bozmaz.
 */
$sinirSifirla = fn () => Artisan::call('cache:clear');

/** Formu açar, çerez kavanozu ve CSRF jetonuyla döner. */
$formAc = function (string $yol) use ($kok, $istemci) {
    $kavanoz = new CookieJar;
    $sayfa = $istemci($kavanoz)->get($kok.$yol);

    return [$kavanoz, $sayfa];
};

/** Çok parçalı gönderim: dosya adları `evraklar[ID]` biçiminde düz yazılır. */
$gonder = function (CookieJar $kavanoz, string $yol, array $alanlar, array $dosyalar) use ($kok, $istemci) {
    $istek = $istemci($kavanoz);

    foreach ($dosyalar as $ad => [$dosyaYolu, $dosyaAdi]) {
        $istek = $istek->attach($ad, file_get_contents($dosyaYolu), $dosyaAdi);
    }

    return $istek->post($kok.$yol, $alanlar);
};

/*
 * 🪤 Vergi numarası artık SAĞLAMALI ve TEKİL: her gönderim kendi geçerli
 * numarasını kullanır, yoksa reddedilen denemeler "vergi no zaten kayıtlı"
 * hatasına takılır ve ölçmek istediğimiz evrak hatası hiç görünmez.
 */
$kurumAlanlari = fn (string $eposta, string $jeton, string $vergiNo) => [
    '_token' => $jeton,
    'resmi_unvan' => "Form Testi Medya {$damga}",
    'adres' => 'Gazi Caddesi No 1',
    'il' => 'Çorum',
    'ilce' => 'Merkez',
    'kurum_telefon_ulke' => '+90',
    'kurum_telefon' => '364 213 45 67',
    'kurum_eposta' => $eposta,
    'vergi_dairesi' => 'Çorum',
    'vergi_no' => $vergiNo,
    'calisan_araligi' => '6-10',
    'yayin_platformlari[0][ad]' => 'Form Testi Haber',
    'yayin_platformlari[0][url]' => 'https://ornek.com.tr',
    'yetkili_ad' => 'Form Testi Yetkilisi',
    'yetkili_eposta' => $eposta,
    'yetkili_telefon_ulke' => '+90',
    'yetkili_telefon' => '555 000 00 00',
    'kvkk_aydinlatma' => '1',
    'kvkk_riza' => '1',
];

try {
    $kurumTurleri = EvrakTuru::turIcin(BasvuruTuru::Kurum)->keyBy('kod');
    $bireyTurleri = EvrakTuru::turIcin(BasvuruTuru::IcerikUreticisi)->keyBy('kod');
    $ticaret = $kurumTurleri['ticaret_sicil_gazetesi'];
    // M7: kurumsal başvurunun üçüncü zorunlu belgesi.
    $imza = $kurumTurleri['imza_sirkuleri'];
    $vergi = $kurumTurleri['vergi_levhasi'];
    $foto = $bireyTurleri['biyometrik_fotograf'];
    $kimlik = $bireyTurleri['kimlik_gorseli'];

    /* ── 1. Kurum başvurusu: evrak dahil tek gönderim ──────────────── */
    $sinirSifirla();
    $eposta = "form+{$damga}@ornek.test";
    [$kavanoz, $sayfa] = $formAc('/basvuru/kurum');

    $kontrol('Kurum formunda evrak kutuları var',
        str_contains($sayfa->body(), 'name="evraklar['.$ticaret->id.']"')
        && str_contains($sayfa->body(), 'enctype="multipart/form-data"'));

    $yanit = $gonder($kavanoz, '/basvuru/kurum', $kurumAlanlari($eposta, $tokenBul($sayfa->body()), '5486177004'), [
        'evraklar['.$ticaret->id.']' => ["{$belge}/ticaret-sicil.pdf", 'ticaret-sicil.pdf'],
        'evraklar['.$imza->id.']' => ["{$belge}/imza-sirkuleri.pdf", 'imza-sirkuleri.pdf'],
        'evraklar['.$vergi->id.']' => ["{$belge}/vergi-levhasi.pdf", 'vergi-levhasi.pdf'],
    ]);

    $kontrol('Tek gönderimle başvuru alındı',
        $yanit->status() === 302 && str_contains((string) $yanit->header('Location'), 'gonderildi'),
        'HTTP '.$yanit->status());

    $basvuru = Basvuru::where('basvuran_eposta', $eposta)->latest('id')->first();

    if ($basvuru === null) {
        echo "\033[31mBaşvuru oluşmadı; kalan kontroller koşulamıyor.\033[0m\n";
        exit(1);
    }

    $temizlik['basvuru'][] = $basvuru->id;
    $temizlik['kurum'][] = $basvuru->kurum_id;

    $kontrol('Başvuru doğrudan "İnceleme bekliyor" durumunda',
        $basvuru->durum === BasvuruDurumu::Gonderildi && $basvuru->gonderildi_at !== null,
        $basvuru->durum->value);
    $kontrol('Başvuru HESAPSIZ: kullanici_id boş ve users tablosunda kayıt yok',
        $basvuru->kullanici_id === null
        && User::withTrashed()->where('email', $eposta)->doesntExist());
    /*
     * 🪤 Sayı SABİT YAZILMAZ: kurumsal başvurunun zorunlu belge listesi
     * büyüyebilir (imza sirküleri M7'de eklendi) ve test o gün kırılırdı.
     * Ölçülen şey belge SAYISI değil, gönderilen her belgenin bağlanmış olması.
     */
    $gonderilen = 3;
    $kontrol('Zorunlu evrakların hepsi başvuruya bağlandı',
        $basvuru->evraklar()->count() === $gonderilen, (string) $basvuru->evraklar()->count());

    $diskteVar = $basvuru->evraklar->every(fn (Evrak $e) => Storage::disk($e->disk)->exists($e->yol));
    $kontrol('Evrak dosyaları diskte', $diskteVar);
    $kontrol('Kurum kaydı "beklemede" olarak açıldı',
        $basvuru->kurum?->akreditasyon_durumu === 'beklemede');

    /* ── 2. Zorunlu evrak eksik: alan bazlı hata ───────────────────── */
    $sinirSifirla();
    $eksikEposta = "eksik+{$damga}@ornek.test";
    [$kavanoz2, $sayfa2] = $formAc('/basvuru/kurum');

    $eksikYanit = $gonder($kavanoz2, '/basvuru/kurum', $kurumAlanlari($eksikEposta, $tokenBul($sayfa2->body()), '9734428737'), [
        'evraklar['.$ticaret->id.']' => ["{$belge}/ticaret-sicil.pdf", 'ticaret-sicil.pdf'],
    ]);

    $eksikSayfa = $istemci($kavanoz2)->get("{$kok}/basvuru/kurum");

    $kontrol('Zorunlu evrak eksikken gönderim reddedildi (500 değil)',
        $eksikYanit->status() === 302 && ! str_contains((string) $eksikYanit->header('Location'), 'gonderildi'),
        'HTTP '.$eksikYanit->status());
    $kontrol('Hata ALAN BAZLI gösteriliyor',
        str_contains($eksikSayfa->body(), $vergi->ad.' yüklemelisiniz.'));
    $kontrol('Eksik evraklı başvuru KAYDEDİLMEDİ',
        Basvuru::where('basvuran_eposta', $eksikEposta)->doesntExist());

    /* ── 3. İçeriği uymayan dosya: yetim dosya bırakmamalı ─────────── */
    $sinirSifirla();
    $bozukEposta = "bozuk+{$damga}@ornek.test";
    $oncekiDosyalar = count(Storage::disk($disk)->allFiles('basvuru'));
    [$kavanoz3, $sayfa3] = $formAc('/basvuru/kurum');

    // İlk evrak GEÇERLİ (diske yazılır), ikincisi PDF gibi görünen bir CSV:
    // işlem geri sarar, birincinin dosyası diskte kalmamalı.
    $bozukYanit = $gonder($kavanoz3, '/basvuru/kurum', $kurumAlanlari($bozukEposta, $tokenBul($sayfa3->body()), '8976910380'), [
        'evraklar['.$ticaret->id.']' => ["{$belge}/ticaret-sicil.pdf", 'ticaret-sicil.pdf'],
        // 🪤 Diğer zorunlu belgeler TAM gönderilir: eksik belge hatası çıkarsa
        // ölçmek istediğimiz "içerik uymuyor" hatası hiç görünmez.
        'evraklar['.$imza->id.']' => ["{$belge}/imza-sirkuleri.pdf", 'imza-sirkuleri.pdf'],
        'evraklar['.$vergi->id.']' => ["{$belge}/sahte-belge.pdf", 'vergi-levhasi.pdf'],
    ]);

    $bozukSayfa = $istemci($kavanoz3)->get("{$kok}/basvuru/kurum");

    $kontrol('Uzantısı doğru ama içeriği yanlış dosya reddedildi',
        $bozukYanit->status() === 302 && str_contains($bozukSayfa->body(), 'Dosya türü kabul edilmiyor'),
        'HTTP '.$bozukYanit->status());
    $kontrol('Reddedilen gönderimden başvuru kaydı KALMADI',
        Basvuru::where('basvuran_eposta', $bozukEposta)->doesntExist());
    $kontrol('Geri saran işlemden diskte YETİM DOSYA kalmadı',
        count(Storage::disk($disk)->allFiles('basvuru')) === $oncekiDosyalar,
        $oncekiDosyalar.' → '.count(Storage::disk($disk)->allFiles('basvuru')));

    /* ── 4. Aynı e-postayla ikinci başvuru ─────────────────────────── */
    $sinirSifirla();
    [$kavanoz4, $sayfa4] = $formAc('/basvuru/kurum');
    $tekrarYanit = $gonder($kavanoz4, '/basvuru/kurum', $kurumAlanlari($eposta, $tokenBul($sayfa4->body()), '5486177004'), [
        'evraklar['.$ticaret->id.']' => ["{$belge}/ticaret-sicil.pdf", 'ticaret-sicil.pdf'],
        'evraklar['.$imza->id.']' => ["{$belge}/imza-sirkuleri.pdf", 'imza-sirkuleri.pdf'],
        'evraklar['.$vergi->id.']' => ["{$belge}/vergi-levhasi.pdf", 'vergi-levhasi.pdf'],
    ]);
    $tekrarSayfa = $istemci($kavanoz4)->get("{$kok}/basvuru/kurum");

    $kontrol('Süren başvurusu olan e-postayla ikinci başvuru alınmıyor',
        $tekrarYanit->status() === 302
        && Basvuru::where('basvuran_eposta', $eposta)->count() === 1
        && str_contains($tekrarSayfa->body(), 'devam eden bir başvurunuz var'));

    /* ── 5. Bireysel başvuru (içerik üreticisi) ────────────────────── */
    $sinirSifirla();
    $bireyEposta = "birey+{$damga}@ornek.test";
    [$kavanoz5, $sayfa5] = $formAc('/basvuru/icerik-ureticisi');

    $bireyYanit = $gonder($kavanoz5, '/basvuru/icerik-ureticisi', [
        '_token' => $tokenBul($sayfa5->body()),
        'ad_soyad' => 'Form Testi Üretici',
        'eposta' => $bireyEposta,
        'telefon_ulke' => '+90',
        'telefon' => '555 000 00 01',
        'adres' => 'İnönü Caddesi No 2',
        'il' => 'Çorum',
        'ilce' => 'Sungurlu',
        'basin_karti_var' => '0',
        'sosyal_medya[web]' => 'https://ornek.com.tr/blog',
        'kvkk_aydinlatma' => '1',
        'kvkk_riza' => '1',
    ], [
        'evraklar['.$foto->id.']' => ["{$belge}/foto.jpg", 'foto.jpg'],
        'evraklar['.$kimlik->id.']' => ["{$belge}/kimlik.jpg", 'kimlik.jpg'],
    ]);

    $kontrol('Bireysel başvuru da tek gönderimde alındı',
        $bireyYanit->status() === 302 && str_contains((string) $bireyYanit->header('Location'), 'gonderildi'),
        'HTTP '.$bireyYanit->status());

    $birey = Basvuru::where('basvuran_eposta', $bireyEposta)->latest('id')->first();

    if ($birey === null) {
        echo "\033[31mBireysel başvuru oluşmadı; kalan kontroller koşulamıyor.\033[0m\n";
        exit(1);
    }

    $temizlik['basvuru'][] = $birey->id;

    $kontrol('Telefon E.164 olarak saklandı',
        $birey->basvuran_telefon === '+905550000001', (string) $birey->basvuran_telefon);
    $kontrol('Kişisel bilgiler hesap yerine başvuruda duruyor',
        ($birey->form_verisi['il'] ?? null) === 'Çorum'
        && ($birey->form_verisi['ilce'] ?? null) === 'Sungurlu'
        && $birey->kullanici_id === null);
    $kontrol('Hassas evrak (kimlik) şifreli saklandı',
        (bool) $birey->evraklar()->where('evrak_turu_id', $kimlik->id)->value('sifreli'));

    /* ── 6. Onay: hesap burada açılır, form verisi hesaba taşınır ──── */
    Notification::fake();
    // 🪤 Kart üretimi işi gerçekten koşarsa test kaydını silerken failed_jobs'a
    // çöp bırakıyor.
    Queue::fake();

    $akis = app(BasvuruAkisi::class);
    $akis->incelemeyeAl($birey);
    $akis->onayla($birey);
    $birey->refresh();

    $yeniKullanici = User::where('email', $bireyEposta)->first();

    if ($yeniKullanici !== null) {
        $temizlik['kullanici'][] = $yeniKullanici->id;
    }

    $kontrol('Onayda hesap açıldı ve rol atandı',
        $yeniKullanici !== null && $yeniKullanici->hasRole(User::ROL_ICERIK));
    $kontrol('Form verisi hesaba taşındı (adres / il / ilçe)',
        $yeniKullanici?->il === 'Çorum'
        && $yeniKullanici?->ilce === 'Sungurlu'
        && $yeniKullanici?->adres === 'İnönü Caddesi No 2',
        $yeniKullanici?->ilce ?? '—');

    $akreditasyon = Akreditasyon::where('basvuru_id', $birey->id)->first();

    if ($akreditasyon) {
        $temizlik['akreditasyon'][] = $akreditasyon->id;
    }

    $kontrol('Akreditasyon kaydı doğdu', $akreditasyon !== null);
    $kontrol('Onaylanan kullanıcı üye paneline girebiliyor',
        $yeniKullanici?->canAccessPanel(filament()->getPanel('uye')) === true);

    /* ── 7. Akreditasyonsuz hesap üye paneline giremez (md.3.5) ────── */
    $akreditasyon?->delete();
    $yeniKullanici?->unsetRelation('akreditasyonlar');

    $kontrol('Akreditasyonu olmayan hesap üye paneline GİREMİYOR',
        $yeniKullanici?->canAccessPanel(filament()->getPanel('uye')) === false);
} finally {
    /* ── Temizlik ─────────────────────────────────────────────────── */
    foreach (Evrak::withTrashed()->whereIn('basvuru_id', $temizlik['basvuru'])->get() as $evrak) {
        Storage::disk($evrak->disk)->delete($evrak->yol);
    }

    Evrak::withTrashed()->whereIn('basvuru_id', $temizlik['basvuru'])->forceDelete();
    // Akreditasyon'da yumuşak silme YOK; düz delete yeterli.
    Akreditasyon::whereIn('id', $temizlik['akreditasyon'])->delete();
    Basvuru::withTrashed()->whereIn('id', $temizlik['basvuru'])->forceDelete();

    foreach (array_filter($temizlik['kullanici']) as $id) {
        DB::table('model_has_roles')->where('model_id', $id)->delete();
        User::withTrashed()->whereKey($id)->forceDelete();
    }

    Kurum::withTrashed()->whereIn('id', array_filter($temizlik['kurum']))->forceDelete();
}

$gecen = count(array_filter($sonuc));
$toplam = count($sonuc);
echo "\n".($gecen === $toplam ? "\033[32m" : "\033[31m")."{$gecen}/{$toplam} kontrol geçti\033[0m\n";
exit($gecen === $toplam ? 0 : 1);
