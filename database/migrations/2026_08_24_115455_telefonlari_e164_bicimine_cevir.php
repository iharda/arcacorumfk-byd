<?php

use App\Support\Telefon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Telefonları tek biçime çevirir -- Revizyon md.5.2.
 *
 * Eski akış `+90 XXX XXX XX XX` (boşluklu) saklıyordu, tanımadığı biçimi de
 * OLDUĞU GİBİ bırakıyordu. Yeni biçim E.164 (`+905321234567`): boşluksuz,
 * aranabilir, karşılaştırılabilir.
 *
 * 🪤 Çevrilemeyen değer (ör. 10 haneye tamamlanmamış numara) OLDUĞU GİBİ
 * bırakılır: veri kaybetmektense elde düzeltmek yeğdir.
 */
return new class extends Migration
{
    /** @var array<string, string> tablo => sütun */
    private array $alanlar = [
        'users' => 'telefon',
        'basvurular' => 'basvuran_telefon',
        'kurumlar' => 'telefon',
    ];

    public function up(): void
    {
        foreach ($this->alanlar as $tablo => $sutun) {
            DB::table($tablo)
                ->whereNotNull($sutun)
                ->orderBy('id')
                ->chunkById(200, function ($satirlar) use ($tablo, $sutun) {
                    foreach ($satirlar as $satir) {
                        $yeni = Telefon::e164($satir->{$sutun});

                        if ($yeni === null || $yeni === $satir->{$sutun}) {
                            continue;
                        }

                        DB::table($tablo)->where('id', $satir->id)->update([$sutun => $yeni]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Geri dönüş YOK: eski biçim bilgi taşımıyordu, E.164'ten görüntüleme
        // biçimi her zaman üretilebilir (Telefon::goster).
    }
};
