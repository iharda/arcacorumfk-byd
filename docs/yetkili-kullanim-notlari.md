# Basın Yönetim Sistemi — Yetkili Kullanım Notları

ARCA Çorum FK · Kulüp yetkilisi için günlük kullanım rehberi.

---

## 1. Giriş

Adres: **`/yonetim`**

Girişte parolanın yanında **telefonunuzdaki doğrulama uygulamasından altı haneli
kod** istenir. Bu zorunludur; kapatılamaz. İlk girişinizde kurulum ekranı çıkar:

1. Uygulamanızla (Google Authenticator, Microsoft Authenticator, 1Password…)
   ekrandaki kareyi okutun.
2. Size verilen **kurtarma kodlarını** telefondan bağımsız bir yere kaydedin.
   Telefonunuzu kaybederseniz sisteme yalnızca bunlarla girebilirsiniz.

> **Kurtarma kodlarını da kaybederseniz** hesabınızı biz sıfırlamak zorunda
> kalırız. Kod listesini paylaşmayın.

---

## 2. Günlük akış: başvuruları sonuçlandırmak

Panoda **"Kuyrukta başvuru"** kutusu bekleyen sayısını gösterir; üzerine
tıklayınca listeye gider.

**Başvurular** ekranı varsayılan olarak yalnızca **sonuçlanmamış** başvuruları
gösterir (Gönderildi · İncelemede · Eksik evrak). En eski başvuru en üsttedir —
sıra bozulmasın diye böyle.

Bir başvuruyu açtığınızda:

- **Solda** kurum ve kişi bilgileri, yüklenen evrakların listesi
- **Sağda** seçili evrakın önizlemesi — soldaki listeden tıklayarak değiştirin
- **Üstte** karar düğmeleri

### Sıra

1. **İncelemeye al** — başvuruyu üstlenirsiniz; adınız kayda geçer, başka bir
   yetkili aynı başvuruyla uğraşmaz.
2. Evrakları kontrol edin. Fotoğrafı büyütmek için "Yeni sekmede aç".
3. Karar:
   - **Onayla** — kurum akredite olur ya da kişiye kart üretilir. Başvuranın
     **hesabı da bu anda açılır**: onay e-postası "şifremi belirle" bağlantısı
     taşır.
   - **Eksik evrak iste** — hangi alanın neden sorunlu olduğunu **tek tek**
     yazarsınız. Başvurana bu liste, **tek kullanımlık bir düzeltme bağlantısıyla
     birlikte** e-postayla gider; işaretlediğiniz alanları hesap açmadan
     düzeltip yeniden gönderir. Bağlantı ulaşmazsa "Düzeltme bağlantısını
     yeniden gönder" ile yenisini yollarsınız.
   - **Reddet** — gerekçe zorunludur ve başvurana **aynen** iletilir. Reddedilen
     kişiye hesap **açılmaz**.

> **Onay geri alınamaz.** Hesap açılır, kart üretilir, e-posta gider.
> Şüphedeyseniz önce eksik evrak isteyin.

> Başvuran onaya kadar sisteme hiç girmez: evrakını başvuru formunda verir,
> eksiğini geçici bağlantıdan tamamlar.

### Kurum teyidi bekleyenler

Ayarlarda "kurum teyidi" açıksa, kendisi başvuran basın mensuplarının başvurusu
**önce kurumunun onayını bekler** ve sizin kuyruğunuza hiç düşmez. Kurum
"çalışanımız değil" derse başvuru kendiliğinden düşer.

---

## 3. Akreditasyonlar

Onaylanan her kişi için bir akreditasyon ve **kart numarası** oluşur
(`2026-K-0042` biçiminde). Bu ekrandan:

| Düğme | Ne yapar |
|---|---|
| **Bölge yetkisi** | Kişinin girebileceği alanları belirler. Değiştirince kart yeniden üretilir. |
| **Askıya al** | Kart geçici olarak geçersiz olur. Geri alınabilir. |
| **Askıyı kaldır** | Kart yeniden geçerli olur. |
| **İptal et** | **Kalıcıdır.** Kart bir daha geçmez, kişi yeniden başvurmalıdır. |
| **Kartı yeniden üret** | Yeni sürüm çıkar; kart numarası ve QR **değişmez**. |

> Bir kişi kurumundan ayrıldığında kurum bunu kendi panelinden bildirir ve
> akreditasyon **otomatik iptal olur**. Sizin bir şey yapmanız gerekmez.

