{{-- Liste satırındaki kapak. Görsel varsa küçük hâli, yoksa videonun oynat
     işaretli çerçevesi, ikisi de yoksa boş çerçeve.

     🔑 `<video>` BURADA KURULMAZ: listede on kayıt var, hepsinin videosunu
     kurmak sayfayı ikiye katlıyordu. Oynatma detayda. --}}
@props(['gorsel' => null, 'video' => null])

@if ($gorsel)
    <img src="{{ route('icerik.dosya', ['yol' => $gorsel]) }}"
         alt="" loading="lazy" class="size-full object-cover">
@elseif ($video)
    <div class="flex size-full items-center justify-center bg-gray-800">
        <x-filament::icon icon="heroicon-m-play-circle" class="size-7 text-white/80" />
    </div>
@else
    <div class="flex size-full items-center justify-center">
        <x-filament::icon icon="heroicon-o-megaphone" class="size-6 text-gray-300 dark:text-gray-600" />
    </div>
@endif
