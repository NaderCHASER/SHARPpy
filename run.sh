#!/bin/sh
Xvfb :99 &
/etc/init.d/php7.0-fpm start
nginx -g 'daemon off;'
