# BYS — Basın Yönetim Sistemi

ARCA Çorum FK'nın basın akreditasyonu ve stadyum girişi sistemi. Basın
mensuplarının başvurusundan basın kartına, oradan turnikede QR okutulmasına
kadar olan süreci tek yerden yönetir.

> Adı **Basın Yönetim Sistemi**, kısaltması **BYS**. "Medya Merkezi" değil.

## Ne yapar

1. **Başvuru** — kurumlar (gazete, ajans, TV) ve bağımsız gazeteciler/içerik
   üreticileri web formundan başvurur, evraklarını yükler.
2. **İnceleme** — kulüp yetkilisi evrakları yan yana önizleyerek inceler, eksik
   evrakı alan bazında geri ister, onaylar veya reddeder. **Nihai onay her zaman
   kulüptedir**; kurum yalnızca çalışanını teyit eder.
3. **Akreditasyon** — onaylanana kart numarası (`2026-K-0042`) ve imzalı QR
   taşıyan **basın kartı** üretilir. Çalışan kurumdan ayrılırsa akreditasyonu
   otomatik iptal olur.
4. **Kapı** — turnikedeki görevli `/kapi` ekranından QR okutur; sistem fotoğraflı
   yanıt döner ve her okutma kayda geçer.
5. **İçerik** — duyuru, antrenman takvimi ve bülten yayımlanır; bildirimler
   yalnızca ilk yayında ve kuyrukta parçalı gönderilir.
6. **Değerlendirme** — kulüp yetkilisi başvurana **1–5 arası puan** ve not
   yazar (kuruma ya da kişiye). Puan **karar değildir**: onay/red akışını
   etkilemez, kimseyi engellemez; yetkiliye geçmişi hatırlatır. 🔒 Yalnızca
   yönetim panelinde görünür — kurum/üye panelinde, kapı API'sinde ve kartta
   hiç geçmez.

## Panolar

| Yol | Kim kullanır |
|---|---|
| `/yonetim` | Kulüp yetkilisi — başvurular, akreditasyonlar, kapılar, denetim kaydı (2FA zorunlu) |
| `/kurum` | Basın kuruluşu — kendi çalışanları ve başvuruları |
| `/panel` | Basın mensubu — kendi kartı, evrakları, içerikler |
| `/kapi` | Turnike görevlisi — QR okutma (PWA, çevrimdışı uyarısı var) |

Üç panelin de girişinde **"Genel bakış"** panosu karşılar: üye kendi kartının
geçerliliğini ve kendisinden bekleneni, kurum yetkilisi teyidini bekleyen
başvuruları ve kontenjanını, kulüp yetkilisi kuyruk yaşını, karar dağılımını,
maç günü geçiş akışını ve elini değdirmesi gereken satırları görür.
**Verisi olmayan kutu hiç çizilmez** — boş kutu, dolu kutudan kötüdür.

Giriş **tek kapıdan**: `/giris`. Kurum yetkilisi, basın mensubu ve içerik
üreticisi buradan girer; sistem rolüne göre panele yollar, iki panele birden
girebilen kişiye seçim ekranı gösterir. Kulüp yetkilisinin kapısı ayrı kalır
(`/yonetim/login`, iki adımlı doğrulama zorunlu). Şifre sıfırlama üçü için de
tek adreste: `/sifremi-unuttum`.

## Yığın

PHP 8.3 · Laravel 13 · Filament 5 · Livewire 4 · PostgreSQL 16 · Redis ·
Horizon (kuyruk) · Tailwind + Vite · Browsershot (kart görselleri) ·
spatie/laravel-permission (rol/yetki) · endroid/qr-code

## Kurulum

```bash
git clone git@github.com:iharda/arcacorumfk-bys.git
cd arcacorumfk-bys
composer setup          # .env oluşturur, key üretir, migrate eder, npm build alır
```

Ardından `.env` içinde kendi **PostgreSQL** ve **Redis** bilgilerinizi girin.

`.env` deposunda **yoktur ve olmayacaktır** — uygulama anahtarı, veritabanı ve
SMTP parolaları oradadır. Canlı `.env`'i kopyalamayın; kendi ortamınızı kurun.

Geliştirirken:

```bash
composer dev            # sunucu + kuyruk + vite birlikte
php artisan bys:pilot-verisi        # örnek kurum/başvuru/kart üretir (--sil ile temizler)
```

## Günlük akış

Kod GitHub'da (`master` dalı), canlı sürüm `byd.ordolive.com`'da çalışır. İkisi
otomatik bağlı değildir.

```bash
git pull                # başlarken
git push                # bitirince
```

Sunucuya yansıtmak için **sunucuda**:

```bash
bash dagit.sh
```

