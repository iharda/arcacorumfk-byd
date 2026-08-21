<?php

namespace App\Servisler;

use App\Models\Ayar;
use App\Models\Davet;
use App\Models\Kurum;
use App\Models\User;
use App\Notifications\CalisanDaveti;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Çalışan daveti -- Plan v1.0 md.5.2 "Yol B".
 *
 * 🔒 Ham token SAKLANMAZ; yalnızca sha256 hash'i tutulur ve e-postayla bir kez
 * gönderilir. Kaybolursa yeniden üretilir (eski token geçersiz olur).
 */
class DavetAkisi
{
    public function __construct(private DenetimYazici $denetim) {}

    public function olustur(Kurum $kurum, string $adSoyad, string $eposta): Davet
    {
        if (! $kurum->akrediteMi()) {
            throw new RuntimeException('Kurum akredite olmadan çalışan daveti gönderilemez.');
        }

        if (User::where('email', $eposta)->exists()) {
            throw new RuntimeException('Bu e-posta ile zaten bir hesap var; kişi doğrudan başvurabilir.');
        }

        if ($kurum->kontenjanDoldu()) {
            throw new RuntimeException('Kurum kontenjanı dolu. Yeni davet için kulüple görüşün.');
        }

        return DB::transaction(function () use ($kurum, $adSoyad, $eposta) {
            // Aynı kişiye bekleyen davet varsa yenisi eskisini geçersiz kılar.
            $kurum->davetler()
                ->where('eposta', $eposta)
                ->whereNull('kullanildi_at')
                ->whereNull('iptal_at')
                ->update(['iptal_at' => now()]);

            $token = Str::random(48);

            $davet = $kurum->davetler()->create([
                'olusturan_id'     => Auth::id(),
                'ad_soyad'         => $adSoyad,
                'eposta'           => $eposta,
                'token_hash'       => Davet::tokenHash($token),
                'gecerlilik_bitis' => now()->addDays((int) Ayar::al('davet_gecerlilik_gun', 7)),
            ]);

            $this->denetim->yaz('davet.olusturuldu', $davet, yeni: [
                'eposta' => $eposta, 'kurum' => $kurum->resmi_unvan,
            ]);

            Notification::route('mail', $eposta)->notify(new CalisanDaveti($davet, $token));

            return $davet;
        });
    }

    public function yenidenGonder(Davet $davet): void
    {
        if (! $davet->kullanilabilirMi()) {
            throw new RuntimeException('Bu davet artık geçerli değil; yenisini oluşturun.');
        }

        $token = Str::random(48);

        $davet->update([
            'token_hash'       => Davet::tokenHash($token),
            'gecerlilik_bitis' => now()->addDays((int) Ayar::al('davet_gecerlilik_gun', 7)),
            'gonderim_sayisi'  => $davet->gonderim_sayisi + 1,
        ]);

        $this->denetim->yaz('davet.yeniden_gonderildi', $davet);

        Notification::route('mail', $davet->eposta)->notify(new CalisanDaveti($davet, $token));
    }

    public function iptalEt(Davet $davet): void
    {
        $davet->update(['iptal_at' => now()]);

        $this->denetim->yaz('davet.iptal', $davet);
    }

    /** Ham token ile daveti bulur. Geçersizse null. */
    public function tokenlaBul(string $token): ?Davet
    {
        $davet = Davet::with('kurum')->where('token_hash', Davet::tokenHash($token))->first();

        return $davet?->kullanilabilirMi() ? $davet : null;
    }
}
