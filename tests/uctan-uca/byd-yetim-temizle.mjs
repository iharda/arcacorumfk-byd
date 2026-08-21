/**
 * Veritabaninda karsiligi olmayan evrak/kart dosyalarini bulur ve siler.
 * Testlerden ya da yarim kalmis islerden arta kalanlari toplar.
 * node /root/byd-yetim-temizle.mjs [--kuru]
 */
import { execFileSync } from 'node:child_process';
const kuru = process.argv.includes('--kuru');
const kod = `
$rapor = [];
foreach ([['evrak', App\\Models\\Evrak::withTrashed()->whereNotNull('yol')->pluck('yol')->all()],
          ['kart',  App\\Models\\Kart::whereNotNull('pdf_yolu')->pluck('pdf_yolu')
                        ->merge(App\\Models\\Kart::whereNotNull('gorsel_yolu')->pluck('gorsel_yolu'))->all()]] as [$disk, $kayitli]) {
    $diskte = Illuminate\\Support\\Facades\\Storage::disk($disk)->allFiles();
    $yetim = array_values(array_diff($diskte, $kayitli));
    if (! ${kuru ? 'true' : 'false'}) {
        foreach ($yetim as $y) { Illuminate\\Support\\Facades\\Storage::disk($disk)->delete($y); }
    }
    $rapor[] = $disk . '=' . count($yetim) . '/' . count($diskte);
}
echo 'YETIM:' . implode(' ', $rapor);`;
const cikti = execFileSync('sudo', ['-u', 'byd', 'php', 'artisan', 'tinker', '--execute', kod],
  { cwd: '/home/byd.ordolive.com/laravel', encoding: 'utf8', timeout: 120000 });
console.log((cikti.match(/YETIM:.*/) || ['sonuç yok'])[0] + (kuru ? '  (kuru çalışma, silinmedi)' : '  → silindi'));
