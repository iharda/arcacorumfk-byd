<?php

namespace App\Filament\Ortak;

use App\Models\Antrenman;
use BackedEnum;
use Illuminate\Support\Collection;

/** Basına açık antrenman takvimi — akredite kullanıcı görünümü. */
abstract class TakvimSayfasi extends MedyaMerkeziSayfasi
{
    protected string $view = 'filament.ortak.takvim';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Antrenman takvimi';

    protected static ?string $title = 'Antrenman takvimi';

    protected static ?int $navigationSort = 11;

    /** @return Collection<int, Antrenman> */
    public function getYaklasanlarProperty(): Collection
    {
        return Antrenman::query()->yayinda()
            ->where('baslangic_at', '>=', now()->startOfDay())
            ->orderBy('baslangic_at')
            ->limit(50)
            ->get();
    }

    /**
     * Yaklaşanlar AYA göre gruplanır: elli kayıt düz bir liste olarak
     * "bu hafta ne var" sorusunu cevaplamıyordu.
     *
     * @return Collection<string, Collection<int, Antrenman>>
     */
    public function getAylaraGoreProperty(): Collection
    {
        return $this->yaklasanlar->groupBy(
            fn (Antrenman $a) => $a->baslangic_at->timezone('Europe/Istanbul')->translatedFormat('F Y'),
        );
    }

    /** Önümüzdeki yedi gündeki basına AÇIK seans sayısı — üstteki özet karo. */
    public function getBuHaftaAcikProperty(): int
    {
        return Antrenman::query()->yayinda()
            ->where('basina_acik', true)
            ->whereBetween('baslangic_at', [now(), now()->copy()->addWeek()])
            ->count();
    }

    /**
     * Sıradaki basına açık seans; yoksa null.
     *
     * 🪤 `yaklasanlar` GÜN BAŞINDAN itibaren getiriyor -- bugün başlamış bir
     * seans da listede kalsın diye. Ama "sıradaki" henüz BAŞLAMAMIŞ olan
     * demek: filtresiz bırakınca üstteki karo "0 seans" derken bu karo
     * saati geçmiş bir seansı gösteriyordu.
     */
    public function getSiradakiProperty(): ?Antrenman
    {
        return $this->yaklasanlar
            ->firstWhere(fn (Antrenman $a) => $a->basina_acik && $a->baslangic_at->isFuture());
    }

    /** @return Collection<int, Antrenman> */
    public function getGecmisProperty(): Collection
    {
        return Antrenman::query()->yayinda()
            ->where('baslangic_at', '<', now()->startOfDay())
            ->orderByDesc('baslangic_at')
            ->limit(10)
            ->get();
    }
}
