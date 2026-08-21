<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#16181D">
<title>Kapı — ARCA Çorum FK</title>
<link rel="manifest" href="{{ route('kapi.manifest') }}">
<link rel="icon" href="{{ asset('marka/favicon-64.png') }}">
<link rel="apple-touch-icon" href="{{ asset('marka/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<style>
    /* Kapı ekranı kendi CSS'ini taşır: gürültülü, hızlı, tek amaçlı bir yüzey.
       Panelin ya da kamu yüzünün stil paketiyle ortak yanı yok. */
    :root {
        --kirmizi: #C11119; --koyu: #16181D; --yesil: #12833C;
        --gri: #8b929c; --acik: #f4f5f7;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

    /* 🪤 ŞART: aşağıda .tarama ve .sonuc'a display:flex veriyoruz; yazar kuralı
       tarayıcının `hidden` için koyduğu display:none'ı EZER ve gizlenmesi
       gereken ekranlar aynı anda görünür. */
    [hidden] { display: none !important; }
    html, body { height: 100%; }
    body {
        background: var(--koyu); color: #fff;
        font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        display: flex; flex-direction: column;
        padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
    }

    header {
        display: flex; align-items: center; gap: .65rem;
        padding: .7rem 1rem; border-bottom: 3px solid var(--kirmizi);
        flex: none;
    }
    header img { width: 34px; height: 34px; }
    header .ad { font-size: .95rem; font-weight: 700; line-height: 1.1; }
    header .kapi { font-size: .72rem; color: #ffb3ac; }
    header .sag { margin-left: auto; display: flex; gap: .35rem; }

    .yon {
        border: 1px solid #3a4049; background: transparent; color: #c7ccd3;
        border-radius: .5rem; padding: .4rem .7rem; font-size: .8rem; font-weight: 600;
        cursor: pointer;
    }
    .yon.etkin { background: #fff; color: var(--koyu); border-color: #fff; }

    main { flex: 1; display: flex; flex-direction: column; min-height: 0; }

    /* ── Kurulum ── */
    .kurulum { padding: 2rem 1.25rem; max-width: 26rem; margin: 0 auto; width: 100%; }
    .kurulum h1 { font-size: 1.25rem; margin-bottom: .4rem; }
    .kurulum p { font-size: .87rem; color: #aeb4bc; line-height: 1.5; margin-bottom: 1.25rem; }
    .kurulum label { display: block; font-size: .8rem; color: #aeb4bc; margin-bottom: .35rem; }
    .kurulum input {
        width: 100%; padding: .8rem .9rem; border-radius: .6rem;
        border: 1px solid #3a4049; background: #1e222a; color: #fff;
        font-size: 1rem; font-family: ui-monospace, monospace;
    }
    .kurulum input:focus { outline: none; border-color: var(--kirmizi); }
    .btn {
        width: 100%; margin-top: 1rem; padding: .9rem; border: 0; border-radius: .6rem;
        background: var(--kirmizi); color: #fff; font-size: 1rem; font-weight: 700; cursor: pointer;
    }
    .btn--sade { background: #2a2f38; }
    .hata { margin-top: .75rem; font-size: .85rem; color: #ff9b93; }

    /* ── Tarama ── */
    .tarama { flex: 1; position: relative; display: flex; flex-direction: column; min-height: 0; }
    .tarama video { flex: 1; width: 100%; object-fit: cover; background: #000; min-height: 0; }
    .hedef {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        pointer-events: none;
    }
    .hedef span {
        width: min(62vw, 260px); aspect-ratio: 1;
        border: 3px solid rgb(255 255 255 / .85); border-radius: 1.25rem;
        box-shadow: 0 0 0 100vmax rgb(0 0 0 / .35);
    }
    .okuma-durum {
        flex: none; text-align: center; padding: .9rem 1rem 1.1rem;
        font-size: .95rem; font-weight: 600; background: var(--koyu);
    }

    /* ── Sonuç ── */
    /* Bölüm de esnemeli: yoksa sonuç ekranı yarım kalır, düğmeler ortada asılı
       durur ve altta ölü siyah bir alan oluşur. */
    section[data-ekran="sonuc"] { flex: 1; display: flex; }
    .sonuc { flex: 1; display: flex; flex-direction: column; }
    .sonuc--izinli .bant { background: var(--yesil); }
    .sonuc--ret .bant { background: var(--kirmizi); }
    .bant { padding: 1.1rem 1.25rem; }
    .bant .etiket { font-size: 1.9rem; font-weight: 800; line-height: 1.05; letter-spacing: -.01em; }
    .bant .mesaj { font-size: .95rem; margin-top: .3rem; opacity: .95; }
    .bant .zaman { font-size: .8rem; margin-top: .35rem; opacity: .8; }

    .kisi { flex: 1; display: flex; gap: 1rem; padding: 1.1rem 1.25rem; align-items: flex-start; min-height: 0; }
    .kisi .isim { hyphens: auto; }
    .kisi img, .foto-yok {
        width: 36vw; max-width: 180px; aspect-ratio: 3/4; flex: none;
        object-fit: cover; border-radius: .7rem; background: #2a2f38;
    }
    .foto-yok { display: flex; align-items: center; justify-content: center; font-size: .8rem; color: var(--gri); text-align: center; padding: .5rem; }
    .kisi .bilgi { min-width: 0; }
    .kisi .isim { font-size: 1.45rem; font-weight: 700; line-height: 1.15; word-break: break-word; }
    .kisi .kurum { font-size: .95rem; color: #c7ccd3; margin-top: .35rem; }
    .kisi .kartno { font-size: .9rem; color: var(--gri); margin-top: .6rem; font-family: ui-monospace, monospace; }

    .alt-cubuk { flex: none; padding: 1rem 1.25rem calc(1rem + env(safe-area-inset-bottom)); display: flex; gap: .6rem; }
    .alt-cubuk .btn { margin-top: 0; }
</style>
</head>
<body>

<header>
    <img src="{{ asset('marka/kulup-logo.webp') }}" alt="">
    <div>
        <div class="ad">Kapı Doğrulama</div>
        <div class="kapi" id="kapi-adi">ARCA Çorum FK</div>
    </div>
    <div class="sag">
        <button class="yon" data-yon="giris" type="button">Giriş</button>
        <button class="yon" data-yon="cikis" type="button">Çıkış</button>
    </div>
</header>

<main>
    {{-- Kurulum: cihaz anahtarı --}}
    <section data-ekran="kurulum" class="kurulum" hidden>
        <h1>Cihazı tanıt</h1>
        <p>
            Bu kapı için verilen anahtarı girin. Anahtar yalnızca bu cihazda saklanır
            ve panelden istenildiği an iptal edilebilir.
        </p>
        <form id="kurulum-form">
            <label for="anahtar">Kapı anahtarı</label>
            <input id="anahtar" name="anahtar" autocomplete="off" autocapitalize="off"
                   spellcheck="false" inputmode="text" required>
            <button class="btn" type="submit">Bağlan</button>
            <p class="hata" id="kurulum-hata"></p>
        </form>
    </section>

    {{-- Tarama --}}
    <section data-ekran="tarama" class="tarama" hidden>
        <video id="kamera" playsinline muted></video>
        <div class="hedef"><span></span></div>
        <div class="okuma-durum" id="okuma-durum">Kartı kameraya gösterin</div>
    </section>

    {{-- Sonuç --}}
    <section data-ekran="sonuc" hidden>
        <div class="sonuc" id="sonuc">
            <div class="bant">
                <div class="etiket" id="sonuc-etiket"></div>
                <div class="mesaj" id="sonuc-mesaj"></div>
                <div class="zaman" id="sonuc-zaman"></div>
            </div>

            <div class="kisi" id="kisi" hidden>
                <img id="kisi-foto" alt="" hidden>
                <div class="foto-yok" id="foto-yok" hidden>Fotoğraf yok<br>kimlik sorun</div>
                <div class="bilgi">
                    <div class="isim" id="kisi-isim"></div>
                    <div class="kurum" id="kisi-kurum"></div>
                    <div class="kartno" id="kisi-kartno"></div>
                </div>
            </div>

            <div class="alt-cubuk">
                <button class="btn" id="devam" type="button">Sonraki kart</button>
                <button class="btn btn--sade" id="cihaz-sifirla" type="button" style="width:auto; padding-inline:1rem;">Cihazı sıfırla</button>
            </div>
        </div>
    </section>
</main>

@vite('resources/js/kapi.js')
</body>
</html>
