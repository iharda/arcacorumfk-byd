#!/usr/bin/env bash
# BYS -- kod degisikligi sonrasi calistir.  bash <uygulama-dizini>/dagit.sh
#
# 🐙 Once GitHub'daki yeni kodu indirir, sonra dagitir.
#    Indirmeden sadece dagitmak icin:  PULLSUZ=1 bash dagit.sh
#
# 🪤 En sik tuzak: `artisan optimize` config'i ONBELLEGE ALIR. config/ altinda
# bir sey degistirip bunu calistirmazsan degisiklik CANLIYA YANSIMAZ
# ("Disk [evrak] does not have a configured driver" hatasi tam olarak buydu).
# ⚠️ artisan'i ROOT ile calistirma -- root'un biraktigi dosyalar 500 uretir.
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ----------------------------------------------------- GitHub'dan yeni kodu al
if [ "${PULLSUZ:-0}" != "1" ]; then
  # Takip edilen dosyalarda kaydedilmemis degisiklik varsa pull cakisir -> DUR.
  if [ -n "$(sudo -u bys git status --porcelain --untracked-files=no)" ]; then
    echo "⛔ Sunucuda kaydedilmemiş değişiklik var. GitHub'dan indirme yapılmadı."
    echo "   Bunları önce kaydedin (commit), yoksa gelen kodla çakışır:"
    sudo -u bys git status --short --untracked-files=no
    echo
    echo "   Sadece dağıtmak istiyorsanız:  PULLSUZ=1 bash dagit.sh"
    exit 1
  fi

  # Takip edilmeyen yeni dosyalar pull'u bozmaz, ama haberiniz olsun.
  YENI_DOSYA=$(sudo -u bys git ls-files --others --exclude-standard | head -5)
  if [ -n "$YENI_DOSYA" ]; then
    echo "ℹ️  Depoya eklenmemiş dosyalar var (dağıtımı etkilemez):"
    echo "$YENI_DOSYA" | sed 's/^/     /'
  fi

  ONCEKI=$(sudo -u bys git rev-parse HEAD)
  echo "⬇️  GitHub'dan kod alınıyor..."
  sudo -u bys git pull --ff-only
  YENI=$(sudo -u bys git rev-parse HEAD)

  if [ "$ONCEKI" = "$YENI" ]; then
    echo "✔️  GitHub'da yeni bir şey yok."
  else
    DEGISEN=$(sudo -u bys git diff --name-only "$ONCEKI" "$YENI")
    echo "📥 $(echo "$DEGISEN" | wc -l) dosya güncellendi."

    # Yeni PHP paketi geldiyse kur. --no-dev YOK: larastan/pint burada lazim
    # (`composer denetle`), burasi henuz gelistirme sunucusu.
    if echo "$DEGISEN" | grep -q '^composer\.lock$'; then
      echo "📦 composer.lock değişmiş → composer install"
      sudo -u bys composer install --no-interaction --optimize-autoloader
    fi

    # Yeni JS paketi geldiyse kur (npm ci node_modules'u sifirdan kurar, yavas --
    # o yuzden sadece kilit dosyasi degisince).
    if echo "$DEGISEN" | grep -qE '^package(-lock)?\.json$'; then
      echo "📦 package-lock.json değişmiş → npm ci"
      sudo -u bys npm ci
    fi

    # Blade/CSS/JS degistiyse yeniden derle. Blade DE sart: Tailwind sinif
    # isimlerini Blade dosyalarindan tarar, derlemezsen yeni sinif calismaz.
    if echo "$DEGISEN" | grep -qE '^(resources/|vite\.config\.|package(-lock)?\.json$)'; then
      echo "🎨 Arayüz dosyaları değişmiş → npm run build"
      sudo -u bys npm run build
    fi
  fi
fi

# --------------------------------------------------------------------- dagitim
sudo -u bys php artisan optimize:clear
sudo -u bys php artisan migrate --force
sudo -u bys php artisan filament:assets
sudo -u bys php artisan optimize
sudo -u bys php artisan filament:optimize

chown -R bys:bys "$PWD"
chmod -R 775 storage bootstrap/cache

# Kuyruk isleyicisi yeni kodu almali
systemctl restart bys-horizon

echo "✅ dağıtım tamam"
