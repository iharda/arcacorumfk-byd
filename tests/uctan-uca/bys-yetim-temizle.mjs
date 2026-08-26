/**
 * Veritabaninda karsiligi olmayan evrak/kart/icerik dosyalarini bulur ve siler.
 * (icerik = duyuru gorseli+videosu, bulten ekleri -- 60 MB'lik yetim video pahali.)
 * Testlerden ya da yarim kalmis islerden arta kalanlari toplar.
 * node /root/bys-yetim-temizle.mjs [--kuru]
 */
import { execFileSync } from 'node:child_process';
const kuru = process.argv.includes('--kuru');
const kod = `
$rapor = [];
/* 🔒 HER LISTE withTrashed(): yumusak silinmis kayit geri alinabilir, dosyasi
      duruyor olmali. Trashed'i atlayan bir liste, geri alinabilir icerigin
      dosyasini yetim sanip SILER. */
$icerikKayitli = App\\Models\\Duyuru::withTrashed()->whereNotNull('gorsel_yolu')->pluck('gorsel_yolu')
    ->merge(App\\Models\\Duyuru::withTrashed()->whereNotNull('video_yolu')->pluck('video_yolu'))
    // Bulten ekleri dizi: duzlestir.
    ->merge(App\\Models\\Bulten::withTrashed()->whereNotNull('ekler')->pluck('ekler')->flatten())
    ->filter()->unique()->values()->all();

foreach ([['evrak', App\\Models\\Evrak::withTrashed()->whereNotNull('yol')->pluck('yol')->all()],
          ['kart',  App\\Models\\Kart::whereNotNull('pdf_yolu')->pluck('pdf_yolu')
                        ->merge(App\\Models\\Kart::whereNotNull('gorsel_yolu')->pluck('gorsel_yolu'))->all()],
          ['icerik', $icerikKayitli]] as [$disk, $kayitli]) {
    $diskte = Illuminate\\Support\\Facades\\Storage::disk($disk)->allFiles();
    $yetim = array_values(array_diff($diskte, $kayitli));
    if (! ${kuru ? 'true' : 'false'}) {
        foreach ($yetim as $y) { Illuminate\\Support\\Facades\\Storage::disk($disk)->delete($y); }
    }
    $rapor[] = $disk . '=' . count($yetim) . '/' . count($diskte);
}
echo 'YETIM:' . implode(' ', $rapor);`;
const cikti = execFileSync('sudo', ['-u', 'bys', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: (process.env.BYS_KOK ?? import.meta.dirname + '/../..'), encoding: 'utf8', timeout: 120000 });
console.log((cikti.match(/YETIM:.*/) || ['sonuç yok'])[0] + (kuru ? '  (kuru çalışma, silinmedi)' : '  → silindi'));
