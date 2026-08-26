<?php

/*
 * BYS — panelsiz eksik evrak düzeltmesi (Başvuru akışı v2, Revizyon md.3.3-3.4).
 *
 * Ölçülenler:
 *   1. Bağlantı HESAP GEREKTİRMEDEN açılıyor mu?
 *   2. Sayfada YALNIZCA işaretli alanlar açık mı? (işaretsiz evrak türü yok)
 *   3. Düzeltme gönderilince başvuru "Gönderildi"ye dönüyor, bilet ölüyor mu?
 *   4. Aynı bağlantı ikinci kez açılmıyor mu? (410)
 *   5. Bir başvurunun bileti BAŞKA başvuruya erişim veriyor mu? (vermemeli)
 *   6. Süresi dolmuş / iptal edilmiş bilet 410 mü, 500 mü?
 *   7. Yeni bilet üretilince eski bilet iptal oluyor mu?
 *
 * Gerçek siteye HTTP ile gider (CSRF ve hız sınırı dahil).
 * ⚠️ ÜRETİME YAZAR. Kendi kayıtlarını oluşturur, sonunda siler.
 *
 * sudo -u bys php tests/uctan-uca/bys-duzeltme-bileti-testi.php
 */

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\BasvuruBileti;
use App\Models\Evrak;
use App\Models\EvrakTuru;
use App\Servisler\BasvuruBiletiAkisi;
use App\Servisler\EvrakYukleyici;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
$biletAkisi = app(BasvuruBiletiAkisi::class);
$temizlik = ['basvuru' => [], 'evrak' => []];
$ornekDosya = (getenv('BYS_TEST_DOSYALARI') ?: __DIR__.'/../../../test-dosyalari').'/vergi-levhasi.pdf';
$fotoDosya = (getenv('BYS_TEST_DOSYALARI') ?: __DIR__.'/../../../test-dosyalari').'/foto.jpg';

/** Evrak türünün kabul ettiği biçime uygun örnek dosya. */
$ornek = function (EvrakTuru $tur) use ($ornekDosya, $fotoDosya) {
    $izinli = $tur->izinli_formatlar ?: ['pdf'];

    return in_array('pdf', $izinli, true)
        ? [$ornekDosya, 'ornek.pdf', 'application/pdf']
        : [$fotoDosya, 'ornek.jpg', 'image/jpeg'];
};

/** Oturum çerezini taşıyan istemci: CSRF için şart. */
$istemci = fn (CookieJar $kavanoz) => Http::withOptions([
    'cookies' => $kavanoz,
    'allow_redirects' => false,
    'verify' => false,
])->withHeaders(['User-Agent' => 'BYS-duzeltme-testi']);

$tokenBul = fn (string $govde) => preg_match('/name="_token" value="([^"]+)"/', $govde, $e) ? $e[1] : '';

/** İçerik üreticisi başvurusu: eksik evrak durumunda, işaretli tek evrak türü. */
$basvuruUret = function (array $notlar, string $gerekce) use (&$temizlik, $damga) {
    $basvuru = Basvuru::create([
        'tur' => BasvuruTuru::IcerikUreticisi,
        'durum' => BasvuruDurumu::EksikEvrak,
        'kullanici_id' => null,
        'basvuran_ad' => 'Bilet Testi',
        'basvuran_eposta' => "bilet+{$damga}@ornek.test",
        'basvuran_telefon' => '+90 555 000 00 00',
        'gonderildi_at' => now()->subDay(),
        'duzeltme_notlari' => $notlar,
        'karar_gerekcesi' => $gerekce,
    ]);
    $temizlik['basvuru'][] = $basvuru->id;

    return $basvuru;
};

