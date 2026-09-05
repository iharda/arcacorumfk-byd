<?php

namespace App\Filament\Yonetim\Resources\Kurumlar\Pages;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Filament\Yonetim\Ortak\DegerlendirmeEylemi;
use App\Filament\Yonetim\Ortak\DetaySayfasi;
use App\Filament\Yonetim\Resources\Basvurus\BasvuruResource;
use App\Filament\Yonetim\Resources\Kurumlar\KurumResource;
use App\Models\Basvuru;
use App\Models\Kurum;
use App\Models\User;
use App\Support\DuzeltmeAlanlari;
use App\Support\Telefon;
use Filament\Actions\Action;

/**
 * Kurum detayı -- T5. Ortak şablonun (S1) ikinci uygulaması.
 *
 * Videodaki cümle: "Kurumlar sadece okuma ekranı, ben burada hiçbir işlem
 * yapamıyorum." Künye buraya, düzenleme ayrı sayfaya (KurumDuzenle) taşındı.
 */
class KurumDetay extends DetaySayfasi
{
    protected static string $resource = KurumResource::class;

    protected static ?string $title = 'Kurum';

    /** `ilgiliBasvuru()` belleği: false = henüz bakılmadı, null = yok. */
    private Basvuru|false|null $ilgiliBasvuru = false;

    /** `eksikEvrakBasvurusu()` belleği; aynı kalıp. */
    private Basvuru|false|null $eksikEvrakBasvurusu = false;

    /** ⚠️ `iptal` ile `iptal_edildi` ayrımı için bkz. KurumlarTable::DURUMLAR (M1-A). */
    private const DURUMLAR = [
        'beklemede' => ['Beklemede', 'warning'],
        'akredite' => ['Akredite', 'success'],
        'iptal' => ['İptal', 'danger'],
        'reddedildi' => ['Reddedildi', 'danger'],
        'iptal_edildi' => ['Başvuru iptal edildi', 'gray'],
    ];

    public function kimlik(): string
    {
        return $this->kayit()->resmi_unvan;
    }

    public function altBaslik(): ?string
    {
        $k = $this->kayit();

        return collect([$k->il, $k->ilce])->filter()->implode(' / ') ?: null;
    }

    public function durumRozeti(): ?array
    {
        [$etiket, $renk] = self::DURUMLAR[$this->kayit()->akreditasyon_durumu]
            ?? [$this->kayit()->akreditasyon_durumu, 'gray'];

        return ['etiket' => $etiket, 'renk' => $renk];
    }

    /**
     * "Eksik evrak bekleniyor" bandı -- Cüneyt Bey revizyonu (05.09.2026).
     *
     * 💀 Kurumdan belge istendiğinde bilgi YALNIZCA başvurunun inceleme
     * ekranında duruyordu. Kurum detayına bakan yetkili "bu kuruluşta bekleyen
     * bir iş var mı" sorusunu yanıtlayamıyor, kurum da belgeyi yüklemeden
     * haftalarca bekleyebiliyordu. Bant sekmeye girmeden görünür.
     */
    public function uyariBandi(): ?array
    {
        $basvuru = $this->eksikEvrakBasvurusu();

        if (! $basvuru) {
            return null;
        }

        $gun = $basvuru->acikDuzeltme()?->talep_at?->diffInDays(now());

        return [
            'renk' => 'warning',
            'ikon' => 'heroicon-m-exclamation-triangle',
            'baslik' => 'Eksik evrak bekleniyor',
            'metin' => trim(sprintf(
                '%s başvurusu için belge veya bilgi istendi%s. Kuruluş kendi panelinden yükleyebilir.',
                $basvuru->basvuru_no ?? 'Numarasız',
                $gun === null ? '' : sprintf(' — %d gündür bekliyor', (int) $gun),
            )),
            'baglanti' => [
                'etiket' => 'Başvuru detayına git',
                'url' => BasvuruResource::getUrl('inceleme', ['record' => $basvuru]),
            ],
        ];
    }

