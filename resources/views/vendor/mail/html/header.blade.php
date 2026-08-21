@props(['url'])
{{-- ARCA Çorum FK — e-posta başlığı.
     Arma uzak görsel olarak gelir; e-posta istemcileri görselleri engelleyebilir,
     bu yüzden kulüp adı METİN olarak da yazılı (alt metne güvenmiyoruz). --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display:inline-block; text-decoration:none;">
<img src="{{ asset('marka/favicon-64.png') }}" width="44" height="44"
     alt="ARCA Çorum FK" style="display:block; margin:0 auto 10px; border:0;">
<span style="display:block; color:#16181D; font-size:17px; font-weight:700; letter-spacing:.01em;">
ARCA Çorum FK
</span>
<span style="display:block; color:#8b929c; font-size:12px; letter-spacing:.12em; text-transform:uppercase; margin-top:3px;">
Basın Yönetim Sistemi
</span>
</a>
</td>
</tr>
