# Uçtan uca testler

Bu betikler **çalışan siteye** karşı koşar (tarayıcı otomasyonu + API çağrıları),
`phpunit` testi değildir. Amaç: akışın gerçekten baştan sona işlediğini görmek.

```bash
node tests/uctan-uca/byd-basvuru-akisi-testi.mjs     # kurumsal başvuru → onay
node tests/uctan-uca/byd-bireysel-akis-testi.mjs     # bireysel + davet + ayrılış
node tests/uctan-uca/byd-yeniden-basvuru-testi.mjs   # reddedilen/ayrılan yeniden başvurur
sudo -u byd php tests/uctan-uca/byd-islem-butunlugu.php  # işlem bütünlüğü (PHP, tarayıcısız)
node tests/uctan-uca/byd-kart-kapi-testi.mjs         # kart, QR, doğrulama API'si
node tests/uctan-uca/byd-guvenlik-testi.mjs          # yetki sınırları
node tests/uctan-uca/byd-giris-testi.mjs             # giriş + 2FA + panel sınırı
node tests/uctan-uca/byd-sertlestirme-denetimi.mjs   # canlıya hazırlık denetimi (salt okunur)
node tests/uctan-uca/byd-yuk-testi.mjs 15 24        # turnike ucu yük ölçümü
node tests/uctan-uca/byd-yetim-temizle.mjs --kuru   # yetim dosya taraması
```

## ⚠️ Bilinmesi gerekenler

- **Testler üretime YAZAR.** Her biri kendi kaydını oluşturur ve `finally`
  bloğunda siler. Ayar değiştiren test, eski değeri saklayıp aynen geri yazar.
  Yarıda kesersen artık kalabilir: `byd-yetim-temizle.mjs` ile dosyaları,
  `@ornek.test` uzantılı hesapları elle temizle.
- **Hız sınırı:** başvuru gönderimi 10 dakikada 5 istek. Testleri arka arkaya
  koşarsan form 429 alır ve test "gönderilemedi" der. Aradan `php artisan
  cache:clear` geçirin ya da 10 dakika bekleyin.
- **Dış girdiler** (repoda DEĞİL, sunucuda durur):
  - `/root/.byd-admin-pass` — yönetici parolası
  - `/root/.byd-admin-totp` — yöneticinin TOTP gizli anahtarı
  - `/root/byd-test-dosyalari/` — örnek evrak dosyaları (pdf/jpg)
  Devirde bunlar yeni sunucuda yeniden üretilir.
- **Yük testi** geçiş kaydı yazar ve `mukerrer_okutma_saniye` ayarını geçici
  olarak 0 yapar; sonunda ikisini de geri alır. Yarıda kesersen ayarı elle
  30'a çevir.
- Chrome yolu `/root/.cache/puppeteer` altından okunur (test koşucusunun
  Chrome'u; uygulamanınki `.env` içindeki `BYD_CHROME`).
