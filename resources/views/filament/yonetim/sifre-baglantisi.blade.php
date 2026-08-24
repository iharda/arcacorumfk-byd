{{-- Yetkili giriş sayfasındaki "şifremi unuttum" bağlantısı. Panelin kendi
     şifre sıfırlama sayfası kaldırıldı; tek rota kamuya açık `/sifremi-unuttum`. --}}
<div style="margin-top:.75rem; text-align:center;">
    <a href="{{ route('sifre.istek') }}"
       style="font-size:.875rem; text-decoration:underline; color:rgb(var(--primary-600));">
        Şifremi unuttum
    </a>
</div>
