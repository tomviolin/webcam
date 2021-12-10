FROM php:7.4-cli

MAINTAINER Tom Hansen "tomh@uwm.edu"

COPY . .


RUN echo deb http://http.us.debian.org/debian bullseye main contrib non-free >> /etc/apt/sources.list

RUN apt-get update && apt-get install -y ttf-mscorefonts-installer \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
	imagemagick \
	ffmpeg

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) gd
RUN docker-php-ext-install mysqli


RUN ln -fs /usr/share/zoneinfo/America/Chicago /etc/localtime
RUN dpkg-reconfigure --frontend noninteractive tzdata
RUN /var/www/html/intake/mkphptz.sh
RUN echo '[mysql]' > /root/.my.cnf
RUN echo 'host=waterdata.glwi.uwm.edu' >> /root/.my.cnf

CMD ./iphonepics.php
