{{-- Tek dosya önizleme bileşeni -- Saha notları S2.

     🪤 Yerine geçtiği mantık İKİ DALLIYDI: görselse <img>, DEĞİLSE <iframe>.
     `@else` kör bir varsayımdı -- bugün yalnız PDF geldiği için çalışıyordu.
     `mime_izin` listesine docx eklendiği gün iframe boş bir kutu gösterir,
     hata da vermezdi. Burada her tür AÇIKÇA karşılanır; tanımadığımız tür
     sessizce boş alan değil, ne olduğunu söyleyen bir kart olur.

     🎨 Satır içi stil KASITLI: panelde uygulamanın Tailwind sınıfları
     çalışmıyor (panel teması henüz kurulmadı) ve bu bileşen hem panelde hem
     kamu tarafındaki düzeltme ekranında kullanılıyor. Tek görsel dil ancak
     böyle korunuyor.

     🔒 Dış kaynak YOK: CDN'den PDF.js gibi bir görüntüleyici eklenmez --
     CSP `default-src 'self'` ve KVKK gerekçesi kodda zaten yazılı. --}}
@props([
    'kaynak',                 // dosyanın adresi (aynı köken)
    'mime' => null,
    'ad' => null,
    'boyut' => null,          // bayt
    'indir' => null,          // ayrı indirme adresi; yoksa kaynak kullanılır
    'yukseklik' => 'min(78vh, 900px)',
    'imha' => false,          // dosya imha edildi mi? (Evrak::imhaEdildiMi())
    'imhaTarihi' => null,     // imha anı; eski kayıtlarda bilinmiyor olabilir
])

@php
    $mime = (string) $mime;
    $indirAdresi = $indir ?: $kaynak;
    $boyutMetni = \App\Support\DosyaBoyutu::metin($boyut !== null ? (int) $boyut : null);

    $tur = match (true) {
        str_starts_with($mime, 'image/') => 'gorsel',
        $mime === 'application/pdf' => 'pdf',
        str_starts_with($mime, 'video/') => 'video',
        in_array($mime, ['text/plain', 'text/csv'], true) => 'metin',
        default => 'bilinmeyen',
    };

    // "application/vnd...sheet" -> "XLSX" gibi kısa bir rozet.
    $uzanti = strtoupper(pathinfo((string) $ad, PATHINFO_EXTENSION));
    $rozet = $uzanti !== '' ? $uzanti : (str_contains($mime, '/') ? strtoupper(explode('/', $mime)[1]) : 'DOSYA');
    $rozet = mb_strimwidth($rozet, 0, 8, '');
@endphp

@if ($imha)
    {{-- 🔑 İMHA EDİLMİŞ EVRAK (Tutarsızlık incelemesi M2.2).
         Buradan aşağısı hiç çizilmez: ne <img>, ne <iframe>, ne "İndir"
         bağlantısı. Hepsi `evrak.goster`'e gider ve orada 410 alır --
         yetkiliye kırık bir kutu ya da inen boş dosya göstermenin anlamı yok.
         Kayıt DURUYOR: hangi belgenin yüklendiği ve ne zaman imha edildiği
         hâlâ okunabilir olmalı. --}}
    <div {{ $attributes->merge(['style' => 'min-width:0;']) }}>
        <div style="border:1px dashed rgba(127,127,127,.45); border-radius:.5rem;
                    padding:1.25rem; text-align:center;">
            <span style="display:inline-block; font-size:.7rem; letter-spacing:.06em; font-weight:600;
                         padding:.15rem .5rem; border-radius:.25rem;
                         background:rgba(127,127,127,.15);">İMHA EDİLDİ</span>
            <p style="margin:.6rem 0 .15rem; font-weight:500; overflow-wrap:anywhere;">{{ $ad ?? 'Dosya' }}</p>
            {{-- 🪤 DİREKTİFİ KELİMEYE BİTİŞTİRME: `edildi@if (...)` yazınca
                 Blade `@if`i DERLEMEZ (yönerge deseni `\B@` ister, harften
                 sonra gelen `@` sınırı bozar) ama eşleşen `@endif` derlenir ve
                 BİR ÜSTTEKİ bloğu kapatır. Sonuç sayfanın kendisinde değil,
                 derlenmiş dosyada bir ParseError olur. Metin bu yüzden
                 önceden kuruluyor. --}}
            @php
                $imhaMetni = 'Saklama süresi doldu, dosya imha edildi'
                    .($imhaTarihi ? ' ('.$imhaTarihi->timezone('Europe/Istanbul')->format('d.m.Y').')' : '')
                    .'.';
            @endphp
            <p style="margin:.6rem 0 0; font-size:.82rem; opacity:.75;">{{ $imhaMetni }}</p>
            <p style="margin:.35rem 0 0; font-size:.75rem; opacity:.55;">
                Başvuru kaydı ve karar geçmişi duruyor; yalnızca dosya silindi (KVKK).
            </p>
        </div>
    </div>
