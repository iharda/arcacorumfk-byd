<?php

namespace App\Servisler;

use App\Enums\CalisanAraligi;
use App\Models\Basvuru;
use App\Rules\TelefonNumarasi;
use App\Rules\VergiNumarasi;
use App\Support\DuzeltmeAlanlari;
use App\Support\IlIlce;
use App\Support\Telefon;
use App\Support\UlkeKodu;
use Closure;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Düzeltme alanlarını OKUR ve YAZAR -- Yusuf revizyonu 25.08.2026.
 *
 * 🔑 TEK KAPI. `BasvuruPolicy::update()` bilerek `false` döner (serbest
 * düzenleme yok); veri düzeltmesi yalnızca buradan geçer ve yalnızca
 * yetkilinin İŞARETLEDİĞİ alanlara dokunur. İşaretlenmemiş bir anahtar
 * gelirse istisna fırlatılır -- sessizce yok sayılmaz.
 *
 * Değer üç yerde durabilir (`kaynak` tanımı):
 *   `basvuru:<sutun>` · `form:<anahtar>` (form_verisi jsonb) · `kurum:<sutun>`
 */
class DuzeltmeUygulayici
{
    /** Girdi adı: `veri:telefon` HTML'de `alan[veri_telefon]` olur. */
    public static function girdiAdi(string $anahtar): string
    {
        return str_replace(':', '_', $anahtar);
    }

    public static function anahtaraCevir(string $girdiAdi): string
    {
        return preg_replace('/^(veri|evrak|ek)_/', '$1:', $girdiAdi) ?? $girdiAdi;
    }

    /** Alanın ŞU ANKİ değeri (ham). */
    public function deger(Basvuru $basvuru, string $anahtar): mixed
    {
        $tanim = DuzeltmeAlanlari::tanim($basvuru->tur, $anahtar);

        if ($tanim === null) {
            return null;
        }

        [$kok, $alan] = explode(':', $tanim['kaynak'], 2);

        if (str_contains($alan, '+')) {
            [$a, $b] = explode('+', $alan, 2);

            return array_filter([
                $a => $this->tekDeger($basvuru, $kok, $a),
                $b => $this->tekDeger($basvuru, $kok, $b),
            ], fn ($d) => $d !== null);
        }

        return $this->tekDeger($basvuru, $kok, $alan);
    }

    /** Ekranda gösterilecek hâli -- boş değer "—". */
    public function goster(Basvuru $basvuru, string $anahtar, mixed $deger = null): string
    {
        $deger ??= $this->deger($basvuru, $anahtar);
        $tanim = DuzeltmeAlanlari::tanim($basvuru->tur, $anahtar);

        if ($deger === null || $deger === '' || $deger === []) {
            return '—';
        }

        /*
         * 💥 ENUM'U ÖNCE DÜZLEŞTİR. `Kurum::calisan_araligi` modelde
         * `CalisanAraligi` enum'una cast ediliyor; `(string) $deger` bir
         * nesne üzerinde ÖLÜMCÜL hata verir ("could not be converted to
         * string") ve düzeltme sayfası komple 500 döner.
         *
         * 💀 Bu ancak "İlk bilgiler" TÜM alanları okumaya başlayınca ortaya
         * çıktı: daha önce yalnızca yetkilinin işaretlediği alanlar
         * basılıyordu ve kimse çalışan aralığını işaretlememişti. Ders:
         * bir okuyucuyu "her alan" üzerinde çalıştırmak yeni yollar açar.
         */
        if ($deger instanceof \BackedEnum) {
            return method_exists($deger, 'etiket')
                ? (string) $deger->etiket()
                : (string) $deger->value;
        }

        return match ($tanim['tip'] ?? 'metin') {
            'telefon' => Telefon::goster(is_string($deger) ? $deger : null),
            'il-ilce' => is_array($deger) ? implode(' / ', array_filter($deger)) : $this->metne($deger),
            'evet-hayir' => $deger ? 'Var' : 'Yok',
            'aralik' => is_string($deger)
                ? (CalisanAraligi::tryFrom($deger)?->etiket() ?? $deger)
                : (string) json_encode($deger, JSON_UNESCAPED_UNICODE),
            'sosyal' => is_array($deger) ? implode(' · ', array_filter($deger)) : $this->metne($deger),
            'platformlar' => is_array($deger)
                ? collect($deger)->map(fn ($p) => trim(($p['ad'] ?? '').' — '.($p['url'] ?? ''), ' —'))->implode(' · ')
                : $this->metne($deger),
            default => $this->metne($deger),
        };
    }

