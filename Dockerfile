FROM php:8.2.35-fpm-alpine

# Install Nginx and system dependencies
RUN apk add --no-cache nginx curl bash

# Install PHP extensions required for MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Configure Nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Expose port 80 and start Nginx & PHP-FPM
EXPOSE 80
CMD ["sh", "-c", "nginx && php-fpm"]
