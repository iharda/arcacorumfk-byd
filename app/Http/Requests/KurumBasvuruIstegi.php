<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kurumsal başvuru formu -- Plan v1.0 md.3.1.
 * Doğrulama SUNUCUDA; tarayıcı tarafı yalnızca kolaylık.
 */
class KurumBasvuruIstegi extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Kurum
            'resmi_unvan'    => ['required', 'string', 'min:3', 'max:200'],
            'adres'          => ['required', 'string', 'max:300'],
            'il'             => ['required', 'string', 'max:60'],
            'ilce'           => ['required', 'string', 'max:60'],
            'kurum_telefon'  => ['required', 'string', 'max:25'],
            'kurum_eposta'   => ['required', 'email:rfc', 'max:150'],
            'vergi_dairesi'  => ['required', 'string', 'max:100'],
            'vergi_no'       => ['required', 'string', 'regex:/^\d{10,11}$/'],
            'calisan_sayisi' => ['required', 'integer', 'min:1', 'max:100000'],

            'yayin_platformlari'        => ['required', 'array', 'min:1'],
            'yayin_platformlari.*.ad'   => ['required', 'string', 'max:120'],
            'yayin_platformlari.*.url'  => ['required', 'url', 'max:300'],

            'sosyal_medya'          => ['array'],
            'sosyal_medya.*'        => ['nullable', 'url', 'max:300'],

            // Yetkili kişi -- hesap bu kişiye açılır
            'yetkili_ad'      => ['required', 'string', 'min:3', 'max:120'],
            'yetkili_eposta'  => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')],
            'yetkili_telefon' => ['required', 'string', 'max:25'],

            // KVKK -- açık rıza olmadan başvuru alınmaz (md.11)
            'kvkk_aydinlatma' => ['accepted'],
            'kvkk_riza'       => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'resmi_unvan' => 'resmi ünvan', 'adres' => 'adres', 'il' => 'il', 'ilce' => 'ilçe',
            'kurum_telefon' => 'kurum telefonu', 'kurum_eposta' => 'kurum e-postası',
            'vergi_dairesi' => 'vergi dairesi', 'vergi_no' => 'vergi numarası',
            'calisan_sayisi' => 'çalışan sayısı', 'yayin_platformlari' => 'yayın platformları',
            'yetkili_ad' => 'yetkili adı soyadı', 'yetkili_eposta' => 'yetkili e-postası',
            'yetkili_telefon' => 'yetkili telefonu',
            'kvkk_aydinlatma' => 'aydınlatma metni onayı', 'kvkk_riza' => 'açık rıza onayı',
        ];
    }

    public function messages(): array
    {
        return [
            'vergi_no.regex'          => 'Vergi numarası 10 veya 11 haneli olmalıdır.',
            'yetkili_eposta.unique'   => 'Bu e-posta adresiyle daha önce bir hesap açılmış. Giriş yapabilirsiniz.',
            'kvkk_aydinlatma.accepted' => 'Aydınlatma metnini okuduğunuzu onaylamalısınız.',
            'kvkk_riza.accepted'      => 'Başvurunun değerlendirilebilmesi için açık rıza gereklidir.',
            'yayin_platformlari.min'  => 'En az bir yayın platformu bağlantısı girmelisiniz.',
        ];
    }
}
