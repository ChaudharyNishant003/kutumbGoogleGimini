#!/bin/sh
set -e

# The base php:apache image sometimes has both mpm_event and mpm_prefork
# enabled at container start (mpm_prefork is required for mod_php); force
# only prefork to be active.
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null 2>&1 || true

# A persistent volume mounted at /var/www/html/uploads starts empty and
# shadows the photos baked into the image at build time. Copy in anything
# from the baked-in backup that isn't already on the volume (-n = never
# overwrite a file that's already there, so admin-panel uploads are safe).
mkdir -p /var/www/html/uploads/properties /var/www/html/uploads/documents
cp -rvn /var/www/html/uploads-seed/. /var/www/html/uploads/

# The mounted volume can come back owned by root regardless of what the image
# set at build time, which would block admin-panel uploads (written as
# www-data) with a permission error. Fix ownership on every boot, not just
# on first-seed, since a redeploy doesn't re-run the seed branch above.
chown -R www-data:www-data /var/www/html/uploads

sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