@else
<div {{ $attributes->merge(['style' => 'min-width:0;']) }}
     x-data="{ tamEkran: false, metin: null, metinHata: false, aci: 0, olcek: 1 }">

    {{-- ── Araç şeridi: her türde aynı yerde ───────────────────── --}}
    <div style="display:flex; gap:.75rem; align-items:center; justify-content:flex-end;
                flex-wrap:wrap; margin-bottom:.5rem; font-size:.8rem;">
        @if ($ad)
            <span title="{{ $ad }}"
                  style="margin-right:auto; min-width:0; overflow:hidden; text-overflow:ellipsis;
                         white-space:nowrap; opacity:.75;">{{ $ad }}</span>
        @endif

        @if ($tur === 'gorsel')
            {{-- 🔑 DÖNDÜR ve YAKINLAŞTIR (M6.3 md.5). Kimlik belgeleri
                 telefonla, çoğu zaman YAN çekiliyor; yetkili başını çevirerek
                 okumak zorunda kalıyordu. Tam ekran vardı ama döndürme yoktu.
                 Salt görüntüleme: dosya diskte olduğu gibi kalır. --}}
            <button type="button" @click="aci = (aci + 90) % 360" title="90° döndür"
                    style="border:0; background:none; padding:0; cursor:pointer; text-decoration:underline;">
                Döndür
            </button>
            <button type="button" @click="olcek = Math.min(olcek + 0.25, 4)" title="Yakınlaştır"
                    style="border:0; background:none; padding:0; cursor:pointer; text-decoration:underline;">+</button>
            <button type="button" @click="olcek = Math.max(olcek - 0.25, 0.5)" title="Uzaklaştır"
                    style="border:0; background:none; padding:0; cursor:pointer; text-decoration:underline;">−</button>
            <button type="button" x-show="aci !== 0 || olcek !== 1" x-cloak
                    @click="aci = 0; olcek = 1" title="Görünümü sıfırla"
                    style="border:0; background:none; padding:0; cursor:pointer; text-decoration:underline; opacity:.7;">
                Sıfırla
            </button>
        @endif

        <span style="opacity:.6;">{{ $boyutMetni }}</span>
        <a href="{{ $kaynak }}" target="_blank" rel="noopener noreferrer">Yeni sekmede aç</a>
        <a href="{{ $indirAdresi }}" download>İndir</a>
    </div>

    @if ($tur === 'gorsel')
        {{-- 🪤 Döndürme kapsayıcının yüksekliğini DEĞİŞTİRMEZ (transform akışı
             etkilemez); 90°'de görsel taşmasın diye kapsayıcı overflow'lu. --}}
        <div style="overflow:auto; border-radius:.5rem;">
            <img src="{{ $kaynak }}" alt="{{ $ad ?? 'Önizleme' }}" @click="tamEkran = true"
                 :style="`transform: rotate(${aci}deg) scale(${olcek}); transition: transform .15s;`"
                 style="max-width:100%; height:auto; border-radius:.5rem; display:block;
                        margin-inline:auto; cursor:zoom-in;">
        </div>

        {{-- Tam ekran: büyütmek için yeni sekmeye gitmek gerekmesin.
             Döndürme burada da geçerli -- yan çekilmiş kimliği tam ekranda
             düzeltmek en sık yapılan iş. --}}
        <div x-show="tamEkran" x-cloak @click="tamEkran = false" @keydown.escape.window="tamEkran = false"
             style="position:fixed; inset:0; z-index:60; background:rgba(0,0,0,.85);
                    display:flex; align-items:center; justify-content:center; padding:2rem; cursor:zoom-out;">
            <img src="{{ $kaynak }}" alt="{{ $ad ?? 'Önizleme' }}"
                 :style="`transform: rotate(${aci}deg);`"
                 style="max-width:100%; max-height:100%; object-fit:contain;">
        </div>

    @elseif ($tur === 'pdf')
        {{-- Aynı köken; CSP 'self' izin veriyor. --}}
        <iframe src="{{ $kaynak }}" title="{{ $ad ?? 'PDF önizleme' }}"
                style="width:100%; height:{{ $yukseklik }}; border:0; border-radius:.5rem; background:#fff;"></iframe>

    @elseif ($tur === 'video')
        {{-- preload=metadata: liste açılırken tüm videoyu indirmesin. --}}
        <video src="{{ $kaynak }}" controls preload="metadata"
               style="width:100%; max-height:{{ $yukseklik }}; border-radius:.5rem; background:#000;">
            Tarayıcınız video oynatmayı desteklemiyor.
        </video>

    @elseif ($tur === 'metin')
        <div x-init="fetch('{{ $kaynak }}')
                        .then(c => c.ok ? c.text() : Promise.reject())
                        .then(t => metin = t.split('\n').slice(0, 200).join('\n'))
                        .catch(() => metinHata = true)">
            <template x-if="metin === null && ! metinHata">
                <p style="font-size:.85rem; opacity:.6;">Yükleniyor…</p>
            </template>
            <template x-if="metinHata">
                <p style="font-size:.85rem; opacity:.6;">Önizleme açılamadı. Dosyayı indirerek görüntüleyebilirsiniz.</p>
            </template>
            <pre x-show="metin !== null" x-text="metin" x-cloak
                 style="max-height:{{ $yukseklik }}; overflow:auto; font-size:.8rem; line-height:1.5;
                        padding:.75rem; border-radius:.5rem; background:rgba(127,127,127,.08);
                        white-space:pre; margin:0;"></pre>
            <p x-show="metin !== null" x-cloak style="font-size:.75rem; opacity:.55; margin-top:.4rem;">
                İlk 200 satır gösteriliyor.
            </p>
        </div>

    @else
        {{-- 🔑 Tanımadığımız tür SESSİZCE boş alan göstermez: ne olduğunu ve
             ne yapılabileceğini söyler. --}}
        <div style="border:1px dashed rgba(127,127,127,.45); border-radius:.5rem;
                    padding:1.25rem; text-align:center;">
            <span style="display:inline-block; font-size:.7rem; letter-spacing:.06em; font-weight:600;
                         padding:.15rem .5rem; border-radius:.25rem;
                         background:rgba(127,127,127,.15);">{{ $rozet }}</span>
            <p style="margin:.6rem 0 .15rem; font-weight:500; overflow-wrap:anywhere;">{{ $ad ?? 'Dosya' }}</p>
            <p style="margin:0; font-size:.8rem; opacity:.6;">{{ $boyutMetni }}</p>
            <p style="margin:.6rem 0 0; font-size:.8rem; opacity:.75;">
                Bu tür tarayıcıda açılamıyor.
            </p>
        </div>
    @endif
</div>
@endif
