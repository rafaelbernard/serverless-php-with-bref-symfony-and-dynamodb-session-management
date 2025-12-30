#!/bin/sh
#set -x

cd /app

npm ci

cd /app/php
composer install --no-scripts

php -S 0.0.0.0:8000 -t public
