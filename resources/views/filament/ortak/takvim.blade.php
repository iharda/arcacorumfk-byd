{{-- Basına açık antrenman takvimi.

     🔑 Yaklaşanlar AYA göre gruplanır ve bugün işaretlenir: elli kayıt düz bir
     liste olarak "bu hafta ne var" sorusunu cevaplamıyordu. --}}
<x-filament-panels::page>

    {{-- İki özet karo: sayfaya girer girmez cevaplanan iki soru. --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <x-filament::section>
            <div class="text-xs text-gray-400 dark:text-gray-500">Önümüzdeki 7 gün</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                {{ $this->buHaftaAcik }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">basına açık seans</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs text-gray-400 dark:text-gray-500">Sıradaki açık seans</div>
            @if ($this->siradaki)
                <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $this->siradaki->baslangic_at->timezone('Europe/Istanbul')->translatedFormat('j F, H:i') }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $this->siradaki->baslik ?: 'Antrenman' }}@if ($this->siradaki->yer) · {{ $this->siradaki->yer }} @endif
                </div>
            @else
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Planlanmış açık seans yok</div>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Yaklaşan antrenmanlar</x-slot>

        @if ($this->yaklasanlar->isEmpty())
            <x-filament::empty-state
                icon="heroicon-o-calendar-days"
                heading="Takvimde yaklaşan antrenman yok"
                description="Kulüp basına açık bir antrenman yayınladığında burada görünür." />
        @else
            @foreach ($this->aylaraGore as $ay => $seanslar)
                <div class="mt-6 first:mt-0">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {{ $ay }}
                    </h3>

                    <div class="flex flex-col gap-2">
                        @foreach ($seanslar as $a)
                            @php($bugun = $a->baslangic_at->timezone('Europe/Istanbul')->isToday())
                            <div @class([
                                    'flex flex-wrap items-start gap-4 rounded-lg border p-icerik-satir',
                                    'border-gray-200 dark:border-white/10' => ! $bugun,
                                    // ♿ Bugün hem çerçeve rengiyle hem "Bugün" rozetiyle işaretli.
                                    'border-primary-600 bg-primary-50/40 dark:bg-primary-500/5' => $bugun,
                                ])>
                                {{-- Tarih bloğu: listede gözle taranabilsin --}}
                                <div class="flex-none text-center" style="min-width:3.4rem">
                                    <div class="text-xl font-bold leading-none tabular-nums text-gray-950 dark:text-white">
                                        {{ $a->baslangic_at->timezone('Europe/Istanbul')->format('d') }}
                                    </div>
                                    <div class="text-[.7rem] uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                        {{ $a->baslangic_at->timezone('Europe/Istanbul')->translatedFormat('D') }}
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $a->baslik ?: 'Antrenman' }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $a->baslangic_at->timezone('Europe/Istanbul')->format('H:i') }}
                                        @if ($a->bitis_at) – {{ $a->bitis_at->timezone('Europe/Istanbul')->format('H:i') }} @endif
                                        @if ($a->yer) · {{ $a->yer }} @endif
                                    </div>
                                    @if (filled($a->not))
                                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $a->not }}</div>
                                    @endif
                                </div>

                                <div class="flex flex-none items-center gap-2">
                                    @if ($bugun)
                                        <x-filament::badge color="primary" size="xs">Bugün</x-filament::badge>
                                    @endif
                                    <x-filament::badge :color="$a->basina_acik ? 'success' : 'gray'">
                                        {{ $a->basina_acik ? 'Basına açık' : 'Basına kapalı' }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </x-filament::section>

    @if ($this->gecmis->isNotEmpty())
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Geçmiş antrenmanlar</x-slot>

            <div class="flex flex-col gap-1">
                @foreach ($this->gecmis as $a)
                    <div class="flex gap-3 text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex-none tabular-nums" style="min-width:8.5rem">
                            {{ $a->baslangic_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                        </span>
                        <span>{{ $a->baslik ?: 'Antrenman' }}@if ($a->yer) · {{ $a->yer }} @endif</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
