FROM geshan/php-composer-alpine

WORKDIR /usr/local/twitter

COPY composer.json send.php receive.php filter.php wait_for twitter.php ./
COPY codebird-php codebird-php

RUN apk add --no-cache php-bcmath && composer install