    /** 🔒 Her tipte GÜVENLİ metin: nesne/dizi asla `(string)` cast'ine girmez. */
    private function metne(mixed $deger): string
    {
        return is_scalar($deger)
            ? (string) $deger
            : (string) json_encode($deger, JSON_UNESCAPED_UNICODE);
    }

    /**
     * İşaretli alanlar için doğrulama kuralları.
     *
     * @param  array<int, string>  $anahtarlar
     * @return array<string, mixed>
     */
    public function kurallar(Basvuru $basvuru, array $anahtarlar): array
    {
        $kurallar = [];

        foreach ($anahtarlar as $anahtar) {
            $tanim = DuzeltmeAlanlari::tanim($basvuru->tur, $anahtar);

            if ($tanim === null || ! $tanim['duzeltilebilir']) {
                continue;
            }

            $ad = 'alan.'.self::girdiAdi($anahtar);

            $kurallar += match ($tanim['tip']) {
                'metin' => [$ad => ['required', 'string', 'min:2', 'max:150']],
                'metin-uzun' => [$ad => ['required', 'string', 'max:300']],
                'sayi' => [$ad => ['required', 'integer', 'min:0', 'max:70']],
                'evet-hayir' => [$ad => ['required', 'boolean']],
                'vergi-no' => [$ad => ['required', 'string', new VergiNumarasi]],
                'aralik' => [$ad => ['required', Rule::enum(CalisanAraligi::class)]],
                /*
                 * 🪤 `TelefonNumarasi` ülke kodunu `request()->input()` ile
                 * okur: NOKTALI TAM YOL vermek şart, yoksa hep varsayılana
                 * düşer. İkinci parametre "cep zorunlu mu" -- KURUM telefonu
                 * sabit hat olabilir (0364 …), yetkilinin telefonu cep.
                 */
                'telefon' => [
                    $ad.'_ulke' => ['required', Rule::in(UlkeKodu::kodlar())],
                    $ad => ['required', 'string', 'max:25',
                        new TelefonNumarasi($ad.'_ulke', cep: $tanim['kaynak'] !== 'kurum:telefon')],
                ],
                'il-ilce' => [
                    $ad.'_il' => ['required', 'string', Rule::in(IlIlce::iller())],
                    $ad.'_ilce' => ['required', 'string', $this->ilceKurali($ad.'_il')],
                ],
                'sosyal' => [$ad => ['array'], $ad.'.*' => ['nullable', 'url', 'max:300']],
                'platformlar' => [
                    $ad => ['array', 'min:1'],
                    $ad.'.*.ad' => ['required', 'string', 'max:120'],
                    $ad.'.*.url' => ['required', 'url', 'max:300'],
                ],
                default => [],
            };
        }

        return $kurallar;
    }

