#!/usr/bin/env bash
# BYD -- kod degisikligi sonrasi calistir.  bash /home/byd.ordolive.com/laravel/dagit.sh
#
# 🪤 En sik tuzak: `artisan optimize` config'i ONBELLEGE ALIR. config/ altinda
# bir sey degistirip bunu calistirmazsan degisiklik CANLIYA YANSIMAZ
# ("Disk [evrak] does not have a configured driver" hatasi tam olarak buydu).
# ⚠️ artisan'i ROOT ile calistirma -- root'un biraktigi dosyalar 500 uretir.
set -euo pipefail
cd /home/byd.ordolive.com/laravel

sudo -u byd php artisan optimize:clear
sudo -u byd php artisan migrate --force
sudo -u byd php artisan filament:assets
sudo -u byd php artisan optimize
sudo -u byd php artisan filament:optimize

chown -R byd:byd /home/byd.ordolive.com/laravel
chmod -R 775 storage bootstrap/cache

# Kuyruk isleyicisi yeni kodu almali
systemctl restart byd-horizon

echo "✅ dağıtım tamam"
