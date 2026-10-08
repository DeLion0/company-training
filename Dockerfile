FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        libxml2-dev \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        simplexml \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app

EXPOSE 8080

CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-8080} -t public"]