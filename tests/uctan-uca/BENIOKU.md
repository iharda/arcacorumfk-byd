# Uçtan uca testler

Bu betikler **çalışan siteye** karşı koşar (tarayıcı otomasyonu + API çağrıları),
`phpunit` testi değildir. Amaç: akışın gerçekten baştan sona işlediğini görmek.

```bash
node tests/uctan-uca/bys-basvuru-akisi-testi.mjs     # kurumsal başvuru → onay
node tests/uctan-uca/bys-bireysel-akis-testi.mjs     # bireysel + davet + ayrılış
node tests/uctan-uca/bys-yeniden-basvuru-testi.mjs   # reddedilen/ayrılan yeniden başvurur
sudo -u bys php tests/uctan-uca/bys-islem-butunlugu.php  # işlem bütünlüğü (PHP, tarayıcısız)
sudo -u bys php tests/uctan-uca/bys-onayda-hesap-testi.php # hesap ONAY anında açılıyor mu (PHP)
sudo -u bys php tests/uctan-uca/bys-duzeltme-bileti-testi.php # panelsiz eksik evrak düzeltmesi (PHP)
sudo -u bys php tests/uctan-uca/bys-formda-evrak-testi.php # evrak başvuru formunda + yetim dosya (PHP)
node tests/uctan-uca/bys-form-alanlari-testi.mjs     # il/ilçe · telefon · vergi no · çalışan aralığı
node tests/uctan-uca/bys-kart-kapi-testi.mjs         # kart, QR, doğrulama API'si
node tests/uctan-uca/bys-icerik-testi.mjs            # duyuru/bülten/takvim yayını · bildirim · duyuru videosu
node tests/uctan-uca/bys-duyuru-video-testi.mjs      # duyuru videosu yönetim formundan yükleniyor mu
node tests/uctan-uca/bys-insan-senaryosu.mjs         # altı perdelik uçtan uca senaryo (ekran görüntülü)
node tests/uctan-uca/bys-panel-yonlendirme-testi.mjs # üç panel arası yönlendirme, yasak panel
node tests/uctan-uca/bys-pano-shot.mjs               # üç panelin panosu (masaüstü + 375px mobil)
node tests/uctan-uca/bys-degerlendirme-testi.mjs     # 1-5 değerlendirme + kulüp dışına sızmıyor
node tests/uctan-uca/bys-guvenlik-testi.mjs          # yetki sınırları
node tests/uctan-uca/bys-giris-testi.mjs             # yetkili girişi + 2FA + panel sınırı
node tests/uctan-uca/bys-tek-giris-testi.mjs         # tek giriş kapısı · kilit · panel seçimi · şifre sıfırlama
node tests/uctan-uca/bys-sertlestirme-denetimi.mjs   # canlıya hazırlık denetimi (salt okunur)
node tests/uctan-uca/bys-yuk-testi.mjs 15 24        # turnike ucu yük ölçümü
node tests/uctan-uca/bys-yetim-temizle.mjs --kuru    # yetim evrak/kart/içerik dosyası taraması
```

Birim testleri (tarayıcısız, veritabanısız — `tests/Unit`):

```bash
sudo -u bys php artisan test --testsuite=Unit   # VKN/TCKN sağlaması + telefon E.164
```

## ⚠️ Bilinmesi gerekenler

- **Testler üretime YAZAR.** Her biri kendi kaydını oluşturur ve `finally`
  bloğunda siler. Ayar değiştiren test, eski değeri saklayıp aynen geri yazar.
  Yarıda kesersen artık kalabilir: `bys-yetim-temizle.mjs` ile dosyaları,
  `@ornek.test` uzantılı hesapları elle temizle.
- **Hız sınırı:** başvuru gönderimi 10 dakikada 5 istek. Testleri arka arkaya
  koşarsan form 429 alır ve test "gönderilemedi" der. Aradan `php artisan
  cache:clear` geçirin ya da 10 dakika bekleyin.
- **Dış girdiler** (repoda DEĞİL, sunucuda durur):
  - `/root/.bys-admin-pass` — yönetici parolası
  - `/root/.bys-admin-totp` — yöneticinin TOTP gizli anahtarı
  - `<uygulama-dizini>/../test-dosyalari/` — örnek evrak dosyaları (pdf/jpg)
  - `bys-pano-shot.mjs` ve `bys-degerlendirme-testi.mjs` **pilot hesapları**
    kullanır (`*+pilot@ornek.test`, şifre `Pilot-Deneme-2026`). Yoksa önce
    `php artisan bys:pilot-verisi` ile üretin.
  Devirde bunlar yeni sunucuda yeniden üretilir.
- **Yük testi** geçiş kaydı yazar ve `mukerrer_okutma_saniye` ayarını geçici
  olarak 0 yapar; sonunda ikisini de geri alır. Yarıda kesersen ayarı elle
  30'a çevir.
- Chrome yolu `/root/.cache/puppeteer` altından okunur (test koşucusunun
  Chrome'u; uygulamanınki `.env` içindeki `BYS_CHROME`).
