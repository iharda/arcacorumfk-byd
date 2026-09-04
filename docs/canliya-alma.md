# Canlıya alma kontrol listesi

Sistem canlıya çıkmadan önce buradaki her maddenin **evet** olması gerekir.
İşaretlenmemiş bir madde varsa çıkılmaz.

---

## 1 · Müşteriden beklenenler

- [ ] **KVKK metinleri** hukuk onayından geçti ve Ayarlar'a girildi
      *(taslak: `docs/kvkk-taslak.md`)*
- [ ] **Kart tür harfleri** onaylandı (şu an K = basın mensubu, B = içerik üreticisi)
- [ ] **Bölge listesi** onaylandı (saha kenarı · basın locası · karma alan · basın toplantı salonu)
- [ ] **Yetkili listesi** verildi; hesaplar açıldı ve her birinde 2FA kuruldu
- [ ] **Turnike** kararı: gerçek turnikeye mi bağlanacak, kapı uygulamasıyla mı devam
- [ ] Geçiş kayıtları ve denetim kaydı için **saklama süreleri** belirlendi

## 2 · Alan adı ve sertifika

- [ ] Kulübün kendi alan adı belirlendi *(şu an `byd.ordolive.com` — geliştirme)*
- [ ] DNS yönlendirildi, Let's Encrypt sertifikası alındı
- [ ] `APP_URL` yeni adrese güncellendi *(e-postalardaki bağlantılar buradan üretiliyor)*
- [ ] Cloudflare **Full (strict)** modda
- [ ] ⚠️ nginx'teki **origin kilidi** (`if ($cf_edge_mi = 0) { return 444; }`) yeni
      alan adı için de geçerli — alan gri buluta alınırsa site komple düşer

## 3 · Posta

*Durum 04.09.2026'da DNS'e ve `.env`'e bakılarak güncellendi.*

- [x] SMTP çalışıyor *(Hostinger, `smtp.hostinger.com:465`, `noreply@corumfk.com.tr`)*
- [x] Gönderen adresi kulübün kendi alan adına taşındı
      *(eskiden Google Workspace / `arcacorumfk@ordolive.com` idi)*
- [x] **SPF** tanımlı — `v=spf1 include:_spf.mail.hostinger.com ~all`
- [ ] ⚠️ **DKIM YOK.** `hostingermail1/2/3`, `default`, `mail`, `selector1/2`
      selektörlerinin hiçbirinde kayıt dönmüyor. Gmail ve Yahoo'nun toplu
      gönderici kuralları SPF + DKIM + hizalı DMARC istiyor; imzasız posta
      doğrudan spam adayı. Hostinger panelinden DKIM açılıp verilen CNAME'ler
      DNS'e girilmeli — **kulüp/DNS tarafında yapılacak iş**
- [ ] ⚠️ **DMARC var ama dişi sökülmüş**: `v=DMARC1; p=none`, `rua=` yok.
      Ne uygulama var ne rapor toplanıyor. Sıra: önce DKIM, sonra
      `p=none; rua=mailto:…` ile raporu aç, raporlar temiz çıkınca
      `p=quarantine`
- [ ] Bir test başvurusuyla e-postanın **spam'e düşmediği** doğrulandı
      *(DKIM kurulmadan yapılması anlamsız — sonucu bilgi vermez)*
- [x] **Giden posta hız sınırı** kuruldu *(04.09)* — `config/bys.php`'deki
      `posta.dakikada` / `posta.saatte` kovaları ve
      `App\Notifications\Concerns\PostaKuyrugu`. 03.09'da sekiz işçi aynı
      anda SMTP'ye yüklenince sağlayıcı `451 Ratelimit` dedi ve **34 bildirim
      düştü**; artık kova dolduğunda iş başarısız olmuyor, kuyruğa geri
      bırakılıyor
- [ ] Sayılar sağlayıcının **gerçek** sınırıyla eşitlendi. Şu an temkinli
      varsayılan: dakikada 20, saatte 400. Hostinger planının sınırı
      öğrenilip `BYS_POSTA_DAKIKADA` / `BYS_POSTA_SAATTE` ona göre ayarlanmalı
