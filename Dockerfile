FROM dunglas/frankenphp:1-php8.5-bookworm AS base

RUN install-php-extensions \
    gd \
    pgsql \
    pdo_pgsql \
    redis \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV SERVER_NAME=":9003"
ENV CADDY_AUTO_HTTPS=off

WORKDIR /app

COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

FROM base AS dev

# Dev target: sources are expected to be mounted via volume at /app.
# No COPY and no build-time composer install here.

RUN install-php-extensions xdebug