<?php

namespace App\Filament\Yonetim\Ortak;

use App\Support\DuzeltmeAlanlari;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Talep modalının ORTAK alanları -- başvuru inceleme (karar öncesi düzeltme)
 * ve akreditasyon detayı (karar sonrası belge talebi) aynı iki listeyi
 * kullanır: "listedeki kalemler" + "listede olmayan ek talep".
 *
 * 💀 İKİ LİSTE BİRBİRİNİ KİLİTLİYORDU (İbrahim Bey, 05.09.2026). Üstteki
 * liste `minItems(1)` ve içindeki seçim `required()` idi; oysa servis
 * (`eksikEvrakIste` / `belgeTalepEt`) YALNIZCA ek talep gönderilmesine izin
 * veriyor. Yetkili başlığı ve açıklamayı ek talep bölümüne yazıp gönderdiğinde
 * "İstenen belgeler en az bir öğe içermelidir" hatası alıyor, çıkış yolunu
 * göremiyordu: dokunmadığı boş satırı silmesi gerektiğini bilemezdi.
 *
 * 🔑 Kural artık şu: satır ya TAMAMEN BOŞ (sessizce atılır) ya da TAM DOLU.
 * Zorunluluk satır içinde değil, gönderim anında ve İKİ LİSTEYE BİRDEN
 * bakılarak sorulur (`kalemHatasi`).
 */
class TalepAlanlari
{
    /**
     * Listedeki kalemler. Seçenek kümesini çağıran verir: inceleme ekranı veri
     * alanlarını da açar, belge talebi yalnızca evrak türlerini.
     *
     * @param  \Closure(): array<string, string>  $secenekler
     */
    public static function kalemler(
        \Closure $secenekler,
        string $etiket = 'İstenen belgeler',
        string $ekleEtiketi = 'Belge ekle',
    ): Repeater {
        return Repeater::make('notlar')
            ->label($etiket)
            ->helperText('Listede olmayan bir şey istiyorsanız burayı boş bırakıp aşağıdan '
                .'"Ek talep ekle" diyebilirsiniz.')
            ->addActionLabel($ekleEtiketi)
            /*
             * 🪤 `minItems(1)` + satır içi `required()` YAZILAMAZ: dokunulmamış
             * varsayılan satır, yalnızca ek talep göndermek isteyeni kilitler.
             * Boş satır atılır; yarım satır aşağıda yakalanır.
             */
            ->minItems(0)
            ->defaultItems(1)
            ->schema([
                Select::make('alan')
                    ->label('Kalem')
                    ->options($secenekler)
                    ->native(),
                TextInput::make('aciklama')
                    ->label('Açıklama')
                    ->maxLength(300),
            ])
            ->columns(2);
    }

    /** Listede OLMAYAN talep: yetkilinin kendi başlığıyla tanımladığı istek. */
    public static function ekTalep(): Repeater
    {
        return Repeater::make('ek_talepler')
            ->label('Listede olmayan ek talep')
            ->addActionLabel('Ek talep ekle')
            ->defaultItems(0)
            ->schema([
                TextInput::make('etiket')
                    ->label('Başlık')
                    ->placeholder('Örn. Yayın sözleşmesi')
                    ->required()
                    ->maxLength(120),
                Select::make('tip')
                    ->label('İstenen')
                    ->options(['dosya' => 'Dosya yüklemesi', 'metin' => 'Yazılı bilgi'])
                    ->default('dosya')
                    ->native()
                    ->required(),
                TextInput::make('aciklama')
                    ->label('Açıklama')
                    ->required()
                    ->maxLength(300)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Listedeki kalemleri servis biçimine çevirir: anahtar => açıklama.
     * Tamamen boş satırlar atılır.
     *
     * @param  array<int, array<string, mixed>>|null  $ham
     * @return array<string, string>
     */
    public static function kalemleriTopla(?array $ham): array
    {
        return collect($ham ?? [])
            ->filter(fn ($s) => filled($s['alan'] ?? null))
            ->mapWithKeys(fn ($s) => [$s['alan'] => (string) ($s['aciklama'] ?? '')])
            ->all();
    }

    /**
     * Formdan gelen ek talepleri servis biçimine çevirir.
     *
     * 🔑 Anahtar BAŞLIKTAN DEĞİL sıradan üretilir (`ek:1`): başlık serbest
     * metin, sonradan düzeltilse bile bağ kopmamalı -- md.11'de tam bu hataya
     * düşülmüştü.
     *
     * @param  array<int, array<string, mixed>>|null  $ham
     * @return array<int, array<string, string>>
     */
    public static function ekTalepleriTopla(?array $ham): array
    {
        return collect($ham ?? [])
            ->filter(fn ($e) => filled($e['etiket'] ?? null))
            ->values()
            ->map(fn ($e, $i) => [
                'anahtar' => DuzeltmeAlanlari::EK_ONEK.($i + 1),
                'etiket' => $e['etiket'],
                'tip' => $e['tip'] ?? 'dosya',
                'aciklama' => $e['aciklama'] ?? '',
            ])
            ->all();
    }

    /**
     * Gönderim engeli varsa okunur sebebi; yoksa null.
     *
     * İki şeyi birden sorar, çünkü ikisi birbirinin alternatifi:
     *   1. yarım satır -- kalem seçilmiş açıklama yok ya da tersi. Sessizce
     *      atılsaydı yetkilinin yazdığı açıklama kaybolurdu.
     *   2. iki liste de boş -- servis zaten reddeder; hatayı BURADA vermek
     *      modalı açık tutar ve doldurulan alanlar kaybolmaz.
     *
     * @param  array<int, array<string, mixed>>|null  $kalemler
     * @param  array<int, array<string, mixed>>|null  $ekTalepler
     */
    public static function kalemHatasi(?array $kalemler, ?array $ekTalepler): ?string
    {
        foreach ($kalemler ?? [] as $satir) {
            $alan = filled($satir['alan'] ?? null);
            $aciklama = filled($satir['aciklama'] ?? null);

            if ($alan !== $aciklama) {
                return $alan
                    ? 'Seçtiğiniz kalemin açıklamasını da yazın; karşı taraf ne istendiğini oradan okuyor.'
                    : 'Açıklama yazdığınız satırda kalem seçilmemiş. Kalemi seçin ya da satırı silin.';
            }
        }

        if (self::kalemleriTopla($kalemler) === [] && self::ekTalepleriTopla($ekTalepler) === []) {
            return 'En az bir kalem seçin ya da "Ek talep ekle" ile listede olmayan bir istek tanımlayın.';
        }

        return null;
    }
}