    /**
     * Doğrulanmış girdiyi yazar ve ÖNCEKİ/SONRAKİ değerleri döndürür.
     *
     * @param  array<string, mixed>  $girdi  `alan` dizisi (girdi adlarıyla)
     * @param  array<int, string>  $izinli  yetkilinin işaretlediği anahtarlar
     * @return array<string, array{eski: mixed, yeni: mixed}>
     */
    public function yaz(Basvuru $basvuru, array $girdi, array $izinli): array
    {
        $degisimler = [];
        $formVerisi = $basvuru->form_verisi ?? [];
        $basvuruAlanlari = [];
        $kurumAlanlari = [];

        foreach ($girdi as $girdiAdi => $ham) {
            $anahtar = self::anahtaraCevir(preg_replace('/_(ulke|il|ilce)$/', '', $girdiAdi) ?? $girdiAdi);

            // Bileşik alanların yardımcı girdileri ana anahtarla işlenir.
            if (! DuzeltmeAlanlari::veriMi($anahtar) || isset($degisimler[$anahtar])) {
                continue;
            }

            /*
             * 🔒 İşaretlenmemiş alan SESSİZCE ATLANMAZ, istisna olur:
             * "kaydettim ama değişmedi" en pahalı hata türü.
             */
            if (! in_array($anahtar, $izinli, true)) {
                throw new RuntimeException("Bu alan düzeltme talebinde yok: {$anahtar}");
            }

            $tanim = DuzeltmeAlanlari::tanim($basvuru->tur, $anahtar);

            if ($tanim === null || ! $tanim['duzeltilebilir']) {
                continue;
            }

            $eski = $this->deger($basvuru, $anahtar);
            $yeni = $this->girdidenDeger($tanim, $anahtar, $girdi);

            if ($yeni === null || $this->ayniMi($eski, $yeni)) {
                continue;
            }

            $degisimler[$anahtar] = ['eski' => $eski, 'yeni' => $yeni];

            [$kok, $alan] = explode(':', $tanim['kaynak'], 2);
            $parcalar = str_contains($alan, '+') ? explode('+', $alan) : [$alan];

            foreach ($parcalar as $parca) {
                $deger = is_array($yeni) && str_contains($alan, '+') ? ($yeni[$parca] ?? null) : $yeni;

                match ($kok) {
                    'basvuru' => $basvuruAlanlari[$parca] = $deger,
                    'form' => $formVerisi[$parca] = $deger,
                    'kurum' => $kurumAlanlari[$parca] = $deger,
                    default => null,
                };
            }
        }

        if ($formVerisi !== ($basvuru->form_verisi ?? [])) {
            $basvuruAlanlari['form_verisi'] = $formVerisi;
        }

        if ($basvuruAlanlari !== []) {
            $basvuru->forceFill($basvuruAlanlari)->save();
        }

        if ($kurumAlanlari !== [] && $basvuru->kurum) {
            $basvuru->kurum->forceFill($kurumAlanlari)->save();
        }

        return $degisimler;
    }

    private function tekDeger(Basvuru $basvuru, string $kok, string $alan): mixed
    {
        return match ($kok) {
            'basvuru' => $basvuru->{$alan},
            'form' => ($basvuru->form_verisi ?? [])[$alan] ?? null,
            'kurum' => $basvuru->kurum?->{$alan},
            default => null,
        };
    }

    /** @param array<string, mixed> $girdi */
    private function girdidenDeger(array $tanim, string $anahtar, array $girdi): mixed
    {
        $ad = self::girdiAdi($anahtar);
        $ham = $girdi[$ad] ?? null;

        return match ($tanim['tip']) {
            'telefon' => Telefon::e164((string) $ham, (string) ($girdi[$ad.'_ulke'] ?? UlkeKodu::VARSAYILAN)),
            'il-ilce' => ['il' => $girdi[$ad.'_il'] ?? null, 'ilce' => $girdi[$ad.'_ilce'] ?? null],
            'evet-hayir' => (bool) $ham,
            'sayi' => (int) $ham,
            'sosyal' => array_filter((array) $ham) ?: null,
            'platformlar' => array_values(array_filter((array) $ham, fn ($p) => filled($p['url'] ?? null))) ?: null,
            default => is_string($ham) ? trim($ham) : $ham,
        };
    }

    private function ayniMi(mixed $eski, mixed $yeni): bool
    {
        return json_encode($eski) === json_encode($yeni);
    }

    private function ilceKurali(string $ilAlani): Closure
    {
        return function (string $alan, mixed $deger, Closure $hata) use ($ilAlani): void {
            $il = request()->input($ilAlani);

            if (! IlIlce::gecerliMi(is_string($il) ? $il : '', (string) $deger)) {
                $hata('Seçilen ilçe, ile ait değil.');
            }
        };
    }
}
