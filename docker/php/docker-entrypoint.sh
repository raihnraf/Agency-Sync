#!/bin/bash

# Set permissions for Laravel directories
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Execute main process
exec php-fpm
