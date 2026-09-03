<?php

namespace App\Http\Requests;

use App\Enums\BasvuruTuru;
use App\Enums\DeneyimAraligi;
use App\Http\Requests\Concerns\EvrakKurallari;
use App\Rules\TelefonNumarasi;
use App\Servisler\BasvuruUygunlugu;
use App\Support\IlIlce;
use App\Support\UlkeKodu;
use App\Support\WebAdresi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Basın mensubu ve içerik üreticisi başvurusu -- Plan v1.0 md.3.2 / md.3.3.
 * Tek sınıf, türe göre değişen kurallar: iki formun ortak alanı çok.
 */
class BireyselBasvuruIstegi extends FormRequest
{
    use EvrakKurallari;

    public function authorize(): bool
    {
        return true;
    }

    public function tur(): BasvuruTuru
    {
        return $this->routeIs('*icerik-ureticisi*')
            ? BasvuruTuru::IcerikUreticisi
            : BasvuruTuru::BasinMensubu;
    }

    /** Davet bağlantısıyla gelindiyse ad/e-posta/kurum sabittir. */
    public function davetliMi(): bool
    {
        return $this->routeIs('davet.*');
    }

    public function rules(): array
    {
        $kurallar = [
            'adres' => ['required', 'string', 'max:300'],
            // 🔑 İl/ilçe SERBEST METİN DEĞİL: çift resmi listeyle doğrulanır (md.5.1).
            'il' => ['required', 'string', Rule::in(IlIlce::iller())],
            'ilce' => ['required', 'string', function (string $alan, mixed $deger, \Closure $hata) {
                if (! IlIlce::gecerliMi($this->input('il'), (string) $deger)) {
                    $hata('Seçilen ilçe, ile ait değil.');
                }
            }],
            'telefon_ulke' => ['required', Rule::in(UlkeKodu::kodlar())],
            'telefon' => ['required', 'string', 'max:25', new TelefonNumarasi('telefon_ulke')],

            'basin_karti_var' => ['required', 'boolean'],

            'kvkk_aydinlatma' => ['accepted'],
            'kvkk_riza' => ['accepted'],
        ];

        // Davette kimlik bilgisi kurumdan gelir, başvuran değiştiremez.
        if (! $this->davetliMi()) {
            $kurallar['ad_soyad'] = ['required', 'string', 'min:3', 'max:120'];
            // 🔑 `unique` DEĞİL: reddedilen ya da ayrılan kişi aynı e-postayla
            // yeniden başvurabilmeli. Engel varsa sebebini kural kendisi yazar.
            $kurallar['eposta'] = ['required', 'email:rfc', 'max:150', BasvuruUygunlugu::kural($this->tur())];
        }

        if ($this->tur() === BasvuruTuru::BasinMensubu) {
            if (! $this->davetliMi()) {
                // Yalnızca AKREDİTE kurum seçilebilir (md.3.2 ön koşulu).
                $kurallar['kurum_ulid'] = ['required', 'string', Rule::exists('kurumlar', 'ulid')
                    ->where('akreditasyon_durumu', 'akredite')
                    ->whereNull('deleted_at')];
            }
            $kurallar['sigorta_212_var'] = ['required', 'boolean'];
            // Serbest rakam DEGIL aralik (Cuneyt Bey revizyonu 03.09.2026).
            $kurallar['calisma_yili'] = ['required', Rule::enum(DeneyimAraligi::class)];
        } else {
            // İçerik üreticisinde en az bir platform bağlantısı istenir.
            $kurallar['sosyal_medya'] = ['required', 'array'];
            $kurallar['sosyal_medya.*'] = ['nullable', 'url', 'max:300'];
        }

        // Evrak da bu formda alınır (Revizyon md.3.1): fotoğraf, kimlik ve
        // -- basın mensubunda -- çalışma belgesi.
        return $kurallar + $this->evrakKurallari($this->tur());
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->tur() === BasvuruTuru::IcerikUreticisi
                && count(array_filter($this->input('sosyal_medya', []))) === 0) {
                $v->errors()->add('sosyal_medya', 'En az bir profil veya yayın bağlantısı girmelisiniz.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'ad_soyad' => 'ad soyad', 'eposta' => 'e-posta', 'telefon' => 'telefon',
            'telefon_ulke' => 'telefon ülke kodu',
            'adres' => 'adres', 'il' => 'il', 'ilce' => 'ilçe',
            'kurum_ulid' => 'kurum', 'sigorta_212_var' => '212 sigortası',
            'basin_karti_var' => 'basın kartı', 'calisma_yili' => 'medya sektöründeki deneyim',
            'sosyal_medya' => 'yayın kanalları',
            'kvkk_aydinlatma' => 'aydınlatma metni onayı', 'kvkk_riza' => 'açık rıza onayı',
        ] + $this->evrakAdlari($this->tur());
    }

    public function messages(): array
    {
        return [
            'kurum_ulid.required' => 'Çalıştığınız medya kuruluşunu seçmelisiniz.',
            'kurum_ulid.exists' => 'Seçilen medya kuruluşu onaylı değil. Kuruluş önce kendi başvurusunu tamamlamalı.',
            'sosyal_medya.*.url' => 'Geçerli bir profil veya yayın bağlantısı girin.',
            'kvkk_aydinlatma.accepted' => 'Aydınlatma metnini okuduğunuzu onaylamalısınız.',
            'kvkk_riza.accepted' => 'Başvurunun değerlendirilebilmesi için açık rıza gereklidir.',
            'evraklar.*.required' => ':attribute yüklemelisiniz.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->evrakTaslaginiCanlandir($this->tur());

        // Radyo düğmeleri "1"/"0" gönderir; boolean kuralı için normalleştir.
        foreach (['basin_karti_var', 'sigorta_212_var'] as $alan) {
            if ($this->has($alan)) {
                $this->merge([$alan => filter_var($this->input($alan), FILTER_VALIDATE_BOOLEAN)]);
            }
        }

        /*
         * 🔑 Yayın kanallarında sema SUNUCUDA tamamlanır: başvuran
         * `ornek.com` ya da `instagram.com/kullanici` yazabilmeli
         * (Cuneyt Bey revizyonu 03.09.2026).
         */
        $sosyal = $this->input('sosyal_medya');

        if (is_array($sosyal)) {
            $this->merge(['sosyal_medya' => WebAdresi::dizi($sosyal)]);
        }
    }
}