    /**
     * Kurumun belge beklenen KURUMSAL başvurusu.
     *
     * 🪤 Bireysel başvurular ayıklanır: çalışanın eksik evrakı kurumun
     * künyesinde uyarı doğurmamalı, o kişinin kendi işi.
     */
    private function eksikEvrakBasvurusu(): ?Basvuru
    {
        // 🪤 `??=` KULLANILAMAZ: başlangıç değeri `false` (null değil), yani
        // atama hiç çalışmaz ve metot sorguyu bir kez bile yapmaz.
        if ($this->eksikEvrakBasvurusu !== false) {
            return $this->eksikEvrakBasvurusu;
        }

        return $this->eksikEvrakBasvurusu = $this->kayit()->basvurular()
            ->where('tur', BasvuruTuru::Kurum->value)
            ->where('durum', BasvuruDurumu::EksikEvrak->value)
            ->latest('id')
            ->first();
    }

    /**
     * Evraklar sekmesinde gösterilecek "yüklenmeyi bekleyen" kalemler.
     *
     * @return array<int, string>
     */
    private function beklenenEvraklar(Basvuru $basvuru): array
    {
        $duzeltme = $basvuru->acikDuzeltme();

        return collect(array_keys($duzeltme?->talep_notlari ?? $basvuru->duzeltme_notlari ?? []))
            ->map(fn (string $anahtar) => DuzeltmeAlanlari::etiket($basvuru, $anahtar))
            ->values()
            ->all();
    }

    public function kunye(): array
    {
        $k = $this->kayit();

        return [
            'Vergi / T.C. no' => ['deger' => $k->vergi_no, 'kopyala' => true],
            'Vergi dairesi' => $k->vergi_dairesi,
            'E-posta' => ['deger' => $k->eposta, 'kopyala' => true],
            'Telefon' => $k->telefon ? Telefon::goster($k->telefon) : null,
            'Adres' => $k->adres,
            'İl / ilçe' => collect([$k->il, $k->ilce])->filter()->implode(' / ') ?: null,
            'Çalışan sayısı' => $k->calisan_araligi?->etiket(),
            'Kontenjan' => $this->kontenjanMetni(),
            /*
             * M2.4 md.4: bu ikisi başvuru formunda toplanıp yalnızca inceleme
             * ekranında görünüyordu. Kurum onaylandıktan sonra "bu kuruluş
             * nerede yayın yapıyor" sorusunun cevabı hiçbir ekranda yoktu.
             */
            'Yayın platformları' => $this->listeMetni($k->yayin_platformlari),
            'Sosyal medya' => $this->listeMetni($k->sosyal_medya),
        ];
    }

