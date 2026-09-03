<?php

namespace App\Filament\Yonetim\Resources\Kurumlar\Tables;

use App\Enums\DegerlendirmePuani;
use App\Filament\Yonetim\Ortak\DegerlendirmeEylemi;
use App\Models\Degerlendirme;
use App\Models\Kurum;
use App\Servisler\DenetimYazici;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KurumlarTable
{
    private const DURUMLAR = [
        'beklemede' => 'Beklemede',
        'akredite' => 'Akredite',
        'iptal' => 'İptal',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            // ⚠️ Parametre adı $query olmalı (Filament ada göre enjekte eder).
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount([
                    'akreditasyonlar as aktif_kart_sayisi' => fn (Builder $query) => $query->where('durum', 'aktif'),
                ])
                // Değerlendirme sütunu satır başına sorgu açmasın.
                ->with('degerlendirme'))
            ->defaultSort('resmi_unvan')
            ->columns([
                TextColumn::make('resmi_unvan')
                    ->label('Ünvan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('il')
                    ->label('İl')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('akreditasyon_durumu')
                    ->label('Akreditasyon')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'akredite' => 'success',
                        'iptal' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => self::DURUMLAR[$state] ?? $state),

                TextColumn::make('aktif_kart_sayisi')
                    ->label('Aktif kart')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('kontenjan')
                    ->label('Kontenjan')
                    ->placeholder('Sınırsız')
                    ->toggleable(),

                /*
                 * 🔒 Yalnızca kulüp tarafı. Sütun `visible()` ile gizlenir ama
                 * asıl güvence bu tablonun YÖNETİM panelinde olması: kurum ve
                 * üye panelinde bu veriyi getiren hiçbir sorgu yok.
                 */
                TextColumn::make('degerlendirme.puan')
                    ->label('Değerlendirme')
                    ->badge()
                    ->color(fn (?DegerlendirmePuani $state) => $state?->renk() ?? 'gray')
                    ->formatStateUsing(fn (?DegerlendirmePuani $state) => $state
                        ? $state->value.' · '.$state->etiket()
                        : '—')
                    ->placeholder('—')
                    /*
                     * 🪤 Nokta yazımlı ilişki sütununda çıplak `sortable()`
                     * JOIN üretmez; `order by degerlendirmeler.puan` geçersiz
                     * SQL olur. Sıralama ilişkili ALT SORGUYLA yapılır
                     * (`degerlendirmeler.kurum_id` indeksli).
                     */
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(
                        Degerlendirme::query()
                            ->select('puan')
                            ->whereColumn('degerlendirmeler.kurum_id', 'kurumlar.id')
                            ->where('hedef_tip', Degerlendirme::HEDEF_KURUM)
                            ->limit(1),
                        $direction,
                    ))
                    ->visible(fn () => auth()->user()?->can('degerlendirme.yonet') ?? false),

                TextColumn::make('created_at')
                    ->label('Kayıt')
                    ->dateTime('d.m.Y', 'Europe/Istanbul')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('akreditasyon_durumu')
                    ->label('Akreditasyon')
                    ->options(self::DURUMLAR),

                SelectFilter::make('degerlendirme')
                    ->label('Değerlendirme')
                    ->options(DegerlendirmePuani::secenekler() + ['yok' => 'Değerlendirilmemiş'])
                    ->visible(fn () => auth()->user()?->can('degerlendirme.yonet') ?? false)
                    ->query(function (Builder $query, array $data) {
                        $deger = $data['value'] ?? null;

                        if (blank($deger)) {
                            return $query;
                        }

                        return $deger === 'yok'
                            ? $query->whereDoesntHave('degerlendirme')
                            : $query->whereHas('degerlendirme',
                                fn (Builder $q) => $q->where('puan', (int) $deger));
                    }),
            ])
            ->recordActions([
                DegerlendirmeEylemi::kurum(),

                Action::make('akreditasyonuKaldir')
                    ->label('Akreditasyonu kaldır')
                    ->icon('heroicon-m-no-symbol')
                    ->color('danger')
                    ->visible(fn (Kurum $record) => $record->akrediteMi()
                        && auth()->user()->can('akredite', $record))
                    ->schema([
                        Textarea::make('gerekce')
                            ->label('Gerekçe')
                            ->required()
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->modalDescription('Kurumun çalışanları yeni başvuru yapamaz. Mevcut kartlar bu adımla İPTAL OLMAZ; onları ayrıca askıya alın.')
                    ->action(function (Kurum $record, array $data) {
                        $eski = $record->akreditasyon_durumu;
                        $record->update(['akreditasyon_durumu' => 'iptal']);

                        app(DenetimYazici::class)->yaz('kurum.akreditasyon_kaldirildi', $record,
                            eski: ['akreditasyon_durumu' => $eski],
                            yeni: ['akreditasyon_durumu' => 'iptal'],
                            not: $data['gerekce']);

                        Notification::make()->title('Akreditasyon kaldırıldı.')->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Kayıtlı kurum yok')
            ->emptyStateDescription('Kurumlar başvuru formundan oluşur.');
    }
}
