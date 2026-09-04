<?php

namespace App\Models;

use App\Concerns\UlidAnahtari;
use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Servisler\DuzeltmeUygulayici;
use App\Support\DuzeltmeAlanlari;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * @property string $ulid
 * @property ?string $basvuru_no 2026-BV-0137 -- GONDERIM aninda verilir
 * @property ?int $no_yil basvuru numarasindaki yil
 * @property ?int $no_sira o yilin kacinci basvurusu
 * @property BasvuruTuru $tur
 * @property BasvuruDurumu $durum
 * @property ?int $kullanici_id hesap ONAY aninda acilir; o ana kadar null
 * @property ?User $kullanici
 * @property ?string $basvuran_ad
 * @property ?string $basvuran_eposta
 * @property ?string $basvuran_telefon
 * @property ?array<string, mixed> $form_verisi
 * @property ?array<string, string> $duzeltme_notlari
 * @property ?int $kurum_id
 * @property ?Kurum $kurum
 * @property ?Carbon $gonderildi_at
 * @property ?Carbon $incelemeye_alindi_at
 * @property ?Carbon $karar_at
 */
class Basvuru extends Model
{
    use SoftDeletes, UlidAnahtari;

    protected $table = 'basvurular';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tur' => BasvuruTuru::class,
            'durum' => BasvuruDurumu::class,
            'form_verisi' => 'array',
            'duzeltme_notlari' => 'array',
            'kurum_baslatti' => 'boolean',
            'kurum_teyidi_gerekli' => 'boolean',
            'kurum_teyidi' => 'boolean',
            'kurum_teyidi_at' => 'datetime',
            'gonderildi_at' => 'datetime',
            'incelemeye_alindi_at' => 'datetime',
            'karar_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kullanici_id');
    }

    /** @return BelongsTo<Kurum, $this> */
    public function kurum(): BelongsTo
    {
        return $this->belongsTo(Kurum::class, 'kurum_id');
    }

    public function inceleyen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inceleyen_id');
    }

    public function kararVeren(): BelongsTo
    {
        return $this->belongsTo(User::class, 'karar_veren_id');
    }

    /**
     * Başvurunun evrakları -- FORMDAKİ SIRAYLA.
     *
     * 💀 Sıralama YOKTU: Postgres satırları hangi sırada verirse o sırada
     * geliyordu. İki zorunlu belge varken kimse fark etmiyordu; imza sirküleri
     * eklenip üçe çıkınca inceleme ekranında listenin ve ilk açılan
     * önizlemenin sırası rastgeleleşti. Yetkili her başvuruda belgeleri farklı
     * dizilimde görüyordu.
     *
     * 🔑 Sıra evrak TÜRÜNÜN sırasından geliyor: başvuran formu hangi sırayla
     * doldurduysa yetkili de o sırayla görür. Eşitlikte `id` (ek talep
     * belgeleri aynı türü paylaşır, aralarında yükleme sırası korunur).
     *
     * @return HasMany<Evrak, $this>
     */
    public function evraklar(): HasMany
    {
        return $this->hasMany(Evrak::class, 'basvuru_id')
            ->orderBy(
                EvrakTuru::query()
                    ->select('sira')
                    ->whereColumn('evrak_turleri.id', 'evraklar.evrak_turu_id')
                    ->limit(1),
            )
            ->orderBy('evraklar.id');
    }

    /** @return HasOne<Akreditasyon, $this> */
    public function akreditasyon(): HasOne
    {
        return $this->hasOne(Akreditasyon::class, 'basvuru_id');
    }

    public function davet(): HasOne
    {
        return $this->hasOne(Davet::class, 'basvuru_id');
    }

    /** @return HasMany<BasvuruBileti, $this> */
    public function biletler(): HasMany
    {
        return $this->hasMany(BasvuruBileti::class, 'basvuru_id');
    }

    /** Kullanilabilir durumdaki eksik evrak bileti; yoksa null. */
    public function acikBilet(): ?BasvuruBileti
    {
        return $this->biletler()
            ->whereNull('kullanildi_at')
            ->whereNull('iptal_at')
            ->where('gecerlilik_bitis', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * Bildirim hedefi -- TEK kapi. Hesap ONAY aninda acildigi icin (Revizyon
     * md.1) basvurunun buyuk bolumunde kullanici YOKTUR; bildirim ham e-posta
     * adresine gider. Cagiran kod "hesap var mi" diye sormaz.
     */
    public function bildirimHedefi(): object
    {
        if ($this->kullanici !== null) {
            return $this->kullanici;
        }

        $eposta = $this->basvuran_eposta
            ?? throw new RuntimeException('Başvurunun bildirim adresi yok.');

        return Notification::route('mail', $eposta);
    }

    /** Kuyrukta ve ekranlarda gosterilecek ad; hesap acilmamis olabilir. */
    public function basvuranAdi(): string
    {
        return $this->kullanici->name ?? $this->basvuran_ad ?? '—';
    }

    public function basvuranEpostasi(): ?string
    {
        return $this->kullanici->email ?? $this->basvuran_eposta;
    }

    /**
     * Kuyrukta gecen sure -- gun. Kuyrukta olmayan ya da hic gonderilmemis
     * basvuruda null.
     *
     * 🕐 Gun farki GUN BASINDAN sayilir: dun 23:00'te gelen basvuru "0 gun"
     * degil "1 gundur" bekliyor; yetkilinin takvimi saat degil gun.
     */
    public function bekleyenGun(): ?int
    {
        if ($this->gonderildi_at === null
            || ! in_array($this->durum, BasvuruDurumu::kuyruk(), true)) {
            return null;
        }

        return (int) $this->gonderildi_at->copy()->startOfDay()
            ->diffInDays(now()->startOfDay());
    }

    /**
     * Ayni surenin okunur hali: "14 gündür kuyrukta". Panoda ve kuyruk
     * listesinde AYNI cumle gorunsun diye tek tanim (saha notlari T4).
     */
    public function bekleyenSure(): ?string
    {
        $gun = $this->bekleyenGun();

        return match (true) {
            $gun === null => null,
            $gun === 0 => 'bugün geldi',
            default => $gun.' gündür kuyrukta',
        };
    }

    /**
     * Yetkili kuyruğu. Kurum teyidi bekleyen başvuru buraya DÜŞMEZ —
     * önce kurumun "bu kişi çalışanımız" demesi gerekir (Plan v1.0 md.5.2).
     */
    public function scopeKuyrukta(Builder $query): Builder
    {
        return $query
            ->whereIn('durum', array_column(BasvuruDurumu::kuyruk(), 'value'))
            ->where(fn (Builder $alt) => $alt
                ->where('kurum_teyidi_gerekli', false)
                ->orWhereNotNull('kurum_teyidi'));
    }

    /** Kurumun cevabını bekleyenler (kurum panelinde listelenir). */
    public function scopeTeyitBekleyen(Builder $query): Builder
    {
        return $query
            ->where('kurum_teyidi_gerekli', true)
            ->whereNull('kurum_teyidi')
            ->where('durum', BasvuruDurumu::Gonderildi->value);
    }

    public function kurumTeyidiBekliyorMu(): bool
    {
        return $this->kurum_teyidi_gerekli
            && $this->kurum_teyidi === null
            && $this->durum === BasvuruDurumu::Gonderildi;
    }

    /**
     * Durum degistirme -- TEK kapi. Gecerli olmayan gecis sessizce yutulmaz,
     * hata firlatir; boylece bir ekranda unutulan kontrol veriyi bozamaz.
     */
    /**
     * Ekranda görünecek durum etiketi -- Cüneyt Bey revizyonu (05.09.2026).
     *
     * 🪤 "Akredite edildi" GEÇMİŞ BİR KARARDIR, bugünkü durum değil. Kurumun
     * akreditasyonu sonradan kaldırılmış olabilir; başvuru satırı yine
     * "Akredite edildi" derken Kurumlar ekranı "İptal" diyordu. İkisi de
     * doğru ama yan yana görülünce çelişki gibi duruyor.
     *
     * Etiket artık kararın BUGÜNKÜ karşılığını da söylüyor. Enum etiketi
     * (`BasvuruDurumu::etiket`) sade kalır -- kayda erişimi yok ve denetim
     * kaydı gibi yerlerde tek başına kullanılıyor.
     *
     * ⚠️ `kurum` / `akreditasyon` ilişkileri okunur: liste kullanıyorsa
     * `with()` ile yüklensin, yoksa satır başına sorgu açar.
     */
    public function durumEtiketi(): string
    {
        $etiket = $this->durum->etiket();

        if ($this->durum !== BasvuruDurumu::Onaylandi) {
            return $etiket;
        }

        $ek = $this->tur === BasvuruTuru::Kurum
            // Kurumsal onay = kurumun akredite olması; bugünkü hâli oradan.
            ? ($this->kurum && $this->kurum->akreditasyon_durumu !== 'akredite'
                ? 'sonradan kaldırıldı' : null)
            // Bireysel onay = kart; kartın bugünkü durumu.
            : match ($this->akreditasyon?->durum) {
                AkreditasyonDurumu::Iptal => 'sonradan kaldırıldı',
                AkreditasyonDurumu::Askida => 'askıda',
                default => null,
            };

        return $ek ? "{$etiket} ({$ek})" : $etiket;
    }

    public function durumaGec(BasvuruDurumu $hedef): void
    {
        if (! $this->durum->gecebilirMi($hedef)) {
            /*
             * 💀 M9 №8: BU MESAJ EKRANA ÇIKIYOR.
             *
             * İki yetkili aynı başvuruyu aynı anda açtığında ikincisi karar
             * verince tam olarak buraya düşüyor -- ve "Geçersiz durum geçişi:
             * incelemede → onaylandi" yazan ham bir hata görüyordu. Ne olduğunu
             * anlatmıyor, ne yapacağını hiç söylemiyor.
             *
             * Durum adları kullanıcının listede gördüğü etiketlerle aynı
             * (BasvuruDurumu::etiket) ve cümle "birisi senden önce davrandı"
             * ihtimalini açıkça söylüyor -- en sık sebebi bu.
             */
            throw new RuntimeException(sprintf(
                'Bu başvuru artık "%s" durumunda; "%s" adımı uygulanamaz. '
                .'Başka bir yetkili sizden önce işlem yapmış olabilir — sayfayı yenileyin.',
                $this->durum->etiket(),
                $hedef->etiket(),
            ));
        }

        $this->durum = $hedef;
    }

    /**
     * Düzeltme turları -- en yeni en üstte (Yusuf revizyonu 25.08.2026).
     *
     * @return HasMany<BasvuruDuzeltmesi, $this>
     */
    public function duzeltmeler(): HasMany
    {
        return $this->hasMany(BasvuruDuzeltmesi::class, 'basvuru_id')->orderByDesc('sira');
    }

    /** Yanıtlanmamış açık tur -- başvuranın şu an doldurduğu. */
    public function acikDuzeltme(): ?BasvuruDuzeltmesi
    {
        return $this->duzeltmeler()->whereNull('yanit_at')->first();
    }

    /** Basvuran yalnizca isaretli alanlari duzeltebilir (Plan v1.0 md.4). */
    public function duzeltilebilirAlanlar(): array
    {
        return array_keys($this->duzeltme_notlari ?? []);
    }

    /**
     * BAŞVURU ANINDAKİ değerler -- Yusuf revizyonu md.4: zaman çizelgesi
     * "ilk bilgiler"den başlamalı, tur 01'den değil.
     *
     * 🔑 Ayrı bir anlık görüntü SAKLANMAZ: turlar zaten her değişimin `eski`
     * hâlini tutuyor. Bir alanın ilk değeri, onu DEĞİŞTİREN EN ESKİ turun
     * `eski` değeridir; hiç değişmemiş alan bugünkü hâliyle aynıdır.
     * Böylece ikinci bir doğruluk kaynağı doğmuyor.
     *
     * @return array<string, mixed> anahtar => ilk değer
     */
    public function ilkDegerler(): array
    {
        $uygulayici = app(DuzeltmeUygulayici::class);
        $degerler = [];

        foreach (DuzeltmeAlanlari::veriTanimlari($this->tur) as $anahtar => $tanim) {
            $degerler[$anahtar] = $uygulayici->deger($this, $anahtar);
        }

        // Eskiden yeniye: her alanın İLK gördüğü `eski` değer kazanır.
        $bulunanlar = [];

        foreach ($this->duzeltmeler->sortBy('sira') as $tur) {
            foreach ($tur->degisiklikler ?? [] as $anahtar => $degisim) {
                // Evrak ve ek talepler "ilk bilgiler" tablosuna girmez.
                if (! array_key_exists($anahtar, $degerler) || isset($bulunanlar[$anahtar])) {
                    continue;
                }

                $degerler[$anahtar] = $degisim['eski'];
                $bulunanlar[$anahtar] = true;
            }
        }

        return $degerler;
    }

    /**
     * Düzeltme anahtarının ekranda görünen adı. Anahtarlar `evrak:<kod>` /
     * `veri:<alan>` biçimindedir; etiket hiç saklanmaz, her seferinde
     * üretilir (Düzeltme listesi md.11).
     */
    public function duzeltmeEtiketi(string $anahtar): string
    {
        return DuzeltmeAlanlari::etiket($this, $anahtar);
    }

    /**
     * Bir düzeltme değerinin ekranda görünecek hâli.
     *
     * 🪤 Biçim ALANIN TİPİNE bağlı: telefon `+90 532 111 22 33` diye
     * gruplanır, evet/hayır "Var/Yok" olur, il+ilçe birleşir. Görünümde
     * genel bir yardımcıyla basmak telefonu ham `+905321112233` gösteriyordu.
     * Evrak ve ek talep değerleri (dosya adı) düz metindir.
     */
    public function duzeltmeDegeriGoster(string $anahtar, mixed $deger): string
    {
        if (DuzeltmeAlanlari::veriMi($anahtar)) {
            return app(DuzeltmeUygulayici::class)->goster($this, $anahtar, $deger);
        }

        if ($deger === null || $deger === '' || $deger === []) {
            return '—';
        }

        if (is_bool($deger)) {
            return $deger ? 'Evet' : 'Hayır';
        }

        return is_scalar($deger)
            ? (string) $deger
            : (string) json_encode($deger, JSON_UNESCAPED_UNICODE);
    }

    public function eksikEvrakBekleniyorMu(): bool
    {
        return $this->durum === BasvuruDurumu::EksikEvrak;
    }
}
