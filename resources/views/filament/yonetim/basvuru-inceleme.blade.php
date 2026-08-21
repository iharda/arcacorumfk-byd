{{-- Başvuru inceleme — Plan v1.0 md.8: "yan yana evrak önizlemeli inceleme".
     ⚠️ Panelde kendi Tailwind sınıflarımız derlenmez → yerleşim satır içi stille. --}}
<x-filament-panels::page>

    {{-- 🪤 Yerleşim MEDYA SORGUSUYLA: panelde kendi Tailwind sınıflarımız
         derlenmiyor, Alpine ile pencere genişliğine bakmak da ilk çizimde
         gecikiyordu. Satır içi <style> hem çalışıyor hem CSP'ye uygun
         (style-src 'self' 'unsafe-inline'). --}}
    <style>
        .byd-inceleme { display:grid; gap:1.25rem; grid-template-columns:minmax(0,1fr); align-items:start; }
        @media (min-width: 1280px) {
            .byd-inceleme { grid-template-columns:minmax(0,23rem) minmax(0,1fr); }
            .byd-inceleme__onizleme { position:sticky; top:1rem; }
        }
    </style>

    <div class="byd-inceleme">

        {{-- ── SOL: başvuru verisi + evrak listesi ─────────────── --}}
        <div style="display:flex; flex-direction:column; gap:1.25rem; min-width:0;">

            <x-filament::section compact>
                <x-slot name="heading">Durum</x-slot>
                <div style="display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;">
                    @php $rozet = $this->getDurumRozeti(); @endphp
                    <x-filament::badge :color="$rozet['renk']" size="lg">{{ $rozet['etiket'] }}</x-filament::badge>
                    @if ($record->inceleyen)
                        <span style="font-size:.78rem; opacity:.65;">İnceleyen: {{ $record->inceleyen->name }}</span>
                    @endif
                </div>

                <dl style="margin-top:.9rem; display:grid; grid-template-columns:auto 1fr; gap:.35rem .75rem; font-size:.8rem;">
                    @foreach ([
                        'Gönderim'  => $record->gonderildi_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                        'İncelemeye alındı' => $record->incelemeye_alindi_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                        'Karar'     => $record->karar_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                    ] as $etiket => $deger)
                        @if ($deger)
                            <dt style="opacity:.6;">{{ $etiket }}</dt>
                            <dd>{{ $deger }}</dd>
                        @endif
                    @endforeach
                </dl>
            </x-filament::section>

            @if ($record->kurum)
                <x-filament::section compact>
                    <x-slot name="heading">Kurum</x-slot>
                    <dl style="display:grid; grid-template-columns:auto 1fr; gap:.4rem .75rem; font-size:.82rem;">
                        @foreach ([
                            'Ünvan'        => $record->kurum->resmi_unvan,
                            'Adres'        => trim(($record->kurum->adres ?? '') . ' · ' . ($record->kurum->ilce ?? '') . '/' . ($record->kurum->il ?? ''), ' ·/'),
                            'Telefon'      => $record->kurum->telefon,
                            'E-posta'      => $record->kurum->eposta,
                            'Vergi'        => trim(($record->kurum->vergi_dairesi ?? '') . ' · ' . ($record->kurum->vergi_no ?? ''), ' ·'),
                            'Çalışan'      => $record->kurum->calisan_sayisi,
                        ] as $etiket => $deger)
                            @if (filled($deger))
                                <dt style="opacity:.6; white-space:nowrap;">{{ $etiket }}</dt>
                                <dd style="word-break:break-word;">{{ $deger }}</dd>
                            @endif
                        @endforeach
                    </dl>

                    @if (filled($record->kurum->yayin_platformlari))
                        <div style="margin-top:.9rem;">
                            <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Yayın platformları</div>
                            <ul style="margin-top:.35rem; display:flex; flex-direction:column; gap:.25rem; font-size:.82rem;">
                                @foreach ($record->kurum->yayin_platformlari as $p)
                                    <li>
                                        {{ $p['ad'] ?? '' }} —
                                        <a href="{{ $p['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer"
                                           style="text-decoration:underline; word-break:break-all;">{{ $p['url'] ?? '' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (filled(array_filter($record->kurum->sosyal_medya ?? [])))
                        <div style="margin-top:.9rem;">
                            <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Sosyal medya</div>
                            <ul style="margin-top:.35rem; display:flex; flex-wrap:wrap; gap:.4rem;">
                                @foreach (array_filter($record->kurum->sosyal_medya) as $ag => $url)
                                    <li>
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">
                                            <x-filament::badge color="gray">{{ $ag }}</x-filament::badge>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </x-filament::section>
            @endif

            <x-filament::section compact>
                <x-slot name="heading">Başvuran yetkili</x-slot>
                <dl style="display:grid; grid-template-columns:auto 1fr; gap:.4rem .75rem; font-size:.82rem;">
                    <dt style="opacity:.6;">Ad</dt><dd>{{ $record->kullanici?->name }}</dd>
                    <dt style="opacity:.6;">E-posta</dt><dd style="word-break:break-all;">{{ $record->kullanici?->email }}</dd>
                    @if ($record->kullanici?->telefon)
                        <dt style="opacity:.6;">Telefon</dt><dd>{{ $record->kullanici->telefon }}</dd>
                    @endif
                </dl>
            </x-filament::section>

            <x-filament::section compact>
                <x-slot name="heading">Evraklar</x-slot>
                @if ($record->evraklar->isEmpty())
                    <p style="font-size:.82rem; opacity:.6;">Henüz evrak yüklenmemiş.</p>
                @else
                    <div style="display:flex; flex-direction:column; gap:.4rem;">
                        @foreach ($record->evraklar as $evrak)
                            @php $secili = $evrak->ulid === $seciliEvrak; @endphp
                            <button type="button" wire:click="evrakSec('{{ $evrak->ulid }}')"
                                    style="text-align:start; padding:.6rem .75rem; border-radius:.55rem; border:1px solid;
                                           border-color:{{ $secili ? 'rgb(var(--primary-600))' : 'rgb(var(--gray-200))' }};
                                           background:{{ $secili ? 'rgb(var(--primary-50))' : 'transparent' }};
                                           cursor:pointer;">
                                <span style="display:block; font-size:.83rem; font-weight:600;">{{ $evrak->turu?->ad }}</span>
                                <span style="display:block; font-size:.72rem; opacity:.6; margin-top:.1rem;">
                                    {{ $evrak->orijinal_ad }} ·
                                    {{ $evrak->boyut < 1024 ? $evrak->boyut . ' B' : number_format($evrak->boyut / 1024, 0, ',', '.') . ' KB' }}
                                    @if ($evrak->sifreli) · şifreli @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            @if (filled($record->duzeltme_notlari))
                <x-filament::section compact>
                    <x-slot name="heading">İstenen düzeltmeler</x-slot>
                    <ul style="display:flex; flex-direction:column; gap:.4rem; font-size:.82rem;">
                        @foreach ($record->duzeltme_notlari as $alan => $aciklama)
                            <li><strong>{{ $alan }}</strong> — {{ $aciklama }}</li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif
        </div>

        {{-- ── SAĞ: evrak önizleme ─────────────────────────────── --}}
        <div class="byd-inceleme__onizleme" style="min-width:0;">
            <x-filament::section>
                <x-slot name="heading">
                    {{ $this->seciliEvrakModeli?->turu?->ad ?? 'Evrak önizleme' }}
                </x-slot>
                @if ($this->seciliEvrakModeli)
                    <x-slot name="headerEnd">
                        <x-filament::link
                            :href="route('evrak.goster', $this->seciliEvrakModeli)"
                            target="_blank" rel="noopener noreferrer" size="sm">
                            Yeni sekmede aç
                        </x-filament::link>
                    </x-slot>
                @endif

                @php $e = $this->seciliEvrakModeli; @endphp
                @if (! $e)
                    <p style="font-size:.85rem; opacity:.6;">Soldan bir evrak seçin.</p>
                @elseif (str_starts_with($e->mime, 'image/'))
                    <img src="{{ route('evrak.goster', $e) }}" alt="{{ $e->turu?->ad }}"
                         style="max-width:100%; height:auto; border-radius:.5rem; display:block; margin-inline:auto;">
                @else
                    {{-- PDF: aynı köken, CSP 'self' izin veriyor --}}
                    <iframe src="{{ route('evrak.goster', $e) }}" title="{{ $e->turu?->ad }}"
                            style="width:100%; height:min(78vh, 900px); border:0; border-radius:.5rem; background:#fff;"></iframe>
                @endif
            </x-filament::section>
        </div>
    </div>

</x-filament-panels::page>
