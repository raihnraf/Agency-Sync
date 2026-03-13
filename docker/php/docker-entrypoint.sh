#!/bin/bash

# Set permissions for Laravel directories
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Execute main process (Supervisor will manage PHP-FPM and workers)
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