try {
    $turler = EvrakTuru::turIcin(BasvuruTuru::IcerikUreticisi);
    $isaretli = $turler->first();
    $isaretsiz = $turler->skip(1)->first();

    if ($isaretli === null || $isaretsiz === null) {
        echo "\033[31mEvrak türü tanımı yetersiz; test koşulamıyor.\033[0m\n";
        exit(1);
    }

    $basvuru = $basvuruUret([
        $isaretli->ad => 'Belge okunaklı değil, yeniden yükleyin.',
        'Telefon' => 'Numara eksik hane içeriyor.',
    ], 'Birinci başvurunun gerekçesi.');

    /*
     * Gerçek durum: başvuran evrakını göndermiş, yetkili BİRİNİ eksik/hatalı
     * bulmuş. Zorunlu evrakların hepsi baştan yüklü olmalı, yoksa yeniden
     * gönderim (haklı olarak) reddedilir.
     */
    foreach ($turler->where('zorunlu', true) as $zorunlu) {
        [$yol, $ad, $mime] = $ornek($zorunlu);

        app(EvrakYukleyici::class)->yukle(
            $basvuru, $zorunlu, new UploadedFile($yol, $ad, $mime, null, true),
        );
    }
    $basvuru->load('evraklar');
    foreach ($basvuru->evraklar as $evrak) {
        $temizlik['evrak'][] = $evrak->id;
    }

    /* ── 1. Bilet üret ve bağlantıyı hesapsız aç ───────────────────── */
    $token = $biletAkisi->uret($basvuru);
    $bilet = $basvuru->acikBilet();

    $kavanoz = new CookieJar;
    $sayfa = $istemci($kavanoz)->get("{$kok}/basvuru/duzelt/{$token}");

    $kontrol('Düzeltme bağlantısı hesapsız açılıyor', $sayfa->status() === 200, 'HTTP '.$sayfa->status());
    $kontrol('Yalnızca işaretli evrak türü açık',
        str_contains($sayfa->body(), 'evraklar['.$isaretli->id.']')
        && ! str_contains($sayfa->body(), 'evraklar['.$isaretsiz->id.']'),
        $isaretli->ad.' var, '.$isaretsiz->ad.' yok');
    $kontrol('İşaretli veri alanı ve gerekçe ekranda',
        str_contains($sayfa->body(), 'Telefon')
        && str_contains($sayfa->body(), 'Birinci başvurunun gerekçesi.'));

    /* ── 2. Geçersiz token ─────────────────────────────────────────── */
    $sahte = $istemci(new CookieJar)->get("{$kok}/basvuru/duzelt/".Str::random(48));
    $kontrol('Geçersiz bağlantı 410 veriyor (500 değil)', $sahte->status() === 410, 'HTTP '.$sahte->status());

    /* ── 3. Düzeltmeyi gönder ──────────────────────────────────────── */
    $yanit = $istemci($kavanoz)
        ->attach('evraklar['.$isaretli->id.']', file_get_contents($ornek($isaretli)[0]), $ornek($isaretli)[1])
        ->post("{$kok}/basvuru/duzelt/{$token}", [
            '_token' => $tokenBul($sayfa->body()),
            'aciklama' => 'Telefonum 0555 000 00 01 olacak.',
        ]);

    $kontrol('Düzeltme gönderildi (yönlendirme)',
        $yanit->status() === 302 && str_contains((string) $yanit->header('Location'), 'gonderildi'),
        'HTTP '.$yanit->status());

    $basvuru->refresh();
    $bilet->refresh();

    $kontrol('Başvuru "Gönderildi" durumuna döndü',
        $basvuru->durum === BasvuruDurumu::Gonderildi, $basvuru->durum->value);
    $kontrol('Düzeltme notları temizlendi', blank($basvuru->duzeltme_notlari));
    $kontrol('Evrak yüklendi', $basvuru->evraklar()->where('evrak_turu_id', $isaretli->id)->exists());
    $kontrol('Açıklama yetkiliye kaydedildi',
        str_contains((string) ($basvuru->form_verisi['duzeltme_aciklamasi'] ?? ''), '0555 000 00 01'));
    $kontrol('Bilet tüketildi', $bilet->kullanildi_at !== null);

    foreach ($basvuru->evraklar as $evrak) {
        $temizlik['evrak'][] = $evrak->id;
    }

    /* ── 4. Aynı bağlantı ikinci kez ───────────────────────────────── */
    $tekrar = $istemci(new CookieJar)->get("{$kok}/basvuru/duzelt/{$token}");
    $kontrol('Kullanılmış bağlantı ikinci kez açılmıyor', $tekrar->status() === 410, 'HTTP '.$tekrar->status());

    /* ── 5. Bilet BAŞKA başvuruya erişim vermiyor ──────────────────── */
    $ikinci = $basvuruUret([$isaretli->ad => 'İkinci başvurunun eksiği.'], 'İkinci başvurunun gerekçesi.');
    $ikinciToken = $biletAkisi->uret($ikinci);

    $ikinciSayfa = $istemci(new CookieJar)->get("{$kok}/basvuru/duzelt/{$ikinciToken}");
    $kontrol('Bilet yalnızca kendi başvurusunu açıyor',
        $ikinciSayfa->status() === 200
        && str_contains($ikinciSayfa->body(), 'İkinci başvurunun eksiği.')
        && ! str_contains($ikinciSayfa->body(), 'Birinci başvurunun gerekçesi.'));

    /* ── 6. Yeni bilet eskiyi öldürüyor ────────────────────────────── */
    $eskiId = $ikinci->acikBilet()->id;
    $yeniToken = $biletAkisi->yenidenGonder($ikinci);

    $kontrol('Yeniden gönderim aynı bileti tazeliyor',
        $ikinci->acikBilet()?->id === $eskiId && $ikinci->acikBilet()?->gonderim_sayisi === 2);

    $olu = $istemci(new CookieJar)->get("{$kok}/basvuru/duzelt/{$ikinciToken}");
    $kontrol('Eski bağlantı yeni üretimden sonra ölüyor', $olu->status() === 410, 'HTTP '.$olu->status());

    $canli = $istemci(new CookieJar)->get("{$kok}/basvuru/duzelt/{$yeniToken}");
    $kontrol('Yeni bağlantı çalışıyor', $canli->status() === 200, 'HTTP '.$canli->status());

    /* ── 7. Süresi dolmuş bilet ────────────────────────────────────── */
    $ikinci->acikBilet()->update(['gecerlilik_bitis' => now()->subMinute()]);
    $suresizYanit = $istemci(new CookieJar)->get("{$kok}/basvuru/duzelt/{$yeniToken}");
    $kontrol('Süresi dolmuş bağlantı 410 + açıklama',
        $suresizYanit->status() === 410 && str_contains($suresizYanit->body(), 'süresi dolmuş'),
        'HTTP '.$suresizYanit->status());
} finally {
    /* ── Temizlik ─────────────────────────────────────────────────── */
    foreach (Evrak::withTrashed()->whereIn('id', $temizlik['evrak'])->get() as $evrak) {
        Storage::disk($evrak->disk)->delete($evrak->yol);
    }

    Evrak::withTrashed()->whereIn('basvuru_id', $temizlik['basvuru'])->forceDelete();
    BasvuruBileti::whereIn('basvuru_id', $temizlik['basvuru'])->delete();
    // Denetim kaydına DOKUNULMAZ: tetikleyiciyle kilitli ve silinmesi de yanlış.
    Basvuru::whereIn('id', $temizlik['basvuru'])->forceDelete();
}

$gecen = count(array_filter($sonuc));
$toplam = count($sonuc);
echo "\n".($gecen === $toplam ? "\033[32m" : "\033[31m")."{$gecen}/{$toplam} kontrol geçti\033[0m\n";
exit($gecen === $toplam ? 0 : 1);
