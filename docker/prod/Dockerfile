# Imagem de produção: PHP-FPM 8.5 (o Nginx roda em container separado).
FROM php:8.5-fpm

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.9.17/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_pgsql pgsql zip gd intl bcmath redis pcntl exif opcache

# pg_dump/psql 18 (mesma versão do servidor) para gerar e restaurar backups pela tela.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl gnupg \
    && install -d /etc/apt/keyrings \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/keyrings/pgdg.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt trixie-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-18 \
    && apt-get purge -y gnupg && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

# Os workers rodam com o mesmo UID/GID (1000) dono de storage/ no host.
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY php.ini $PHP_INI_DIR/conf.d/99-grc.ini
COPY www.conf /usr/local/etc/php-fpm.d/zz-grc.conf

WORKDIR /var/www/html
