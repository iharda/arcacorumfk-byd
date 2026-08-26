/**
 * ARCA Çorum FK — Kapı Uygulaması  ·  Plan v1.0 md.6, md.7
 *
 * Tarayıcıda çalışır, kurulum istemez. Kamerayla QR okur, sunucudan doğrular,
 * görevliye BÜYÜK fotoğraf + sonuç gösterir.
 *
 * Tasarım kararları:
 *  · Anahtar cihazda (localStorage) durur — bu kaçınılmaz. Karşılığı: anahtar
 *    kapı başına ayrı, IP kısıtlı ve panelden anında iptal edilebilir.
 *  · Çevrimdışıyken doğrulama YAPILMAZ, ekran "manuel kontrole geçin" der
 *    (md.7 v1 kararı). Sahte "izinli" göstermek en tehlikeli hata olurdu.
 *  · Okuma önce tarayıcının yerleşik BarcodeDetector'ı ile denenir; yoksa
 *    jsQR'a düşülür (paket yerelde, dış kaynak yok).
 */
import jsQR from 'jsqr';

const $ = (seç) => document.querySelector(seç);
const ANAHTAR_KEY = 'bys.kapi.anahtar';
const YON_KEY = 'bys.kapi.yon';

const durum = {
  anahtar: localStorage.getItem(ANAHTAR_KEY) || '',
  yon: localStorage.getItem(YON_KEY) || 'giris',
  okuyor: false,
  sonOkuma: '',
  sonOkumaZaman: 0,
  akis: null,
};

/* ─────────────── Sunucu ─────────────── */

async function istek(yol, secenekler = {}) {
  const y = await fetch(yol, {
    ...secenekler,
    headers: {
      'X-Kapi-Anahtar': durum.anahtar,
      'Accept': 'application/json',
      ...(secenekler.body ? { 'Content-Type': 'application/json' } : {}),
      ...(secenekler.headers || {}),
    },
  });
  const veri = await y.json().catch(() => ({}));
  return { ok: y.ok, kod: y.status, veri };
}

/* ─────────────── Ekranlar ─────────────── */

function ekranGoster(ad) {
  document.querySelectorAll('[data-ekran]').forEach((e) => {
    e.hidden = e.dataset.ekran !== ad;
  });
}

function sonucGoster(v) {
  const kutu = $('#sonuc');
  const izinli = v.izinli === true;
  // Uyari: gecis SERBEST ama gorevli baksin (ayni kapida yinelenen okuma ya
  // da kart baska kapida okutulmus). Kirmizi ret ekrani DEGIL.
  const uyari = v.uyari === true;

  kutu.className = 'sonuc ' + (uyari ? 'sonuc--uyari' : izinli ? 'sonuc--izinli' : 'sonuc--ret');
  $('#sonuc-etiket').textContent = v.etiket ?? 'Sonuç';
  $('#sonuc-mesaj').textContent = v.mesaj ?? '';
  $('#sonuc-zaman').textContent = v.zaman ?? '';

  const kisi = v.kisi;
  $('#kisi').hidden = !kisi;
  if (kisi) {
    $('#kisi-isim').textContent = kisi.isim ?? '—';
    $('#kisi-kurum').textContent = kisi.kurum ?? 'Bağımsız';
    $('#kisi-kartno').textContent = kisi.kartNo ?? '';
    const foto = $('#kisi-foto');
    if (kisi.foto) { foto.src = kisi.foto; foto.hidden = false; $('#foto-yok').hidden = true; }
    else { foto.hidden = true; $('#foto-yok').hidden = false; }
  }

  ekranGoster('sonuc');
  // Titreşim, gürültülü stadyumda sesten daha güvenilir bir geri bildirim.
  navigator.vibrate?.(uyari ? [40, 40, 40] : izinli ? 60 : [80, 60, 80]);
}

function hataGoster(baslik, mesaj) {
  sonucGoster({ izinli: false, etiket: baslik, mesaj, kisi: null, zaman: '' });
}

/* ─────────────── QR okuma ─────────────── */

let dedektor = null;

async function dedektorHazirla() {
  if ('BarcodeDetector' in window) {
    try {
      const turler = await window.BarcodeDetector.getSupportedFormats();
      if (turler.includes('qr_code')) {
        dedektor = new window.BarcodeDetector({ formats: ['qr_code'] });
      }
    } catch { /* jsQR'a düşeriz */ }
  }
}

async function kameraBaslat() {
  try {
    durum.akis = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: false,
    });
  } catch {
    hataGoster('Kamera açılamadı', 'Tarayıcıda kamera iznini verin, sonra “Yeniden dene”ye basın.');
    return false;
  }

  const video = $('#kamera');
  video.srcObject = durum.akis;
  await video.play();
  return true;
}

