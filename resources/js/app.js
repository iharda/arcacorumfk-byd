import Alpine from 'alpinejs';
import mask from '@alpinejs/mask';

// Telefon alanındaki `x-mask` için (Revizyon md.5.2). Elle yazılan maskeler
// geri silme ve yapıştırmada imleci kaybediyor; resmi eklenti bunu doğru yapar.
Alpine.plugin(mask);

window.Alpine = Alpine;
Alpine.start();
