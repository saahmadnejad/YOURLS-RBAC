FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install \
    mysqli \
    pdo_mysql \
    gd \
    mbstring \
    intl \
    ctype \
    dom

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache: ServerName + directory overrides
RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && rm -f /etc/apache2/sites-available/000-default.conf

# YOURLS Apache vhost (handles URL routing directly, no .htaccess dependency)
COPY docker/yourls-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2ensite 000-default

# Clone YOURLS
WORKDIR /var/www/html
RUN git clone --depth 1 https://github.com/YOURLS/YOURLS.git /tmp/yourls \
    && cp -a /tmp/yourls/. /var/www/html/ \
    && rm -rf /tmp/yourls \
    && chown -R www-data:www-data /var/www/html

# Install Composer and YOURLS dependencies
# --ignore-platform-req=php bypasses YOURLS's platform: {php: "8.1.0"} override
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer update --no-dev --optimize-autoloader --ignore-platform-req=php

# Create RBAC plugin directory (plugin files mounted as volumes in compose)
RUN mkdir -p /var/www/html/user/plugins/rbac

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