    public function sekmeler(): array
    {
        $k = $this->kayit();

        /*
         * 💀 KURUMUN KENDİ HESABI ÇALIŞAN DEĞİL (Cüneyt Bey revizyonu
         * 05.09.2026). Kurum panelini kullanan yetkilinin hesabı da
         * `kurum_id` taşıdığı için çalışan listesinde görünüyordu; kulüp
         * "bu kurumun 4 çalışanı var" sanıyordu, oysa biri kurumun kendisiydi.
         *
         * ⚠️ Yalnızca SADECE kurum rolü olanlar ayıklanır. Bir kişi hem kurum
         * yetkilisi hem basın mensubu olabilir (gazetenin sahibi maça giden
         * muhabir); onun kartı var ve listede DURMALI.
         *
         * Akreditasyon durumu satırda görünsün diye ilişki de yükleniyor.
         */
        $calisanlar = $k->calisanlar()
            ->with(['roles', 'akreditasyon'])
            ->orderBy('name')
            ->get()
            ->reject(fn (User $c) => $c->roles->count() === 1
                && $c->roles->first()?->name === User::ROL_KURUM)
            ->values();
        $akreditasyonlar = $k->akreditasyonlar()->with('kullanici')->latest('id')->get();
        $basvurular = $k->basvurular()->latest('id')->limit(20)->get();

        /*
         * Evrak sekmesinin kaynağı: kurumun EN SON ONAYLANMIŞ kurumsal
         * başvurusu. Onaylanmış olan seçiliyor çünkü kurumun bugünkü
         * akreditasyonu ona dayanıyor; sonradan gönderilmiş yarım bir başvuru
         * geçerli evrakın önüne geçmemeli. Hiç onaylanmışı yoksa en son
         * kurumsal başvuruya düşülür -- inceleme sürerken de evrak görünsün.
         */
        $evrakBasvurusu = $k->basvurular()
            ->where('tur', BasvuruTuru::Kurum->value)
            ->orderByRaw('CASE WHEN durum = ? THEN 0 ELSE 1 END', [BasvuruDurumu::Onaylandi->value])
            ->latest('id')
            ->first();

        $evraklar = $evrakBasvurusu?->evraklar()->with('turu')->get() ?? collect();

        return [
            'calisanlar' => [
                'baslik' => 'Çalışanlar',
                'rozet' => $calisanlar->count() ?: null,
                'view' => 'filament.yonetim.kurum.calisanlar',
                'veri' => ['calisanlar' => $calisanlar],
            ],
            'akreditasyonlar' => [
                'baslik' => 'Akreditasyonlar',
                'rozet' => $akreditasyonlar->count() ?: null,
                'view' => 'filament.yonetim.kurum.akreditasyonlar',
                'veri' => ['akreditasyonlar' => $akreditasyonlar],
            ],
            'basvurular' => [
                'baslik' => 'Başvuru geçmişi',
                'rozet' => $basvurular->count() ?: null,
                'view' => 'filament.yonetim.kurum.basvurular',
                'veri' => ['basvurular' => $basvurular],
            ],

            /*
             * 💀 M2: onaylanmış bir kurumun Ticaret Sicili Gazetesi'ne ulaşmanın
             * tek yolu Kurumlar → detay → Başvuru geçmişi → numaraya tıkla →
             * inceleme ekranı idi. Üç tıklama ve ekran değiştirme.
             *
             * 🔑 Kurumsal onayda AKREDİTASYON KAYDI DOĞMUYOR
             * (AkreditasyonAkisi:33 `return null`), yani kurumun evrakları için
             * "Akreditasyon detayı" gibi bir ev de yok. O ev burası.
             */
            'evraklar' => [
                'baslik' => 'Evraklar',
                'rozet' => $evraklar->count() ?: null,
                'view' => 'filament.yonetim.kurum.evraklar',
                'veri' => [
                    'evraklar' => $evraklar,
                    'basvuru' => $evrakBasvurusu,
                    // Yüklenmeyi bekleyen kalemler: sekmenin kendisi de
                    // "eksik var mı" sorusunu yanıtlasın (bant üstte duruyor).
                    'eksikEvrakBasvurusu' => $this->eksikEvrakBasvurusu(),
                    'beklenenEvraklar' => ($b = $this->eksikEvrakBasvurusu())
                        ? $this->beklenenEvraklar($b) : [],
                ],
            ],

            /*
             * 🔒 Yalnızca `degerlendirme.yonet`. Puan ve not kulüp dışına
             * çıkmaz; kurum kendi panelinde bunu göremez.
             */
            ...(auth()->user()?->can('degerlendirme.yonet') ? ['degerlendirme' => [
                'baslik' => 'Değerlendirme',
                'view' => 'filament.yonetim.kurum.degerlendirme',
                'veri' => ['degerlendirme' => $k->degerlendirme, 'kurumAdi' => $k->resmi_unvan],
            ]] : []),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            /*
             * 🔑 BAŞVURU EYLEMLERİ BURAYA KOPYALANMAZ, BURADAN GİDİLİR (T?).
             *
             * "Kurum detayında başvuru durumuna göre değişen aksiyon düğmeleri"
             * istendi. İki yol vardı: eylemleri bu sayfaya da koymak, ya da
             * eylemlerin YAŞADIĞI sayfaya yönlendirmek. İkincisi seçildi:
             *
             *   · İnceleme ekranındaki yedi eylemin her biri kendi modalını,
             *     policy kontrolünü ve `pasifSebebi()` açıklamasını taşıyor;
             *     kopyalansalardı akış kuralı İKİ yerde yaşardı ve biri
             *     güncellenmeyi unuturdu.
             *   · Bu sayfanın `$record`'u Kurum; eylemler Basvuru üzerinde
             *     çalışıyor. Kurumun birden çok başvurusu olabildiği için
             *     "eylemin öznesi hangisi" sorusunun burada tek cevabı yok.
             *   · İnceleme ekranı ayrıca "başkası inceliyor" kilidini de
             *     gösteriyor; kopya düğmeler o uyarıyı görmeden karar
             *     verilmesine yol açardı.
             *
             * Değişen şey düğmenin KENDİSİ: etiketi, rengi ve simgesi ilgili
             * başvurunun bugünkü durumundan geliyor, yani yetkili sayfaya
             * bakınca "burada iş var mı" sorusunu tıklamadan yanıtlıyor.
             */
            Action::make('basvuruyaGit')
                ->label(fn () => $this->basvuruDugmesi()['etiket'] ?? '')
                ->icon(fn () => $this->basvuruDugmesi()['ikon'] ?? null)
                ->color(fn () => $this->basvuruDugmesi()['renk'] ?? 'gray')
                // Hangi başvuruya gidildiği tıklamadan önce belli olsun.
                ->tooltip(fn () => ($d = $this->basvuruDugmesi())
                    ? trim(($d['basvuru']->basvuru_no ?? '').' · '.$d['basvuru']->durumEtiketi(), ' ·')
                    : null)
                ->visible(fn () => $this->basvuruDugmesi() !== null)
                ->url(fn () => ($d = $this->basvuruDugmesi())
                    ? BasvuruResource::getUrl('inceleme', ['record' => $d['basvuru']])
                    : null),

            Action::make('duzenle')
                ->label('Künyeyi düzenle')
                ->icon('heroicon-m-pencil-square')
                ->visible(fn () => auth()->user()?->can('update', $this->kayit()) ?? false)
                ->url(fn () => KurumResource::getUrl('duzenle', ['record' => $this->kayit()])),
        ];
    }

