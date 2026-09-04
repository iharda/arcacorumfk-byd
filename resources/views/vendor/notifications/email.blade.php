<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Saygılarımızla,<br>
ARCA Çorum FK
@endif

{{-- Subcopy --}}
@if (isset($actionText))
<x-slot:subcopy>
“{{ $actionText }}” düğmesi çalışmıyorsa aşağıdaki bağlantıyı kopyalayıp tarayıcınızın adres çubuğuna yapıştırın: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@elseif (! empty($dipnot))
{{-- 🔑 Düğmesi olmayan bildirimlerde de imzanın ALTINA ince bir not
     konabilsin diye ($mesaj->viewData['dipnot']). "Bu e-posta ...
     otomatik olarak gönderilmiştir." satırı buradan çıkıyor. --}}
<x-slot:subcopy>
{{ $dipnot }}
</x-slot:subcopy>
@endif
</x-mail::message>
