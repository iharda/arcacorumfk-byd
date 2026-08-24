<?php

/*
 * BYD — işlem (transaction) bütünlüğü testi.
 *
 * Yusuf/IT'nin 2026-08-23 tespitlerinin doğrudan kanıtı:
 *   1. Kart numarası çakışmasında yeniden deneme PostgreSQL'de GERÇEKTEN
 *      çalışıyor mu? (Eskiden ölü koddu: PG'de ilk hata işlemi iptal ediyor,
 *      ikinci denemenin SELECT'i 25P02 fırlatıyordu → eşzamanlı iki onay 500.)
 *   2. Ayrılış → akreditasyon iptali atomik mi? (İptal patlarsa kişi "ayrıldı"
 *      görünüp turnikeden geçmeye devam etmemeli.)
 *   3. Ayrılış kişinin BÜTÜN akreditasyonlarını kapatıyor mu?
 *      (latestOfMany yalnızca en yenisini veriyordu.)
 *   4. Yeniden başvuru uygunluğu doğru karar veriyor mu?
 *
 * ⚠️ ÜRETİME YAZAR. Kendi kayıtlarını oluşturur ve sonunda siler; ayar
 *    değiştiren bölüm eski değeri saklayıp aynen geri yazar.
 *
 * sudo -u byd php tests/uctan-uca/byd-islem-butunlugu.php
 */

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Akreditasyon;
use App\Models\Ayar;
use App\Models\Basvuru;
use App\Models\User;
use App\Servisler\AkreditasyonAkisi;
use App\Servisler\BasvuruUygunlugu;
use App\Servisler\KartNoUretici;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$uygulama = require __DIR__.'/../../bootstrap/app.php';
$uygulama->make(Kernel::class)->bootstrap();

$sonuc = [];
$kontrol = function (string $ad, bool $gecti, string $ek = '') use (&$sonuc) {
    $sonuc[] = $gecti;
    echo ($gecti ? "\033[32m✅\033[0m " : "\033[31m❌\033[0m ").$ad.($ek ? "  → {$ek}" : '')."\n";
};

$damga = (string) Str::ulid();
$eposta = "islem+{$damga}@ornek.test";
$temizlik = ['akreditasyon' => [], 'basvuru' => [], 'kullanici' => null];
$eskiBekleme = Ayar::al('yeniden_basvuru_bekleme_gun');

