<?php

/*
 * BYD — hesap ONAY anında açılıyor mu? (Başvuru akışı v2, Revizyon md.3.2)
 *
 * Yeni akışta başvuran onaya kadar sisteme hiç girmez; başvuru kaydı bir
 * kullanıcıya bağlı OLMADAN yaşar. Bu testin ölçtükleri:
 *   1. Hesapsız başvuru onaylanınca hesap, rol ve akreditasyon doğuyor mu?
 *   2. Onay e-postası "şifremi belirle" bağlantısı taşıyor mu? (düz metin
 *      şifre GÖNDERİLMEZ — İbrahim'in kararı, doküman §6 seçenek B)
 *   3. Ayrılmış hesap ikinci kez açılmıyor, yeniden etkinleşiyor mu?
 *   4. Kurum yetkilisi, basın mensubu onayında kurum rolünü koruyor mu?
 *   5. Hesapsız başvuru REDDEDİLİNCE hesap açılmıyor ve red e-postası yine
 *      de gidiyor mu?
 *
 * ⚠️ ÜRETİME YAZAR. Kendi kayıtlarını oluşturur, sonunda siler.
 *    Bildirimler Notification::fake() ile yakalanır; gerçek e-posta GİTMEZ.
 *
 * sudo -u byd php tests/uctan-uca/byd-onayda-hesap-testi.php
 */

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Jobs\KartUret;
use App\Models\Akreditasyon;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use App\Notifications\BasvuruOnaylandi;
use App\Notifications\BasvuruReddedildi;
use App\Servisler\BasvuruAkisi;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$uygulama = require __DIR__.'/../../bootstrap/app.php';
$uygulama->make(Kernel::class)->bootstrap();

$sonuc = [];
$kontrol = function (string $ad, bool $gecti, string $ek = '') use (&$sonuc) {
    $sonuc[] = $gecti;
    echo ($gecti ? "\033[32m✅\033[0m " : "\033[31m❌\033[0m ").$ad.($ek ? "  → {$ek}" : '')."\n";
};

Notification::fake();
// 🪤 Kuyruk da sahte: kart üretimi işi gerçekten koşarsa, test kayıtlarını
// silerken iş kaydı bulamayıp failed_jobs'a çöp bırakıyor.
Queue::fake();

$damga = substr((string) Str::ulid(), -10);
$akis = app(BasvuruAkisi::class);
$temizlik = ['basvuru' => [], 'kullanici' => [], 'kurum' => []];

/** Hesapsız başvuru üretir. */
$basvuruYap = function (BasvuruTuru $tur, string $eposta, string $ad, ?Kurum $kurum = null) use (&$temizlik) {
    $basvuru = Basvuru::create([
        'tur' => $tur,
        'durum' => BasvuruDurumu::Gonderildi,
        'kullanici_id' => null,
        'kurum_id' => $kurum?->id,
        'basvuran_ad' => $ad,
        'basvuran_eposta' => $eposta,
        'basvuran_telefon' => '+90 555 000 00 00',
        'gonderildi_at' => now(),
    ]);
    $temizlik['basvuru'][] = $basvuru->id;

    return $basvuru;
};

