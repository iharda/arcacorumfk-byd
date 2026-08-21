<?php

namespace App\Servisler;

use App\Models\Basvuru;
use App\Models\Evrak;
use App\Models\EvrakTuru;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Evrak yükleme -- Plan v1.0 md.11.
 *
 * Kurallar (hepsi SUNUCU tarafında, tarayıcıya güvenilmez):
 *  - Uzantı YETMEZ: dosyanın gerçek türü içerikten (magic byte) doğrulanır.
 *  - Boyut sınırı evrak türünde tanımlı.
 *  - Dosya adı ASLA kullanıcıdan gelen adla saklanmaz (path traversal / XSS).
 *  - Hassas evrak (kimlik, çalışma belgesi) at-rest ŞİFRELİ yazılır.
 *  - Depolama web root DIŞINDA; public URL yok.
 */
class EvrakYukleyici
{
    public function __construct(private DenetimYazici $denetim) {}

    public function yukle(Basvuru $basvuru, EvrakTuru $tur, UploadedFile $dosya): Evrak
    {
        // 🔑 Türü DOSYANIN KENDİSİNDEN oku. Ne uzantıya ne de tarayıcının
        // gönderdiği Content-Type'a güvenilir; Livewire'ın TemporaryUploadedFile
        // sarmalayıcısına da bağlı kalmıyoruz.
        $mime = $this->gercekMime($dosya);
        $this->dogrula($tur, $dosya, $mime);

        $disk = config('byd.evrak_disk');
        $uzanti = $this->uzantiyaCevir($mime);
        // Ad tahmin edilemez ve kullanıcı girdisi içermez.
        $yol = sprintf('basvuru/%s/%s.%s', $basvuru->ulid, Str::ulid(), $uzanti);

        $icerik = file_get_contents($dosya->getRealPath());
        $sha = hash('sha256', $icerik);

        if ($tur->hassas) {
            $icerik = Crypt::encryptString(base64_encode($icerik));
            $yol .= '.sifreli';
        }

        Storage::disk($disk)->put($yol, $icerik);

        // Aynı türden önceki evrak arşive alınır (tekrar yükleme = düzeltme).
        // 🪤 ->each SORGU BUILDER'da yok, Collection'da var. Soft delete sorgu
        // üzerinden de doğru çalışır.
        $basvuru->evraklar()->where('evrak_turu_id', $tur->id)->delete();

        $evrak = $basvuru->evraklar()->create([
            'evrak_turu_id' => $tur->id,
            'disk' => $disk,
            'yol' => $yol,
            'orijinal_ad' => $this->temizAd($dosya->getClientOriginalName()),
            'mime' => $mime,
            'boyut' => strlen($icerik) ?: filesize($dosya->getRealPath()),
            'sha256' => $sha,
            'icerik_dogrulandi' => true,
            'sifreli' => $tur->hassas,
            'dogrulama_durumu' => 'bekliyor',
            'imha_tarihi' => $tur->imha_gun ? now()->addDays($tur->imha_gun)->toDateString() : null,
        ]);

        $this->denetim->yaz('evrak.yuklendi', $evrak, yeni: [
            'evrak_turu' => $tur->kod,
            'boyut' => $evrak->boyut,
        ]);

        return $evrak;
    }

    /** Şifreli evrakı okur; şifresizse olduğu gibi döner. */
    public function icerik(Evrak $evrak): string
    {
        $ham = Storage::disk($evrak->disk)->get($evrak->yol);

        return $evrak->sifreli ? base64_decode(Crypt::decryptString($ham)) : $ham;
    }

    private function dogrula(EvrakTuru $tur, UploadedFile $dosya, string $mime): void
    {
        $yol = $dosya->getRealPath();

        if ($yol === false || ! is_file($yol)) {
            throw new RuntimeException('Dosya yüklenemedi.');
        }

        $kb = (int) ceil(filesize($yol) / 1024);
        if ($kb > $tur->maks_boyut_kb) {
            throw new RuntimeException("Dosya çok büyük: {$kb} KB (en fazla {$tur->maks_boyut_kb} KB).");
        }
        if ($kb === 0) {
            throw new RuntimeException('Dosya boş.');
        }

        if (! in_array($mime, config('byd.yukleme.mime_izin'), true)) {
            throw new RuntimeException('Dosya türü kabul edilmiyor.');
        }

        $izinli = $tur->izinli_formatlar ?: ['pdf', 'jpg', 'jpeg', 'png'];
        $uzanti = $this->uzantiyaCevir($mime);
        // jpg/jpeg aynı türdür; liste hangisini yazarsa yazsın kabul edilir.
        $esler = $uzanti === 'jpg' ? ['jpg', 'jpeg'] : [$uzanti];

        if (! array_intersect($esler, $izinli)) {
            throw new RuntimeException('Bu evrak için izin verilen biçimler: '.implode(', ', $izinli));
        }
    }

    /** Magic byte okuması — dosyanın gerçek türü. */
    private function gercekMime(UploadedFile $dosya): string
    {
        $yol = $dosya->getRealPath();
        $mime = $yol ? (new \finfo(FILEINFO_MIME_TYPE))->file($yol) : false;

        if ($mime === false) {
            throw new RuntimeException('Dosya türü belirlenemedi.');
        }

        return $mime;
    }

    private function uzantiyaCevir(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Dosya türü kabul edilmiyor.'),
        };
    }

    private function temizAd(string $ad): string
    {
        return Str::limit(preg_replace('/[^\p{L}\p{N}\.\-_ ]+/u', '', $ad) ?: 'evrak', 120, '');
    }
}
