<?php

namespace App\Servisler;

use App\Enums\BasvuruTuru;
use App\Models\Basvuru;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Onaylanan başvurudan hesap açar -- Revizyon md.3.2.
 *
 * 🔑 Hesap BAŞVURU anında değil ONAY anında doğar: onaylanmayan kişinin hesabı
 * hiç açılmaz, panele erişim sorunu kaynağında biter.
 *
 * 🔒 Sistem ŞİFRE ÜRETİP GÖNDERMEZ. Kullanıcı şifresini onay e-postasındaki
 * imzalı, süreli bağlantıyla kendisi belirler (e-postada düz metin şifre,
 * posta kutusu ele geçtiğinde hesabı da verir; ayrıca kutuda süresiz durur).
 *
 * 🪤 Aynı e-postayla hesap VARSA yenisi açılmaz, mevcut hesap yeniden
 * etkinleştirilir: reddedilip yeniden başvuran ya da kurumundan ayrılıp geri
 * dönen kişi bu yoldan geçer.
 */
class HesapAcici
{
    public function __construct(private DenetimYazici $denetim) {}

    /**
     * @return array{0: User, 1: bool} kullanıcı ve şifre belirlemesi gerekip
     *                                 gerekmediği (yeni hesap ya da hiç
     *                                 etkinleştirilmemiş hesap ise true)
     */
    public function onaydanOlustur(Basvuru $basvuru): array
    {
        $eposta = $basvuru->basvuranEpostasi()
            ?? throw new RuntimeException('Başvuruda e-posta adresi yok; hesap açılamaz.');

        [$rol, $eskiRol] = $this->roller($basvuru->tur);

        // Kişisel bilgiler onaya kadar başvurunun üstünde durur; hesap açılınca
        // oraya taşınır (kurumsal başvuruda bu alanlar yoktur).
        $form = $basvuru->form_verisi ?? [];

        return DB::transaction(function () use ($basvuru, $eposta, $rol, $eskiRol, $form) {
            // withTrashed: ayrılıp silinmiş hesap yeniden kullanılır, ikinci
            // kayıt açılmaz (e-posta zaten benzersiz).
            $kullanici = User::withTrashed()->where('email', $eposta)->first();
            $yeni = $kullanici === null;

            if ($yeni) {
                $kullanici = User::create([
                    'name' => $basvuru->basvuran_ad,
                    'email' => $eposta,
                    // Yer tutucu; kullanıcı şifresini kendisi belirleyecek.
                    'password' => Hash::make(Str::random(64)),
                    'telefon' => $basvuru->basvuran_telefon,
                    'adres' => $form['adres'] ?? null,
                    'il' => $form['il'] ?? null,
                    'ilce' => $form['ilce'] ?? null,
                    'kurum_id' => $basvuru->kurum_id,
                    'aktif' => true,
                ]);

                $this->denetim->yaz('hesap.olusturuldu', $kullanici, yeni: [
                    'eposta' => $eposta, 'kaynak' => 'basvuru_onayi',
                ]);
            } else {
                if ($kullanici->trashed()) {
                    $kullanici->restore();
                }

                // Ayrılış işareti kalkar; kişi yeniden süreçte.
                $kullanici->forceFill(array_filter([
                    'name' => $basvuru->basvuran_ad,
                    'telefon' => $basvuru->basvuran_telefon,
                    'adres' => $form['adres'] ?? null,
                    'il' => $form['il'] ?? null,
                    'ilce' => $form['ilce'] ?? null,
                    'kurum_id' => $basvuru->kurum_id,
                ], fn ($deger) => $deger !== null) + [
                    'aktif' => true,
                    'ayrildi_at' => null,
                ])->save();

                $this->denetim->yaz('hesap.yeniden_etkinlestirildi', $kullanici, yeni: [
                    'eposta' => $eposta, 'kaynak' => 'basvuru_onayi',
                ]);
            }

            // 🪤 syncRoles DEĞİL: gazetenin sahibi hem kurum yetkilisi hem basın
            // mensubu olabilir. Yalnızca DİĞER bireysel tür rolü kaldırılır.
            if ($eskiRol !== null) {
                $kullanici->removeRole($eskiRol);
            }

            $kullanici->assignRole($rol);

            $basvuru->update(['kullanici_id' => $kullanici->id]);
            // İlişki tazelensin: onay akışının devamı (akreditasyon, bildirim)
            // bu kullanıcıyı kullanır.
            $basvuru->setRelation('kullanici', $kullanici);

            // Hiç etkinleştirilmemiş hesap da şifre belirlemelidir: hesap yeni
            // açılmış olabileceği gibi, eski başvurusunda bağlantıya hiç
            // tıklamamış da olabilir.
            return [$kullanici, $kullanici->email_verified_at === null];
        });
    }

    /**
     * Başvuru türünün rolü ve (varsa) artık geçersiz olan tür rolü.
     *
     * @return array{0: string, 1: ?string}
     */
    private function roller(BasvuruTuru $tur): array
    {
        return match ($tur) {
            BasvuruTuru::Kurum => [User::ROL_KURUM, null],
            BasvuruTuru::BasinMensubu => [User::ROL_BASIN, User::ROL_ICERIK],
            BasvuruTuru::IcerikUreticisi => [User::ROL_ICERIK, User::ROL_BASIN],
        };
    }
}