    /**
     * Düğmenin etiketi/rengi/simgesi -- ilgili başvurunun durumundan türer.
     * Gösterilecek başvuru yoksa ya da yetkili onu göremiyorsa null (düğme
     * hiç çizilmez; var olmayan sayfaya götüren bir düğme koymuyoruz).
     *
     * @return array{basvuru: Basvuru, etiket: string, renk: string, ikon: string}|null
     */
    private function basvuruDugmesi(): ?array
    {
        $basvuru = $this->ilgiliBasvuru();

        if (! $basvuru || ! (auth()->user()?->can('view', $basvuru) ?? false)) {
            return null;
        }

        return ['basvuru' => $basvuru] + match ($basvuru->durum) {
            // Kuyruktakiler: yetkilinin YAPACAĞI iş var, düğme öne çıksın.
            BasvuruDurumu::Gonderildi,
            BasvuruDurumu::YenidenInceleme => [
                'etiket' => 'Başvuruyu incele',
                'renk' => 'primary',
                'ikon' => 'heroicon-m-inbox-arrow-down',
            ],
            BasvuruDurumu::Incelemede => [
                'etiket' => 'İncelemeye devam et',
                'renk' => 'primary',
                'ikon' => 'heroicon-m-eye',
            ],
            BasvuruDurumu::EksikEvrak => [
                'etiket' => 'Belge bekleyen başvuruya git',
                'renk' => 'warning',
                'ikon' => 'heroicon-m-exclamation-triangle',
            ],
            // Taslak henüz gönderilmedi: gidilir ama iş değildir.
            BasvuruDurumu::Taslak => [
                'etiket' => 'Taslak başvuruyu gör',
                'renk' => 'gray',
                'ikon' => 'heroicon-m-document',
            ],
            // Karara bağlanmışlar: "bu kurum neden bu durumda" sorusunun evi.
            default => [
                'etiket' => 'Başvuru detayına git',
                'renk' => 'gray',
                'ikon' => 'heroicon-m-document-text',
            ],
        };
    }

