FROM php:8.2-cli

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /app
WORKDIR /app

CMD ["php", "-S", "0.0.0.0:8080"]
