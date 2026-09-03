<?php

namespace App\Http\Requests;

use App\Enums\BasvuruTuru;
use App\Enums\CalisanAraligi;
use App\Http\Requests\Concerns\EvrakKurallari;
use App\Models\Kurum;
use App\Rules\TelefonNumarasi;
use App\Rules\VergiNumarasi;
use App\Servisler\BasvuruUygunlugu;
use App\Support\IlIlce;
use App\Support\UlkeKodu;
use App\Support\WebAdresi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Kurumsal başvuru formu -- Plan v1.0 md.3.1.
 * Doğrulama SUNUCUDA; tarayıcı tarafı yalnızca kolaylık.
 *
 * 🔑 Evrak da bu formda alınır (Revizyon md.3.1): başvuru tek adımda tamamlanır,
 * arada hesap açma / giriş yapma adımı yoktur.
 */
class KurumBasvuruIstegi extends FormRequest
{
    use EvrakKurallari;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Kurum
            'resmi_unvan' => ['required', 'string', 'min:3', 'max:200'],
            'adres' => ['required', 'string', 'max:300'],
            // 🔑 İl/ilçe SERBEST METİN DEĞİL: istemcinin gönderdiği çift resmi
            // listeyle karşılaştırılır (md.5.1).
            'il' => ['required', 'string', Rule::in(IlIlce::iller())],
            'ilce' => ['required', 'string', function (string $alan, mixed $deger, \Closure $hata) {
                if (! IlIlce::gecerliMi($this->input('il'), (string) $deger)) {
                    $hata('Seçilen ilçe, ile ait değil.');
                }
            }],
            'kurum_telefon_ulke' => ['required', Rule::in(UlkeKodu::kodlar())],
            // Kurum telefonu SABİT HAT olabilir: cep zorunluluğu yok.
            'kurum_telefon' => ['required', 'string', 'max:25', new TelefonNumarasi('kurum_telefon_ulke', cep: false)],
            'kurum_eposta' => ['required', 'email:rfc', 'max:150'],
            'vergi_dairesi' => ['required', 'string', 'max:100'],
            'vergi_no' => ['required', 'string', new VergiNumarasi, $this->vergiNoTekilligi()],
            'calisan_araligi' => ['required', Rule::enum(CalisanAraligi::class)],

            'yayin_platformlari' => ['required', 'array', 'min:1'],
            'yayin_platformlari.*.ad' => ['required', 'string', 'max:120'],
            // 🔑 Sema `prepareForValidation`'da tamamlaniyor: basvuran
            // `ornek.com` yazabilmeli (Cuneyt Bey revizyonu 03.09.2026).
            'yayin_platformlari.*.url' => ['required', 'url', 'max:300'],

            'sosyal_medya' => ['array'],
            'sosyal_medya.*' => ['nullable', 'url', 'max:300'],

            // Yetkili kişi -- hesap ONAY anında bu kişiye açılır
            'yetkili_ad' => ['required', 'string', 'min:3', 'max:120'],
            // 🔑 `unique` DEĞİL: başvurusu reddedilen kurum yetkilisi aynı
            // e-postayla yeniden başvurabilmeli (bkz. BasvuruUygunlugu).
            'yetkili_eposta' => ['required', 'email:rfc', 'max:150', BasvuruUygunlugu::kural(BasvuruTuru::Kurum)],
            'yetkili_telefon_ulke' => ['required', Rule::in(UlkeKodu::kodlar())],
            'yetkili_telefon' => ['required', 'string', 'max:25', new TelefonNumarasi('yetkili_telefon_ulke')],

            // KVKK -- açık rıza olmadan başvuru alınmaz (md.11)
            'kvkk_aydinlatma' => ['accepted'],
            'kvkk_riza' => ['accepted'],
        ] + $this->evrakKurallari(BasvuruTuru::Kurum);
    }

    /**
     * Aynı vergi numarasıyla ikinci kurum kaydı açılamaz. Yetkilinin KENDİ
     * önceki (akredite olmayan) kurum kaydı hariç: yeniden başvuran kişi kendi
     * numarasına takılmamalı.
     */
    private function vergiNoTekilligi(): Unique
    {
        $kural = Rule::unique('kurumlar', 'vergi_no')->whereNull('deleted_at');
        $onceki = Kurum::yetkilininOncekiKurumu((string) $this->input('yetkili_eposta'));

        return $onceki === null ? $kural : $kural->ignore($onceki->id);
    }

    public function attributes(): array
    {
        return [
            'resmi_unvan' => 'ticari unvan', 'adres' => 'açık adres', 'il' => 'il', 'ilce' => 'ilçe',
            'kurum_telefon' => 'telefon', 'kurum_eposta' => 'kurumsal e-posta',
            'vergi_dairesi' => 'vergi dairesi', 'vergi_no' => 'vergi numarası',
            'calisan_araligi' => 'çalışan sayısı', 'yayin_platformlari' => 'web siteleri ve yayın kanalları',
            'yetkili_ad' => 'adı soyadı', 'yetkili_eposta' => 'yetkili e-posta adresi',
            'yetkili_telefon' => 'yetkili telefonu',
            'kurum_telefon_ulke' => 'kurum telefonu ülke kodu',
            'yetkili_telefon_ulke' => 'yetkili telefonu ülke kodu',
            'kvkk_aydinlatma' => 'aydınlatma metni onayı', 'kvkk_riza' => 'açık rıza onayı',
        ] + $this->evrakAdlari(BasvuruTuru::Kurum);
    }

    public function messages(): array
    {
        return [
            'vergi_no.unique' => 'Bu vergi numarasıyla kayıtlı bir kurum zaten var. Kurumunuz başvurduysa yetkilisiyle görüşün.',
            'kvkk_aydinlatma.accepted' => 'Aydınlatma metnini okuduğunuzu onaylamalısınız.',
            'kvkk_riza.accepted' => 'Başvurunun değerlendirilebilmesi için açık rıza gereklidir.',
            'yayin_platformlari.min' => 'En az bir yayın adresi girmelisiniz.',
            'yayin_platformlari.*.url.url' => 'Geçerli bir web sitesi adresi girin.',
            'sosyal_medya.*.url' => 'Geçerli bir profil bağlantısı girin.',
            'evraklar.*.required' => ':attribute yüklemelisiniz.',
        ];
    }

    /**
     * 🔑 URL'lerin semasini SUNUCUDA tamamla. Alanlar artik `type="url"`
     * degil; "http:// yazmaya zorlamamaliyiz" (Cuneyt Bey, 03.09.2026).
     */
    protected function prepareForValidation(): void
    {
        $this->evrakTaslaginiCanlandir(BasvuruTuru::Kurum);

        $platformlar = $this->input('yayin_platformlari');

        if (is_array($platformlar)) {
            $this->merge(['yayin_platformlari' => array_map(
                fn ($satir) => is_array($satir)
                    ? ['url' => WebAdresi::duzelt($satir['url'] ?? null)] + $satir
                    : $satir,
                $platformlar,
            )]);
        }

        $sosyal = $this->input('sosyal_medya');

        if (is_array($sosyal)) {
            $this->merge(['sosyal_medya' => WebAdresi::dizi($sosyal)]);
        }
    }
}
