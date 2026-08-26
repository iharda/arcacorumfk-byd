# KVKK metinleri — TASLAK

> ⚠️ **Bu bir taslaktır, hukuki görüş değildir.** Kulübün hukuk danışmanı
> onaylamadan yayımlanmamalıdır. Amaç: sistemin **gerçekte hangi veriyi, neden,
> ne kadar süre** işlediğini eksiksiz ortaya koymak; hukukçu bunun üzerinden
> çalışsın, sıfırdan başlamasın.
>
> Metin, kulübün web sitesindeki genel aydınlatma metninin **yerine geçmez**.
> Site metni ziyaretçi/çerez verisini anlatır; burada **kimlik belgesi görseli,
> biyometrik fotoğraf ve stadyum giriş-çıkış kayıtları** işleniyor. Bunlar
> ayrı bir aydınlatma gerektirir.

Onaylanan metinler **Yönetim → Ayarlar → KVKK metinleri** alanına yapıştırılır;
`/metin/aydinlatma`, `/metin/acik-riza` ve `/metin/gizlilik` adreslerinde
yayımlanır ve başvuru formundaki onay kutuları bu sayfalara bağlanır.

---

## 1. Aydınlatma metni (taslak)

### Veri sorumlusu
ARCA Çorum FK — *(tam ticari unvan, MERSİS/vergi no, adres, KEP adresi
eklenecek)*

### İşlenen kişisel veriler

Sistem, başvuru türüne göre aşağıdaki verileri işler:

| Kategori | Veriler | Kimden |
|---|---|---|
| Kimlik | Ad soyad | Tüm başvuranlar |
| İletişim | E-posta, telefon, adres, il/ilçe | Tüm başvuranlar |
| Mesleki deneyim | Çalıştığı kurum, 212 sigortası bilgisi, basın kartı bilgisi, sektörde çalışma yılı | Basın mensubu |
| Görsel kayıt | **Biyometrik fotoğraf** (kartta ve kapı doğrulama ekranında kullanılır) | Basın mensubu, içerik üreticisi |
| Kimlik belgesi | **Kimlik / ehliyet / pasaport görseli** | Basın mensubu, içerik üreticisi |
| Çalışma belgesi | İş giriş belgesi veya SGK belgesi | Basın mensubu |
| Kurumsal | Ünvan, vergi dairesi/no, adres, çalışan sayısı, yayın ve sosyal medya bağlantıları | Kurum başvurusu |
| İşlem güvenliği | **Kulüp girişlerinde kart okutma kayıtları** (kapı, yön, zaman, sonuç), IP adresi, oturum açma kayıtları | Akredite kişiler |

> Kimlik belgesi görselleri ve çalışma belgeleri **sunucuda şifreli** saklanır;
> herkese açık bir adresten erişilemez.

### İşleme amaçları
- Akreditasyon başvurusunun alınması ve değerlendirilmesi
- Başvuranın kimliğinin ve mesleki bağının doğrulanması
- Dijital basın kartının üretilmesi
- Kulüp tesis ve etkinlik alanlarına **giriş yetkisinin denetlenmesi**
- Basına yönelik duyuru, antrenman takvimi ve bültenlerin iletilmesi
- Hukuki yükümlülüklerin yerine getirilmesi ve uyuşmazlıklarda ispat

### Hukuki sebepler (KVKK m.5)
- **m.5/2-c** — akreditasyon ilişkisinin kurulması ve yürütülmesi için gerekli olması *(kimlik, iletişim, mesleki bilgiler, geçiş kayıtları)*
- **m.5/2-f** — kulübün tesis güvenliğine ilişkin meşru menfaati *(geçiş kayıtları, oturum kayıtları)*
- **m.5/1 açık rıza** — kimlik belgesi görseli ve biyometrik fotoğrafın işlenmesi

> Açık rıza **verilmezse** başvuru değerlendirilemez; bu, rızanın diğer
> işlemeler için ön koşul olduğu anlamına gelmez. Bu ayrımın metinde net
> yazılması gerekir.

### Aktarım
Veriler üçüncü kişilerle paylaşılmaz. İstisnalar:
- Yasal talep hâlinde yetkili kamu kurum ve kuruluşları
- *(Turnike entegrasyonu yapılırsa: geçiş doğrulaması sırasında turnike
  sistemine yalnızca **geçiş izni sonucu**, ad ve fotoğraf iletilir. Sağlayıcı
  belirlendiğinde bu madde somutlaştırılmalı.)*
