# Pilot deneme senaryosu

ARCA Çorum FK · Basın Yönetim Sistemi · **Aşama 07**

Amaç: sistemi gerçek kullanıcıyla, gerçek kapıda denemek. Hedef bir hata
bulmamak değil, **bulmak** — pilot bunun için var.

---

## Önce: ortamı hazırla

```bash
cd /home/byd.ordolive.com/laravel
php artisan byd:pilot-verisi          # örnek kurum, kişi, kart, kapı, içerik
```

Komut sonunda ekrana **kapı anahtarı** ve üç giriş bilgisi yazar. Anahtarı
kopyalayın; bir daha gösterilmez (panelden yenilenebilir).

Denemeden sonra:

```bash
php artisan byd:pilot-verisi --sil
```

> Denetim kaydı bilerek silinmez — değiştirilemez bir kayıttır ve pilotta
> nelerin yapıldığını gösterir.

---

## Kim ne yapıyor

| Rol | Kim | Nerede |
|---|---|---|
| Kulüp yetkilisi | Basın sorumlusu | Dizüstü, `/yonetim` |
| Kapı görevlisi | Turnike görevlisi | Telefon veya tablet, `/kapi` |
| Başvuran | 2–3 gerçek basın mensubu | Kendi telefonları |
| Gözlemci | Biz | Yanında, not alan |

---

## Akış

### 1 · Başvuru (başvuran, kendi telefonundan)

1. `byd.ordolive.com` → **Basın mensubu**
2. Formu doldurur, kurumunu seçer, KVKK kutularını işaretler
3. **E-postasına gelen bağlantıdan şifresini belirler**
4. Panelde fotoğrafını, kimliğini ve çalışma belgesini yükler → **Gönder**

**Ölçülecek:** E-posta kaç saniyede geldi? Spam'e düştü mü? Telefondan fotoğraf
yüklemek kolay mı? Formun anlaşılmayan yeri var mı?

### 2 · İnceleme (yetkili)

1. `/yonetim` → **Başvurular** → başvuruyu aç
2. **İncelemeye al** → evrakları sağdaki bölmede kontrol et
3. Bir başvuruda bilerek **eksik evrak iste** → başvuranın panelinde ve
   e-postasında göründüğünü doğrula → başvuran düzeltip yeniden gönderir
4. **Onayla**

**Ölçülecek:** Evrak önizlemesi okunaklı mı? Karar düğmeleri anlaşılır mı?
Eksik evrak açıklaması başvurana net geldi mi?

### 3 · Kart (başvuran)

1. Onaydan sonra e-postayla **PDF kart** gelir
2. Panelde `/panel` → **Kartım** → kart görünür, PDF indirilebilir

**Ölçülecek:** Kart telefonda okunaklı mı? Fotoğraf doğru mu? İsimdeki Türkçe
harfler doğru mu? Yazdırıldığında ölçü tutuyor mu?

### 4 · Kapı (kapı görevlisi)

1. Cihazda `byd.ordolive.com/kapi` → anahtarı gir
2. Başvuranın telefonundaki karttan **QR okut**
3. Ekranda yeşil **İzinli** + fotoğraf + isim + kurum çıkmalı
4. Görevli **fotoğrafla yüzü karşılaştırır**

Sonra bunları da deneyin:

| Deneme | Beklenen |
|---|---|
| Aynı kartı hemen tekrar okut | "Bu kart az önce okutuldu" |
| Yetkili panelinden kartı **askıya al**, tekrar okut | Kırmızı "Askıda" |
| Askıyı kaldır, tekrar okut | Yeşil "İzinli" |
| Kartın yetkisi olmayan bir bölge kapısında okut | "Bölge yetkisi yok" |
| Cihazın internetini kapat, okut | "Manuel kontrole geçin" |
| Rastgele bir QR (örneğin bir ürün barkodu) okut | "İmza geçersiz" |

**Ölçülecek:** Okutma kaç saniye sürüyor? Güneş altında/loş ışıkta okuyor mu?
Ekran görevliye yeterince büyük geliyor mu?

### 5 · Kayıtlar (yetkili)

1. `/yonetim` → **Geçiş kayıtları** → yapılan tüm okutmalar, başarısızlar dâhil
2. `/yonetim` → **Denetim kaydı** → kimin neyi onayladığı, giriş denemeleri

**Ölçülecek:** Kayıtlar bekleneni gösteriyor mu? Eksik bir bilgi var mı?

---

## Ters giderse

| Durum | Ne yapılır |
|---|---|
| Kapı uygulaması açılmıyor | Tarayıcıyı yenile; olmazsa **manuel kontrol** (kimlik + kart no) |
| QR okunmuyor | Kartı büyüt, ekran parlaklığını aç; olmazsa manuel |
| "Cihaz yetkisiz" | Anahtar yanlış veya iptal edilmiş → panelden yenile |
| E-posta gelmiyor | Spam'e bak. Davet bağlantısı kurum panelinde de gösteriliyor |
| Sistem yavaş/açılmıyor | `systemctl status byd-horizon php8.3-fpm nginx` |

**Pilot sırasında hiçbir şeyi "sonra bakarız" diye geçmeyin** — o an not alın,
ekran görüntüsü çekin. Sonradan hatırlanmıyor.

---

## Pilot sonrası

1. Notları topla, düzeltmeleri yap
2. `php artisan byd:pilot-verisi --sil`
3. **Canlıya alma listesini** işle: `docs/canliya-alma.md`
