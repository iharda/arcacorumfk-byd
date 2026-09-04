<?php

namespace App\Filament\Yonetim\Resources\EvrakTurleri\Schemas;

use App\Enums\BasvuruDurumu;
use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\EvrakTuru;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Validation\Rule;

class EvrakTuruFormu
{
    /** MIME beyaz listesiyle AYNI kümeden türer; iki liste ayrışmasın. */
    private const FORMATLAR = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    /** @return array<int, mixed> */
    public static function alanlar(): array
    {
        return [
            TextInput::make('ad')
                ->label('Belge adı')
                ->helperText('Başvuranın formda gördüğü başlık.')
                ->placeholder('İmza sirküleri')
                ->required()
                ->maxLength(120),

            /*
             * 🔑 KOD OLUŞTURURKEN YAZILIR, SONRA SALT OKUNUR.
             * Düzeltme anahtarları `evrak:<kod>` şemasından üretiliyor
             * (DuzeltmeAlanlari); kod sonradan değişirse yolda olan düzeltme
             * biletleri artık hiçbir alanı açmaz ve başvuran belge yükleyemez.
             * Aynı hataya `13-duzeltme-listesi.md` md.11'de bir kez düşülmüş.
             */
            TextInput::make('kod')
                ->label('Kod')
                ->helperText('Teknik anahtar. Kayıt oluşturulduktan sonra DEĞİŞTİRİLEMEZ: '
                    .'düzeltme bağlantıları bu koda bağlı.')
                ->placeholder('imza_sirkuleri')
                ->required()
                ->maxLength(60)
                ->regex('/^[a-z][a-z0-9_]*$/')
                ->validationMessages(['regex' => 'Yalnızca küçük harf, rakam ve alt çizgi; harfle başlamalı.'])
                ->rule(fn (?EvrakTuru $record) => Rule::unique('evrak_turleri', 'kod')->ignore($record?->id))
                ->disabled(fn (?EvrakTuru $record) => $record !== null)
                ->dehydrated(fn (?EvrakTuru $record) => $record === null),

            Textarea::make('aciklama')
                ->label('Açıklama')
                ->helperText('Hangi belgelerin kabul edildiğini başvurana anlatır.')
                ->rows(2)
                ->maxLength(500),

            CheckboxList::make('basvuru_turleri')
                ->label('Hangi başvuru türlerinde istensin')
                ->options(fn () => collect(BasvuruTuru::cases())
                    ->mapWithKeys(fn (BasvuruTuru $t) => [$t->value => $t->etiket()])->all())
                ->required()
                ->columns(3),

            CheckboxList::make('izinli_formatlar')
                ->label('İzinli dosya biçimleri')
                /*
                 * 🪤 `config/bys.php` `mime_izin` ile bu liste BİRLİKTE
                 * yürümeli; burada seçilebilecekler bilerek o beyaz listeyle
                 * sınırlı. `tests/Feature/DosyaTuruListeleriTest` ayrışmayı
                 * yakalar. (svg ASLA -- md.3'te bir kez düzeltilmiş bir hata.)
                 */
                ->options(array_combine(self::FORMATLAR, array_map('strtoupper', self::FORMATLAR)))
                ->required()
                ->columns(5),

            TextInput::make('maks_boyut_kb')
                ->label('En büyük dosya boyutu (KB)')
                ->numeric()
                ->minValue(64)
                ->maxValue(65536)
                ->required()
                ->default(8192),

            Toggle::make('zorunlu')
                ->label('Zorunlu belge')
                ->helperText(fn (?EvrakTuru $record) => self::zorunlulukUyarisi($record))
                ->live(),

            /*
             * 💀 Zorunluluğun YÜRÜRLÜK TARİHİ (M7.2). Boş bırakılırsa kural
             * geçmişe de işler ve KUYRUKTAKİ başvurular kilitlenir: düzeltme
             * bileti yalnız işaretli alanları açtığı için başvuran yeni belgeyi
             * yükleyemez bile.
             */
            DatePicker::make('zorunlu_baslangic')
                ->label('Zorunluluk başlangıcı')
                ->helperText('Bu tarihten SONRA açılan başvurular için zorunlu olur. '
                    .'Boş bırakılırsa kuyrukta bekleyen başvurular da bu belgeyi yüklemek '
                    .'zorunda kalır ve gönderim yapamazlar.')
                ->native(false)
                ->visible(fn ($get) => (bool) $get('zorunlu')),

            Toggle::make('hassas')
                ->label('Hassas belge')
                ->helperText('Açıksa dosya at-rest ŞİFRELİ saklanır ve her görüntüleme '
                    .'denetim kaydına yazılır (kimlik belgesi gibi).'),

            TextInput::make('imha_gun')
                ->label('Saklama süresi (gün)')
                ->helperText('Dolduğunda dosya imha edilir, KAYIT kalır. Boş = süresiz saklanır.')
                ->numeric()
                ->minValue(1)
                ->maxValue(3650),

            TextInput::make('sira')
                ->label('Sıra')
                ->helperText('Formda küçükten büyüğe dizilir.')
                ->numeric()
                ->required()
                ->default(100),

            Toggle::make('aktif')
                ->label('Etkin')
                ->helperText('Kapatılırsa yeni başvurularda istenmez. '
                    .'Kayıt SİLİNMEZ: eski evraklar bu türün adına bakıyor.')
                ->default(true),
        ];
    }

    /**
     * "Bunu zorunlu yaparsam ne olur?" sorusunun cevabı, tıklamadan ÖNCE.
     * Doküman bunu bir uyarı kipi olarak istiyor (M7.3).
     */
    private static function zorunlulukUyarisi(?EvrakTuru $record): string
    {
        $temel = 'Bu belge yüklenmeden başvuru gönderilemez.';

        $turler = $record->basvuru_turleri ?? [];

        if ($turler === []) {
            return $temel;
        }

        $kuyrukta = Basvuru::query()
            ->whereIn('tur', $turler)
            ->whereIn('durum', BasvuruDurumu::degerleri(...BasvuruDurumu::kuyruk()))
            ->count();

        return $kuyrukta === 0
            ? $temel
            : $temel." ⚠️ Kuyrukta {$kuyrukta} başvuru var; zorunluluk başlangıcı "
                .'belirtilmezse bu belgeyi yükleyemedikleri için yeniden gönderim yapamazlar.';
    }
}
