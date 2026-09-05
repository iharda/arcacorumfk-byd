<?php

namespace App\Policies;

use App\Enums\AkreditasyonDurumu;
use App\Enums\BasvuruDurumu;
use App\Models\Akreditasyon;
use App\Models\User;

class AkreditasyonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('akreditasyon.gor');
    }

    public function view(User $user, Akreditasyon $akreditasyon): bool
    {
        /*
         * 💥 KENDİ akreditasyonunu görmek için yetki GEREKMEZ.
         * `akreditasyon.gor` BAŞKALARININ kaydını görme yetkisidir ve basın
         * mensubu / içerik üreticisi rollerinde yok. Bu kontrol en başta
         * durduğu için üye kendi kartının görselini açamıyor, "Kartım"
         * ekranında kart boş bir kutu olarak kalıyordu (`/kart/{ulid}/gorsel`
         * → 403). PDF indirme kendi sorgusuyla çalıştığı için sorun yalnızca
         * görselde görünüyordu.
         */
        if ($akreditasyon->kullanici_id === $user->id) {
            return true;
        }

        if (! $user->can('akreditasyon.gor')) {
            return false;
        }

        if ($user->hasAnyRole([User::ROL_SUPER, User::ROL_YETKILI])) {
            return true;
        }

        // Kurum yalnızca kendi çalışanlarını, birey yalnızca kendini görür.
        return $user->hasRole(User::ROL_KURUM)
            ? $akreditasyon->kurum_id === $user->kurum_id
            : $akreditasyon->kullanici_id === $user->id;
    }

    /**
     * Akredite kişiden belge isteyebilir mi? -- Cüneyt Bey revizyonu
     * (05.09.2026).
     *
     * 🔑 Başvurunun "İnceleniyor" olması İSTENMEZ; istenen tam tersi:
     * karara bağlanmış ve kartı AKTİF bir akreditasyon. `BasvuruPolicy::
     * eksikEvrakIste` karar öncesinin kuralıdır, bu onun karar sonrası eşi.
     *
     * ⚠️ "Zaten açık bir talep var" durumu BURADA sorulmaz: o geçici bir
     * engel, yetkisizlik değil. Ekran düğmeyi pasifleştirip sebebini yazar
     * (Inceleme::pasifSebebi kalıbı), servis de ayrıca doğrular.
     */
    public function belgeIste(User $user, Akreditasyon $akreditasyon): bool
    {
        return $user->can('basvuru.incele')
            && $akreditasyon->durum === AkreditasyonDurumu::Aktif
            && $akreditasyon->basvuru?->durum === BasvuruDurumu::Onaylandi;
    }

    public function create(User $user): bool
    {
        return false;   // Onaylanan başvurudan doğar.
    }

    public function update(User $user, Akreditasyon $akreditasyon): bool
    {
        return false;   // Değişiklik AkreditasyonAkisi üzerinden.
    }

    public function delete(User $user, Akreditasyon $akreditasyon): bool
    {
        return false;   // Silinmez; iptal edilir, geçmiş korunur.
    }
}
