# =============================================================
# Subscription Engine - Production Dockerfile
# =============================================================
# Multi-stage build for optimized production image
# Based on official PHP 8.3 Alpine image

# Stage 1: Build dependencies
FROM php:8.3-fpm-alpine AS builder

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && docker-php-ext-install pdo_mysql pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy application source
COPY . .

# Install Node dependencies and build assets
RUN npm install && npm run build

# Optimize Laravel
RUN php artisan optimize

# Stage 2: Production image
FROM php:8.3-fpm-alpine

# Add labels
LABEL maintainer="subscription-engine"
LABEL description="Subscription Management API"
LABEL version="1.0.0"

# Install runtime dependencies
RUN apk add --no-cache \
    libpng \
    libxml2 \
    mysql-client \
    && docker-php-ext-install pdo_mysql

# Copy application from builder
COPY --from=builder /app /var/www/html

# Set working directory
WORKDIR /var/www/html

# Create necessary directories
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy PHP-FPM configuration
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-custom.conf

# Copy Nginx configuration
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Install Nginx
RUN apk add --no-cache nginx

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:80/api/health || exit 1

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Switch to non-root user
USER www-data

# Start PHP-FPM and Nginx
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