Bu betik GitHub'dan kodu çeker, gerekiyorsa `composer install` / `npm ci` /
`npm run build` çalıştırır, önbelleği tazeler, migration'ları uygular ve
Horizon'u yeniden başlatır. Sunucuda kaydedilmemiş değişiklik varsa **durur** —
çekmeden dağıtmak için `PULLSUZ=1 bash dagit.sh`.

## Kalite

```bash
composer denetle        # pint (biçim) + phpstan (analiz) — ikisi de temiz olmalı
composer test           # phpunit
```

Çalışan siteye karşı koşan uçtan uca testler `tests/uctan-uca/` altında;
kullanımı ve uyarıları için [tests/uctan-uca/BENIOKU.md](tests/uctan-uca/BENIOKU.md).
**Bu testler üretime yazar**, önce o dosyayı okuyun.

## Belgeler

| Dosya | İçerik |
|---|---|
| [docs/yetkili-kullanim-notlari.md](docs/yetkili-kullanim-notlari.md) | Kulüp yetkilisi için kullanım rehberi |
| [docs/pilot-senaryosu.md](docs/pilot-senaryosu.md) | Müşteriyle yapılacak deneme akışı |
| [docs/canliya-alma.md](docs/canliya-alma.md) | Canlıya çıkış kontrol listesi |
| [docs/kvkk-taslak.md](docs/kvkk-taslak.md) | Aydınlatma/saklama metni taslağı (hukuk onayı bekliyor) |
| [docs/revizyon-20260903.md](docs/revizyon-20260903.md) | Cüneyt Bey'in 03.09.2026 revizyonu: madde madde ne değişti |

## Tuzaklar

Vakit kaybettiren, tekrar eden hatalar:

- **`artisan optimize` config'i önbelleğe alır.** `config/` altını değiştirip
  `dagit.sh` çalıştırmazsanız değişiklik canlıya yansımaz.
- **Blade değiştirdiyseniz `npm run build` şart.** Tailwind sınıf adlarını Blade
  dosyalarından tarar; derlemezseniz yeni sınıf sessizce çalışmaz.
- **artisan'ı root ile çalıştırmayın.** Root'un bıraktığı dosyalar 500 üretir.
  Sunucuda her komut `sudo -u bys ...` ile.
- **Kendi kaydını görmek için yetki aranmaz.** Policy'lerde önce sahiplik
  kontrolü yapın; `...gor` yetkileri *başkasının* kaydı içindir.
- **Kuyrukta `uniqueId()` tek başına etkisizdir** — `ShouldBeUnique` gerekir.
  Çakışmayı önlemek için `WithoutOverlapping`.
- **Evrak ve kart diskleri web kökünün dışındadır**, public URL'leri yoktur.
  Erişim her zaman policy'den geçer.
- Kişisel veri saklanıyor: kimlik belgeleri gece çalışan `bys:evrak-imha` ile
  süresi dolduğunda silinir. Yeni bir alan eklerken saklama süresini de düşünün.
- **Panelde kendi Tailwind sınıflarımız derlenmiyor.** Pano ve şerit gibi
  parçalarda yerleşim/renk **satır içi `style`** ile yazılır; yeni sınıf adı
  üretirseniz sessizce çalışmaz.
- **Değerlendirme puanı kulüp dışına çıkmaz.** Blade'de `@can` sarmalı YETMEZ;
  veriyi getiren sorgu da yalnızca yönetim tarafında olmalı.
  `bys-degerlendirme-testi.mjs` bunu sayfa kaynağı üzerinden doğruluyor.
- **`X-Frame-Options`/`frame-ancestors` `DENY`/`none` OLAMAZ.** İnceleme
  ekranındaki PDF önizlemesi kendi origin'imizdeki bir `<iframe>`; `none`
  onu da engelliyor ve ekranda boş gri kutu çıkıyor. Doğrusu `SAMEORIGIN` /
  `frame-ancestors 'self'` (nginx: `snippets/bys-guvenlik-basliklari.conf`,
  `bys-sertlestirme-denetimi.mjs` bunu doğruluyor).
- **Dosya girdisi `old()` ile geri doldurulamaz.** Doğrulama hatasında
  başvuran evraklarını yeniden seçmesin diye `EvrakTaslagi` dosyayı sunucuda
  tutuyor. Formda yeni bir dosya alanı açarsanız o mekanizmayı da bağlayın,
  yoksa "form yenilendi, dosyaları tekrar seçin" hatası geri gelir.
- **`users.email` küçük harfe indirgenmeden saklanıyor.** E-posta anahtarlı bir
  bağ kuracaksanız iki tarafı da indirgeyin (`DegerlendirmeAkisi::epostaAnahtari`).

## Lisans

Tescilli yazılım. ARCA Çorum FK için Minima Kreatif tarafından geliştirilmektedir.
Açık kaynak değildir, izinsiz kopyalanamaz ve dağıtılamaz.
