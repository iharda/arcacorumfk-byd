<?php

namespace App\Filament\Yonetim\Auth;

use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Kulüp yönetimi giriş ekranı -- İbrahim Bey, 05.09.2026.
 *
 * 💀 Kamu girişinde (`/giris`) şifresini doğru giren bir kulüp yetkilisi
 * buraya YÖNLENDİRİLİYOR: oturumu kapatılıyor, çünkü iki adımlı doğrulama
 * yalnız burada zorunlu ve oradan içeri almak onu atlatan bir yan kapı olurdu
 * (GirisController). Yönlendirme doğru ama kullanıcıya anlatılmıyordu:
 *
 *   · tek açıklama 6 saniyede kaybolan bir köşe bildirimiydi;
 *   · geriye kalan ekran Filament'in çıplak varsayılanıydı ve neresi olduğunu
 *     söyleyen KALICI tek kelime taşımıyordu -- az önce ayrıldığı sayfaya çok
 *     benziyor, kişi "beni neden buraya attı" diye kalıyordu;
 *   · e-postasını ve şifresini baştan yazmak zorundaydı.
 *
 * 🔑 Bu sayfa iki şeyi düzeltir: sabit bir alt başlık (bildirim kaybolsa da
 * durur, adresi doğrudan açana da doğru bağlamı verir) ve kamu girişinden
 * taşınan e-posta.
 *
 * 🔒 ŞİFRE TAŞINMAZ. E-posta taşımak sızıntı değil: yönlendirme ancak şifre
 * DOĞRUYSA oluşuyor, yani "bu adres yönetici mi" bilgisi zaten karşı tarafta.
 *
 * 🪤 Sınıf `Filament/Yonetim/Pages` ALTINDA DEĞİL: panel o dizini
 * `discoverPages()` ile tarıyor ve giriş sayfası orada dursaydı normal bir
 * panel sayfası olarak da kaydedilirdi.
 */
class YonetimGirisi extends Login
{
    /** Kamu girişinin taşıdığı e-posta -- oturum anahtarı tek yerde. */
    public const EPOSTA_ANAHTARI = 'yonetim_girisi_eposta';

    public function mount(): void
    {
        parent::mount();

        if (filled($eposta = session(self::EPOSTA_ANAHTARI))) {
            $this->form->fill(['email' => $eposta]);
        }
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kulüp yönetimi girişi · iki adımlı doğrulama gerekir';
    }
}
