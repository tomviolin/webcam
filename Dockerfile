FROM php:7.4-cli

MAINTAINER Tom Hansen "tomh@uwm.edu"
RUN echo deb http://http.us.debian.org/debian bullseye main contrib non-free >> /etc/apt/sources.list
RUN apt-get update && apt-get install -y exiftool ffmpeg imagemagick libfreetype6-dev libjpeg62-turbo-dev \
	libpng-dev ttf-mscorefonts-installer cron lockfile-progs

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) gd
RUN docker-php-ext-install mysqli

RUN ln -fs /usr/share/zoneinfo/America/Chicago /etc/localtime && \
	dpkg-reconfigure --frontend noninteractive tzdata 

RUN ln -s /usr/local/bin/php /usr/bin/php

COPY . .
COPY webcam_crontab ./etc/cron.d
RUN echo '[mysql]' > /root/.my.cnf
RUN echo 'host=waterdata.glwi.uwm.edu' >> /root/.my.cnf

RUN ./mkphptz.sh

RUN addgroup --gid 1000 tomh     ; adduser --uid 1000 --gid 1000 --gecos "" --disabled-password tomh
RUN addgroup --gid 1001 wcupload ; adduser --uid 1001 --gid 1001 --gecos "" --disabled-password wcupload


CMD service cron start && ./webcampics.php