function kameraDurdur() {
  durum.akis?.getTracks().forEach((t) => t.stop());
  durum.akis = null;
}

async function karedenOku(video, tuval, ctx) {
  if (dedektor) {
    const bulunan = await dedektor.detect(video).catch(() => []);
    return bulunan[0]?.rawValue ?? null;
  }

  const en = video.videoWidth, boy = video.videoHeight;
  if (!en || !boy) return null;

  // Ortadaki kareyi tara: hem hızlı hem de yanlış QR'ı yakalamaz.
  const kenar = Math.min(en, boy);
  tuval.width = kenar; tuval.height = kenar;
  ctx.drawImage(video, (en - kenar) / 2, (boy - kenar) / 2, kenar, kenar, 0, 0, kenar, kenar);
  const veri = ctx.getImageData(0, 0, kenar, kenar);
  return jsQR(veri.data, kenar, kenar, { inversionAttempts: 'dontInvert' })?.data ?? null;
}

async function dongu() {
  const video = $('#kamera');
  const tuval = document.createElement('canvas');
  const ctx = tuval.getContext('2d', { willReadFrequently: true });

  const adim = async () => {
    if (!durum.okuyor) return;

    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      const yuk = await karedenOku(video, tuval, ctx);
      const simdi = Date.now();

      // Aynı kart kamera önünde dururken saniyede 30 kez sorgulanmasın.
      if (yuk && !(yuk === durum.sonOkuma && simdi - durum.sonOkumaZaman < 2500)) {
        durum.sonOkuma = yuk;
        durum.sonOkumaZaman = simdi;
        await gonder(yuk);
        return;
      }
    }

    requestAnimationFrame(adim);
  };

  requestAnimationFrame(adim);
}

async function gonder(yuk) {
  durum.okuyor = false;
  $('#okuma-durum').textContent = 'Doğrulanıyor…';

  if (!navigator.onLine) {
    hataGoster('Bağlantı yok', 'Doğrulama yapılamıyor. MANUEL kontrole geçin: kimlik ve kart numarasını elle kontrol edin.');
    return;
  }

  try {
    const { ok, kod, veri } = await istek('/api/kapi/dogrula', {
      method: 'POST',
      body: JSON.stringify({ yuk, yon: durum.yon }),
    });

    if (!ok) {
      hataGoster(kod === 401 || kod === 403 ? 'Cihaz yetkisiz' : 'Doğrulanamadı',
        veri.mesaj || 'Sunucu yanıt vermedi. Sorun sürerse manuel kontrole geçin.');
      return;
    }

    sonucGoster(veri);
  } catch {
    hataGoster('Bağlantı hatası', 'Sunucuya ulaşılamadı. MANUEL kontrole geçin.');
  }
}

/* ─────────────── Akış ─────────────── */

async function taramayaGec() {
  ekranGoster('tarama');
  $('#okuma-durum').textContent = 'Kartı kameraya gösterin';

  if (!durum.akis && !(await kameraBaslat())) return;

  durum.okuyor = true;
  dongu();
}

async function anahtarDogrula(anahtar) {
  durum.anahtar = anahtar;
  const { ok, veri } = await istek('/api/kapi/tanim');

  if (!ok) {
    durum.anahtar = '';
    return { ok: false, mesaj: veri.mesaj || 'Anahtar kabul edilmedi.' };
  }

  localStorage.setItem(ANAHTAR_KEY, anahtar);
  $('#kapi-adi').textContent = veri.kapi;
  return { ok: true };
}

function yonAyarla(yon) {
  durum.yon = yon;
  localStorage.setItem(YON_KEY, yon);
  document.querySelectorAll('[data-yon]').forEach((d) => {
    d.classList.toggle('etkin', d.dataset.yon === yon);
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  await dedektorHazirla();
  yonAyarla(durum.yon);

  $('#kurulum-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    $('#kurulum-hata').textContent = '';
    const sonuc = await anahtarDogrula($('#anahtar').value.trim());
    if (sonuc.ok) { taramayaGec(); } else { $('#kurulum-hata').textContent = sonuc.mesaj; }
  });

  document.querySelectorAll('[data-yon]').forEach((d) =>
    d.addEventListener('click', () => yonAyarla(d.dataset.yon)));

  $('#devam').addEventListener('click', () => taramayaGec());
  $('#cihaz-sifirla').addEventListener('click', () => {
    localStorage.removeItem(ANAHTAR_KEY);
    kameraDurdur();
    location.reload();
  });

  if (durum.anahtar) {
    const sonuc = await anahtarDogrula(durum.anahtar);
    if (sonuc.ok) { taramayaGec(); return; }
  }

  ekranGoster('kurulum');
});

// Uygulama kabuğu çevrimdışı da açılsın; doğrulama yine de ağ ister.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/kapi/sw.js', { scope: '/kapi' }).catch(() => {});
}
