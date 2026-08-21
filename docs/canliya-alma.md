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

- [x] SMTP çalışıyor *(Google Workspace, `arcacorumfk@ordolive.com`)*
- [ ] Gönderen adresi kulübün kendi alan adına taşındı
- [ ] **SPF · DKIM · DMARC** yeni alan adı için tanımlandı
- [ ] Bir test başvurusuyla e-postanın **spam'e düşmediği** doğrulandı

## 4 · Güvenlik

- [ ] ⚠️ **`BYD_2FA_ZORUNLU=true`** — yetkili panelinde iki adımlı doğrulama
      zorunluluğu. Deneme sırasında **kapatıldı** (21.08.2026); canlıya
      çıkmadan geri açılacak (Plan v1.0 md.11 zorunlu sayıyor)
- [x] `APP_DEBUG=false`, `APP_ENV=production`
- [x] Oturum çerezi Secure + oturum verisi şifreli
- [x] Denetim kaydı veritabanı seviyesinde kilitli
- [x] Güvenlik başlıkları (HSTS, CSP, X-Frame-Options)
- [ ] `php artisan byd:pilot-verisi --sil` çalıştırıldı, tanıtım hesapları silindi
- [ ] Yönetici parolası değiştirildi ve kurtarma kodları güvenli yere alındı
- [ ] Kapı anahtarları **yalnızca gerçek cihazlar için** üretildi, IP kısıtı girildi
- [ ] `node tests/uctan-uca/byd-sertlestirme-denetimi.mjs` → uyarısız geçiyor

## 5 · Yedek ve kurtarma

- [x] Günlük yedek kurulu *(`/etc/cron.d/byd-yedek`, 03:40, 14 gün saklama)*
- [x] Yedek her gece **geri yüklenerek doğrulanıyor**
- [ ] ⚠️ **Yedek DIŞARIYA kopyalanıyor** (R2/S3) — şu an yalnızca aynı sunucuda;
      sunucu tamamen giderse yedek de gider
- [ ] Geri yükleme bir kez **elle denendi** ve süresi ölçüldü
- [ ] `.env` (**APP_KEY**) ayrıca güvenli bir yerde: bu anahtar olmadan şifreli
      kimlik evrakları bir daha açılamaz

## 6 · İzleme

- [ ] Site erişilebilirlik izlemesi kuruldu *(Minima CRM'deki Site İzleme'ye eklendi)*
- [ ] `byd-horizon` servisi düşerse haber veren bir kontrol var
- [ ] Disk doluluk uyarısı *(kart ve evrak dosyaları birikir)*

## 7 · Devir (kulübün sunucusuna taşınırsa)

- [ ] Hedef sunucuda: PHP 8.3+, PostgreSQL, Redis, başsız Chrome
- [ ] Yoksa yedek planlar: MySQL/MariaDB · veritabanı kuyruğu + cron · PHP PDF motoru
- [ ] `.env`'deki her yol yeni sunucuya göre güncellendi *(kodda sabit yol yok)*
- [ ] `opcache.max_accelerated_files` **en az 32000** — varsayılan 10.000 yetmiyor,
      site 9 kat yavaşlıyor
- [ ] Zamanlayıcı cron'u kuruldu *(evrak imhası)*
- [ ] Yedek cron'u kuruldu

---

## Geri yükleme (yedekten dönüş)

```bash
# 1) Veritabanı
gunzip -c /root/backups/byd/db-TARIH.sql.gz \
  | docker exec -i parabu-postgres psql -U parabu -d byd

# 2) Dosyalar
tar xzf /root/backups/byd/dosyalar-TARIH.tar.gz -C /home/byd.ordolive.com/laravel

# 3) Ortam dosyası (APP_KEY!)
cp /root/backups/byd/env-TARIH /home/byd.ordolive.com/laravel/.env

# 4) Önbellekleri tazele
bash /home/byd.ordolive.com/laravel/dagit.sh
```

## Günlük bakım komutları

```bash
bash /root/byd-yedekle.sh                                  # elle yedek
node tests/uctan-uca/byd-sertlestirme-denetimi.mjs         # sağlık denetimi
node tests/uctan-uca/byd-yetim-temizle.mjs --kuru          # yetim dosya taraması
php artisan byd:evrak-imha --kuru                          # imha sırası gelenler
systemctl status byd-horizon                               # kuyruk işleyicisi
```

*Son güncelleme: 21.08.2026*
