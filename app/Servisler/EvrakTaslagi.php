<?php

namespace App\Servisler;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

/**
 * Doğrulama hatasından SAĞ ÇIKAN evrak seçimi -- Cüneyt Bey revizyonu
 * (03.09.2026): "ya formu yeniden yükletmemeliyiz, ya da yüklense bile
 * evrak seçimi yaptırmamalıyız."
 *
 * 💀 SORUN: HTML'de `<input type="file">` `old()` ile geri DOLDURULAMAZ.
 * Formda tek bir yazım hatası olsa bile sayfa yeniden çiziliyor ve başvuran
 * kimliğini, fotoğrafını, sicil gazetesini BAŞTAN seçmek zorunda kalıyordu.
 * Ekranda özür niteliğinde kırmızı bir satır vardı; kullanıcının işini
 * kolaylaştırmıyordu.
 *
 * 🔑 ÇÖZÜM: yüklenen dosya, doğrulama patlasa bile sunucuda geçici bir
 * taslak olarak durur. Form yeniden çizilirken kutuda "seçili dosya" görünür;
 * başvuran yeniden seçmezse sonraki gönderimde taslaktaki dosya kullanılır.
 *
 * 🔒 Bağ OTURUMDA: hangi taslağın kime ait olduğu yalnızca session'da yazılı,
 * yol tahmin edilemez bir ULID ve depo web root DIŞINDA. Başkasının
 * taslağını istemek diye bir şey yok, çünkü istenecek bir adres yok.
 *
 * 🧹 Taslaklar başarılı gönderimde silinir; yarım kalanları
 * `evrak:taslak-temizle` günlük süpürür (KVKK: kimlik belgesi diskte
 * süresiz durmasın).
 */
class EvrakTaslagi
{
    /** Session anahtari: evrak_turu_id => dosya bilgisi. */
    private const ANAHTAR = 'bys_evrak_taslagi';

    private const DISK = 'local';

    private const KOK = 'evrak-taslagi';

    /**
     * Yuklenen dosyalari taslaga alir. Zaten taslakta olan (yani bu istekte
     * taslaktan CANLANDIRILMIS) dosyalar tekrar yazilmaz.
     *
     * @param  array<int|string, mixed>  $dosyalar  evrak_turu_id => UploadedFile
     */
    public function sakla(array $dosyalar): void
    {
        $kayit = $this->kayit();

        foreach ($dosyalar as $turId => $dosya) {
            // 🪤 SYMFONY tipi: istek torbasindan okurken dosyalar henuz
            // Illuminate sarmalayicisina cevrilmemis olabiliyor.
            if (! $dosya instanceof SymfonyUploadedFile || ! $dosya->isValid()) {
                continue;
            }

            $turId = (int) $turId;

            // Canlandirilmis dosya: yolu zaten taslakta, yeniden kopyalama.
            if (($kayit[$turId]['yol'] ?? null) !== null
                && $this->tamYol($kayit[$turId]['yol']) === $dosya->getRealPath()) {
                continue;
            }

            $yeniYol = self::KOK.'/'.Str::ulid().'.taslak';

            Storage::disk(self::DISK)->put(
                $yeniYol,
                (string) file_get_contents($dosya->getRealPath()),
            );

            // Ayni tur icin onceki taslak artik gereksiz.
            $this->dosyayiSil($kayit[$turId]['yol'] ?? null);

            $kayit[$turId] = [
                'yol' => $yeniYol,
                'ad' => $dosya->getClientOriginalName(),
                'mime' => $dosya->getClientMimeType(),
                'boyut' => $dosya->getSize(),
            ];
        }

        Session::put(self::ANAHTAR, $kayit);
    }

    /**
     * Ekranda gosterilecek taslak listesi: evrak_turu_id => [ad, boyut].
     *
     * @return array<int, array{ad: string, boyut: int}>
     */
    public function ozet(): array
    {
        $ozet = [];

        foreach ($this->kayit() as $turId => $bilgi) {
            $ozet[$turId] = ['ad' => $bilgi['ad'], 'boyut' => (int) $bilgi['boyut']];
        }

        return $ozet;
    }

    /**
     * Istekteki dosyalarla taslaktakileri birlestirir: BU İSTEKTE gelen
     * dosya her zaman kazanir, gelmeyen tur icin taslak canlandirilir.
     *
     * @param  array<int|string, mixed>  $dosyalar
     * @return array<int, SymfonyUploadedFile>
     */
    public function birlestir(array $dosyalar): array
    {
        $sonuc = [];

        foreach ($dosyalar as $turId => $dosya) {
            if ($dosya instanceof SymfonyUploadedFile && $dosya->isValid()) {
                $sonuc[(int) $turId] = $dosya;
            }
        }

        foreach ($this->kayit() as $turId => $bilgi) {
            if (isset($sonuc[$turId])) {
                continue;
            }

            $sonuc[$turId] = new UploadedFile(
                $this->tamYol($bilgi['yol']),
                $bilgi['ad'],
                $bilgi['mime'],
                null,
                // 🪤 `test: true` SART: dosya gercek bir HTTP yuklemesi degil,
                // `is_uploaded_file()` false doner ve Symfony onu gecersiz sayar.
                test: true,
            );
        }

        return $sonuc;
    }

    /** Basarili gonderimden sonra: diskte de oturumda da iz kalmasin. */
    public function temizle(): void
    {
        foreach ($this->kayit() as $bilgi) {
            $this->dosyayiSil($bilgi['yol']);
        }

        Session::forget(self::ANAHTAR);
    }

    /**
     * Yarim kalmis taslaklari siler (zamanlanmis is).
     *
     * @return int silinen dosya sayisi
     */
    public function suresiGecenleriSil(int $saat = 24): int
    {
        $disk = Storage::disk(self::DISK);
        $sinir = now()->subHours($saat)->getTimestamp();
        $silinen = 0;

        foreach ($disk->files(self::KOK) as $yol) {
            if ($disk->lastModified($yol) < $sinir) {
                $disk->delete($yol);
                $silinen++;
            }
        }

        return $silinen;
    }

    /** @return array<int, array{yol: string, ad: string, mime: string, boyut: int}> */
    private function kayit(): array
    {
        $ham = Session::get(self::ANAHTAR, []);

        if (! is_array($ham)) {
            return [];
        }

        $temiz = [];
        $degisti = false;

        foreach ($ham as $turId => $bilgi) {
            // Dosya diskten gitmisse (sureli temizlik) kayit da gitsin.
            if (is_array($bilgi) && filled($bilgi['yol'] ?? null)
                && Storage::disk(self::DISK)->exists($bilgi['yol'])) {
                $temiz[(int) $turId] = $bilgi;

                continue;
            }

            $degisti = true;
        }

        if ($degisti) {
            Session::put(self::ANAHTAR, $temiz);
        }

        return $temiz;
    }

    private function tamYol(string $yol): string
    {
        return Storage::disk(self::DISK)->path($yol);
    }

    private function dosyayiSil(?string $yol): void
    {
        if (filled($yol)) {
            Storage::disk(self::DISK)->delete($yol);
        }
    }
}