    /**
     * Kurumun akreditasyon kararını taşıyan başvuru -- düğmenin hedefi.
     *
     * 🪤 KURUMSAL başvurular arasından seçilir. `basvurular()` ilişkisi
     * `kurum_id` üzerinden çalıştığı için çalışanların BİREYSEL başvuruları da
     * gelir; onların kararı kurumun akreditasyonunu değiştirmez ve yetkiliyi
     * yanlış ekrana götürürdü.
     *
     * Sıralama önce İŞ OLANI getirir: kuyrukta bekleyen bir başvuru varsa
     * gidilecek yer orasıdır. Yoksa en son kurumsal başvuruya düşülür --
     * "bu kurum neden iptal" sorusunun cevabı orada yazıyor.
     */
    private function ilgiliBasvuru(): ?Basvuru
    {
        // Etiket, renk, adres ve görünürlük aynı kaydı soruyor; sayfa başına
        // bir kez çözülsün. `false` = "henüz bakılmadı", null = "yok".
        if ($this->ilgiliBasvuru !== false) {
            return $this->ilgiliBasvuru;
        }

        $kurumsal = fn () => $this->kayit()->basvurular()
            ->where('tur', BasvuruTuru::Kurum->value)
            ->latest('id');

        return $this->ilgiliBasvuru = $kurumsal()
            ->whereIn('durum', BasvuruDurumu::degerleri(...BasvuruDurumu::kuyruk()))
            ->first()
            ?? $kurumsal()->first();
    }

    /**
     * Değerlendirme eylemi -- SEKMENİN İÇİNDE (Cüneyt Bey revizyonu 05.09.2026).
     *
     * Düğme sayfanın sağ üstündeydi; puanı gösteren şerit ise Değerlendirme
     * sekmesinin içinde. Yetkili puanı okuduğu yerden değil, ekranın öbür
     * ucundan puanlıyordu. İkisi artık aynı yerde.
     *
     * Blade `{{ $this->degerlendirAction }}` ile çizer (Inceleme sayfasındaki
     * kalıbın aynısı); sekmeler `@include` ile geldiği için `$this` erişilebilir.
     */
    public function degerlendirAction(): Action
    {
        return DegerlendirmeEylemi::kurumSayfasi(fn () => $this->kayit());
    }

    /**
     * Künye tek satır metin basar (şablon dizi bilmez), bu yüzden iki farklı
     * şekli de düz metne indiriyoruz:
     *   yayin_platformlari -> [['ad' => .., 'url' => ..], ..]
     *   sosyal_medya       -> ['twitter' => url, ..]
     * Boş değerler ayıklanır ki künyede "—" yerine yarım liste çıkmasın.
     */
    private function listeMetni(?array $deger): ?string
    {
        $satirlar = collect($deger ?? [])
            ->map(fn ($v, $k) => is_array($v)
                ? trim(($v['ad'] ?? '').' ('.($v['url'] ?? '').')', ' ()')
                : (filled($v) ? "{$k}: {$v}" : null))
            ->filter()
            ->values();

        return $satirlar->isEmpty() ? null : $satirlar->implode(' · ');
    }

    /** "3 / 10" ya da "3 · sınırsız" -- kontenjanDoldu() kuralının okunur hâli. */
    private function kontenjanMetni(): string
    {
        $k = $this->kayit();
        $aktif = $k->akreditasyonlar()->where('durum', 'aktif')->count();

        return $k->kontenjan === null
            ? $aktif.' aktif · sınırsız'
            : $aktif.' / '.$k->kontenjan.($k->kontenjanDoldu() ? ' · DOLU' : '');
    }

    private function kayit(): Kurum
    {
        return $this->getRecord();
    }
}
