#!/bin/sh
Xvfb :99 &
/etc/init.d/php7.3-fpm start
nginx -g 'daemon off;'
