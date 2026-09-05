{{-- Başvuru inceleme — Plan v1.0 md.8: "yan yana evrak önizlemeli inceleme".
     ⚠️ Panelde kendi Tailwind sınıflarımız derlenmez → yerleşim satır içi stille. --}}
<x-filament-panels::page>

    {{-- 🔑 YENİ YERLEŞİM (Tutarsızlık incelemesi M6.2/M6.3).

         💀 Eskiden evrak listesi SOL sütunun ALTINCI bloğuydu: yetkilinin en
         çok kullandığı liste, en çok kaydırılan yerdeydi. Müşterinin cümlesi
         teşhisin kendisiydi -- "sol en alttan seçip sağ en üstte görüntülenmesini
         sağlıyoruz, zorlu bir yöntem bu."

         Artık BELGELER ÖNCE: liste ve önizleme birlikte, sayfanın solunda ve
         sabit (sticky). Künye bilgileri sağda, kaydırılan sütunda -- onlara
         bir kez bakılır, belgelere onlarca kez.

         🪤 Yerleşim MEDYA SORGUSUYLA: panelde kendi Tailwind sınıflarımız
         derlenmiyor, Alpine ile pencere genişliğine bakmak da ilk çizimde
         gecikiyordu. Satır içi <style> hem çalışıyor hem CSP'ye uygun
         (style-src 'self' 'unsafe-inline'). --}}
    <style>
        .bys-inceleme { display:grid; gap:1.25rem; grid-template-columns:minmax(0,1fr); align-items:start; }
        @media (min-width: 1280px) {
            .bys-inceleme { grid-template-columns:minmax(0,1fr) minmax(0,22rem); }
            .bys-inceleme__belgeler { position:sticky; top:1rem; }
        }
    </style>

    <div class="bys-inceleme">

        {{-- ── SOL: EVRAKLAR + ÖNİZLEME (sabit) ────────────────── --}}
        <div class="bys-inceleme__belgeler" style="min-width:0;">
            <x-filament::section>
                <x-slot name="heading">Evraklar</x-slot>

                {{-- Liste, küçük resim, klavye gezinmesi, imha dalı ve
                     önizleme bileşenin içinde (M2.4 md.7). --}}
                <x-parcalar.evrak-listesi
                    :evraklar="$record->evraklar"
                    bos-mesaj="Henüz evrak yüklenmemiş."
                    sutunlar="minmax(0,15rem) minmax(0,1fr)"
                    yukseklik="min(72vh, 820px)" />
            </x-filament::section>
        </div>

        {{-- ── SAĞ: künye ve karar geçmişi (kaydırılır) ────────── --}}
        <div style="display:flex; flex-direction:column; gap:1.25rem; min-width:0;">

            <x-filament::section compact>
                <x-slot name="heading">Durum</x-slot>
                <div style="display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;">
                    @php $rozet = $this->getDurumRozeti(); @endphp
                    <x-filament::badge :color="$rozet['renk']" size="lg">{{ $rozet['etiket'] }}</x-filament::badge>
                    @if ($record->inceleyen)
                        {{-- 💀 M9 №8: iki yetkili aynı başvuruyu aynı anda açtığında
                             ikincisine hiçbir uyarı yoktu; karar verince ham bir
                             hata mesajı alıyordu. Kimin elinde olduğu artık
                             karar vermeden ÖNCE, göze çarpacak şekilde yazıyor. --}}
                        @if ($record->inceleyen_id === auth()->id())
                            <span style="font-size:.78rem; opacity:.65;">İnceleyen: siz</span>
                        @else
                            <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">
                                {{ $record->inceleyen->name }} inceliyor
                            </x-filament::badge>
                        @endif
                    @endif
                </div>

                {{-- Durum adlarının ne anlama geldiği tek kaynakta (BasvuruDurumu);
                     yetkili "yeniden inceleme bekliyor" ile "inceleme bekliyor"un
                     farkını ekranda okuyabilmeli. --}}
                <p style="margin-top:.45rem; font-size:.75rem; opacity:.6;">{{ $rozet['aciklama'] }}</p>

                <dl style="margin-top:.9rem; display:grid; grid-template-columns:auto 1fr; gap:.35rem .75rem; font-size:.8rem;">
                    @foreach ([
                        'Gönderim'  => $record->gonderildi_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                        'İncelemeye alındı' => $record->incelemeye_alindi_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                        'Karar'     => $record->karar_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                        {{-- "Bu başvuruyu kim onayladı" sorusunun ekrandaki cevabı.
                             Kararı veren her zaman bir kişi değil (kurum teyidi
                             reddi, iptal) -- metni model kuruyor. --}}
                        'Kararı veren' => $record->kararVereniMetni(),
                    ] as $etiket => $deger)
                        @if ($deger)
                            <dt style="opacity:.6;">{{ $etiket }}</dt>
                            <dd>{{ $deger }}</dd>
                        @endif
                    @endforeach
                </dl>

                {{-- 🔑 KURUM TEYİDİ (Tutarsızlık incelemesi M3).
                     `kurum_teyidi` kuyruk mantığının merkezinde: teyit bekleyen
                     başvuru `scopeKuyrukta()` dışında kalır, yani yetkilinin
                     listesinde HİÇ GÖRÜNMEZ. Bu alan bugüne kadar hiçbir ekranda
                     basılmıyordu; yetkili başvurunun neden görünmediğini de
                     öğrenemiyordu. "Başvuru yaptım, kayboldu" şikâyetinin en
                     olası kaynağı buydu. --}}
                @if ($record->kurum_teyidi_gerekli)
                    @php
                        [$teyitEtiket, $teyitRenk] = match ($record->kurum_teyidi) {
                            true => ['Verildi', 'success'],
                            false => ['Reddedildi', 'danger'],
                            default => ['Bekleniyor', 'warning'],
                        };
                    @endphp
                    <div style="margin-top:.9rem; padding-top:.75rem; border-top:1px solid rgba(127,127,127,.2);">
                        <div style="display:flex; flex-wrap:wrap; gap:.5rem; align-items:center;">
                            <span style="font-size:.78rem; opacity:.6;">Kurum teyidi</span>
                            <x-filament::badge :color="$teyitRenk">{{ $teyitEtiket }}</x-filament::badge>
                            @if ($record->kurum)
                                <span style="font-size:.78rem; opacity:.65;">{{ $record->kurum->resmi_unvan }}</span>
                            @endif
                        </div>

                        {{-- 🪤 Yönerge kelimeye BİTİŞİK yazılmaz: `görünmez@if (...)`
                             derlenmez (bkz. dosya-onizleme bileşenindeki not).
                             Metin önceden kuruluyor. --}}
                        @php
                            $teyitAciklamasi = $record->kurum_teyidi === null
                                ? 'Kurum yanıtlayana kadar bu başvuru inceleme kuyruğunda görünmez'
                                    .($record->gonderildi_at
                                        ? ' · '.$record->gonderildi_at->diffInDays(now()).' gündür bekliyor'
                                        : '').'.'
                                : ($record->kurum_teyidi_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? '—');
                        @endphp
                        <p style="margin-top:.35rem; font-size:.75rem; opacity:.6;">{{ $teyitAciklamasi }}</p>
                    </div>
                @endif
            </x-filament::section>

            {{-- Değerlendirme -- briefi md. A.7.3. Karar DEĞİL: kararlar üst
                 çubukta, bu bölüm sayfa içinde durur.
                 🔒 Veriyi getiren sorgu da yetkiye bakar (Inceleme sayfasındaki
                 computed property); burada yalnızca ikinci kapı. --}}
            @can('degerlendirme.yonet')
                <x-filament::section compact>
                    <x-slot name="heading">Değerlendirme</x-slot>

                    <x-parcalar.degerlendirme-serit :degerlendirme="$this->degerlendirme" baslik="" />

                    @if ($this->kurumDegerlendirmesi)
                        {{-- Başvuranın kurumu da değerlendirilmişse geçmişi
                             burada salt okunur görünür. --}}
                        <div style="margin-top:.9rem; padding-top:.75rem; border-top:1px solid rgba(127,127,127,.2);">
                            <div style="font-size:.72rem; opacity:.55;">
                                Kurum: {{ $this->kurumDegerlendirmesi->hedefAdi() }}
                            </div>
                            <div style="margin-top:.3rem;">
                                <x-parcalar.degerlendirme-serit
                                    :degerlendirme="$this->kurumDegerlendirmesi"
                                    kompakt
                                    :baslik="'Kurum değerlendirmesi (' . $this->kurumDegerlendirmesi->hedefAdi() . ')'" />
                            </div>
                        </div>
                    @endif

                    <div style="margin-top:.9rem;">{{ $this->degerlendirAction }}</div>
                </x-filament::section>
            @endcan

            @if ($record->kurum)
                <x-filament::section compact>
                    <x-slot name="heading">Kurum</x-slot>
                    <dl style="display:grid; grid-template-columns:auto 1fr; gap:.4rem .75rem; font-size:.82rem;">
                        @foreach ([
                            'Ticari unvan' => $record->kurum->resmi_unvan,
                            'Açık adres'   => trim(($record->kurum->adres ?? '') . ' · ' . ($record->kurum->ilce ?? '') . '/' . ($record->kurum->il ?? ''), ' ·/'),
                            'Telefon'      => \App\Support\Telefon::goster($record->kurum->telefon),
                            'E-posta'      => $record->kurum->eposta,
                            'Vergi'        => trim(($record->kurum->vergi_dairesi ?? '') . ' · ' . ($record->kurum->vergi_no ?? ''), ' ·'),
                            'Çalışan'      => $record->kurum->calisan_araligi?->etiket() ?? $record->kurum->calisan_sayisi,
                        ] as $etiket => $deger)
                            @if (filled($deger))
                                <dt style="opacity:.6; white-space:nowrap;">{{ $etiket }}</dt>
                                <dd style="word-break:break-word;">{{ $deger }}</dd>
                            @endif
                        @endforeach
                    </dl>

                    @if (filled($record->kurum->yayin_platformlari))
                        <div style="margin-top:.9rem;">
                            <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Web siteleri ve yayın kanalları</div>
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

            @if (filled($record->form_verisi['duzeltme_aciklamasi'] ?? null))
                <x-filament::section compact>
                    <x-slot name="heading">Başvuranın düzeltme açıklaması</x-slot>
                    <p style="font-size:.82rem; white-space:pre-line;">{{ $record->form_verisi['duzeltme_aciklamasi'] }}</p>
                </x-filament::section>
            @endif

            <x-filament::section compact>
                <x-slot name="heading">{{ $record->tur === \App\Enums\BasvuruTuru::Kurum ? 'Başvuran yetkili' : 'Başvuran' }}</x-slot>
                <dl style="display:grid; grid-template-columns:auto 1fr; gap:.4rem .75rem; font-size:.82rem;">
                    {{-- Hesap ONAY anında açılır: onaya kadar bilgiler başvurunun üstünde. --}}
                    <dt style="opacity:.6;">Ad</dt><dd>{{ $record->basvuranAdi() }}</dd>
                    <dt style="opacity:.6;">E-posta</dt><dd style="word-break:break-all;">{{ $record->basvuranEpostasi() }}</dd>
                    {{-- Saklama biçimi E.164; okunur biçim ekranda üretilir. --}}
                    @php $telefon = $record->basvuran_telefon ?? $record->kullanici?->telefon; @endphp
                    @if ($telefon)
                        <dt style="opacity:.6;">Telefon</dt><dd>{{ \App\Support\Telefon::goster($telefon) }}</dd>
                    @endif
                </dl>
            </x-filament::section>

            {{-- Bireysel başvurunun form verisi. Hesap onaya kadar açılmadığı
                 için (Revizyon md.1) bu bilgiler kullanıcı kaydında DEĞİL,
                 başvurunun `form_verisi` alanında durur. --}}
            @if ($record->tur !== \App\Enums\BasvuruTuru::Kurum)
                @php
                    $form = $record->form_verisi ?? [];
                    $evetHayir = fn (string $anahtar) => array_key_exists($anahtar, $form)
                        ? ($form[$anahtar] ? 'Evet' : 'Hayır')
                        : null;
                @endphp

                <x-filament::section compact>
                    <x-slot name="heading">Başvuru bilgileri</x-slot>
                    <dl style="display:grid; grid-template-columns:auto 1fr; gap:.4rem .75rem; font-size:.82rem;">
                        @foreach ([
                            'Açık adres' => trim(($form['adres'] ?? '') . ' · ' . ($form['ilce'] ?? '') . '/' . ($form['il'] ?? ''), ' ·/'),
                            'Basın kartı' => $evetHayir('basin_karti_var'),
                            'Basın İş Kanunu sigortası' => $evetHayir('sigorta_212_var'),
                            'Medya sektöründeki deneyim' => \App\Enums\DeneyimAraligi::goster($form['calisma_yili'] ?? null),
                        ] as $etiket => $deger)
                            @if (filled($deger))
                                <dt style="opacity:.6; white-space:nowrap;">{{ $etiket }}</dt>
                                <dd style="word-break:break-word;">{{ $deger }}</dd>
                            @endif
                        @endforeach
                    </dl>

                    @if (filled(array_filter($form['sosyal_medya'] ?? [])))
                        <div style="margin-top:.9rem;">
                            <div style="font-size:.72rem; text-transform:uppercase; letter-spacing:.04em; opacity:.55;">Yayın kanalları</div>
                            <ul style="margin-top:.35rem; display:flex; flex-wrap:wrap; gap:.4rem;">
                                @foreach (array_filter($form['sosyal_medya']) as $ag => $url)
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

            @if ($this->gecmisBasvurular->isNotEmpty())
                <x-filament::section compact>
                    <x-slot name="heading">Önceki başvuruları</x-slot>
                    <div style="display:flex; flex-direction:column; gap:.5rem;">
                        @foreach ($this->gecmisBasvurular as $gecmis)
                            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; font-size:.8rem;">
                                <x-filament::badge :color="$gecmis->durum->renk()">{{ $gecmis->durum->etiket() }}</x-filament::badge>
                                <span>{{ $gecmis->tur->etiket() }}</span>
                                <span style="opacity:.6;">
                                    {{ ($gecmis->karar_at ?? $gecmis->created_at)?->timezone('Europe/Istanbul')->format('d.m.Y') }}
                                </span>
                                @if (filled($gecmis->karar_gerekcesi))
                                    <span style="flex-basis:100%; opacity:.75;">{{ $gecmis->karar_gerekcesi }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            @if (filled($record->duzeltme_notlari))
                <x-filament::section compact>
                    <x-slot name="heading">İstenen düzeltmeler</x-slot>
                    <ul style="display:flex; flex-direction:column; gap:.4rem; font-size:.82rem;">
                        @foreach ($record->duzeltme_notlari as $alan => $aciklama)
                            <li><strong>{{ $record->duzeltmeEtiketi($alan) }}</strong> — {{ $aciklama }}</li>
                        @endforeach
                    </ul>
                </x-filament::section>
            @endif

            {{-- Düzeltme geçmişi: hangi turda ne istendi, başvuran neyi neyle
                 değiştirdi. Yetkili "önceki değer neydi" sorusunu ekrandan
                 cevaplayabilmeli (Yusuf revizyonu 25.08.2026). --}}
            @php $turlar = $record->duzeltmeler()->with('talepEden')->get(); @endphp

            @if ($turlar->isNotEmpty())
                <x-filament::section compact collapsible>
                    <x-slot name="heading">Düzeltme geçmişi ({{ $turlar->count() }})</x-slot>

                    <div style="display:flex; flex-direction:column; gap:1rem; font-size:.82rem;">
                        {{-- ⏱️ Zaman çizelgesi BAŞVURUNUN KENDİSİNDEN başlar
                             (Yusuf revizyonu md.4: "ilk bilgiler · düzeltme
                             talebi 01 · düzeltme 02"). Ayrı anlık görüntü
                             saklanmıyor; değerler turların `eski` alanından
                             geriye doğru çözülüyor. --}}
                        @php $ilk = $record->ilkDegerler(); @endphp

                        <div style="border-left:2px solid rgb(var(--gray-300)); padding-left:.75rem;">
                            <p style="font-weight:600;">
                                İlk bilgiler
                                <span style="font-weight:400; opacity:.6;">
                                    · {{ ($record->gonderildi_at ?? $record->created_at)?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                    · başvuru alındı
                                </span>
                            </p>

                            <ul style="margin-top:.35rem; display:flex; flex-direction:column; gap:.25rem; opacity:.85;">
                                @foreach ($ilk as $anahtar => $deger)
                                    @continue($record->duzeltmeDegeriGoster($anahtar, $deger) === '—')
                                    <li>
                                        <strong>{{ $record->duzeltmeEtiketi($anahtar) }}</strong>
                                        — {{ $record->duzeltmeDegeriGoster($anahtar, $deger) }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @foreach ($turlar->sortBy('sira') as $tur)
                            <div style="border-left:2px solid rgb(var(--gray-200)); padding-left:.75rem;">
                                <p style="font-weight:600;">
                                    {{ $tur->baslik() }}
                                    <span style="font-weight:400; opacity:.6;">
                                        · {{ $tur->talep_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                        @if ($tur->talepEden) · {{ $tur->talepEden->name }} @endif
                                        @if ($tur->yanitlandiMi())
                                            · yanıtlandı {{ $tur->yanit_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                        @else
                                            · <span style="color:rgb(var(--warning-600));">yanıt bekleniyor</span>
                                        @endif
                                    </span>
                                </p>

                                <ul style="margin-top:.35rem; display:flex; flex-direction:column; gap:.3rem;">
                                    @foreach ($tur->maddeler() as $madde)
                                        <li>
                                            <strong>{{ $madde['etiket'] }}</strong>
                                            @if (filled($madde['aciklama'])) — {{ $madde['aciklama'] }} @endif
                                            @if ($madde['degisti'])
                                                <span style="display:block; opacity:.7;">
                                                    <span style="text-decoration:line-through;">{{ $record->duzeltmeDegeriGoster($madde['anahtar'], $madde['eski']) }}</span>
                                                    →
                                                    <strong>{{ $record->duzeltmeDegeriGoster($madde['anahtar'], $madde['yeni']) }}</strong>
                                                </span>
                                            @elseif ($tur->yanitlandiMi())
                                                <span style="display:block; opacity:.6;">değişmedi</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>

                                @if (filled($tur->yanit_aciklama))
                                    <p style="margin-top:.35rem; opacity:.8;">
                                        Başvuranın açıklaması: {{ $tur->yanit_aciklama }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>

</x-filament-panels::page>