try {
    /* ── 1. Hesapsız başvuru onaylanınca hesap doğuyor ─────────────── */
    $eposta1 = "onay1+{$damga}@ornek.test";
    $basvuru = $basvuruYap(BasvuruTuru::IcerikUreticisi, $eposta1, 'Deniz Onay');

    $kontrol('Başvuru hesapsız yaşayabiliyor',
        $basvuru->kullanici_id === null && Basvuru::whereKey($basvuru->id)->exists());

    $kontrol('Hesapsız başvuruda bildirim hedefi ham e-posta',
        $basvuru->bildirimHedefi() instanceof AnonymousNotifiable);

    $akis->incelemeyeAl($basvuru);   // durum makinesi: gönderildi → incelemede → onaylandı
    $akis->onayla($basvuru);
    $basvuru->refresh();

    $kullanici = User::where('email', $eposta1)->first();
    if ($kullanici) {
        $temizlik['kullanici'][] = $kullanici->id;
    }

    $kontrol('Onayda hesap açıldı', $kullanici !== null, $kullanici?->name ?? '—');
    $kontrol('Başvuru hesaba bağlandı', $basvuru->kullanici_id === $kullanici?->id);
    $kontrol('Rol atandı', (bool) $kullanici?->hasRole(User::ROL_ICERIK));
    $kontrol('Şifre HENÜZ belirlenmedi (e-posta doğrulanmamış)',
        $kullanici?->email_verified_at === null);

    $akreditasyon = Akreditasyon::where('basvuru_id', $basvuru->id)->first();
    if ($akreditasyon) {
        $temizlik['akreditasyon'][] = $akreditasyon->id;
    }
    $kontrol('Akreditasyon ve kart numarası doğdu',
        $akreditasyon !== null && filled($akreditasyon->kart_no), $akreditasyon?->kart_no ?? '—');

    Queue::assertPushed(KartUret::class);
    $kontrol('Kart üretimi kuyruğa düştü', true);

    $onayBildirimi = null;
    Notification::assertSentTo($kullanici, BasvuruOnaylandi::class,
        function (BasvuruOnaylandi $bildirim) use (&$onayBildirimi) {
            $onayBildirimi = $bildirim;

            return true;
        });

    $kontrol('Onay e-postası "şifremi belirle" kipinde',
        $onayBildirimi?->sifreBelirlenecek === true);

    $govde = $onayBildirimi?->toMail($kullanici);
    $kontrol('E-postada imzalı şifre bağlantısı var, düz metin şifre YOK',
        $govde !== null
        && str_contains((string) $govde->actionUrl, '/hesap/aktivasyon/')
        && str_contains((string) $govde->actionUrl, 'signature=')
        && ! str_contains(json_encode($govde->introLines + $govde->outroLines, JSON_UNESCAPED_UNICODE), 'şifreniz:'),
        (string) $govde?->actionText);

    /* ── 2. Ayrılmış hesap yeniden etkinleşiyor, ikincisi açılmıyor ── */
    $eposta2 = "onay2+{$damga}@ornek.test";
    $eski = User::create([
        'name' => 'Ayrılan Kişi', 'email' => $eposta2,
        'password' => bcrypt(Str::random(32)),
        'aktif' => false, 'ayrildi_at' => now()->subMonth(),
        'email_verified_at' => now()->subYear(),
    ]);
    $temizlik['kullanici'][] = $eski->id;

    $basvuru2 = $basvuruYap(BasvuruTuru::IcerikUreticisi, $eposta2, 'Ayrılan Kişi');
    $akis->incelemeyeAl($basvuru2);   // durum makinesi: gönderildi → incelemede → onaylandı
    $akis->onayla($basvuru2);
    $eski->refresh();

    $kontrol('İkinci hesap AÇILMADI', User::where('email', $eposta2)->count() === 1);
    $kontrol('Ayrılış işareti kalktı, hesap aktif',
        $eski->ayrildi_at === null && $eski->aktif);

    $bildirim2 = null;
    Notification::assertSentTo($eski, BasvuruOnaylandi::class,
        function (BasvuruOnaylandi $b) use (&$bildirim2) {
            $bildirim2 = $b;

            return true;
        });
    $kontrol('Şifresi olan kullanıcıdan şifre belirlemesi İSTENMİYOR',
        $bildirim2?->sifreBelirlenecek === false);

    $ak2 = Akreditasyon::where('basvuru_id', $basvuru2->id)->first();
    if ($ak2) {
        $temizlik['akreditasyon'][] = $ak2->id;
    }

    /* ── 3. Kurum rolü onayda kaybolmuyor ──────────────────────────── */
    $kurum = Kurum::create([
        'resmi_unvan' => "Test Medya {$damga}",
        'akreditasyon_durumu' => 'akredite',
    ]);
    $temizlik['kurum'][] = $kurum->id;

    $eposta3 = "onay3+{$damga}@ornek.test";
    $sahip = User::create([
        'name' => 'Gazete Sahibi', 'email' => $eposta3,
        'password' => bcrypt(Str::random(32)), 'aktif' => true,
        'email_verified_at' => now(), 'kurum_id' => $kurum->id,
    ]);
    $sahip->assignRole(User::ROL_KURUM);
    $temizlik['kullanici'][] = $sahip->id;

    $basvuru3 = $basvuruYap(BasvuruTuru::BasinMensubu, $eposta3, 'Gazete Sahibi', $kurum);
    $akis->incelemeyeAl($basvuru3);   // durum makinesi: gönderildi → incelemede → onaylandı
    $akis->onayla($basvuru3);
    $sahip->refresh();

    $kontrol('Kurum yetkilisi basın mensubu onayında kurum rolünü KORUYOR',
        $sahip->hasRole(User::ROL_KURUM) && $sahip->hasRole(User::ROL_BASIN),
        implode(', ', $sahip->getRoleNames()->all()));

    $ak3 = Akreditasyon::where('basvuru_id', $basvuru3->id)->first();
    if ($ak3) {
        $temizlik['akreditasyon'][] = $ak3->id;
    }

    /* ── 4. Red hesap AÇMAZ, ama e-posta gider ─────────────────────── */
    $eposta4 = "onay4+{$damga}@ornek.test";
    $basvuru4 = $basvuruYap(BasvuruTuru::IcerikUreticisi, $eposta4, 'Reddedilen Aday');
    $akis->reddet($basvuru4, 'Evraklar yetersiz.');
    $basvuru4->refresh();

    $kontrol('Reddedilen kişinin hesabı AÇILMADI',
        User::withTrashed()->where('email', $eposta4)->doesntExist());
    $kontrol('Başvuru "Reddedildi" durumunda ve hesapsız',
        $basvuru4->durum === BasvuruDurumu::Reddedildi && $basvuru4->kullanici_id === null);

    Notification::assertSentOnDemand(BasvuruReddedildi::class,
        fn ($bildirim, $kanallar, AnonymousNotifiable $hedef) => $hedef->routes['mail'] === $eposta4);
    $kontrol('Red e-postası hesapsız adrese gitti', true, $eposta4);
} finally {
    /* ── Temizlik ─────────────────────────────────────────────────── */
    Akreditasyon::whereIn('id', $temizlik['akreditasyon'] ?? [])->forceDelete();
    Basvuru::whereIn('id', $temizlik['basvuru'])->forceDelete();

    foreach ($temizlik['kullanici'] as $id) {
        DB::table('model_has_roles')->where('model_id', $id)->delete();
        User::withTrashed()->whereKey($id)->forceDelete();
    }

    Kurum::whereIn('id', $temizlik['kurum'])->forceDelete();
}

$gecen = count(array_filter($sonuc));
$toplam = count($sonuc);
echo "\n".($gecen === $toplam ? "\033[32m" : "\033[31m")."{$gecen}/{$toplam} kontrol geçti\033[0m\n";
exit($gecen === $toplam ? 0 : 1);