- [ ] `php artisan queue:failed` **boş**. Şu an 39 kayıt var (36'sı 03.09
      hız sınırından, 3'ü daha eski); canlıya çıkmadan incelenip
      `queue:flush` ile temizlenmeli — bunlar test verisi

## 4 · Güvenlik

- [ ] ⚠️ **`BYS_2FA_ZORUNLU=true`** — yetkili panelinde iki adımlı doğrulama
      zorunluluğu. Deneme sırasında **kapatıldı** (21.08.2026); canlıya
      çıkmadan geri açılacak (Plan v1.0 md.11 zorunlu sayıyor)
- [x] `APP_DEBUG=false`, `APP_ENV=production`
- [x] Oturum çerezi Secure + oturum verisi şifreli
- [x] Denetim kaydı veritabanı seviyesinde kilitli
- [x] Güvenlik başlıkları (HSTS, CSP, X-Frame-Options)
- [ ] `php artisan bys:pilot-verisi --sil` çalıştırıldı, tanıtım hesapları silindi
- [ ] Yönetici parolası değiştirildi ve kurtarma kodları güvenli yere alındı
- [ ] Kapı anahtarları **yalnızca gerçek cihazlar için** üretildi, IP kısıtı girildi
- [ ] `node tests/uctan-uca/bys-sertlestirme-denetimi.mjs` → uyarısız geçiyor

## 5 · Yedek ve kurtarma

- [x] Günlük yedek kurulu *(`/etc/cron.d/bys-yedek`, 03:40, 14 gün saklama)*
- [x] Yedek her gece **geri yüklenerek doğrulanıyor**
- [ ] ⚠️ **Yedek DIŞARIYA kopyalanıyor** (R2/S3) — şu an yalnızca aynı sunucuda;
      sunucu tamamen giderse yedek de gider
- [ ] Geri yükleme bir kez **elle denendi** ve süresi ölçüldü
- [ ] `.env` (**APP_KEY**) ayrıca güvenli bir yerde: bu anahtar olmadan şifreli
      kimlik evrakları bir daha açılamaz

## 6 · İzleme

- [ ] Site erişilebilirlik izlemesi kuruldu *(Minima CRM'deki Site İzleme'ye eklendi)*
- [ ] `bys-horizon` servisi düşerse haber veren bir kontrol var
- [ ] Disk doluluk uyarısı *(kart ve evrak dosyaları birikir)*

## 7 · Devir (kulübün sunucusuna taşınırsa)

- [ ] Hedef sunucuda: PHP 8.3+, PostgreSQL, Redis, başsız Chrome
- [ ] Yoksa yedek planlar: MySQL/MariaDB · veritabanı kuyruğu + cron · PHP PDF motoru
- [ ] `.env`'deki her yol yeni sunucuya göre güncellendi *(kodda sabit yol yok)*
- [ ] **Yükleme sınırı** (bu sunucudaki değerler): php-fpm
      `upload_max_filesize` **64M**, `post_max_size` **80M**; nginx
      `client_max_body_size` **96M** — yani nginx ≥ post_max ≥ upload_max.
      Sıra bozulursa taşan istek uygulamanın 413 sayfasına değil nginx'in
      çıplak 413'üne düşer. Ayrıca `config/livewire.php` içindeki
      `temporary_file_upload.rules` aynı tavanı taşımalı: 💣 Livewire kendi
      12 MB'lık varsayılanını aşan dosyayı SESSİZCE reddeder.
      Ölçüler: basın mensubu başvurusu üç evrakla 21 MB; bülten eki ve duyuru
      videosu 60 MB'a kadar.
      🚫 Havuz dosyasında değerin yanına `# yorum` YAZMA — php-fpm açılmaz ve
      o PHP'yi kullanan **tüm siteler 502** döner (25.08'de yaşandı).
- [ ] `opcache.max_accelerated_files` **en az 32000** — varsayılan 10.000 yetmiyor,
      site 9 kat yavaşlıyor
- [ ] Zamanlayıcı cron'u kuruldu *(evrak imhası)*
- [ ] Yedek cron'u kuruldu

---

## Geri yükleme (yedekten dönüş)

```bash
# 1) Veritabanı
gunzip -c /root/backups/bys/db-TARIH.sql.gz \
  | docker exec -i parabu-postgres psql -U parabu -d bys

# 2) Dosyalar
tar xzf /root/backups/bys/dosyalar-TARIH.tar.gz -C <uygulama-dizini>

# 3) Ortam dosyası (APP_KEY!)
cp /root/backups/bys/env-TARIH <uygulama-dizini>/.env

# 4) Önbellekleri tazele
bash <uygulama-dizini>/dagit.sh
```

## Günlük bakım komutları

```bash
bash /root/bys-yedekle.sh                                  # elle yedek
node tests/uctan-uca/bys-sertlestirme-denetimi.mjs         # sağlık denetimi
node tests/uctan-uca/bys-yetim-temizle.mjs --kuru          # yetim dosya taraması
php artisan bys:evrak-imha --kuru                          # imha sırası gelenler
systemctl status bys-horizon                               # kuyruk işleyicisi
```

*Son güncelleme: 21.08.2026*