- Barındırma hizmeti *(sunucu sağlayıcısı — devir sonrası kulübün kendi
  sunucusu olacaksa bu madde güncellenmeli)*

### Saklama süreleri
| Veri | Süre | Sistemdeki karşılığı |
|---|---|---|
| Kimlik belgesi görseli, çalışma belgesi | **180 gün** | `evrak_turleri.imha_gun`; her gece `bys:evrak-imha` çalışır |
| Biyometrik fotoğraf | Akreditasyon geçerli olduğu sürece | — |
| Başvuru ve karar geçmişi | Akreditasyon sona erdikten sonra *(süre belirlenecek)* | — |
| Geçiş kayıtları | *(süre belirlenecek — öneri: 1 yıl)* | `gecis_kayitlari` |
| Denetim kaydı | *(süre belirlenecek)* | `denetim_kaydi` — **değiştirilemez** |

> ⚠️ Boş bırakılan süreler kulüp tarafından belirlenmeli. Sistem şu an yalnızca
> kimlik belgesi ve çalışma belgesi için otomatik imha yapıyor; diğerleri için
> süre verilirse aynı mekanizma genişletilir.

### İlgili kişinin hakları (KVKK m.11)
Başvuran; verisinin işlenip işlenmediğini öğrenme, bilgi talep etme, amacına
uygun kullanılıp kullanılmadığını öğrenme, düzeltilmesini/silinmesini isteme,
aktarıldığı üçüncü kişileri öğrenme, otomatik sistemlerle analiz sonucu aleyhine
bir sonuç doğmasına itiraz etme ve zararın giderilmesini talep etme haklarına
sahiptir.

Başvuru yolu: *(KEP adresi / başvuru formu / e-posta adresi eklenecek)*

---

## 2. Açık rıza metni (taslak)

> KVKK Kurulu'nun ilke kararı gereği açık rıza, aydınlatma metninden **ayrı**
> alınmalıdır. Sistemde iki onay kutusu ayrı ayrı duruyor; bu yapı doğrudur.

Aşağıdaki metin, formdaki ikinci onay kutusuna bağlanır:

> ARCA Çorum FK basın akreditasyon başvurum kapsamında **kimlik/ehliyet/pasaport
> görselimin** ve **biyometrik fotoğrafımın**, kimliğimin doğrulanması, basın
> kartımın üretilmesi ve kulüp girişlerinde yetki kontrolü amaçlarıyla
> işlenmesine; bu verilerin aydınlatma metninde belirtilen süreler boyunca
> saklanmasına açık rıza veriyorum.
>
> Rızamı dilediğim zaman geri çekebileceğimi, geri çekmem hâlinde
> akreditasyonumun sona ereceğini biliyorum.

---

## 3. Gizlilik politikası (taslak)

Bu sayfa kamuya açık başvuru sitesinin genel işleyişini anlatır: hangi çerezler
kullanılıyor, oturum bilgisi nasıl saklanıyor, verilere kimler erişebiliyor.

Sistemde şu an durum:
- **Çerez:** yalnızca oturum çerezi. İzleme/analitik çerezi **yok**.
- **Üçüncü taraf istek yok:** yazı tipleri, ikonlar ve görseller sunucudan
  gelir; hiçbir dış servise istek gitmez.
- **Erişim:** başvuru evrakına yalnızca kulüp yetkilisi ve başvuranın kendisi
  erişebilir; her hassas evrak görüntülemesi denetim kaydına yazılır.

---

## Hukukçuya iletilecek sorular

1. Geçiş kayıtları ve denetim kaydı için saklama süresi ne olmalı?
2. Kimlik belgesi görseli için 180 gün uygun mu, yoksa karar sonrası hemen
   imha mı edilmeli?
3. Reddedilen başvurularda evrak imhası ne zaman yapılmalı?
4. Turnike sağlayıcısına veri aktarımı olacaksa aydınlatma metninde nasıl
   yer almalı?
5. Sistem kulübün kendi sunucusuna taşındığında barındırma maddesi değişecek —
   metin buna göre mi yazılsın?

*Taslak tarihi: 21.08.2026*
