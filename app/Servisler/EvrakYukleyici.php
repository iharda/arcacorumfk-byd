<?php

namespace App\Servisler;

use App\Models\Basvuru;
use App\Models\Evrak;
use App\Models\EvrakTuru;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

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

    /**
     * @param  ?string  $ekEtiket  Alan listemizde olmayan "ek talep" belgesinin
     *                             başlığı. Aynı `ek_belge` türünden birden çok
     *                             belge bunun sayesinde birbirini ezmez.
     */
    public function yukle(Basvuru $basvuru, EvrakTuru $tur, UploadedFile $dosya, ?string $ekEtiket = null): Evrak
    {
        // 🔑 Türü DOSYANIN KENDİSİNDEN oku. Ne uzantıya ne de tarayıcının
        // gönderdiği Content-Type'a güvenilir; Livewire'ın TemporaryUploadedFile
        // sarmalayıcısına da bağlı kalmıyoruz.
        $mime = $this->gercekMime($dosya);
        $this->dogrula($tur, $dosya, $mime);

        $disk = config('bys.evrak_disk');
        $uzanti = $this->uzantiyaCevir($mime);
        // Ad tahmin edilemez ve kullanıcı girdisi içermez.
        $yol = sprintf('basvuru/%s/%s.%s', $basvuru->ulid, Str::ulid(), $uzanti);

        $kaynakYolu = $dosya->getRealPath();

        /*
         * 🔑 PARMAK İZİ ve BOYUT DOSYADAN, BELLEKTEN DEĞİL (M5.3-A).
         * Eskiden `file_get_contents` bütün dosyayı belleğe alıyordu; 8 MB'lık
         * bir belge için tepe bellek ~30 MB'a çıkıyordu (ham + base64 + şifreli
         * kopya). `hash_file` dosyayı parça parça okur, `filesize` hiç okumaz.
         *
         * 💣 BOYUT ŞİFRELEMEDEN ÖNCE ölçülür (Düzeltme listesi md.16).
         * Sonra ölçülünce `Crypt` + base64 şişmesi kayda geçiyordu: ekranda
         * "2,3 MB" yazan bir kimlik fotoğrafı aslında 1,3 MB'tı.
         */
        $sha = hash_file('sha256', $kaynakYolu);
        $boyut = filesize($kaynakYolu);

        if ($tur->hassas) {
            /*
             * ⚠️ HASSAS EVRAK HÂLÂ BELLEKTEN GEÇİYOR ve bu bilerek böyle.
             * `Crypt::encryptString` akış desteklemez; akışa çevirmek DEPOLAMA
             * BİÇİMİNİ değiştirmek, yani canlıdaki şifreli belgeleri yeniden
             * şifrelemek demek. Onlar gerçek kimlik belgeleri; biçim geçişi
             * ayrı ve bilinçli bir iş (M5 F1'in kalan yarısı).
             * Sınır zaten tür başına 5-8 MB olduğu için tepe bellek sınırlı.
             */
            $icerik = Crypt::encryptString(base64_encode(file_get_contents($kaynakYolu)));
            $yol .= '.sifreli';

            Storage::disk($disk)->put($yol, $icerik);
        } else {
            /*
             * Şifresiz evrak (evrakların %79'u ve en büyük dosyalar) hiç
             * belleğe alınmaz: kaynaktan hedefe akar.
             */
            $akis = fopen($kaynakYolu, 'rb');

            try {
                Storage::disk($disk)->writeStream($yol, $akis);
            } finally {
                // Akışı Flysystem kapatmış olabilir; iki kez kapatmak uyarı üretir.
                if (is_resource($akis)) {
                    fclose($akis);
                }
            }
        }

        // Eskiyi arşivleme + yeni kaydı yazma TEK işlemde: kayıt yazılamazsa
        // başvuran önceki evrakını da kaybetmesin.
        try {
            return DB::transaction(function () use ($basvuru, $tur, $disk, $yol, $dosya, $mime, $boyut, $sha, $ekEtiket) {
                // Aynı türden önceki evrak arşive alınır (tekrar yükleme = düzeltme).
                // 🪤 ->each SORGU BUILDER'da yok, Collection'da var. Soft delete sorgu
                // üzerinden de doğru çalışır.
                // 🪤 Ek talep belgeleri AYNI türü paylaşır: etiket de eşleşmezse
                // ikinci ek belge birincisini arşivler.
                $basvuru->evraklar()
                    ->where('evrak_turu_id', $tur->id)
                    ->where('ek_etiket', $ekEtiket)
                    ->delete();

                $evrak = $basvuru->evraklar()->create([
                    'evrak_turu_id' => $tur->id,
                    'ek_etiket' => $ekEtiket,
                    'disk' => $disk,
                    'yol' => $yol,
                    'orijinal_ad' => $this->temizAd($dosya->getClientOriginalName()),
                    'mime' => $mime,
                    'boyut' => $boyut ?: filesize($dosya->getRealPath()),
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
            });
        } catch (Throwable $e) {
            /*
             * 💣 Dosya işlemden ÖNCE diske yazıldı; geri sarma diski
             * temizlemez (Düzeltme listesi md.13). Kayıt yazılamadıysa dosya
             * da kalmasın -- dar bir pencere ama gerçek.
             */
            rescue(fn () => Storage::disk($disk)->delete($yol), report: false);

            throw $e;
        }
    }

    /** Şifreli evrakı okur; şifresizse olduğu gibi döner. */
    public function icerik(Evrak $evrak): string
    {
        /*
         * 🪤 İkinci kapı (M2.2). Asıl engel EvrakController'daki 410; burası
         * ileride eklenecek bir çağıranın aynı tuzağa düşmesini engeller.
         * `Storage::get(null)` bu diskte ('throw' => true) TypeError/500 verir
         * -- sebebini söylemeyen bir hata. Sebebi burada söylüyoruz.
         */
        if ($evrak->imhaEdildiMi()) {
            throw new RuntimeException('Evrakın saklama süresi doldu; dosya imha edilmiş.');
        }

        $ham = Storage::disk($evrak->disk)->get($evrak->yol);

        return $evrak->sifreli ? base64_decode(Crypt::decryptString($ham)) : $ham;
    }

    /**
     * Şifresiz evrakı AKIŞ olarak verir; şifreli evrakta `null` döner (M5.3-A).
     *
     * 🔑 Neden yalnızca şifresiz: `Crypt::decryptString` bütün gövdeyi ister,
     * akış hâlinde çözülemez. Şifreli evrakı akışa almak depolama biçimini
     * değiştirmek demek ve canlıdaki kimlik belgelerinin yeniden şifrelenmesini
     * gerektirir -- ayrı ve bilinçli bir iş.
     *
     * Çağıran `null` gelirse `icerik()`e düşer; yani davranış her iki durumda
     * da aynı, değişen yalnızca bellek profili.
     *
     * @return resource|null
     */
    public function akis(Evrak $evrak)
    {
        if ($evrak->imhaEdildiMi()) {
            throw new RuntimeException('Evrakın saklama süresi doldu; dosya imha edilmiş.');
        }

        if ($evrak->sifreli) {
            return null;
        }

        return Storage::disk($evrak->disk)->readStream($evrak->yol);
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

        if (! in_array($mime, config('bys.yukleme.mime_izin'), true)) {
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
