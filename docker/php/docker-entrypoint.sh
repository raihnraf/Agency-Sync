#!/bin/bash

# Set permissions for Laravel directories
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Create log directory for worker if it doesn't exist
mkdir -p /var/www/storage/logs

# Execute main process (Supervisor will manage PHP-FPM and workers)
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
