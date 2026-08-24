<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Evrak alınamadı -- hangi form alanının hatalı olduğunu taşır.
 *
 * EvrakYukleyici düz `RuntimeException` fırlatır ve sebebi metinde yazar; ama
 * başvuru formunda üç ayrı yükleme kutusu var. Hatanın HANGİ kutuya ait olduğu
 * kaybolursa kullanıcı "dosya çok büyük" yazısını sayfanın tepesinde görür ve
 * hangi dosyayı değiştireceğini bilemez.
 */
class EvrakAlinamadi extends RuntimeException
{
    public function __construct(
        public readonly int $evrakTuruId,
        string $mesaj,
        ?Throwable $onceki = null,
    ) {
        parent::__construct($mesaj, 0, $onceki);
    }

    /** Form alanının adı: `evraklar.12`. */
    public function alan(): string
    {
        return "evraklar.{$this->evrakTuruId}";
    }
}