try {
    /* ── Hazırlık ─────────────────────────────────────────────────── */
    $kullanici = User::create([
        'name' => 'İşlem Testi', 'email' => $eposta,
        'password' => bcrypt(Str::random(32)), 'aktif' => true,
    ]);
    $kullanici->assignRole(User::ROL_BASIN);
    $temizlik['kullanici'] = $kullanici->id;

    $basvuru = Basvuru::create([
        'tur' => BasvuruTuru::BasinMensubu,
        'durum' => BasvuruDurumu::Onaylandi,
        'kullanici_id' => $kullanici->id,
    ]);
    $temizlik['basvuru'][] = $basvuru->id;

    /* ── 1. Kart numarası: gerçek çakışma + yeniden deneme ────────── */
    // Rakip onay AYRI bir bağlantıdan yazsın: gerçek eşzamanlılık.
    config(['database.connections.rakip' => config('database.connections.'.config('database.default'))]);

    $tur = BasvuruTuru::BasinMensubu;
    $kod = KartNoUretici::kod($tur);
    $yil = (int) (Ayar::al('kart_yil') ?: now()->year);
    $ilkSira = (int) Akreditasyon::where('yil', $yil)->where('tur_kodu', $kod)->max('sira') + 1;

    $deneme = 0;
    $rakipUlid = null;

    $akreditasyon = DB::transaction(function () use (&$deneme, &$rakipUlid, $tur, $kullanici, $basvuru) {
        return app(KartNoUretici::class)->uret($tur, function (array $numara) use (&$deneme, &$rakipUlid, $kullanici, $basvuru) {
            $deneme++;

            if ($deneme === 1) {
                // Başka bir yetkili tam bu anda aynı sırayı aldı ve commit etti.
                $rakipUlid = (string) Str::ulid();
                DB::connection('rakip')->table('akreditasyonlar')->insert($numara + [
                    'ulid' => $rakipUlid,
                    'kullanici_id' => $kullanici->id,
                    'basvuru_id' => $basvuru->id,
                    'durum' => AkreditasyonDurumu::Aktif->value,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return Akreditasyon::create($numara + [
                'kullanici_id' => $kullanici->id,
                'basvuru_id' => $basvuru->id,
                'durum' => AkreditasyonDurumu::Aktif,
            ]);
        });
    });

    $temizlik['akreditasyon'][] = $akreditasyon->id;

    $kontrol('Kart no çakışması yeniden deneniyor (PG işlemi iptal etmiyor)',
        $deneme === 2, "deneme sayısı: {$deneme}");
    $kontrol('Yeniden denemede sıra bir artıyor',
        $akreditasyon->sira === $ilkSira + 1, "{$akreditasyon->kart_no} (rakip: {$ilkSira})");

    // Rakip kaydı temizle
    if ($rakipUlid) {
        Akreditasyon::where('ulid', $rakipUlid)->delete();
    }

    /* ── 2. Ayrılış atomik mi? ────────────────────────────────────── */
    // İkinci (eski) bir akreditasyon: yeniden başvurmuş biri gibi.
    $eskiAkreditasyon = Akreditasyon::create([
        'kart_no' => sprintf('%d-%s-%04d', $yil - 1, $kod, 9990 + random_int(1, 9)),
        'yil' => $yil - 1, 'tur_kodu' => $kod, 'sira' => 9990 + random_int(10, 99),
        'kullanici_id' => $kullanici->id, 'basvuru_id' => $basvuru->id,
        'durum' => AkreditasyonDurumu::Aktif,
    ]);
    $temizlik['akreditasyon'][] = $eskiAkreditasyon->id;

    // İptal yazımını kasten patlat: ayrılış işareti de geri sarılmalı.
    Event::listen('eloquent.saving: '.Akreditasyon::class, function () {
        throw new RuntimeException('deneme arızası');
    });

    $hata = null;
    try {
        app(AkreditasyonAkisi::class)->kullaniciAyrildi($kullanici, 'atomiklik denemesi');
    } catch (Throwable $e) {
        $hata = $e;
    }

    Event::forget('eloquent.saving: '.Akreditasyon::class);
    $kullanici->refresh();

    $kontrol('İptal patlayınca ayrılış işareti de geri sarılıyor',
        $hata !== null && $kullanici->ayrildi_at === null && $kullanici->aktif === true,
        $hata ? 'hata alındı, kayıt temiz' : 'HATA HİÇ OLUŞMADI');

    /* ── 3. Ayrılış BÜTÜN akreditasyonları kapatıyor mu? ──────────── */
    app(AkreditasyonAkisi::class)->kullaniciAyrildi($kullanici, 'ayrılış denemesi');
    $kullanici->refresh();

    $durumlar = Akreditasyon::whereIn('id', $temizlik['akreditasyon'])->pluck('durum', 'id');

    $kontrol('Ayrılışta kişinin TÜM akreditasyonları iptal',
        $durumlar->every(fn ($d) => $d === AkreditasyonDurumu::Iptal),
        $durumlar->map(fn ($d) => $d->value)->implode(', '));
    $kontrol('Ayrılış işareti yazıldı ve hesap pasife alındı',
        $kullanici->ayrildi_at !== null && $kullanici->aktif === false);

    /* ── 4. Yeniden başvuru uygunluğu ─────────────────────────────── */
    $uygunluk = app(BasvuruUygunlugu::class);

    $kontrol('Ayrılan kişi yeniden başvurabilir',
        $uygunluk->engel($kullanici) === null, (string) $uygunluk->engel($kullanici));

    $basvuru->forceFill(['durum' => BasvuruDurumu::Reddedildi, 'karar_at' => now()])->save();
    $kontrol('Reddedilen kişi yeniden başvurabilir',
        $uygunluk->engel($kullanici->refresh()) === null, (string) $uygunluk->engel($kullanici));

    $surenBasvuru = Basvuru::create([
        'tur' => BasvuruTuru::BasinMensubu, 'durum' => BasvuruDurumu::Gonderildi,
        'kullanici_id' => $kullanici->id,
    ]);
    $temizlik['basvuru'][] = $surenBasvuru->id;
    $kontrol('Devam eden başvurusu olan yeniden başvuramaz',
        str_contains((string) $uygunluk->engel($kullanici), 'devam eden'));

    $surenBasvuru->forceFill(['durum' => BasvuruDurumu::Reddedildi, 'karar_at' => now()])->save();

    $aktifAkreditasyon = Akreditasyon::create([
        'kart_no' => sprintf('%d-%s-%04d', $yil - 2, $kod, 9800 + random_int(1, 99)),
        'yil' => $yil - 2, 'tur_kodu' => $kod, 'sira' => 9800 + random_int(1, 99),
        'kullanici_id' => $kullanici->id, 'basvuru_id' => $basvuru->id,
        'durum' => AkreditasyonDurumu::Aktif,
    ]);
    $temizlik['akreditasyon'][] = $aktifAkreditasyon->id;
    $kontrol('Geçerli akreditasyonu olan yeniden başvuramaz',
        str_contains((string) $uygunluk->engel($kullanici), 'geçerli bir akreditasyonunuz'));

    $aktifAkreditasyon->forceFill(['durum' => AkreditasyonDurumu::Iptal])->save();

    Ayar::yaz('yeniden_basvuru_bekleme_gun', 30);
    $kontrol('Bekleme süresi ayarı reddedilen adayı geciktiriyor',
        str_contains((string) $uygunluk->engel($kullanici), 'tarihinden sonra'),
        (string) $uygunluk->engel($kullanici));

    Ayar::yaz('yeniden_basvuru_bekleme_gun', 0);
    $kontrol('Bekleme sıfırlanınca engel kalkıyor', $uygunluk->engel($kullanici) === null);

    $kontrol('Hiç hesabı olmayan için engel yok',
        $uygunluk->engel($uygunluk->hesapBul('hic-yok+'.$damga.'@ornek.test')) === null);
} finally {
    /* ── Temizlik ─────────────────────────────────────────────────── */
    Event::forget('eloquent.saving: '.Akreditasyon::class);

    // Kuyruktaki iptal bildirimi kaydı okuyacak; silmeden önce işlensin diye
    // bekle. Yoksa her koşuda failed_jobs'a ModelNotFound çöpü düşer.
    sleep(5);

    // Ayar ESKİ değeriyle geri: testin ayarı kalıcı değiştirmesi yasak.
    if ($eskiBekleme === null) {
        Ayar::where('anahtar', 'yeniden_basvuru_bekleme_gun')->delete();
    } else {
        Ayar::yaz('yeniden_basvuru_bekleme_gun', $eskiBekleme);
    }

    Akreditasyon::whereIn('id', $temizlik['akreditasyon'])->delete();
    if ($rakipUlid ?? null) {
        Akreditasyon::where('ulid', $rakipUlid)->delete();
    }
    Basvuru::whereIn('id', $temizlik['basvuru'])->forceDelete();
    if ($temizlik['kullanici']) {
        DB::table('model_has_roles')->where('model_id', $temizlik['kullanici'])->delete();
        User::withTrashed()->whereKey($temizlik['kullanici'])->forceDelete();
    }
}

$gecen = count(array_filter($sonuc));
$toplam = count($sonuc);
echo "\n".($gecen === $toplam ? "\033[32m" : "\033[31m")."{$gecen}/{$toplam} kontrol geçti\033[0m\n";
exit($gecen === $toplam ? 0 : 1);
