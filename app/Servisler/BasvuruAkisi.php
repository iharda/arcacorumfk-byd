<?php

namespace App\Servisler;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\User;
use App\Notifications\BasvuruAlindi;
use App\Notifications\BasvuruOnaylandi;
use App\Notifications\BasvuruReddedildi;
use App\Notifications\EksikEvrakTalebi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Başvuru durum akışı -- Plan v1.0 md.4.
 *
 * 🔑 Durum değişikliği YALNIZCA buradan geçer. Ekranlar doğrudan
 * `$basvuru->durum = ...` yazmaz; böylece her geçiş denetim kaydına düşer,
 * bildirim atlanmaz ve geçersiz geçiş sessizce olmaz.
 */
class BasvuruAkisi
{
    public function __construct(private DenetimYazici $denetim) {}

    public function gonder(Basvuru $basvuru): void
    {
        $this->eksikZorunluEvrakVarsaDurdur($basvuru);

        $this->gecir($basvuru, BasvuruDurumu::Gonderildi, 'basvuru.gonderildi', [
            'gonderildi_at'    => now(),
            'duzeltme_notlari' => null,   // düzeltme tamamlandı
        ]);

        $basvuru->kullanici->notify(new BasvuruAlindi($basvuru));
    }

    public function incelemeyeAl(Basvuru $basvuru): void
    {
        $this->gecir($basvuru, BasvuruDurumu::Incelemede, 'basvuru.incelemeye_alindi', [
            'incelemeye_alindi_at' => now(),
            'inceleyen_id'         => Auth::id(),
        ]);
    }

    /** @param array<string, string> $notlar alan adı => açıklama */
    public function eksikEvrakIste(Basvuru $basvuru, array $notlar, ?string $mesaj = null): void
    {
        if ($notlar === []) {
            throw new RuntimeException('En az bir alan işaretlenmeli.');
        }

        $this->gecir($basvuru, BasvuruDurumu::EksikEvrak, 'basvuru.eksik_evrak', [
            'duzeltme_notlari' => $notlar,
            'karar_gerekcesi'  => $mesaj,
        ]);

        $basvuru->kullanici->notify(new EksikEvrakTalebi($basvuru));
    }

    public function onayla(Basvuru $basvuru): void
    {
        $this->gecir($basvuru, BasvuruDurumu::Onaylandi, 'basvuru.onaylandi', [
            'karar_at'       => now(),
            'karar_veren_id' => Auth::id(),
        ]);

        // Kurumsal başvuruda onay = kurumun AKREDİTE olması ve yetkilinin kurum
        // paneline açılması (Plan v1.0 md.5.1). Bireysel türlerde kart üretimi
        // devreye girer -- o 04. aşamada eklenecek.
        if ($basvuru->tur === BasvuruTuru::Kurum && $basvuru->kurum) {
            $basvuru->kurum->update(['akreditasyon_durumu' => 'akredite']);
            $basvuru->kullanici->syncRoles([User::ROL_KURUM]);
        }

        $basvuru->kullanici->notify(new BasvuruOnaylandi($basvuru));
    }

    public function reddet(Basvuru $basvuru, string $gerekce): void
    {
        $this->gecir($basvuru, BasvuruDurumu::Reddedildi, 'basvuru.reddedildi', [
            'karar_at'        => now(),
            'karar_veren_id'  => Auth::id(),
            'karar_gerekcesi' => $gerekce,
        ]);

        $basvuru->kullanici->notify(new BasvuruReddedildi($basvuru));
    }

    /** Ortak geçiş: doğrula → yaz → denetle, hepsi tek işlemde. */
    private function gecir(Basvuru $basvuru, BasvuruDurumu $hedef, string $olay, array $alanlar = []): void
    {
        DB::transaction(function () use ($basvuru, $hedef, $olay, $alanlar) {
            $eski = ['durum' => $basvuru->durum->value];

            $basvuru->durumaGec($hedef);          // geçersizse fırlatır
            $basvuru->fill($alanlar)->save();

            $this->denetim->yaz($olay, $basvuru,
                eski: $eski,
                yeni: ['durum' => $hedef->value] + array_map(
                    fn ($v) => $v instanceof \DateTimeInterface ? $v->format('c') : $v,
                    $alanlar,
                ),
            );
        });
    }

    private function eksikZorunluEvrakVarsaDurdur(Basvuru $basvuru): void
    {
        $gereken = \App\Models\EvrakTuru::turIcin($basvuru->tur)->where('zorunlu', true);
        $yuklenen = $basvuru->evraklar()->pluck('evrak_turu_id')->all();

        $eksik = $gereken->reject(fn ($t) => in_array($t->id, $yuklenen, true));

        if ($eksik->isNotEmpty()) {
            throw new RuntimeException('Eksik zorunlu evrak: ' . $eksik->pluck('ad')->implode(', '));
        }
    }
}
