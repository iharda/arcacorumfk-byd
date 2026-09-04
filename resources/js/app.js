import Alpine from 'alpinejs';
import mask from '@alpinejs/mask';

// Telefon alanındaki `x-mask` için (Revizyon md.5.2). Elle yazılan maskeler
// geri silme ve yapıştırmada imleci kaybediyor; resmi eklenti bunu doğru yapar.
Alpine.plugin(mask);

window.Alpine = Alpine;
Alpine.start();

/*
 Doğrulama hatasından sonra ilk hatalı alana kaydır.

 Kamu formları klasik POST; hata olunca sayfa baştan çizilir ve tarayıcı en
 üste düşer. Başvuru formu uzun olduğu için kullanıcı yalnızca "N alan hatalı"
 yazısını görüp kırmızı kutuyu elle ARAYARAK aşağı iniyordu.

 🪤 `$errors->keys()` KURAL sırasını verir, ekrandaki sırayı değil. "İlk hata"
 diye ilk anahtarı almak formun ortasına atlatabilir; bu yüzden eşleşen
 alanları DOM sırasında geziyoruz.
*/
function ilkHataliAlanaKaydir() {
    let yollar;

    try {
        yollar = JSON.parse(document.body.dataset.hataAlanlari ?? '[]');
    } catch {
        return;
    }

    if (! Array.isArray(yollar) || yollar.length === 0) {
        return;
    }

    // 💣 Hata yolu NOKTALI (`sosyal_medya.x`), girdi adı KÖŞELİ
    // (`sosyal_medya[x]`). İkisini de arıyoruz; yoksa iç içe alanlarda
    // hiçbir eşleşme olmaz ve kaydırma sessizce çalışmaz.
    const adlar = new Set();

    for (const yol of yollar) {
        const parcalar = String(yol).split('.');

        adlar.add(yol);
        adlar.add(parcalar[0] + parcalar.slice(1).map((p) => `[${p}]`).join(''));
    }

    const hedef = Array.from(
        document.querySelectorAll('input[name], select[name], textarea[name]'),
    ).find((alan) => adlar.has(alan.name));

    // `genel` gibi alana bağlanmayan hatalarda eşleşme olmaz; sayfa zaten
    // tepede açıldığı için özet kutusu görünür durumda.
    if (! hedef) {
        return;
    }

    const yumusak = ! window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    hedef.scrollIntoView({ behavior: yumusak ? 'smooth' : 'auto', block: 'center' });
    // Odaklanmanın kendi kaydırması yumuşak kaydırmayı yarıda keserdi.
    hedef.focus({ preventScroll: true });
}

// Alpine ilk çizimini bitirsin: `x-cloak` ile gizli bloklar açılınca sayfa
// kayıyor, ölçümü ondan sonra almak gerekiyor.
requestAnimationFrame(ilkHataliAlanaKaydir);
