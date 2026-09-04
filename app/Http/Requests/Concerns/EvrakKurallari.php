<?php

namespace App\Http\Requests\Concerns;

use App\Enums\BasvuruTuru;
use App\Models\EvrakTuru;
use App\Servisler\EvrakTaslagi;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Collection;

/**
 * Başvuru formundaki evrak alanları -- Revizyon md.3.1.
 *
 * Zorunluluk, boyut ve biçim `evrak_turleri` tablosunda tanımlı; kural listesi
 * de oradan türetilir. Böylece panelden bir evrak türü eklendiğinde form da
 * doğrulama da birlikte değişir, iki yerde elle güncelleme gerekmez.
 *
 * Doğrulama hatasında dosyaların kaybolmaması da buradan yürür
 * ({@see EvrakTaslagi}) -- Cüneyt Bey revizyonu 03.09.2026.
 */
trait EvrakKurallari
{
    /** @var array<string, Collection<int, EvrakTuru>> */
    private array $evrakTuruOnbellegi = [];

    /** @return Collection<int, EvrakTuru> */
    protected function evrakTurleri(BasvuruTuru $tur): Collection
    {
        return $this->evrakTuruOnbellegi[$tur->value] ??= EvrakTuru::turIcin($tur);
    }

    /** @return array<string, array<int, string>> */
    protected function evrakKurallari(BasvuruTuru $tur): array
    {
        // Dizinin kendisi de doğrulanır: `evraklar` yerine dize gönderilirse
        // alan bazlı kurallar hiç çalışmaz, sessizce boş geçerdi.
        $kurallar = ['evraklar' => ['array']];

        foreach ($this->evrakTurleri($tur) as $evrakTuru) {
            $kurallar["evraklar.{$evrakTuru->id}"] = [
                // ⚠️ `zorunlu` bayrağına DOĞRUDAN bakma: zorunluluğun yürürlük
                // tarihi olabilir (M7.2). Form ve akış AYNI kaynaktan sorsun,
                // yoksa form "yüklemelisiniz" derken servis "gerek yok" der.
                $evrakTuru->yeniBasvuruIcinZorunluMu() ? 'required' : 'nullable',
                'file',
                // Boyut sınırı türden; içerik (magic byte) doğrulaması yükleyicide.
                'max:'.$evrakTuru->maks_boyut_kb,
            ];
        }

        return $kurallar;
    }

    /** @return array<string, string> */
    protected function evrakAdlari(BasvuruTuru $tur): array
    {
        return $this->evrakTurleri($tur)
            ->mapWithKeys(fn (EvrakTuru $evrakTuru) => ["evraklar.{$evrakTuru->id}" => $evrakTuru->ad])
            ->all();
    }

    /**
     * Bu istekte gelmeyen evrakları taslaktan canlandırır: başvuran
     * doğrulama hatasından sonra dosyalarını YENİDEN SEÇMEK ZORUNDA DEĞİL.
     *
     * 🔒 Yalnızca BU BAŞVURU TÜRÜNE ait evrak türleri canlanır. Başvuran
     * önce kurum formunu yarım bırakıp sonra bireysel forma geçtiyse
     * oradaki taslak buraya sızmamalı; sızsaydı `BasvuruEvrakAlici`
     * "geçersiz evrak türü" diye başvuruyu komple reddederdi.
     */
    protected function evrakTaslaginiCanlandir(BasvuruTuru $tur): void
    {
        $izinli = $this->evrakTurleri($tur)->pluck('id')->all();

        /*
         * 🪤 Dosyalar `$this->file()` ile DEĞİL ham Symfony torbasından
         * okunur. `file()` sonucu `convertedFiles` içinde önbelleğe alınır;
         * torbayı sonradan güncellediğimizde doğrulama hâlâ ESKİ listeyi
         * görür ve zorunlu evrak "eksik" çıkardı. Torbayı okumak o önbelleği
         * hiç doğurmaz.
         */
        $birlesik = array_intersect_key(
            app(EvrakTaslagi::class)->birlestir((array) $this->files->get('evraklar', [])),
            array_flip($izinli),
        );

        if ($birlesik === []) {
            return;
        }

        $this->files->set('evraklar', $birlesik);
    }

    /** Hatalı gönderimde seçilen dosyalar taslağa alınır. */
    protected function evraklariTaslagaAl(): void
    {
        app(EvrakTaslagi::class)->sakla((array) $this->file('evraklar', []));
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->evraklariTaslagaAl();

        parent::failedValidation($validator);
    }
}
