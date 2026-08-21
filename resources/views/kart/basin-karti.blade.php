{{--
  ARCA ÇORUM FK — BASIN KARTI  ·  Plan v1.0 md.6

  Ölçü: 90 × 130 mm dikey rozet.
    · Telefonda tam ekran okunur (kapıda kart genelde ekrandan gösterilecek)
    · Yaka kartı ölçüsüne yakın, A4'e bolca sığar
    · QR ~32 mm: 56 karakterlik yükü zayıf ışıkta bile okutur

  ⚠️ Bu şablon hem EKRAN önizlemesi hem PDF için kullanılır — tek kaynak.
  Ölçüler mm; başsız Chrome sayfa boyutunu tam bu ölçüde alır.
  Dış kaynak YOK: fotoğraf ve QR data URI olarak gömülür (KVKK + CSP).
--}}
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title>{{ $akreditasyon->kart_no }}</title>
<style>
    /* Yazı tipi KARTA GÖMÜLÜ (Inter — kulübün kariyer sitesiyle aynı aile).
       Müşterinin sunucusunda hangi fontlar kurulu olursa olsun kart birebir
       aynı çıksın; çalışma anında dışarıya İSTEK GİTMEZ.

       ⚠️ İki parça: Türkçe'nin ğ/Ş/İ harfleri "latin-ext" alt kümesinde,
       ç/ö/ü ise "latin"de. unicode-range OLMADAN yalnızca sonuncusu geçerli
       olur ve harflerin bir kısmı başka fontla dolar — karışık tipografi. */
    @font-face {
        font-family: 'KartSans'; font-style: normal; font-weight: 400;
        src: url({{ $font['400-latin'] }}) format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+2000-206F, U+20AC, U+2122;
    }
    @font-face {
        font-family: 'KartSans'; font-style: normal; font-weight: 400;
        src: url({{ $font['400-ext'] }}) format('woff2');
        unicode-range: U+0100-017F, U+0218-021B, U+2113, U+20A0-20BF;
    }
    @font-face {
        font-family: 'KartSans'; font-style: normal; font-weight: 600;
        src: url({{ $font['600-latin'] }}) format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+2000-206F, U+20AC, U+2122;
    }
    @font-face {
        font-family: 'KartSans'; font-style: normal; font-weight: 600;
        src: url({{ $font['600-ext'] }}) format('woff2');
        unicode-range: U+0100-017F, U+0218-021B, U+2113, U+20A0-20BF;
    }

    @page { size: {{ $en }}mm {{ $boy }}mm; margin: 0; }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    html, body {
        width: {{ $en }}mm; height: {{ $boy }}mm;
        font-family: 'KartSans', 'DejaVu Sans', 'Liberation Sans', system-ui, sans-serif;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
        color: #16181D;
    }

    .kart {
        position: relative;
        width: {{ $en }}mm; height: {{ $boy }}mm;
        background: #fff;
        display: flex; flex-direction: column;
        overflow: hidden;
    }

    /* ── Üst bant: armanın ve kulüp adının olduğu siyah alan ── */
    .ust {
        background: #16181D; color: #fff;
        padding: 4.5mm 5mm 4mm;
        display: flex; align-items: center; gap: 3mm;
        /* Kırmızı ince çizgi bandı kartın alt kenarına yapıştırır */
        border-bottom: 1.2mm solid #C11119;
    }
    .arma { width: 13mm; height: 13mm; flex: none; }
    .ust .ad { font-size: 3.5mm; font-weight: 600; letter-spacing: .02em; line-height: 1.15; }
    .ust .tur {
        font-size: 2.5mm; letter-spacing: .16em; text-transform: uppercase;
        color: #ffb3ac; margin-top: .8mm;
    }

    /* ── Gövde: fotoğraf + kimlik bilgileri ── */
    .govde { padding: 5mm 5mm 0; display: flex; gap: 4.5mm; }

    .foto {
        width: 28mm; height: 35mm; flex: none;
        object-fit: cover; background: #f1f2f4;
        border: .4mm solid #16181D; border-radius: 1mm;
    }
    .foto-yok {
        width: 28mm; height: 35mm; flex: none;
        background: #f1f2f4; border: .4mm dashed #b9bec6; border-radius: 1mm;
        display: flex; align-items: center; justify-content: center;
        font-size: 2.4mm; color: #8b929c; text-align: center; padding: 2mm;
    }

    .kimlik { flex: 1; min-width: 0; padding-top: .5mm; }
    .kimlik .isim {
        font-size: 5.2mm; font-weight: 600; line-height: 1.1;
        word-break: break-word; hyphens: auto;
    }
    .kimlik .isim.uzun { font-size: 4.2mm; }
    .etiket {
        font-size: 2.3mm; letter-spacing: .14em; text-transform: uppercase;
        color: #8b929c; margin-top: 3mm;
    }
    .deger { font-size: 3.1mm; line-height: 1.3; margin-top: .6mm; word-break: break-word; }

    /* ── Bölge yetkileri: kapıda görevlinin ilk baktığı yer ── */
    .bolge-baslik {
        padding: 4.5mm 5mm 1.5mm;
        font-size: 2.3mm; letter-spacing: .14em; text-transform: uppercase; color: #8b929c;
    }
    .bolgeler { padding: 0 5mm; display: flex; flex-wrap: wrap; gap: 1.4mm; }
    .bolge {
        font-size: 2.5mm; font-weight: 600; letter-spacing: .02em;
        padding: 1mm 2mm; border-radius: 1mm;
        background: #ffe9e7; color: #920011; border: .25mm solid #f3c4bf;
    }
    .bolge-yok { font-size: 2.5mm; color: #8b929c; }

    /* ── QR ── */
    .qr-alan {
        margin-top: auto; padding: 4mm 5mm 3.5mm;
        display: flex; align-items: flex-end; gap: 4mm;
        border-top: .25mm solid #e5e7ea;
    }
    .qr { width: 34mm; height: 34mm; flex: none; }
    .qr svg { width: 100%; height: 100%; display: block; }
    .qr-yazi { flex: 1; min-width: 0; padding-bottom: 1mm; }
    .kart-no {
        /* Gömülü fontta kalıyoruz: tek tırnaklı bir mono aile eklemek kartı
           yine sisteme bağımlı yapardı. Sabit genişlikli rakam + geniş harf
           aralığı, numarayı okunur ve hizalı tutuyor. */
        font-variant-numeric: tabular-nums;
        font-size: 4.6mm; font-weight: 600; letter-spacing: .06em; color: #C11119;
    }
    .kart-no-etiket {
        font-size: 2.3mm; letter-spacing: .14em; text-transform: uppercase; color: #8b929c;
    }
    .sezon { font-size: 2.7mm; color: #4a5058; margin-top: 1.6mm; }

    /* ── Alt bant: doğrulama uyarısı ── */
    .alt {
        background: #C11119; color: #fff;
        padding: 2.6mm 5mm;
        font-size: 2.35mm; line-height: 1.35;
    }
    .alt strong { font-weight: 600; }
</style>
</head>
<body>
<div class="kart">

    <div class="ust">
        <img class="arma" src="{{ $armaVeri }}" alt="">
        <div>
            <div class="ad">ARCA ÇORUM FK</div>
            <div class="tur">{{ $turEtiketi }}</div>
        </div>
    </div>

    <div class="govde">
        @if ($fotoVeri)
            <img class="foto" src="{{ $fotoVeri }}" alt="">
        @else
            <div class="foto-yok">Fotoğraf<br>yüklenmedi</div>
        @endif

        <div class="kimlik">
            <div class="isim {{ mb_strlen($isim) > 20 ? 'uzun' : '' }}">{{ $isim }}</div>

            <div class="etiket">{{ $kurum ? 'Kurum' : 'Statü' }}</div>
            <div class="deger">{{ $kurum ?? $turEtiketi }}</div>
        </div>
    </div>

    <div class="bolge-baslik">Bölge yetkisi</div>
    <div class="bolgeler">
        @forelse ($bolgeler as $bolge)
            <span class="bolge">{{ $bolge }}</span>
        @empty
            <span class="bolge-yok">Bölge yetkisi kapıda sorgulanır</span>
        @endforelse
    </div>

    <div class="qr-alan">
        <div class="qr">{!! $qrSvg !!}</div>
        <div class="qr-yazi">
            <div class="kart-no-etiket">Kart no</div>
            <div class="kart-no">{{ $akreditasyon->kart_no }}</div>
            @if ($sezon)
                <div class="sezon">Sezon {{ $sezon }}</div>
            @endif
        </div>
    </div>

    <div class="alt">
        <strong>Bu kart tek başına yetki vermez.</strong>
        Geçiş hakkı her okutmada sunucudan doğrulanır; iptal edilen kart anında geçersizdir.
    </div>

</div>
</body>
</html>