---

## 4. Kapılar

Her turnike veya gişe cihazı için ayrı bir kapı tanımlanır.

1. **Kapı ekle** → ad, kapı kodu, (varsa) izinli IP adresleri, bu kapının
   açtığı bölgeler.
2. Kaydedince ekranda **bir kez** bir anahtar görünür. Kopyalayın; sunucuda
   saklanmaz, bir daha gösterilmez.
3. Cihazda tarayıcıdan **`/kapi`** adresini açın, anahtarı girin. Cihaz
   kendini tanıtır ve kamerayı açar.

**IP kısıtı boş bırakılırsa** o anahtarla dünyanın her yerinden okutma
yapılabilir. Stadyumun sabit IP'si varsa mutlaka girin — liste ekranında
kısıtsız kapılar sarı görünür.

Anahtar sızdıysa: **Anahtarı yenile**. Eski anahtar o anda geçersiz olur; o
cihaz yeni anahtar girilene kadar okutma yapamaz.

### Kapıda ne görünür

QR okununca ekran ya **yeşil "İzinli"** ya da **kırmızı** bir sonuç gösterir;
altında kişinin **fotoğrafı, adı, kurumu ve kart numarası** çıkar.
**Fotoğrafla yüzü karşılaştırmak görevlinin işidir** — sistem kartın geçerli
olduğunu söyler, kartı taşıyanın doğru kişi olduğunu söylemez.

İnternet yoksa ekran "**manuel kontrole geçin**" der. Bu durumda kimliğe ve
kart numarasına elle bakılır; sistem asla tahminle "izinli" demez.

---

## 5. Medya merkezi

**Duyurular · Antrenman takvimi · Basın bültenleri** — üçü de aynı şekilde
çalışır: önce taslak olarak yazılır, sonra **Yayına al** denir.

> **İlk yayında** bütün akredite kullanıcılara e-posta gider. Yayından kaldırıp
> tekrar yayınlarsanız **ikinci bir e-posta gitmez** — yazım hatası düzeltmek
> kimseyi rahatsız etmesin diye böyle.

---

## 6. Denetim kaydı

Sistemde yapılan her karar, durum değişikliği ve giriş denemesi buraya yazılır:
kim, ne zaman, neyi, eski hâlinden yeni hâline.

Bu kayıt **silinemez ve değiştirilemez** — panelden de, doğrudan veritabanından
da. Bir anlaşmazlıkta başvurulacak yer burasıdır.

"Yalnızca güvenlik olayları" süzgeci başarısız giriş denemelerini, iptalleri ve
ayar değişikliklerini bir arada gösterir.

---

## 7. Ayarlar

- **Kurum teyidi istensin** — açıkken kendisi başvuran basın mensubu için
  kurumun onayı beklenir.
- **Davet geçerlilik süresi** — kurumun çalışanına gönderdiği bağlantının
  kaç gün geçerli olacağı.
- **KVKK metinleri** — aydınlatma, açık rıza ve gizlilik metinleri. Boş
  bırakılırsa kamuya açık sayfada "henüz yayımlanmadı" yazar; başvuru
  formundaki onay kutuları yine bu sayfalara bağlıdır.

---

## 8. Sık sorulanlar

**Bir kişi kartını kaybetti / telefonu değişti.**
Kart dijitaldir, kaybolmaz. Kişi kendi panelinden (`/panel` → Kartım) yeniden
indirebilir.

**Kartı çalınan biri var, ne yapmalıyım?**
Akreditasyonlar ekranından **Askıya al**. Kart o andan itibaren kapıdan geçmez;
sorun çözülünce askıyı kaldırırsınız.

**Aynı kart iki kez okutuldu, uyarı çıktı.**
Sistem kısa süre içinde tekrar okutulan kartı "mükerrer" işaretler ama geçişi
engellemez. Bu genelde kart paylaşımının işaretidir; görevliye sorun.

**Bir başvuruyu yanlışlıkla reddettim.**
Karar geri alınamaz. Kişinin yeniden başvurması gerekir; eski dosyası
denetim kaydında durur.

**Başvuran e-posta almadığını söylüyor.**
Önce spam klasörünü kontrol ettirin. Sorun sürüyorsa denetim kaydından
başvurunun hangi aşamada olduğunu görebilirsiniz.

---

*Bu notlar sistemle birlikte güncellenir. Son güncelleme: 21.08.2026*
