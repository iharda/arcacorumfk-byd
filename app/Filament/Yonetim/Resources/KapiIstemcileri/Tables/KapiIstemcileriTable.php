<?php

namespace App\Filament\Yonetim\Resources\KapiIstemcileri\Tables;

use App\Filament\Yonetim\Ortak\SiraSutunu;
use App\Filament\Yonetim\Resources\KapiIstemcileri\KapiIstemcisiResource;
use App\Filament\Yonetim\Resources\KapiIstemcileri\Schemas\KapiIstemcisiFormu;
use App\Models\Ayar;
use App\Models\KapiIstemcisi;
use App\Servisler\KapiIstemcisiAkisi;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KapiIstemcileriTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Satırın kendisi detayı açar (S1).
            ->recordUrl(fn (KapiIstemcisi $record) => KapiIstemcisiResource::getUrl('detay', ['record' => $record]))
            /*
             * ⚠️ Parametre adı $query olmalı (Filament ada göre enjekte eder).
             * Bugünkü okutma sayısı TEK sorguda geliyor; satır başına sayım
             * yapmak on kapıda on bir sorgu demekti.
             */
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'gecisKayitlari as bugun_okutma' => fn (Builder $alt) => $alt->whereBetween('okundu_at', [
                    today('Europe/Istanbul')->startOfDay(), today('Europe/Istanbul')->endOfDay(),
                ]),
            ]))
            ->defaultSort('ad')
            ->columns([
                SiraSutunu::yap(),

                TextColumn::make('ad')->label('Kapı')->searchable()->sortable(),
                TextColumn::make('kapi_kodu')->label('Kod')->badge()->color('gray'),

                TextColumn::make('bolgeler')
                    ->label('Bölgeler')
                    ->badge()
                    ->separator()
                    ->formatStateUsing(fn ($state) => ((array) Ayar::al('bolgeler', []))[$state] ?? $state)
                    ->placeholder('Kısıt yok'),

                TextColumn::make('ip_listesi')
                    ->label('IP kısıtı')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->placeholder('YOK')
                    // IP kısıtı olmayan kapı gözden kaçmasın.
                    ->color(fn ($state) => filled($state) ? 'gray' : 'warning')
                    ->wrap(),

                IconColumn::make('aktif')->label('Etkin')->boolean(),

                /*
                 * 🔑 Kapının ÇALIŞTIĞINI söyleyen en doğrudan sayı. "Son
                 * okutma: 14:32" bir cihazın saat 14:32'de yaşadığını söyler;
                 * bugün kaç kez okuttuğu, maç sürerken hâlâ ayakta olup
                 * olmadığını söyler.
                 */
                TextColumn::make('bugun_okutma')
                    ->label('Bugün')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn (int $state) => $state > 0 ? $state.' okutma' : '—'),

                TextColumn::make('son_kullanim_at')
                    ->label('Son okutma')
                    ->dateTime('d.m.Y H:i', 'Europe/Istanbul')
                    ->placeholder('Hiç okutmadı')
                    /*
                     * Hiç okutma yapmamış ETKİN kapı, kurulumu yarım kalmış
                     * cihaz demektir: panelde tanımlı, sahada anahtarı
                     * girilmemiş. Maç günü fark edilmesi geç olur.
                     */
                    ->color(fn (KapiIstemcisi $record) => $record->son_kullanim_at === null && $record->aktif
                        ? 'warning' : null)
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('yeniKapi')
                    ->label('Kapı ekle')
                    ->icon('heroicon-m-plus')
                    ->modalWidth(Width::Large)
                    ->schema(KapiIstemcisiFormu::alanlar())
                    ->action(function (array $data) {
                        $sonuc = app(KapiIstemcisiAkisi::class)->olustur($data);
                        self::anahtariGoster($sonuc['anahtar'], $sonuc['istemci']->ad);
                    }),
            ])
            ->recordActions([
                /*
                 * 💀 Düzenleme DOĞRUDAN modele yazıyordu: kapı açmak ve anahtar
                 * yenilemek denetim kaydına düşerken IP kısıtını kaldırmak iz
                 * bırakmıyordu. Kayıt artık akıştan geçiyor (eski/yeni değerler
                 * denetime yazılıyor); form yalnızca IP metnini diziye çeviriyor.
                 */
                EditAction::make()
                    ->label('Düzenle')
                    ->modalWidth(Width::Large)
                    ->schema(KapiIstemcisiFormu::alanlar())
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['ip_listesi'] = is_array($data['ip_listesi'] ?? null)
                            ? implode(', ', $data['ip_listesi'])
                            : $data['ip_listesi'];

                        return $data;
                    })
                    ->using(fn (KapiIstemcisi $record, array $data) => tap(
                        $record,
                        fn () => app(KapiIstemcisiAkisi::class)->guncelle($record, $data),
                    )),

                /*
                 * 🔻 Kapatmak TURNİKEYİ DURDURUR: düzenleme formundaki bir
                 * anahtarın arkasında değil, sonucunu yazan ayrı bir eylem
                 * olarak duruyor.
                 */
                Action::make('etkinlik')
                    ->label(fn (KapiIstemcisi $record) => $record->aktif ? 'Kapat' : 'Aç')
                    ->icon(fn (KapiIstemcisi $record) => $record->aktif
                        ? 'heroicon-m-pause-circle' : 'heroicon-m-play-circle')
                    ->color(fn (KapiIstemcisi $record) => $record->aktif ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (KapiIstemcisi $record) => $record->aktif
                        ? 'Kapıyı kapatmak istiyor musunuz?' : 'Kapıyı açmak istiyor musunuz?')
                    ->modalDescription(fn (KapiIstemcisi $record) => $record->aktif
                        ? 'Bu cihazdan yapılan okutmalar ANINDA reddedilir. Anahtar geçerli kalır; kapı yeniden açıldığında cihaz çalışmaya devam eder.'
                        : 'Cihaz bir sonraki okutmadan itibaren yeniden çalışır.')
                    ->modalSubmitActionLabel(fn (KapiIstemcisi $record) => $record->aktif ? 'Kapat' : 'Aç')
                    ->action(function (KapiIstemcisi $record) {
                        // 🪤 Hedef durum ÖNCE okunur: akış aynı model örneğini
                        // günceller, sonrasında `aktif` artık yeni değeri taşır
                        // ve mesaj ters çıkardı.
                        $hedef = ! $record->aktif;

                        app(KapiIstemcisiAkisi::class)->etkinlikDegistir($record, $hedef);

                        Notification::make()
                            ->title($record->ad.($hedef ? ' açıldı.' : ' kapatıldı.'))
                            ->success()->send();
                    }),

                Action::make('anahtarYenile')
                    ->label('Anahtarı yenile')
                    ->icon('heroicon-m-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    // Sonucu ÖNCEDEN söyle: bu cihaz yenileme biter bitmez düşer.
                    ->modalDescription('Eski anahtar ANINDA geçersiz olur; o cihaz yeni anahtar girilene kadar okutma yapamaz.')
                    ->action(function (KapiIstemcisi $record) {
                        $anahtar = app(KapiIstemcisiAkisi::class)->anahtariYenile($record);
                        self::anahtariGoster($anahtar, $record->ad);
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Tanımlı kapı yok')
            ->emptyStateDescription('Her turnike veya gişe cihazı için ayrı bir kapı tanımlayın.');
    }

    /**
     * Anahtar BİR KEZ gösterilir. Kalıcı bildirim: görevli kopyalamadan
     * ekrandan kaybolmasın.
     */
    private static function anahtariGoster(string $anahtar, string $kapi): void
    {
        Notification::make()
            ->title($kapi.' için anahtar üretildi')
            ->body('Bu anahtar YALNIZCA ŞİMDİ gösterilir, sunucuda saklanmaz. Cihaza girin:  '.$anahtar)
            ->success()
            ->persistent()
            ->send();
    }
}
