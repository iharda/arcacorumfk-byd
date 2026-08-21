<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roller ve yetkiler -- Plan v1.0 md.2.
 *
 * 💥 ValCert dersi: "is_admin" gibi TUM tikleri yok sayan bir bayrak KOYMA.
 * Super rolu bile yetkileri acikca alir; boylece "rolu degistirdim ama
 * degismedi" tuzagi bastan kapali. Kontrol policy'lerde yapilir.
 */
class RolYetkiSeeder extends Seeder
{
    /** @var array<string, string> yetki => aciklama */
    public const YETKILER = [
        'basvuru.gor'          => 'Başvuruları görüntüleme',
        'basvuru.incele'       => 'Başvuruyu incelemeye alma, eksik evrak talebi',
        'basvuru.karar'        => 'Onay / red kararı verme',

        'kurum.gor'            => 'Kurumları görüntüleme',
        'kurum.yonet'          => 'Kurum bilgisi düzenleme',
        'kurum.akredite'       => 'Kurumu akredite etme / akreditasyonu kaldırma',

        'akreditasyon.gor'     => 'Akreditasyonları görüntüleme',
        'akreditasyon.aski'    => 'Askıya alma / yeniden aktifleştirme',
        'akreditasyon.iptal'   => 'Akreditasyon iptali',

        'kart.uret'            => 'Basın kartı üretme / yeniden üretme',
        'kart.indir'           => 'Kart PDF indirme',

        'kapi.yonet'           => 'Turnike istemcisi ve API anahtarı yönetimi',
        'gecis.gor'            => 'Geçiş kayıtlarını görüntüleme',

        'icerik.yonet'         => 'Duyuru, antrenman takvimi, bülten yönetimi',

        'rapor.gor'            => 'Raporları görüntüleme',
        'rapor.disaaktar'      => 'Rapor dışa aktarma (CSV/Excel)',

        'kullanici.yonet'      => 'Kullanıcı ve rol yönetimi',
        'ayar.yonet'           => 'Sistem ayarları',
        'denetim.gor'          => 'Denetim kaydını görüntüleme',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::YETKILER) as $yetki) {
            Permission::findOrCreate($yetki, 'web');
        }

        // Super: her sey. Yine de yetkiler ACIKCA verilir (bayrak yok).
        Role::findOrCreate(User::ROL_SUPER, 'web')
            ->syncPermissions(array_keys(self::YETKILER));

        // Yetkili (kulup): tek kademe onay merci. Kullanici/rol yonetimi ve
        // kapi anahtari uretimi bilerek DISARIDA -- onlar super'de.
        Role::findOrCreate(User::ROL_YETKILI, 'web')->syncPermissions([
            'basvuru.gor', 'basvuru.incele', 'basvuru.karar',
            'kurum.gor', 'kurum.yonet', 'kurum.akredite',
            'akreditasyon.gor', 'akreditasyon.aski', 'akreditasyon.iptal',
            'kart.uret', 'kart.indir',
            'gecis.gor',
            'icerik.yonet',
            'rapor.gor', 'rapor.disaaktar',
        ]);

        // Kurum hesabi: YALNIZCA kendi calisanlari. Kapsam policy'de kurum_id
        // ile daraltilir -- yetkiye sahip olmak "hepsini gor" demek DEGIL.
        Role::findOrCreate(User::ROL_KURUM, 'web')->syncPermissions([
            'basvuru.gor',
            'akreditasyon.gor',
        ]);

        // Birey hesaplari: kendi kayitlari + medya merkezi icerigi (okuma).
        foreach ([User::ROL_BASIN, User::ROL_ICERIK] as $rol) {
            Role::findOrCreate($rol, 'web')->syncPermissions([
                'kart.indir',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
