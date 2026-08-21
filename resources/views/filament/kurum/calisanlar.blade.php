{{-- Kurum paneli — Çalışanlar.
     ⚠️ Panelde kendi Tailwind sınıflarımız derlenmez; yerleşim satır içi stille. --}}
<x-filament-panels::page>

    {{-- ── 1) Kurum teyidi bekleyenler ───────────────────────── --}}
    @if ($this->teyitBekleyenler->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Teyidinizi bekleyen başvurular</x-slot>
            <x-slot name="description">
                Bu kişiler kurumunuz adına başvurdu. Teyit vermeden başvuru kulüp incelemesine geçmez.
            </x-slot>

            <div style="display:flex; flex-direction:column; gap:.6rem;">
                @foreach ($this->teyitBekleyenler as $basvuru)
                    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem;
                                padding:.85rem 1rem; border:1px solid rgb(var(--warning-300));
                                background:rgb(var(--warning-50)); border-radius:.6rem;">
                        <div style="flex:1 1 16rem; min-width:12rem;">
                            <div style="font-size:.9rem; font-weight:600;">{{ $basvuru->kullanici?->name }}</div>
                            <div style="font-size:.75rem; opacity:.65;">
                                {{ $basvuru->kullanici?->email }}
                                · {{ $basvuru->gonderildi_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                            </div>
                        </div>
                        <div style="display:flex; gap:.5rem;">
                            {{ ($this->teyitAction)(['basvuru' => $basvuru->ulid]) }}
                            {{ ($this->teyitReddetAction)(['basvuru' => $basvuru->ulid]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- ── 2) Çalışanlar ─────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Çalışanlar</x-slot>
        <x-slot name="afterHeader">{{ $this->davetEtAction }}</x-slot>

        @if ($this->calisanlar->isEmpty())
            <p style="font-size:.85rem; opacity:.6;">
                Henüz çalışan kaydı yok. “Çalışan davet et” ile başlayabilirsiniz.
            </p>
        @else
            <div style="display:flex; flex-direction:column; gap:.5rem;">
                @foreach ($this->calisanlar as $kisi)
                    @php
                        $akr = $kisi->akreditasyon;
                        $son = $kisi->basvurular->first();
                        $ayrildi = $kisi->ayrildi_at !== null;
                    @endphp
                    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem;
                                padding:.85rem 1rem; border:1px solid rgb(var(--gray-200));
                                border-radius:.6rem; {{ $ayrildi ? 'opacity:.6;' : '' }}">
                        <div style="flex:1 1 16rem; min-width:12rem;">
                            <div style="font-size:.9rem; font-weight:600;">{{ $kisi->name }}</div>
                            <div style="font-size:.75rem; opacity:.65;">{{ $kisi->email }}</div>
                        </div>

                        @if ($akr)
                            <x-filament::badge :color="$akr->durum->renk()">{{ $akr->durum->etiket() }}</x-filament::badge>
                            <span style="font-size:.75rem; opacity:.7;">{{ $akr->kart_no }}</span>
                        @elseif ($son)
                            <x-filament::badge :color="$son->durum->renk()">{{ $son->durum->etiket() }}</x-filament::badge>
                        @else
                            <x-filament::badge color="gray">Başvuru yok</x-filament::badge>
                        @endif

                        @if ($ayrildi)
                            <x-filament::badge color="danger">
                                Ayrıldı · {{ $kisi->ayrildi_at->timezone('Europe/Istanbul')->format('d.m.Y') }}
                            </x-filament::badge>
                        @else
                            {{ ($this->ayrilisAction)(['kullanici' => $kisi->ulid]) }}
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- ── 3) Bekleyen davetler ──────────────────────────────── --}}
    @if ($this->davetler->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Bekleyen davetler</x-slot>
            <x-slot name="description">Kişi bağlantıyı kullanana kadar burada durur.</x-slot>

            <div style="display:flex; flex-direction:column; gap:.5rem;">
                @foreach ($this->davetler as $davet)
                    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.75rem;
                                padding:.75rem 1rem; border:1px solid rgb(var(--gray-200)); border-radius:.6rem;">
                        <div style="flex:1 1 16rem; min-width:12rem;">
                            <div style="font-size:.87rem; font-weight:600;">{{ $davet->ad_soyad }}</div>
                            <div style="font-size:.75rem; opacity:.65;">
                                {{ $davet->eposta }} · son geçerlilik
                                {{ $davet->gecerlilik_bitis->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                @if ($davet->gonderim_sayisi > 1) · {{ $davet->gonderim_sayisi }} kez gönderildi @endif
                            </div>
                        </div>
                        <div style="display:flex; gap:.5rem;">
                            {{ ($this->davetYenidenGonderAction)(['davet' => $davet->ulid]) }}
                            {{ ($this->davetIptalAction)(['davet' => $davet->ulid]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
