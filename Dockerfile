FROM php:8.2-fpm-alpine

-- Run system updates and install Nginx
RUN apk update && apk add --no-cache nginx bash curl

-- Install PHP extensions for MySQL database connection
RUN docker-php-ext-install pdo pdo_mysql

-- Copy Nginx server configuration
COPY nginx.conf /etc/nginx/http.d/default.conf

-- Set working directory
WORKDIR /var/www/html

-- Copy all project files into the container
COPY . .

-- Expose HTTP port 80 and start services
EXPOSE 80
CMD ["sh", "-c", "nginx && php-fpm"]
