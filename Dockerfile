FROM php:8.3-cli
WORKDIR /app
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*
COPY . /app
RUN mkdir -p /app/data
EXPOSE 3000
CMD ["sh", "-c", "php db/migrate.php && php -S 0.0.0.0:3000 public/index.php"]
