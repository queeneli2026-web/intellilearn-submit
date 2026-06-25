FROM php:8.2-cli

RUN docker-php-ext-install pdo_mysql

WORKDIR /app

COPY . .

EXPOSE 8000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t public"]
