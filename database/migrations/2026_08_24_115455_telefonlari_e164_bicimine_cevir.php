<?php

use App\Support\Telefon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Telefonları tek biçime çevirir -- Revizyon md.5.2.
 *
 * Eski akış `+90 XXX XXX XX XX` (boşluklu) saklıyordu, tanımadığı biçimi de
 * OLDUĞU GİBİ bırakıyordu. Yeni biçim E.164 (`+905321234567`): boşluksuz,
 * aranabilir, karşılaştırılabilir.
 *
 * 🪤 Çevrilemeyen değer (ör. 10 haneye tamamlanmamış numara) OLDUĞU GİBİ
 * bırakılır: veri kaybetmektense elde düzeltmek yeğdir.
 *
 * 💀 İLK HÂLİ BU SÖZÜ TUTMUYORDU (Düzeltme listesi md.1): `Telefon::e164()`
 * ülke kodunu HER ZAMAN parametreden alır, girdideki `+49`'u tanımaz.
 * Parametresiz çağrılınca varsayılan `+90` yapışıyordu:
 * `+49 170 1234567` → `+90491701234567`, `dahili 145` → `+90145`.
 * Artık yalnızca TR'ye çevrilebilen değerler yazılıyor.
 *
 * 💣 Ayrıca `basvurular.basvuran_telefon` sütununu 170000 numaralı göç
 * yaratıyor ve o BUNDAN SONRA çalışıyor: sıfırdan kurulumda bu göç
 * "column does not exist" ile patlıyordu. Sütun yoksa atlanıyor.
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
            // Sütunu yaratan göç bundan SONRA çalışıyor olabilir.
            if (! Schema::hasTable($tablo) || ! Schema::hasColumn($tablo, $sutun)) {
                continue;
            }

            DB::table($tablo)
                ->whereNotNull($sutun)
                ->orderBy('id')
                ->chunkById(200, function ($satirlar) use ($tablo, $sutun) {
                    foreach ($satirlar as $satir) {
                        $yeni = Telefon::trE164((string) $satir->{$sutun});

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
