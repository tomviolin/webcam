FROM php:7.4-cli

MAINTAINER Tom Hansen "tomh@uwm.edu"
RUN echo deb http://http.us.debian.org/debian bullseye main contrib non-free >> /etc/apt/sources.list
RUN apt-get update
RUN apt-get install -y libpng-dev
RUN apt-get install -y libjpeg62-turbo-dev
RUN apt-get install -y libfreetype6-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) gd
RUN docker-php-ext-install mysqli

RUN ln -s /usr/local/bin/php /usr/bin/php

RUN apt-get install -y ttf-mscorefonts-installer
RUN apt-get install -y imagemagick
RUN apt-get install -y ffmpeg
RUN apt-get install -y exiftool


RUN ln -fs /usr/share/zoneinfo/America/Chicago /etc/localtime
RUN dpkg-reconfigure --frontend noninteractive tzdata


COPY . .
RUN ./mkphptz.sh
RUN echo '[mysql]' > /root/.my.cnf
RUN echo 'host=waterdata.glwi.uwm.edu' >> /root/.my.cnf

CMD ./webcampics.php
