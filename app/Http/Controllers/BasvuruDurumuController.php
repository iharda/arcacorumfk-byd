<?php

namespace App\Http\Controllers;

use App\Models\Basvuru;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Başvurum ne oldu?" -- Cüneyt Bey revizyonu (05.09.2026).
 *
 * 💀 Reddedilen adayın hesabı HİÇ AÇILMIYOR (hesap onay anında doğar,
 * HesapAcici). Kişi sonucu öğrenmek için giriş ekranına gidiyor ve
 * "E-posta veya şifre hatalı" görüyordu -- doğru ama cevapsız bir cümle;
 * kişi şifresini yanlış hatırladığını sanıp sıfırlama denemesine giriyordu.
 *
 * 🔒 GİRİŞ EKRANINDA "böyle bir kayıt yok" DEMİYORUZ: adres deneyen biri
 * hangi e-postaların sistemde kayıtlı olduğunu öğrenirdi. Cevap burada ve
 * İKİ bilgi birden isteniyor -- başvuru numarası + e-posta. Numara yalnızca
 * başvuru sahibinin elinde (başvuru alındı e-postasında) olduğu için bu
 * ekran adres taramasına yaramaz.
 */
class BasvuruDurumuController extends Controller
{
    public function form(): View
    {
        return view('basvuru.durum');
    }

    public function sorgula(Request $istek): View
    {
        $veri = $istek->validate(
            [
                'basvuru_no' => ['required', 'string', 'max:30'],
                'eposta' => ['required', 'email:rfc', 'max:150'],
            ],
            [],
            ['basvuru_no' => 'başvuru numarası', 'eposta' => 'e-posta'],
        );

        /*
         * 🪤 İKİSİ BİRDEN eşleşmeli ve eşleşmezse TEK bir cümle döner:
         * "numara doğru ama e-posta yanlış" gibi bir ayrım, numarayı bilen
         * birine adresi doğrulatırdı.
         *
         * E-posta iki yerde durabilir: başvurunun kendi alanında (hesap
         * açılmamış) ya da bağlı hesapta (onaylanmış). İkisine de bakılır.
         */
        $basvuru = Basvuru::query()
            ->with(['kullanici', 'kurum'])
            ->where('basvuru_no', trim($veri['basvuru_no']))
            ->where(fn ($q) => $q
                ->whereRaw('lower(basvuran_eposta) = ?', [mb_strtolower($veri['eposta'])])
                ->orWhereHas('kullanici', fn ($k) => $k
                    ->whereRaw('lower(email) = ?', [mb_strtolower($veri['eposta'])])))
            ->first();

        return view('basvuru.durum', [
            'sorgulandi' => true,
            'basvuru' => $basvuru,
        ]);
    }
}
